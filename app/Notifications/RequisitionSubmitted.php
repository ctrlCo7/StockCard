<?php

namespace App\Notifications;

use App\Models\Requisition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to administrators when a requester submits (or resubmits) an RIS. */
class RequisitionSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Requisition $requisition) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $r = $this->requisition->loadMissing(['items.item', 'requester', 'office']);

        $mail = (new MailMessage)
            ->subject("New requisition {$r->ris_no} from {$r->requester->name}")
            ->greeting('New requisition for processing')
            ->line("**{$r->requester->name}** ({$r->office?->name}) submitted **{$r->ris_no}** dated {$r->requisition_date->format('F j, Y')}.")
            ->line("**Purpose:** {$r->purpose}")
            ->line('**Items requested:**');

        foreach ($r->items as $line) {
            $available = $line->item->current_balance >= $line->quantity_requested ? 'in stock' : "only {$line->item->current_balance} on hand";
            $mail->line("- {$line->quantity_requested} {$line->item->unit} — {$line->item->name} ({$line->item->stock_number}, {$available})");
        }

        return $mail
            ->action('Review requisition', route('requisitions.show', $r))
            ->line('Stock is not deducted until the issuance is posted.');
    }
}
