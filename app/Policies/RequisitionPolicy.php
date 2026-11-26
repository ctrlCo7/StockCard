<?php

namespace App\Policies;

use App\Enums\RequisitionStatus as Status;
use App\Models\Requisition;
use App\Models\User;

/**
 * Record-level rules for RIS documents. Role abilities come from config/permissions.php.
 * These rules are evaluated for administrators too (AppServiceProvider does not bypass
 * requisition policies), so every action is limited to the statuses where it makes sense.
 */
class RequisitionPolicy
{
    public function view(User $user, Requisition $r): bool
    {
        return $this->owns($user, $r) || $user->can('requisitions.view-all');
    }

    /** Only the requester edits their own request, while it is a draft or returned. */
    public function update(User $user, Requisition $r): bool
    {
        return $this->owns($user, $r) && $r->status->isEditable();
    }

    public function submit(User $user, Requisition $r): bool
    {
        return $this->update($user, $r);
    }

    /** The requester may withdraw before approval; an administrator may also cancel an approved, not yet issued RIS. */
    public function cancel(User $user, Requisition $r): bool
    {
        if ($this->owns($user, $r) && in_array($r->status, [Status::Draft, Status::Submitted, Status::PendingApproval, Status::Returned], true)) {
            return true;
        }

        return $user->isAdmin() && in_array($r->status, [Status::Submitted, Status::PendingApproval, Status::Returned, Status::Approved], true);
    }

    public function forward(User $user, Requisition $r): bool
    {
        return $user->can('requisitions.forward') && $r->status === Status::Submitted;
    }

    /** Covers approve, reject and return-for-correction. Requesting personnel never hold this ability. */
    public function approve(User $user, Requisition $r): bool
    {
        return $user->can('requisitions.approve')
            && in_array($r->status, [Status::Submitted, Status::PendingApproval], true);
    }

    public function returnForCorrection(User $user, Requisition $r): bool
    {
        return $this->approve($user, $r) || $this->forward($user, $r);
    }

    /** Stock can be issued only after approval, until everything approved has been issued. */
    public function issue(User $user, Requisition $r): bool
    {
        return $user->can('requisitions.issue') && $r->status->isIssuable();
    }

    /** Only the person who requested the supplies confirms that they received them. */
    public function acknowledge(User $user, Requisition $r): bool
    {
        return in_array($r->status, [Status::PartiallyIssued, Status::FullyIssued], true)
            && $this->owns($user, $r);
    }

    private function owns(User $user, Requisition $r): bool
    {
        return $r->requested_by === $user->id;
    }
}
