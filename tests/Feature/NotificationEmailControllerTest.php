<?php

namespace Tests\Feature;

use App\Models\Requisition;
use App\Notifications\LowStockDigest;
use App\Notifications\RequisitionNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationEmailControllerTest extends TestCase
{
    use RefreshDatabase;

    private function draftRequisition(): Requisition
    {
        $item = $this->makeItem(10, ['name' => 'A4 Bond Paper', 'unit' => 'Ream']);

        return $this->ris()->createDraft(['office_id' => $this->office->id, 'requisition_date' => today(), 'purpose' => 'Monthly supplies'],
            [['item_id' => $item->id, 'quantity' => 3]], $this->requester());
    }

    public function test_administrator_emails_ris_summary_to_the_addresses_entered(): void
    {
        config(['mail.default' => 'smtp']);
        $admin = $this->admin();
        $r = $this->draftRequisition();
        Notification::fake([RequisitionNotice::class]);

        $this->actingAs($admin)->from(route('requisitions.show', $r))->post(route('requisitions.email', $r), [
            'emails' => 'Juan@Example.com; maria@example.com', 'note' => 'Please collect at the Supply Room.',
        ])->assertRedirect(route('requisitions.show', $r))
            ->assertSessionHas('success', "RIS {$r->ris_no} emailed to juan@example.com, maria@example.com.");

        Notification::assertSentOnDemand(RequisitionNotice::class, function (RequisitionNotice $n, array $channels, AnonymousNotifiable $notifiable) use ($r, $admin) {
            $mail = $n->toMail($notifiable);

            return $notifiable->routes['mail'] === ['juan@example.com', 'maria@example.com']
                && $mail->subject === "{$r->ris_no}: Draft"
                && in_array('**Message:** Please collect at the Supply Room.', $mail->introLines, true)
                && in_array('- A4 Bond Paper (Ream): requested 3', $mail->introLines, true)
                && $n->sender->is($admin);
        });
        $this->assertDatabaseHas('audit_logs', ['action' => 'notification.emailed', 'user_id' => $admin->id, 'auditable_id' => $r->id]);
    }

    public function test_sender_is_warned_when_mail_is_only_written_to_the_log(): void
    {
        config(['mail.default' => 'log']);
        $r = $this->draftRequisition();
        Notification::fake([RequisitionNotice::class]);

        $this->actingAs($this->admin())->post(route('requisitions.email', $r), ['emails' => 'juan@example.com'])
            ->assertSessionHas('warning', fn (string $message) => str_contains($message, 'written to the log instead of being delivered'));
    }

    public function test_invalid_email_address_is_rejected_and_nothing_is_sent(): void
    {
        $r = $this->draftRequisition();
        Notification::fake();

        $this->actingAs($this->admin())->post(route('requisitions.email', $r), ['emails' => 'juan@example.com, not-an-email'])
            ->assertSessionHasErrors(['recipients.1' => '"not-an-email" is not a valid email address.']);

        Notification::assertNothingSent();
    }

    public function test_requester_cannot_send_notification_emails(): void
    {
        $r = $this->draftRequisition();
        Notification::fake();

        $this->actingAs($r->requester)->post(route('requisitions.email', $r), ['emails' => 'someone@example.com'])->assertForbidden();
        $this->actingAs($r->requester)->post(route('notifications.low-stock'), ['emails' => 'someone@example.com'])->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_administrator_emails_current_low_stock_report_on_demand(): void
    {
        $admin = $this->admin();
        $this->makeItem(2, ['stock_number' => 'LOW-1', 'reorder_point' => 5], $admin);
        $this->makeItem(40, ['stock_number' => 'OK-1', 'reorder_point' => 5], $admin);
        Notification::fake([LowStockDigest::class]);

        $this->actingAs($admin)->post(route('notifications.low-stock'), ['emails' => 'supply@example.com', 'note' => 'For purchase request'])->assertRedirect();

        Notification::assertSentOnDemand(LowStockDigest::class, fn (LowStockDigest $n, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === ['supply@example.com']
            && $n->items->pluck('stock_number')->all() === ['LOW-1']
            && $n->note === 'For purchase request'
            && $n->sender->is($admin));
    }

    public function test_low_stock_email_is_not_sent_when_no_items_are_low(): void
    {
        $admin = $this->admin();
        $this->makeItem(40, ['reorder_point' => 5], $admin);
        Notification::fake();

        $this->actingAs($admin)->post(route('notifications.low-stock'), ['emails' => 'supply@example.com'])
            ->assertSessionHas('warning', 'No items are at or below their re-order point, so there is nothing to send.');

        Notification::assertNothingSent();
    }

    public function test_delivery_failure_is_shown_to_the_sender_and_not_logged_as_sent(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        $admin = $this->admin();
        $r = $this->draftRequisition();

        $this->actingAs($admin)->post(route('requisitions.email', $r), ['emails' => 'juan@example.com'])
            ->assertSessionHas('error', fn (string $message) => str_starts_with($message, 'The email could not be sent:'));

        $this->assertDatabaseMissing('audit_logs', ['action' => 'notification.emailed']);
    }
}
