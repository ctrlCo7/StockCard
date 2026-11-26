<?php

namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Exceptions\InventoryException;
use App\Models\InventoryItem;
use App\Models\StockTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class StockLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_increases_balance_and_creates_stock_card_row(): void
    {
        $item = $this->makeItem(10);
        $officer = $this->admin();

        $this->actingAs($officer)->post(route('receipts.store'), [
            'idempotency_key' => (string) Str::uuid(), 'date_received' => today()->toDateString(), 'supplier' => 'ABC Trading',
            'reference_type' => 'DR', 'reference_no' => '2026-0142', 'received_by_name' => 'Juan',
            'lines' => [['item_id' => $item->id, 'quantity' => 15]],
        ])->assertRedirect();

        $this->assertSame(25, $item->fresh()->current_balance);
        $row = $item->transactions()->where('type', TransactionType::Receipt)->sole();
        $this->assertSame(15, $row->quantity_received);
        $this->assertSame(0, $row->quantity_issued);
        $this->assertSame(25, $row->balance_after);
        $this->assertSame('DR# 2026-0142', $row->reference_no);
        $this->assertSame('ABC Trading', $row->party);
        $this->assertSame($officer->id, $row->posted_by);
    }

    public function test_duplicate_receipt_submission_posts_only_once(): void
    {
        $item = $this->makeItem(0);
        $payload = [
            'idempotency_key' => (string) Str::uuid(), 'date_received' => today()->toDateString(), 'supplier' => 'ABC',
            'reference_type' => 'PO', 'reference_no' => 'PO-1', 'received_by_name' => 'Juan',
            'lines' => [['item_id' => $item->id, 'quantity' => 7]],
        ];
        $officer = $this->admin();
        $this->actingAs($officer)->post(route('receipts.store'), $payload);
        $this->actingAs($officer)->post(route('receipts.store'), $payload)->assertSessionHas('warning');

        $this->assertSame(7, $item->fresh()->current_balance);
    }

    public function test_multiple_transactions_produce_correct_running_balance(): void
    {
        // Mirrors the paper Stock Card example: 28 → 24 → 19 → 17 → 32.
        $item = $this->makeItem(28);
        foreach ([4, 5, 2] as $qty) {
            $r = $this->approvedRequisition([[$item, $qty]]);
            $this->ris()->issue($r, $this->admin(), [$r->items[0]->id => $qty], $this->issueMeta());
        }
        $this->receive($item, 15);

        $this->assertSame([28, 24, 19, 17, 32], $item->transactions()->orderBy('id')->pluck('balance_after')->all());
        $this->assertSame(32, $item->fresh()->current_balance);
        $this->artisan('inventory:verify')->assertSuccessful();
    }

    public function test_issuance_exceeding_available_stock_is_rejected(): void
    {
        $item = $this->makeItem(5);

        try {
            $this->inventory()->post($item, ['type' => TransactionType::Issue, 'date' => today(), 'reference' => 'X', 'issued' => 6], $this->admin());
            $this->fail('Expected insufficient-stock exception');
        } catch (InventoryException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }
        $this->assertSame(5, $item->fresh()->current_balance);
        $this->assertSame(1, $item->transactions()->count());
    }

    public function test_quantity_rules_are_enforced(): void
    {
        $item = $this->makeItem(5);
        $officer = $this->admin();
        $cases = [
            ['received' => -1],
            ['received' => 2, 'issued' => 1],
            ['received' => 0, 'issued' => 0],
        ];
        foreach ($cases as $qty) {
            try {
                $this->inventory()->post($item, ['type' => TransactionType::Adjustment, 'date' => today(), 'reference' => 'R'] + $qty, $officer);
                $this->fail('Expected rejection for '.json_encode($qty));
            } catch (InventoryException) {
                // expected
            }
        }
        $this->expectException(InventoryException::class);
        $this->inventory()->post($item, ['type' => TransactionType::Receipt, 'date' => today(), 'reference' => '  ', 'received' => 1], $officer);
    }

    public function test_backdating_before_last_transaction_and_future_dates_are_rejected(): void
    {
        $item = $this->makeItem(5, date: today()->subDays(3));
        $this->receive($item, 1, date: today()->subDay());

        $this->expectException(InventoryException::class);
        $this->receive($item, 1, date: today()->subDays(2));
    }

    public function test_reversal_restores_balance_and_preserves_audit_trail(): void
    {
        $item = $this->makeItem(10);
        $this->receive($item, 5);
        $receipt = $item->transactions()->where('type', TransactionType::Receipt)->sole();

        $officer = $this->admin();
        $this->actingAs($officer)->post(route('transactions.reverse', $receipt), ['reason' => 'Wrong item delivered'])->assertRedirect();

        $this->assertSame(10, $item->fresh()->current_balance);
        $this->assertSame(3, $item->transactions()->count(), 'original is preserved; reversal is appended');
        $reversal = $item->transactions()->where('type', TransactionType::Reversal)->sole();
        $this->assertSame($receipt->id, $reversal->reverses_transaction_id);
        $this->assertSame(5, $reversal->quantity_issued);
        $this->assertSame(10, $reversal->balance_after);
        $this->assertDatabaseHas('audit_logs', ['action' => 'stock.reversed', 'auditable_id' => $receipt->id]);

        // A second reversal of the same transaction is refused.
        $this->actingAs($officer)->post(route('transactions.reverse', $receipt), ['reason' => 'Again please'])->assertSessionHasErrors('inventory');
        $this->assertSame(10, $item->fresh()->current_balance);
    }

    public function test_reversing_an_ris_issuance_returns_stock_and_reopens_the_line(): void
    {
        $item = $this->makeItem(10);
        $r = $this->approvedRequisition([[$item, 4]]);
        $this->ris()->issue($r, $this->admin(), [$r->items[0]->id => 4], $this->issueMeta());
        $this->assertSame('fully_issued', $r->fresh()->status->value);

        $issue = $item->transactions()->where('type', TransactionType::Issue)->sole();
        $this->inventory()->reverse($issue, 'Issued to wrong office', $this->admin());

        $this->assertSame(10, $item->fresh()->current_balance);
        $this->assertSame(0, $r->items[0]->fresh()->quantity_issued);
        $this->assertSame('approved', $r->fresh()->status->value);
    }

    public function test_posted_transactions_cannot_be_modified_or_deleted(): void
    {
        $item = $this->makeItem(10);
        $tx = $item->transactions()->first();

        try {
            $tx->update(['quantity_received' => 99]);
            $this->fail('Update should be refused');
        } catch (LogicException) {
        }
        try {
            $tx->delete();
            $this->fail('Delete should be refused');
        } catch (LogicException) {
        }
        $this->assertSame(10, StockTransaction::find($tx->id)->quantity_received);
    }

    public function test_adjustment_requires_reason_and_is_audited(): void
    {
        $item = $this->makeItem(10);
        $officer = $this->admin();

        $this->actingAs($officer)->post(route('adjustments.store'), [
            'idempotency_key' => (string) Str::uuid(), 'item_id' => $item->id, 'adjustment_date' => today()->toDateString(),
            'direction' => 'decrease', 'quantity' => 2, 'reason' => 'damaged', 'reference_no' => 'IR-1', 'remarks' => '',
        ])->assertSessionHasErrors('remarks');
        $this->assertSame(10, $item->fresh()->current_balance);

        $this->actingAs($officer)->post(route('adjustments.store'), [
            'idempotency_key' => (string) Str::uuid(), 'item_id' => $item->id, 'adjustment_date' => today()->toDateString(),
            'direction' => 'decrease', 'quantity' => 2, 'reason' => 'damaged', 'reference_no' => 'IR-1', 'remarks' => 'Water damage in storage',
        ])->assertRedirect();
        $this->assertSame(8, $item->fresh()->current_balance);
        $this->assertDatabaseHas('audit_logs', ['action' => 'adjustment.posted', 'user_id' => $officer->id]);

        // A decrease can never take the balance below zero.
        $this->actingAs($officer)->post(route('adjustments.store'), [
            'idempotency_key' => (string) Str::uuid(), 'item_id' => $item->id, 'adjustment_date' => today()->toDateString(),
            'direction' => 'decrease', 'quantity' => 9, 'reason' => 'lost', 'reference_no' => 'IR-2', 'remarks' => 'Loss report',
        ])->assertSessionHasErrors('inventory');
        $this->assertSame(8, $item->fresh()->current_balance);
    }

    /**
     * Two "simultaneous" issuances: the second request was prepared from a stale
     * view of the item (balance 10) after the first already took 7. The service
     * re-reads the balance under a row lock, so the second is refused instead of
     * driving the balance negative.
     */
    public function test_concurrent_issuances_cannot_cause_negative_balance(): void
    {
        $item = $this->makeItem(10);
        $stale = InventoryItem::find($item->id);
        $this->assertSame(10, $stale->current_balance);

        $first = $this->approvedRequisition([[$item, 7]]);
        $second = $this->approvedRequisition([[$item, 7]]);

        $this->ris()->issue($first, $this->admin(), [$first->items[0]->id => 7], $this->issueMeta());

        try {
            $this->inventory()->post($stale, ['type' => TransactionType::Issue, 'date' => today(), 'reference' => $second->ris_no, 'issued' => 7], $this->admin());
            $this->fail('Second issuance should have been refused');
        } catch (InventoryException) {
        }
        try {
            $this->ris()->issue($second, $this->admin(), [$second->items[0]->id => 7], $this->issueMeta());
            $this->fail('Second issuance should have been refused');
        } catch (InventoryException) {
        }

        $this->assertSame(3, $item->fresh()->current_balance);
        $this->assertSame(0, $second->items[0]->fresh()->quantity_issued);
        $this->assertSame('approved', $second->fresh()->status->value);
        $this->assertGreaterThanOrEqual(0, StockTransaction::min('balance_after'));
    }
}
