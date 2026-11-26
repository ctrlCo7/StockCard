<?php

namespace App\Notifications;

use App\Models\InventoryItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to administrators the moment a posting takes an item to its re-order point or to zero. */
class LowStockAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public InventoryItem $item,
        public int $balance,
        public string $reference,
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
        $item = $this->item;
        $out = $this->balance <= 0;

        return (new MailMessage)
            ->subject(($out ? 'Out of stock: ' : 'Low stock: ')."{$item->name} ({$item->stock_number}) — {$this->balance} {$item->unit} left")
            ->greeting($out ? 'Item is out of stock' : 'Re-order point reached')
            ->line("**{$item->name}** ({$item->stock_number}) now has **{$this->balance} {$item->unit}** on hand. Its re-order point is {$item->reorder_point}.")
            ->line("Triggered by: {$this->reference}")
            ->action('Open Stock Card', route('stock-cards.show', $item))
            ->line('Please arrange replenishment. You will not be alerted again for this item until its balance rises above the re-order point.');
    }
}
