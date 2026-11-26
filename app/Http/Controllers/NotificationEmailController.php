<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendNotificationEmailRequest;
use App\Models\Requisition;
use App\Notifications\LowStockDigest;
use App\Notifications\RequisitionNotice;
use App\Services\AuditLogger;
use App\Services\InventoryNotifier;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\Notification;
use Throwable;

/** Handles the "Send email" buttons: an administrator emails a notice to addresses they type in. */
class NotificationEmailController extends Controller
{
    public function __construct(private InventoryNotifier $notifier) {}

    public function requisition(SendNotificationEmailRequest $request, Requisition $requisition): RedirectResponse
    {
        $notice = new RequisitionNotice($requisition, $request->user(), $request->validated('note'));

        return $this->deliver($request, $notice, "RIS {$requisition->ris_no}", $requisition);
    }

    public function lowStock(SendNotificationEmailRequest $request): RedirectResponse
    {
        $items = $this->notifier->lowStockItems();
        if ($items->isEmpty()) {
            return back()->with('warning', 'No items are at or below their re-order point, so there is nothing to send.');
        }

        return $this->deliver($request, new LowStockDigest($items, $request->validated('note'), $request->user()), 'Low-stock report');
    }

    private function deliver(SendNotificationEmailRequest $request, Notification $notification, string $label, ?Model $subject = null): RedirectResponse
    {
        $recipients = $request->recipients();

        try {
            $this->notifier->sendNow($recipients, $notification);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', "The email could not be sent: {$e->getMessage()}");
        }

        AuditLogger::log('notification.emailed', "{$label} emailed to ".implode(', ', $recipients), $subject, null, ['recipients' => $recipients]);

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            return back()->with('warning', "{$label} was prepared for ".implode(', ', $recipients).', but mail is set to "'.config('mail.default').'", so it was written to the log instead of being delivered. Configure SMTP in .env to send real email.');
        }

        return back()->with('success', "{$label} emailed to ".implode(', ', $recipients).'.');
    }
}
