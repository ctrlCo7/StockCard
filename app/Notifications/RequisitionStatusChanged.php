<?php

namespace App\Notifications;

use App\Enums\RequisitionStatus;
use App\Models\Requisition;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to the requester when the supply office acts on their RIS. */
class RequisitionStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Requisition $requisition,
        public string $action,
        public ?string $remarks = null,
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
        $r = $this->requisition->loadMissing('items.item');
        $status = $r->status;

        $mail = (new MailMessage)
            ->subject("{$r->ris_no}: {$this->headline()}")
            ->greeting($this->headline())
            ->line("Your requisition **{$r->ris_no}** is now **{$status->label()}**.");

        if ($status === RequisitionStatus::Rejected) {
            $mail->error();
        }

        if (in_array($status, [RequisitionStatus::PartiallyIssued, RequisitionStatus::FullyIssued], true)) {
            $mail->line('**Issued so far:**');
            foreach ($r->items as $line) {
                $remaining = $line->remainingToIssue();
                $mail->line("- {$line->item->name}: {$line->quantity_issued} of {$line->quantity_approved} {$line->item->unit}".($remaining > 0 ? " ({$remaining} to follow)" : ''));
            }
            $mail->line('Please collect the items and acknowledge receipt in the system.');
        }

        if ($status === RequisitionStatus::Returned) {
            $mail->line('Please correct the requisition and submit it again.');
        }

        if (filled($this->remarks)) {
            $mail->line("**Remarks:** {$this->remarks}");
        }

        return $mail->action('View requisition', route('requisitions.show', $r));
    }

    private function headline(): string
    {
        return match ($this->action) {
            'approved' => 'Requisition approved',
            'rejected' => 'Requisition rejected',
            'returned' => 'Requisition returned for correction',
            'cancelled' => 'Requisition cancelled',
            'issued' => $this->requisition->status === RequisitionStatus::FullyIssued ? 'Supplies issued' : 'Supplies partially issued',
            default => 'Requisition updated',
        };
    }
}
