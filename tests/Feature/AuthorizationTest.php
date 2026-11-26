<?php

namespace Tests\Feature;

use App\Enums\RequisitionStatus;
use App\Models\Requisition;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['dashboard', 'items.index', 'requisitions.index', 'reports.index', 'audit-logs.index'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }

    public function test_login_and_logout(): void
    {
        $user = $this->requester();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = $this->requester();
        $user->update(['is_active' => false]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_requester_cannot_access_protected_stock_operations(): void
    {
        $item = $this->makeItem(10);
        $requester = $this->requester();
        $tx = $item->transactions()->first();

        $this->actingAs($requester);
        $this->get(route('items.create'))->assertForbidden();
        $this->post(route('items.store'), ['stock_number' => 'X1', 'name' => 'X', 'unit' => 'Box', 'reorder_point' => 1, 'opening_quantity' => 1, 'opening_date' => today()->toDateString()])->assertForbidden();
        $this->put(route('items.update', $item), ['stock_number' => $item->stock_number, 'name' => 'Hacked', 'unit' => 'Box', 'reorder_point' => 1])->assertForbidden();
        $this->patch(route('items.archive', $item))->assertForbidden();
        $this->get(route('stock-cards.show', $item))->assertForbidden();
        $this->get(route('receipts.create'))->assertForbidden();
        $this->post(route('receipts.store'), [])->assertForbidden();
        $this->post(route('adjustments.store'), [])->assertForbidden();
        $this->post(route('transactions.reverse', $tx), ['reason' => 'Because I can'])->assertForbidden();
        $this->get(route('audit-logs.index'))->assertForbidden();
        $this->get(route('users.index'))->assertForbidden();
        $this->get(route('settings.edit'))->assertForbidden();
        $this->get(route('reports.index'))->assertForbidden();

        $this->assertSame(10, $item->fresh()->current_balance);
        $this->assertNotSame('Hacked', $item->fresh()->name);
    }

    public function test_only_authorized_roles_can_approve_or_issue(): void
    {
        $item = $this->makeItem(10);
        $requester = $this->requester();
        $r = $this->ris()->createDraft(['office_id' => $this->office->id, 'requisition_date' => today(), 'purpose' => 'Testing'],
            [['item_id' => $item->id, 'quantity' => 2]], $requester);
        $this->ris()->submit($r, $requester);
        $this->ris()->forward($r->fresh(), $this->admin());
        $lineId = $r->items()->first()->id;

        // Requesters — including the owner — cannot approve.
        $this->actingAs($this->requester())->post(route('requisitions.approve', $r), ['approved' => [$lineId => 2]])->assertForbidden();
        $this->actingAs($requester)->post(route('requisitions.approve', $r), ['approved' => [$lineId => 2]])->assertForbidden();
        $this->assertSame(RequisitionStatus::PendingApproval, $r->fresh()->status);

        $this->actingAs($this->admin())->post(route('requisitions.approve', $r), ['approved' => [$lineId => 2]])->assertRedirect();
        $this->assertSame(RequisitionStatus::Approved, $r->fresh()->status);

        // Requesters cannot issue; the administrator can.
        $issue = ['idempotency_key' => (string) Str::uuid(), 'issue_date' => today()->toDateString(), 'received_by_user_id' => $requester->id, 'quantities' => [$lineId => 2]];
        $this->actingAs($requester)->post(route('requisitions.issue.store', $r), $issue)->assertForbidden();
        $this->actingAs($this->requester())->post(route('requisitions.issue.store', $r), $issue)->assertForbidden();
        $this->assertSame(10, $item->fresh()->current_balance);

        $this->actingAs($this->admin())->post(route('requisitions.issue.store', $r), $issue)->assertRedirect();
        $this->assertSame(8, $item->fresh()->current_balance);
    }

    public function test_requesters_only_see_their_own_requisitions(): void
    {
        $item = $this->makeItem(10);
        $alice = $this->requester();
        $bob = $this->requester();
        $r = $this->ris()->createDraft(['office_id' => $this->office->id, 'requisition_date' => today(), 'purpose' => 'Alice private request'],
            [['item_id' => $item->id, 'quantity' => 2]], $alice);

        $this->actingAs($bob)->get(route('requisitions.show', $r))->assertForbidden();
        $this->actingAs($bob)->get(route('requisitions.pdf', $r))->assertForbidden();
        $this->actingAs($bob)->put(route('requisitions.update', $r), [
            'office_id' => $this->office->id, 'requisition_date' => today()->toDateString(), 'purpose' => 'Changed by Bob',
            'lines' => [['item_id' => $item->id, 'quantity' => 9]],
        ])->assertForbidden();
        $this->actingAs($bob)->get(route('requisitions.index'))->assertOk()->assertDontSee($r->ris_no);
        $this->actingAs($alice)->get(route('requisitions.index'))->assertOk()->assertSee($r->ris_no);
    }

    public function test_submitted_requisition_cannot_be_edited_by_requester(): void
    {
        $item = $this->makeItem(10);
        $alice = $this->requester();
        $r = $this->ris()->createDraft(['office_id' => $this->office->id, 'requisition_date' => today(), 'purpose' => 'Original'],
            [['item_id' => $item->id, 'quantity' => 2]], $alice);
        $this->ris()->submit($r, $alice);

        $this->actingAs($alice)->put(route('requisitions.update', $r), [
            'office_id' => $this->office->id, 'requisition_date' => today()->toDateString(), 'purpose' => 'Changed after submit',
            'lines' => [['item_id' => $item->id, 'quantity' => 9]],
        ])->assertForbidden();
        $this->assertSame('Original', $r->fresh()->purpose);
    }

    public function test_admin_cannot_lock_themselves_out_and_audit_logs_are_restricted(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->patch(route('users.toggle', $admin))->assertStatus(422);
        $this->assertTrue($admin->fresh()->is_active);

        $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk();
        $this->actingAs($this->requester())->get(route('audit-logs.index'))->assertForbidden();
    }

    public function test_only_two_roles_exist_and_requester_sees_only_requisition_features(): void
    {
        $this->assertSame(['admin', 'requester'], Role::orderBy('name')->pluck('name')->all());

        $html = $this->actingAs($this->requester())->get(route('dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('Requisitions &amp; Issue Slips', $html);
        foreach (['Stock Cards', 'Stock Receipts', 'Stock Adjustments', 'Issuance History', 'User Management', 'Audit Logs'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $html);
        }
    }

    public function test_administrator_cannot_issue_a_requisition_before_it_is_approved(): void
    {
        $admin = $this->admin();
        $item = $this->makeItem(11, by: $admin);
        $requester = $this->requester();
        $r = $this->ris()->createDraft(['office_id' => $this->office->id, 'requisition_date' => today(), 'purpose' => 'Testing'],
            [['item_id' => $item->id, 'quantity' => 10]], $requester);
        $this->ris()->submit($r, $requester);
        $lineId = $r->items()->first()->id;

        $this->actingAs($admin)->get(route('requisitions.show', $r))->assertOk()
            ->assertDontSee(route('requisitions.issue', $r))
            ->assertSee('Forward for approval');
        $this->actingAs($admin)->get(route('requisitions.issue', $r))->assertForbidden();
        $this->actingAs($admin)->post(route('requisitions.issue.store', $r), [
            'idempotency_key' => (string) Str::uuid(), 'issue_date' => today()->toDateString(), 'received_by_user_id' => $requester->id, 'quantities' => [$lineId => 10],
        ])->assertForbidden();

        $this->assertSame(11, $item->fresh()->current_balance);
        $this->assertSame(RequisitionStatus::Submitted, $r->fresh()->status);
    }

    public function test_administrator_actions_follow_the_requisition_status(): void
    {
        $admin = $this->admin();
        $item = $this->makeItem(20, by: $admin);
        $requester = $this->requester();
        $draft = $this->ris()->createDraft(['office_id' => $this->office->id, 'requisition_date' => today(), 'purpose' => 'Draft'],
            [['item_id' => $item->id, 'quantity' => 1]], $requester);
        $approved = $this->approvedRequisition([[$item, 2]], $requester);

        $this->assertTrue($admin->cannot('update', $draft), 'admins do not edit other people\'s drafts');
        $this->assertTrue($admin->cannot('forward', $approved));
        $this->assertTrue($admin->cannot('approve', $approved));
        $this->assertTrue($admin->cannot('acknowledge', $approved));
        $this->assertTrue($admin->can('issue', $approved));
        $this->assertTrue($admin->can('cancel', $approved));

        $this->ris()->issue($approved, $admin, [$approved->items[0]->id => 2], $this->issueMeta());
        $this->assertTrue($admin->cannot('cancel', $approved->fresh()));
        $this->assertTrue($admin->cannot('issue', $approved->fresh()));
    }

    public function test_only_requesting_personnel_can_file_requisitions(): void
    {
        $admin = $this->admin();
        $item = $this->makeItem(10, by: $admin);
        $payload = ['office_id' => $this->office->id, 'requisition_date' => today()->toDateString(), 'purpose' => 'Office supplies',
            'lines' => [['item_id' => $item->id, 'quantity' => 1]], 'action' => 'submit'];

        $this->actingAs($admin)->get(route('requisitions.create'))->assertForbidden();
        $this->actingAs($admin)->post(route('requisitions.store'), $payload)->assertForbidden();
        $this->assertSame(0, Requisition::count());

        $this->actingAs($this->requester())->post(route('requisitions.store'), $payload)->assertRedirect();
        $this->assertSame(1, Requisition::count());
    }

    public function test_only_the_requester_can_acknowledge_receipt(): void
    {
        $admin = $this->admin();
        $item = $this->makeItem(10, by: $admin);
        $requester = $this->requester();
        $sameOfficeColleague = $this->requester();
        $r = $this->approvedRequisition([[$item, 2]], $requester);
        $this->ris()->issue($r, $admin, [$r->items[0]->id => 2], $this->issueMeta());

        $this->actingAs($admin)->post(route('requisitions.acknowledge', $r))->assertForbidden();
        $this->actingAs($sameOfficeColleague)->post(route('requisitions.acknowledge', $r))->assertForbidden();
        $this->assertSame(RequisitionStatus::FullyIssued, $r->fresh()->status);

        $this->actingAs($requester)->post(route('requisitions.acknowledge', $r))->assertRedirect();
        $this->assertSame(RequisitionStatus::Completed, $r->fresh()->status);
        $this->assertSame($requester->id, $r->fresh()->received_by);
    }

    public function test_supplies_can_only_be_issued_to_requesting_personnel(): void
    {
        $admin = $this->admin();
        $item = $this->makeItem(10, by: $admin);
        $r = $this->approvedRequisition([[$item, 2]]);

        $this->actingAs($admin)->post(route('requisitions.issue.store', $r), [
            'idempotency_key' => (string) Str::uuid(), 'issue_date' => today()->toDateString(),
            'received_by_user_id' => $admin->id, 'quantities' => [$r->items[0]->id => 2],
        ])->assertSessionHasErrors(['inventory' => 'Supplies can only be issued to active requesting personnel.']);

        $this->assertSame(10, $item->fresh()->current_balance);
        $this->assertSame(0, $r->issuances()->count());
    }

    public function test_deactivated_user_is_signed_out_on_next_request(): void
    {
        $user = $this->requester();
        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        User::whereKey($user->id)->update(['is_active' => false]);
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertRedirect(route('login'));
    }
}
