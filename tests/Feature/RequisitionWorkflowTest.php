<?php

namespace Tests\Feature;

use App\Enums\RequisitionStatus;
use App\Enums\TransactionType;
use App\Exceptions\InventoryException;
use App\Models\Issuance;
use App\Models\Requisition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RequisitionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_creates_and_submits_multi_item_requisition_over_http(): void
    {
        $a = $this->makeItem(10);
        $b = $this->makeItem(3);
        $requester = $this->requester();

        $this->actingAs($requester)->post(route('requisitions.store'), [
            'office_id' => $this->office->id, 'requisition_date' => today()->toDateString(), 'purpose' => 'Monthly office supplies',
            'lines' => [['item_id' => $a->id, 'quantity' => 4], ['item_id' => $b->id, 'quantity' => 5, 'remarks' => 'urgent']],
            'action' => 'submit',
        ])->assertRedirect();

        $r = Requisition::with('items')->sole();
        $this->assertSame(RequisitionStatus::Submitted, $r->status);
        $this->assertCount(2, $r->items);
        $this->assertSame('Bureau of Internal Revenue', $r->entity_name);
        $this->assertMatchesRegularExpression('/^RIS-\d{4}-\d{4}$/', $r->ris_no);
    }

    public function test_requested_quantities_must_be_positive_and_items_unique(): void
    {
        $a = $this->makeItem(10);
        $requester = $this->requester();
        $base = ['office_id' => $this->office->id, 'requisition_date' => today()->toDateString(), 'purpose' => 'Testing purpose'];

        $this->actingAs($requester)->post(route('requisitions.store'), $base + ['lines' => [['item_id' => $a->id, 'quantity' => 0]]])
            ->assertSessionHasErrors('lines.0.quantity');
        $this->actingAs($requester)->post(route('requisitions.store'), $base + ['lines' => [['item_id' => $a->id, 'quantity' => 1], ['item_id' => $a->id, 'quantity' => 2]]])
            ->assertSessionHasErrors('lines.1.item_id');
        $this->assertSame(0, Requisition::count());
    }

    public function test_forwarding_records_stock_availability(): void
    {
        $ok = $this->makeItem(10);
        $short = $this->makeItem(2);
        $requester = $this->requester();
        $r = $this->ris()->createDraft(['office_id' => $this->office->id, 'requisition_date' => today(), 'purpose' => 'Testing'],
            [['item_id' => $ok->id, 'quantity' => 5], ['item_id' => $short->id, 'quantity' => 5]], $requester);
        $this->ris()->submit($r, $requester);
        $this->ris()->forward($r->fresh(), $this->admin());

        $lines = $r->items()->get()->keyBy('item_id');
        $this->assertTrue($lines[$ok->id]->stock_available);
        $this->assertFalse($lines[$short->id]->stock_available);
        $this->assertSame(RequisitionStatus::PendingApproval, $r->fresh()->status);
    }

    public function test_approval_alone_does_not_deduct_stock(): void
    {
        $item = $this->makeItem(10);
        $r = $this->approvedRequisition([[$item, 6]]);

        $this->assertSame(RequisitionStatus::Approved, $r->status);
        $this->assertSame(6, $r->items[0]->quantity_approved);
        $this->assertSame(10, $item->fresh()->current_balance);
        $this->assertSame(0, $item->transactions()->where('type', TransactionType::Issue)->count());
    }

    public function test_posting_issuance_creates_stock_card_entry_referenced_by_ris_number(): void
    {
        $item = $this->makeItem(10);
        $r = $this->approvedRequisition([[$item, 6]]);
        $officer = $this->admin();
        $colleague = $this->requester();
        $colleague->update(['name' => 'Maria Santos']);

        $this->actingAs($officer)->post(route('requisitions.issue.store', $r), [
            'idempotency_key' => (string) Str::uuid(), 'issue_date' => today()->toDateString(), 'received_by_user_id' => $colleague->id,
            'quantities' => [$r->items[0]->id => 6],
        ])->assertRedirect(route('requisitions.show', $r));
        $this->assertDatabaseHas('issuances', ['requisition_id' => $r->id, 'received_by_user_id' => $colleague->id, 'received_by_name' => 'Maria Santos']);

        $tx = $item->transactions()->where('type', TransactionType::Issue)->sole();
        $this->assertSame($r->ris_no, $tx->reference_no);
        $this->assertSame(6, $tx->quantity_issued);
        $this->assertSame(4, $tx->balance_after);
        $this->assertSame($r->id, $tx->requisition_id);
        $this->assertStringContainsString('Maria Santos', $tx->party);
        $this->assertSame(4, $item->fresh()->current_balance);
        $this->assertSame(RequisitionStatus::FullyIssued, $r->fresh()->status);
        $this->assertSame($officer->id, $r->fresh()->issued_by);
    }

    public function test_partial_issuance_tracks_remaining_quantity_and_status(): void
    {
        $paper = $this->makeItem(100);
        $toner = $this->makeItem(1);
        $r = $this->approvedRequisition([[$paper, 20], [$toner, 4]]);
        $lines = $r->items->keyBy('item_id');

        $this->ris()->issue($r, $this->admin(), [$lines[$paper->id]->id => 20, $lines[$toner->id]->id => 1], $this->issueMeta());

        $r->refresh()->load('items');
        $this->assertSame(RequisitionStatus::PartiallyIssued, $r->status);
        $this->assertSame(3, $r->items->firstWhere('item_id', $toner->id)->remainingToIssue());
        $this->assertSame(0, $r->items->firstWhere('item_id', $paper->id)->remainingToIssue());
        $this->assertSame(3, $r->totalRemaining());

        $this->receive($toner, 5);
        $this->ris()->issue($r, $this->admin(), [$lines[$toner->id]->id => 3], $this->issueMeta());
        $this->assertSame(RequisitionStatus::FullyIssued, $r->fresh()->status);
        $this->assertSame(2, $toner->fresh()->current_balance); // 1 − 1 + 5 − 3
    }

    public function test_issued_quantity_cannot_exceed_approved_quantity(): void
    {
        $item = $this->makeItem(50);
        $requester = $this->requester();
        $r = $this->ris()->createDraft(['office_id' => $this->office->id, 'requisition_date' => today(), 'purpose' => 'Testing'],
            [['item_id' => $item->id, 'quantity' => 10]], $requester);
        $this->ris()->submit($r, $requester);
        $this->ris()->forward($r->fresh(), $this->admin());
        $line = $r->items()->first();
        $this->ris()->approve($r->fresh(), $this->admin(), [$line->id => 6]);

        $this->expectException(InventoryException::class);
        $this->expectExceptionMessage('only 6 remain');
        $this->ris()->issue($r->fresh(), $this->admin(), [$line->id => 7], $this->issueMeta());
    }

    public function test_duplicate_issuance_cannot_be_posted_twice(): void
    {
        $item = $this->makeItem(10);
        $r = $this->approvedRequisition([[$item, 4]]);
        $officer = $this->admin();
        $payload = [
            'idempotency_key' => (string) Str::uuid(), 'issue_date' => today()->toDateString(), 'received_by_user_id' => $r->requested_by,
            'quantities' => [$r->items[0]->id => 2],
        ];

        $this->actingAs($officer)->post(route('requisitions.issue.store', $r), $payload)->assertSessionHas('success');
        // Same form submitted again (double click / back button + resubmit).
        $this->actingAs($officer)->post(route('requisitions.issue.store', $r), $payload)->assertSessionHas('warning');

        $this->assertSame(1, Issuance::count());
        $this->assertSame(8, $item->fresh()->current_balance);
        $this->assertSame(1, $item->transactions()->where('type', TransactionType::Issue)->count());

        // Once fully issued, no further issuance is accepted even with a new key.
        $this->ris()->issue($r->fresh(), $officer, [$r->items[0]->id => 2], $this->issueMeta());
        $this->expectException(InventoryException::class);
        $this->ris()->issue($r->fresh(), $officer, [$r->items[0]->id => 1], $this->issueMeta());
    }

    public function test_requester_acknowledges_receipt_and_completes_requisition(): void
    {
        $item = $this->makeItem(10);
        $requester = $this->requester();
        $r = $this->approvedRequisition([[$item, 3]], $requester);
        $this->ris()->issue($r, $this->admin(), [$r->items[0]->id => 3], $this->issueMeta());

        $this->actingAs($requester)->post(route('requisitions.acknowledge', $r), ['remarks' => 'Complete'])->assertRedirect();

        $r->refresh();
        $this->assertSame(RequisitionStatus::Completed, $r->status);
        $this->assertSame($requester->id, $r->received_by);
        $this->assertNotNull($r->received_at);
        $this->assertSame(
            ['created', 'submitted', 'forwarded', 'approved', 'issue_status', 'received'],
            $r->approvals()->pluck('action')->all()
        );
    }

    public function test_reject_and_return_require_remarks_and_never_touch_stock(): void
    {
        $item = $this->makeItem(10);
        $requester = $this->requester();
        $approver = $this->admin();
        $r = $this->ris()->createDraft(['office_id' => $this->office->id, 'requisition_date' => today(), 'purpose' => 'Testing'],
            [['item_id' => $item->id, 'quantity' => 2]], $requester);
        $this->ris()->submit($r, $requester);

        $this->actingAs($approver)->post(route('requisitions.return', $r), ['remarks' => ''])->assertSessionHasErrors('remarks');
        $this->actingAs($approver)->post(route('requisitions.return', $r), ['remarks' => 'Please specify the paper size'])->assertRedirect();
        $this->assertSame(RequisitionStatus::Returned, $r->fresh()->status);

        $this->ris()->submit($r->fresh(), $requester);
        $this->actingAs($approver)->post(route('requisitions.reject', $r), ['remarks' => 'Not a priority this quarter'])->assertRedirect();
        $this->assertSame(RequisitionStatus::Rejected, $r->fresh()->status);
        $this->assertSame(10, $item->fresh()->current_balance);
    }
}
