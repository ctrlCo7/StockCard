<?php

namespace App\Enums;

enum RequisitionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case PendingApproval = 'pending_approval';
    case Returned = 'returned';
    case Approved = 'approved';
    case PartiallyIssued = 'partially_issued';
    case FullyIssued = 'fully_issued';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::PendingApproval => 'Pending Approval',
            self::Returned => 'Returned for Correction',
            self::Approved => 'Approved',
            self::PartiallyIssued => 'Partially Issued',
            self::FullyIssued => 'Fully Issued',
            self::Completed => 'Received / Completed',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Submitted => 'info',
            self::PendingApproval => 'warning',
            self::Returned => 'warning',
            self::Approved => 'primary',
            self::PartiallyIssued => 'teal',
            self::FullyIssued => 'success',
            self::Completed => 'dark',
            self::Rejected, self::Cancelled => 'danger',
        };
    }

    /** The requester may still edit the items and header. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Returned], true);
    }

    /** Stock may be issued against the requisition. */
    public function isIssuable(): bool
    {
        return in_array($this, [self::Approved, self::PartiallyIssued], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
