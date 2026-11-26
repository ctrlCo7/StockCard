<?php

namespace App\Notifications;

use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/** Daily summary of every active item at or below its re-order point. */
class LowStockDigest extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, InventoryItem>  $items
     * @param  User|null  $sender  Set when an administrator sends the report manually
     */
    public function __construct(
        public Collection $items,
        public ?string $note = null,
        public ?User $sender = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $out = $this->items->filter->isOutOfStock()->count();

        return (new MailMessage)
            ->subject("Low-stock report: {$this->items->count()} item(s) need replenishment".($out ? " ({$out} out of stock)" : ''))
            ->markdown('mail.low-stock-digest', [
                'items' => $this->items,
                'note' => $this->note,
                'sender' => $this->sender,
                'reportUrl' => route('reports.show', 'low-stock'),
            ]);
    }
}
