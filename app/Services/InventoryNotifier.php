<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\ApplicationSetting;
use App\Models\InventoryItem;
use App\Models\Requisition;
use App\Models\User;
use App\Notifications\LowStockAlert;
use App\Notifications\LowStockDigest;
use App\Notifications\RequisitionStatusChanged;
use App\Notifications\RequisitionSubmitted;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Decides who is emailed about requisitions and stock levels.
 *
 * Emails are dispatched only after the surrounding database transaction commits, so a
 * rolled-back posting never sends mail. A mail failure is logged and never breaks the
 * posting that triggered it.
 */
class InventoryNotifier
{
    /** An RIS was submitted: tell the supply office. */
    public function requisitionSubmitted(Requisition $requisition, User $actor): void
    {
        if (! $this->enabled('notify_requisition_emails')) {
            return;
        }

        $this->send($this->administrators($actor), new RequisitionSubmitted($requisition), withExtraRecipients: true);
    }

    /** The supply office acted on an RIS: tell the requester (unless they acted themselves). */
    public function requisitionStatusChanged(Requisition $requisition, string $action, User $actor, ?string $remarks = null): void
    {
        $requester = $requisition->requester()->first();
        if (! $this->enabled('notify_requisition_emails') || ! $requester?->is_active || $requester->is($actor) || ! $requester->hasDeliverableEmail()) {
            return;
        }

        $this->send(new Collection([$requester]), new RequisitionStatusChanged($requisition, $action, $remarks));
    }

    /**
     * Alert when a posting takes the balance to or below the re-order point, or to zero.
     * Only the crossing is reported, so repeated issuances below the threshold do not spam.
     */
    public function stockLevelChanged(InventoryItem $item, int $previous, int $new, string $reference): void
    {
        $reachedReorderPoint = $previous > $item->reorder_point && $new <= $item->reorder_point;
        $ranOut = $previous > 0 && $new === 0;

        if ((! $reachedReorderPoint && ! $ranOut) || ! $this->enabled('notify_low_stock_emails')) {
            return;
        }

        $this->send($this->administrators(), new LowStockAlert($item, $new, $reference), withExtraRecipients: true);
    }

    /**
     * Send the daily low-stock summary.
     *
     * @return int Number of items reported (0 when nothing was sent)
     */
    public function lowStockDigest(): int
    {
        if (! $this->enabled('notify_low_stock_emails')) {
            return 0;
        }

        $items = $this->lowStockItems();

        if ($items->isNotEmpty()) {
            $this->send($this->administrators(), new LowStockDigest($items), withExtraRecipients: true);
        }

        return $items->count();
    }

    /** @return Collection<int, InventoryItem> Active items at or below their re-order point, most urgent first. */
    public function lowStockItems(): Collection
    {
        return InventoryItem::active()->whereColumn('current_balance', '<=', 'reorder_point')
            ->orderBy('current_balance')->orderBy('name')->get();
    }

    /**
     * Send a notification immediately to typed-in addresses (the "Send email" button).
     * Unlike automatic emails, failures are thrown so the sender sees them.
     *
     * @param  array<int, string>  $emails
     */
    public function sendNow(array $emails, BaseNotification $notification): void
    {
        Notification::route('mail', $emails)->notifyNow($notification);
    }

    /** @return array<int, string> Account emails of the administrators who receive automatic emails. */
    public function administratorEmails(): array
    {
        return $this->administrators()->pluck('email')->all();
    }

    /**
     * Active administrators with a real (deliverable) email address.
     *
     * @return Collection<int, User>
     */
    private function administrators(?User $except = null): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::Admin->value))
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->get()
            ->filter->hasDeliverableEmail()
            ->values();
    }

    /** @return array<int, string> */
    private function extraRecipients(): array
    {
        return collect(preg_split('/[\s,;]+/', (string) ApplicationSetting::get('notification_extra_emails', '')))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()->values()->all();
    }

    private function enabled(string $setting): bool
    {
        return ApplicationSetting::get($setting) === '1';
    }

    /** @param  Collection<int, User>  $users */
    private function send(Collection $users, BaseNotification $notification, bool $withExtraRecipients = false): void
    {
        $extra = $withExtraRecipients ? $this->extraRecipients() : [];

        DB::afterCommit(function () use ($users, $notification, $extra) {
            try {
                if ($users->isNotEmpty()) {
                    Notification::send($users, $notification);
                }
                if ($extra !== []) {
                    Notification::route('mail', $extra)->notify($notification);
                }
            } catch (Throwable $e) {
                Log::error('Email notification failed: '.$e->getMessage(), ['notification' => $notification::class]);
            }
        });
    }
}
