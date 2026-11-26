<?php

namespace App\Notifications;

use App\Models\Requisition;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A manually sent RIS summary (the "Send email" button). Sent immediately rather than
 * queued so the administrator sees at once whether delivery succeeded.
 */
class RequisitionNotice extends Notification
{
    use Queueable;

    public function __construct(
        public Requisition $requisition,
        public User $sender,
        public ?string $note = null,
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
        $r = $this->requisition->loadMissing(['items.item', 'requester', 'office']);

        $mail = (new MailMessage)
            ->subject("{$r->ris_no}: {$r->status->label()}")
            ->greeting("Requisition {$r->ris_no}")
            ->line("Sent by {$this->sender->name}".($this->sender->designation ? ", {$this->sender->designation}" : '').'.');

        if (filled($this->note)) {
            $mail->line("**Message:** {$this->note}");
        }

        $mail->line("**Status:** {$r->status->label()}")
            ->line("**Requested by:** {$r->requester->name} ({$r->office?->name}), {$r->requisition_date->format('F j, Y')}")
            ->line("**Purpose:** {$r->purpose}")
            ->line('**Items:**');

        foreach ($r->items as $line) {
            $parts = ["requested {$line->quantity_requested}"];
            if ($line->quantity_approved !== null) {
                $parts[] = "approved {$line->quantity_approved}";
            }
            if ($line->quantity_issued > 0) {
                $parts[] = "issued {$line->quantity_issued}";
            }
            $mail->line("- {$line->item->name} ({$line->item->unit}): ".implode(', ', $parts));
        }

        return $mail->action('View requisition', route('requisitions.show', $r));
    }
}
