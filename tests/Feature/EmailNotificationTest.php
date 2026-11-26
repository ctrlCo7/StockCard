<?php

namespace Tests\Feature;

use App\Enums\RequisitionStatus;
use App\Enums\TransactionType;
use App\Exceptions\InventoryException;
use App\Models\ApplicationSetting;
use App\Models\InventoryItem;
use App\Models\User;
use App\Notifications\LowStockAlert;
use App\Notifications\LowStockDigest;
use App\Notifications\RequisitionStatusChanged;
use App\Notifications\RequisitionSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function issue(InventoryItem $item, int $quantity, User $by): void
    {
        $this->inventory()->post($item, ['type' => TransactionType::Issue, 'date' => today(), 'reference' => 'RIS-TEST', 'issued' => $quantity], $by);
    }

    public function test_submitting_requisition_emails_active_administrators_but_not_the_requester(): void
    {
        $admin = $this->admin();
        $inactiveAdmin = $this->admin();
        $inactiveAdmin->update(['is_active' => false]);
        $item = $this->makeItem(10, by: $admin);
        $requester = $this->requester();
        Notification::fake([RequisitionSubmitted::class]);

        $this->actingAs($requester)->post(route('requisitions.store'), [
            'office_id' => $this->office->id, 'requisition_date' => today()->toDateString(), 'purpose' => 'Monthly supplies',
            'lines' => [['item_id' => $item->id, 'quantity' => 3]], 'action' => 'submit',
        ])->assertRedirect();

        Notification::assertSentTo($admin, RequisitionSubmitted::class, function (RequisitionSubmitted $n) use ($admin, $requester) {
            $mail = $n->toMail($admin);

            return $n->requisition->requested_by === $requester->id
                && str_starts_with($mail->subject, "New requisition {$n->requisition->ris_no} from {$requester->name}");
        });
        Notification::assertNotSentTo([$inactiveAdmin, $requester], RequisitionSubmitted::class);
    }

    public function test_requester_is_emailed_when_requisition_is_approved(): void
    {
        $item = $this->makeItem(10);
        $requester = $this->requester();
        Notification::fake([RequisitionStatusChanged::class]);

        $r = $this->approvedRequisition([[$item, 4]], $requester);

        Notification::assertSentTo($requester, RequisitionStatusChanged::class, fn (RequisitionStatusChanged $n) => $n->action === 'approved'
            && $n->toMail($requester)->subject === "{$r->ris_no}: Requisition approved");
    }

    public function test_rejection_email_includes_the_remarks(): void
    {
        $item = $this->makeItem(10);
        $requester = $this->requester();
        $r = $this->ris()->createDraft(['office_id' => $this->office->id, 'requisition_date' => today(), 'purpose' => 'Testing'],
            [['item_id' => $item->id, 'quantity' => 2]], $requester);
        $this->ris()->submit($r, $requester);
        Notification::fake([RequisitionStatusChanged::class]);

        $this->actingAs($this->admin())->post(route('requisitions.reject', $r), ['remarks' => 'Budget exhausted for this quarter'])->assertRedirect();

        Notification::assertSentTo($requester, RequisitionStatusChanged::class, function (RequisitionStatusChanged $n) use ($requester, $r) {
            $mail = $n->toMail($requester);

            return $mail->subject === "{$r->ris_no}: Requisition rejected"
                && in_array('**Remarks:** Budget exhausted for this quarter', $mail->introLines, true);
        });
    }

    public function test_partial_issuance_email_tells_requester_what_remains(): void
    {
        $toner = $this->makeItem(1, ['name' => 'Canon 337 Toner', 'unit' => 'Cartridge', 'reorder_point' => 0]);
        $requester = $this->requester();
        $r = $this->approvedRequisition([[$toner, 4]], $requester);
        Notification::fake([RequisitionStatusChanged::class]);

        $this->ris()->issue($r, $this->admin(), [$r->items[0]->id => 1], $this->issueMeta());

        Notification::assertSentTo($requester, RequisitionStatusChanged::class, function (RequisitionStatusChanged $n) use ($requester, $r) {
            $mail = $n->toMail($requester);

            return $n->requisition->status === RequisitionStatus::PartiallyIssued
                && $mail->subject === "{$r->ris_no}: Supplies partially issued"
                && in_array('- Canon 337 Toner: 1 of 4 Cartridge (3 to follow)', $mail->introLines, true);
        });
    }

    public function test_low_stock_alert_is_sent_once_when_balance_reaches_reorder_point(): void
    {
        $admin = $this->admin();
        $item = $this->makeItem(12, ['name' => 'Air Freshener', 'stock_number' => 'JS-AF', 'unit' => 'Can', 'reorder_point' => 10], $admin);
        Notification::fake([LowStockAlert::class]);

        $this->issue($item, 1, $admin);
        $this->issue($item, 1, $admin);
        $this->issue($item, 1, $admin);

        Notification::assertSentToTimes($admin, LowStockAlert::class, 1);
        Notification::assertSentTo($admin, LowStockAlert::class, fn (LowStockAlert $n) => $n->balance === 10
            && $n->toMail($admin)->subject === 'Low stock: Air Freshener (JS-AF) — 10 Can left');
    }

    public function test_low_stock_alert_skips_administrators_with_placeholder_addresses(): void
    {
        $realAdmin = $this->admin();
        $placeholderAdmin = $this->admin();
        $placeholderAdmin->update(['email' => 'admin@demo.local']);
        $item = $this->makeItem(3, ['reorder_point' => 2], $realAdmin);
        Notification::fake([LowStockAlert::class]);

        $this->issue($item, 1, $realAdmin);

        Notification::assertSentTo($realAdmin, LowStockAlert::class);
        Notification::assertNotSentTo($placeholderAdmin, LowStockAlert::class);
    }

    public function test_out_of_stock_alert_is_sent_when_balance_reaches_zero(): void
    {
        $admin = $this->admin();
        $item = $this->makeItem(5, ['name' => 'Binder Clips', 'stock_number' => 'OS-BC', 'unit' => 'Box', 'reorder_point' => 10], $admin);
        Notification::fake([LowStockAlert::class]);

        $this->issue($item, 5, $admin);

        Notification::assertSentTo($admin, LowStockAlert::class, fn (LowStockAlert $n) => $n->balance === 0
            && $n->toMail($admin)->subject === 'Out of stock: Binder Clips (OS-BC) — 0 Box left');
    }

    public function test_extra_recipients_from_settings_receive_low_stock_alerts(): void
    {
        ApplicationSetting::put('notification_extra_emails', 'supply.office@example.com; chief@example.com');
        $admin = $this->admin();
        $item = $this->makeItem(3, ['reorder_point' => 2], $admin);
        Notification::fake([LowStockAlert::class]);

        $this->issue($item, 1, $admin);

        Notification::assertSentOnDemand(LowStockAlert::class, fn (LowStockAlert $n, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === ['supply.office@example.com', 'chief@example.com']);
    }

    public function test_turning_off_email_settings_sends_nothing(): void
    {
        $this->actingAs($admin = $this->admin())->put(route('settings.update'), [
            'entity_name' => 'Bureau of Internal Revenue', 'division' => 'Large Taxpayers Division – Cebu', 'system_title' => 'Stock Card',
            'items_per_page' => 15, 'notify_requisition_emails' => '0', 'notify_low_stock_emails' => '0',
        ])->assertRedirect();
        $item = $this->makeItem(3, ['reorder_point' => 2], $admin);
        $requester = $this->requester();
        Notification::fake();

        $this->issue($item, 3, $admin);
        $r = $this->approvedRequisition([[$this->makeItem(10, by: $admin), 1]], $requester);

        $this->assertSame(RequisitionStatus::Approved, $r->status);
        Notification::assertNothingSent();
    }

    public function test_invalid_extra_recipient_address_is_rejected(): void
    {
        $this->actingAs($this->admin())->put(route('settings.update'), [
            'entity_name' => 'Bureau of Internal Revenue', 'division' => 'Large Taxpayers Division – Cebu', 'system_title' => 'Stock Card',
            'items_per_page' => 15, 'notification_extra_emails' => 'ok@example.com, not-an-email',
        ])->assertSessionHasErrors(['notification_extra_emails' => 'These are not valid email addresses: not-an-email']);

        $this->assertSame('', ApplicationSetting::get('notification_extra_emails'));
    }

    public function test_low_stock_digest_lists_active_items_at_or_below_reorder_point(): void
    {
        $admin = $this->admin();
        $this->makeItem(5, ['stock_number' => 'LOW-1', 'reorder_point' => 10], $admin);
        $this->makeItem(0, ['stock_number' => 'OUT-1', 'reorder_point' => 3], $admin);
        $this->makeItem(50, ['stock_number' => 'OK-1', 'reorder_point' => 10], $admin);
        $this->makeItem(1, ['stock_number' => 'ARCHIVED-1', 'reorder_point' => 10], $admin)->update(['status' => InventoryItem::STATUS_INACTIVE]);
        Notification::fake([LowStockDigest::class]);

        $this->artisan('inventory:low-stock-digest')->expectsOutput('Low-stock report sent for 2 item(s).')->assertSuccessful();

        Notification::assertSentTo($admin, LowStockDigest::class, fn (LowStockDigest $n) => $n->items->pluck('stock_number')->all() === ['OUT-1', 'LOW-1']
            && $n->toMail($admin)->subject === 'Low-stock report: 2 item(s) need replenishment (1 out of stock)');
    }

    public function test_low_stock_digest_sends_nothing_when_all_items_are_above_reorder_point(): void
    {
        $this->makeItem(50, ['reorder_point' => 10]);
        Notification::fake([LowStockDigest::class]);

        $this->artisan('inventory:low-stock-digest')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_no_alert_is_sent_when_the_issuance_is_rolled_back(): void
    {
        $admin = $this->admin();
        $low = $this->makeItem(11, ['reorder_point' => 10], $admin);
        $scarce = $this->makeItem(5, ['reorder_point' => 0], $admin);
        $r = $this->approvedRequisition([[$low, 2], [$scarce, 5]]);
        $lines = $r->items->keyBy('item_id');
        $this->issue($scarce, 1, $admin);
        Notification::fake([LowStockAlert::class]);

        try {
            $this->ris()->issue($r, $admin, [$lines[$low->id]->id => 2, $lines[$scarce->id]->id => 5], $this->issueMeta());
            $this->fail('The second line should exceed available stock');
        } catch (InventoryException) {
        }

        $this->assertSame(11, $low->fresh()->current_balance);
        Notification::assertNotSentTo($admin, LowStockAlert::class);
    }

    public function test_mail_failure_is_logged_and_does_not_block_the_posting(): void
    {
        $admin = $this->admin();
        $item = $this->makeItem(3, ['reorder_point' => 2], $admin);
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP connection refused'));
        Log::shouldReceive('error')->once()->withArgs(fn (string $message) => str_contains($message, 'SMTP connection refused'));

        $this->issue($item, 1, $admin);

        $this->assertSame(2, $item->fresh()->current_balance);
        $this->assertSame(1, $item->transactions()->where('type', TransactionType::Issue)->count());
    }
}
