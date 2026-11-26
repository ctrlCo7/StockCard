<?php

namespace App\Services;

use App\Enums\RequisitionStatus as Status;
use App\Enums\Role;
use App\Enums\TransactionType;
use App\Exceptions\InventoryException;
use App\Models\ApplicationSetting;
use App\Models\InventoryItem;
use App\Models\Issuance;
use App\Models\Office;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Requisition and Issue Slip workflow:
 *
 *   draft → submitted → pending_approval → approved → partially_issued → fully_issued → completed
 *            ↘ returned (back to requester) / rejected / cancelled
 *
 * Stock is deducted only by issue(), which posts one ledger row per line via InventoryService.
 */
class RequisitionService
{
    public function __construct(
        private InventoryService $inventory,
        private DocumentNumberService $numbers,
        private InventoryNotifier $notifier,
        private SignatureService $signatures,
    ) {}

    /**
     * @param  array<int, array{item_id: int, quantity: int, remarks?: ?string}>  $lines
     */
    public function createDraft(array $header, array $lines, User $user): Requisition
    {
        if ($user->cannot('requisitions.create')) {
            throw new InventoryException('Only requesting personnel can file requisitions. Administrators approve and issue them.');
        }
        $this->validateLines($lines);

        return DB::transaction(function () use ($header, $lines, $user) {
            $office = Office::find($header['office_id'] ?? null) ?? $user->office;
            $requisition = Requisition::create([
                'entity_name' => ApplicationSetting::get('entity_name'),
                'fund_cluster' => ApplicationSetting::get('fund_cluster'),
                'responsibility_center_code' => $office?->responsibility_center_code ?: ApplicationSetting::get('responsibility_center_code'),
                ...array_filter($header, fn ($v) => $v !== null && $v !== ''),
                'office_id' => $office?->id,
                'division' => $office?->division ?: ApplicationSetting::get('division'),
                'ris_no' => $this->numbers->next(DocumentNumberService::RIS),
                'status' => Status::Draft,
                'requested_by' => $user->id,
                'requested_by_designation' => $user->designation,
            ]);
            $this->syncLines($requisition, $lines);
            $this->record($requisition, $user, 'created', null, Status::Draft);
            AuditLogger::log('requisition.created', "RIS {$requisition->ris_no} drafted", $requisition);

            return $requisition;
        });
    }

    public function updateDraft(Requisition $requisition, array $header, array $lines, User $user): Requisition
    {
        $this->validateLines($lines);

        return DB::transaction(function () use ($requisition, $header, $lines, $user) {
            $requisition = $this->lock($requisition);
            $this->assertStatus($requisition, [Status::Draft, Status::Returned], 'edited');

            $office = Office::find($header['office_id'] ?? $requisition->office_id);
            $requisition->update([...$header, 'division' => $office?->division ?: ApplicationSetting::get('division')]);
            $this->syncLines($requisition, $lines);
            AuditLogger::log('requisition.updated', "RIS {$requisition->ris_no} edited", $requisition, null, $header, $user->id);

            return $requisition;
        });
    }

    public function submit(Requisition $requisition, User $user): Requisition
    {
        return $this->transition($requisition, $user, [Status::Draft, Status::Returned], Status::Submitted, 'submitted', null, function (Requisition $r) use ($user) {
            if ($r->items()->count() === 0) {
                throw new InventoryException('Add at least one item before submitting.');
            }
            $r->submitted_at = now();
            $r->requested_by_designation = $user->designation;
            $this->applySignature($r, $user, 'requested');
        });
    }

    /** Inventory officer verifies stock availability and forwards to the approving officer. */
    public function forward(Requisition $requisition, User $user, array $lineRemarks = [], ?string $remarks = null): Requisition
    {
        return $this->transition($requisition, $user, [Status::Submitted], Status::PendingApproval, 'forwarded', $remarks, function (Requisition $r) use ($lineRemarks) {
            foreach ($r->items()->with('item')->get() as $line) {
                $line->stock_available = $line->item->current_balance >= $line->quantity_requested;
                if (array_key_exists($line->id, $lineRemarks)) {
                    $line->remarks = $lineRemarks[$line->id] ?: null;
                }
                $line->save();
            }
        });
    }

    /**
     * Approve with per-line approved quantities (0..requested). Does NOT touch stock.
     *
     * @param  array<int, int>  $approved  requisition_item_id => quantity; omitted lines are approved as requested
     */
    public function approve(Requisition $requisition, User $user, array $approved = [], ?string $remarks = null): Requisition
    {
        if ($requisition->requested_by === $user->id && ! $user->isAdmin()) {
            throw new InventoryException('You cannot approve your own requisition.');
        }

        return $this->transition($requisition, $user, [Status::PendingApproval], Status::Approved, 'approved', $remarks, function (Requisition $r) use ($user, $approved, $remarks) {
            $total = 0;
            foreach ($r->items as $line) {
                $qty = (int) ($approved[$line->id] ?? $line->quantity_requested);
                if ($qty < 0 || $qty > $line->quantity_requested) {
                    throw new InventoryException("Approved quantity for {$line->item->name} must be between 0 and {$line->quantity_requested}.");
                }
                $line->quantity_approved = $qty;
                $line->save();
                $total += $qty;
            }
            if ($total === 0) {
                throw new InventoryException('At least one line must have an approved quantity. Reject the requisition instead.');
            }
            $r->approved_by = $user->id;
            $r->approved_by_designation = $user->designation;
            $r->approved_at = now();
            $r->approval_remarks = $remarks;
            $this->applySignature($r, $user, 'approved');
        });
    }

    public function reject(Requisition $requisition, User $user, string $remarks): Requisition
    {
        $this->requireRemarks($remarks, 'reject');

        return $this->transition($requisition, $user, [Status::Submitted, Status::PendingApproval], Status::Rejected, 'rejected', $remarks, function (Requisition $r) use ($user, $remarks) {
            $r->approved_by = $user->id;
            $r->approved_by_designation = $user->designation;
            $r->approved_at = now();
            $r->approval_remarks = $remarks;
        });
    }

    public function returnForCorrection(Requisition $requisition, User $user, string $remarks): Requisition
    {
        $this->requireRemarks($remarks, 'return');

        return $this->transition($requisition, $user, [Status::Submitted, Status::PendingApproval], Status::Returned, 'returned', $remarks);
    }

    public function cancel(Requisition $requisition, User $user, ?string $remarks = null): Requisition
    {
        $allowed = [Status::Draft, Status::Submitted, Status::PendingApproval, Status::Returned];
        if ($user->isAdmin()) {
            $allowed[] = Status::Approved; // only before anything has been issued
        }

        return $this->transition($requisition, $user, $allowed, Status::Cancelled, 'cancelled', $remarks, function (Requisition $r) {
            if ($r->items()->where('quantity_issued', '>', 0)->exists()) {
                throw new InventoryException('Stock has already been issued against this requisition; it cannot be cancelled.');
            }
        });
    }

    /**
     * Post an issuance. Creates one Stock Card issue row per line, referenced by the RIS number.
     *
     * @param  array<int, int>  $quantities  requisition_item_id => quantity to issue now
     * @return array{0: Issuance, 1: bool} The issuance and whether it was newly posted.
     */
    public function issue(Requisition $requisition, User $user, array $quantities, array $meta): array
    {
        if ($existing = Issuance::where('idempotency_key', $meta['idempotency_key'])->first()) {
            return [$existing, false];
        }

        $quantities = array_filter(array_map('intval', $quantities), fn (int $q) => $q !== 0);
        if ($quantities === []) {
            throw new InventoryException('Enter a quantity to issue for at least one item.');
        }

        try {
            $issuance = DB::transaction(function () use ($requisition, $user, $quantities, $meta) {
                // Locking the requisition serializes issuances for the same RIS, so the
                // "remaining" check below cannot be raced by a second click or tab.
                $r = $this->lock($requisition);
                $this->assertStatus($r, [Status::Approved, Status::PartiallyIssued], 'issued');

                $recipient = $this->recipientFor($r, $meta['received_by_user_id'] ?? null);
                $meta['received_by_name'] = $recipient->name;

                $issuance = Issuance::create([
                    'issuance_no' => $this->numbers->next(DocumentNumberService::ISSUANCE),
                    'requisition_id' => $r->id,
                    'issue_date' => $meta['issue_date'],
                    'issued_by' => $user->id,
                    'received_by_name' => $recipient->name,
                    'received_by_user_id' => $recipient->id,
                    'remarks' => $meta['remarks'] ?? null,
                    'idempotency_key' => $meta['idempotency_key'],
                ]);

                $lines = $r->items()->with('item')->get()->keyBy('id');
                foreach ($quantities as $lineId => $qty) {
                    /** @var RequisitionItem|null $line */
                    $line = $lines->get($lineId);
                    if (! $line) {
                        throw new InventoryException('An issued item does not belong to this requisition.');
                    }
                    if ($qty < 0) {
                        throw new InventoryException("Issued quantity for {$line->item->name} cannot be negative.");
                    }
                    if ($qty > $line->remainingToIssue()) {
                        throw new InventoryException(sprintf('Cannot issue %d %s of %s: only %d remain approved for issuance.',
                            $qty, $line->item->unit, $line->item->name, $line->remainingToIssue()));
                    }

                    $issuanceItem = $issuance->items()->create([
                        'requisition_item_id' => $line->id,
                        'item_id' => $line->item_id,
                        'quantity' => $qty,
                    ]);

                    // Throws (and rolls everything back) if stock is insufficient.
                    $this->inventory->post($line->item_id, [
                        'type' => TransactionType::Issue,
                        'date' => $issuance->issue_date,
                        'reference' => $r->ris_no,
                        // Paper-form convention: "Office (Recipient)", e.g. "ODC/Admin (Tampos)".
                        'party' => trim(($r->office->name ?? '').' ('.$meta['received_by_name'].')'),
                        'issued' => $qty,
                        'remarks' => $meta['remarks'] ?? null,
                        'requisition_id' => $r->id,
                        'issuance_item_id' => $issuanceItem->id,
                    ], $user);

                    $line->increment('quantity_issued', $qty);
                }

                $r->issued_by = $user->id;
                $r->issued_by_designation = $user->designation;
                $this->applySignature($r, $user, 'issued');
                $r->issued_at = now();
                $r->save();

                $this->refreshIssueStatus($r, $user, "Issuance {$issuance->issuance_no} posted");
                $this->notifier->requisitionStatusChanged($r, 'issued', $user, $meta['remarks'] ?? null);
                AuditLogger::log('requisition.issued', "Issuance {$issuance->issuance_no} posted for RIS {$r->ris_no}", $issuance, null, ['lines' => $quantities]);

                return $issuance;
            });
        } catch (UniqueConstraintViolationException) {
            return [Issuance::where('idempotency_key', $meta['idempotency_key'])->firstOrFail(), false];
        }

        return [$issuance, true];
    }

    /** Requester acknowledges receipt of what was issued; finalizes the RIS. */
    public function acknowledgeReceipt(Requisition $requisition, User $user, ?string $remarks = null): Requisition
    {
        if ($requisition->requested_by !== $user->id) {
            throw new InventoryException('Only the person who requested these supplies can acknowledge receiving them.');
        }

        return $this->transition($requisition, $user, [Status::PartiallyIssued, Status::FullyIssued], Status::Completed, 'received', $remarks, function (Requisition $r) use ($user) {
            $r->received_by = $user->id;
            $r->received_by_designation = $user->designation;
            $this->applySignature($r, $user, 'received');
            $r->received_at = now();
        });
    }

    /** Recompute Approved / Partially Issued / Fully Issued from the line quantities. */
    public function refreshIssueStatus(Requisition $requisition, User $user, ?string $remarks = null): void
    {
        $requisition->load('items');
        $issued = $requisition->items->sum('quantity_issued');
        $remaining = $requisition->totalRemaining();

        $status = match (true) {
            $issued === 0 => Status::Approved,
            $remaining === 0 => Status::FullyIssued,
            default => Status::PartiallyIssued,
        };

        if ($status !== $requisition->status) {
            $from = $requisition->status;
            $requisition->status = $status;
            $requisition->save();
            $this->record($requisition, $user, 'issue_status', $from, $status, $remarks);
        }
    }

    // ------------------------------------------------------------------

    private function transition(Requisition $requisition, User $user, array $from, Status $to, string $action, ?string $remarks = null, ?callable $mutate = null): Requisition
    {
        return DB::transaction(function () use ($requisition, $user, $from, $to, $action, $remarks, $mutate) {
            $r = $this->lock($requisition);
            $this->assertStatus($r, $from, $action);

            $previous = $r->status;
            if ($mutate) {
                $mutate($r);
            }
            $r->status = $to;
            $r->save();

            $this->record($r, $user, $action, $previous, $to, $remarks);
            AuditLogger::log("requisition.{$action}", "RIS {$r->ris_no} {$action} ({$previous->label()} → {$to->label()})".($remarks ? ": {$remarks}" : ''), $r, ['status' => $previous->value], ['status' => $to->value], $user->id);

            if ($action === 'submitted') {
                $this->notifier->requisitionSubmitted($r, $user);
            } elseif (in_array($action, ['approved', 'rejected', 'returned', 'cancelled'], true)) {
                $this->notifier->requisitionStatusChanged($r, $action, $user, $remarks);
            }

            return $r;
        });
    }

    /**
     * Stamp a copy of the user's current e-signature onto a signatory step, replacing any
     * earlier copy for that step (for example, after a later partial issuance).
     *
     * @param  'requested'|'approved'|'issued'|'received'  $step
     */
    private function applySignature(Requisition $r, User $user, string $step): void
    {
        $column = "{$step}_signature_path";
        $previous = $r->{$column};
        $r->{$column} = $this->signatures->snapshot($user, "requisitions/{$r->id}");
        if ($previous && $previous !== $r->{$column}) {
            DB::afterCommit(fn () => $this->signatures->delete($previous));
        }
    }

    /**
     * Supplies are handed to requesting personnel only: the chosen recipient, or by default
     * the person who filed the requisition.
     */
    private function recipientFor(Requisition $r, int|string|null $userId): User
    {
        $recipient = User::find($userId ?: $r->requested_by);

        if (! $recipient?->is_active || ! $recipient->hasRole(Role::Requester)) {
            throw new InventoryException('Supplies can only be issued to active requesting personnel.');
        }

        return $recipient;
    }

    private function lock(Requisition $requisition): Requisition
    {
        return Requisition::query()->whereKey($requisition->id)->lockForUpdate()->firstOrFail();
    }

    private function assertStatus(Requisition $r, array $allowed, string $action): void
    {
        if (! in_array($r->status, $allowed, true)) {
            throw new InventoryException("RIS {$r->ris_no} cannot be {$action} while its status is \"{$r->status->label()}\".");
        }
    }

    private function requireRemarks(?string $remarks, string $action): void
    {
        if (blank($remarks)) {
            throw new InventoryException("Remarks are required to {$action} a requisition.");
        }
    }

    private function record(Requisition $r, User $user, string $action, ?Status $from, Status $to, ?string $remarks = null): void
    {
        $r->approvals()->create([
            'user_id' => $user->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'remarks' => $remarks,
        ]);
    }

    private function validateLines(array $lines): void
    {
        if (count($lines) === 0) {
            throw new InventoryException('Add at least one item to the requisition.');
        }
        $ids = array_column($lines, 'item_id');
        if (count(array_unique($ids)) !== count($ids)) {
            throw new InventoryException('Each item may appear only once per requisition. Combine the quantities into a single line.');
        }
        foreach ($lines as $line) {
            if ((int) $line['quantity'] <= 0) {
                throw new InventoryException('Requested quantities must be greater than zero.');
            }
        }
        $inactive = InventoryItem::whereIn('id', $ids)->where('status', '!=', InventoryItem::STATUS_ACTIVE)->pluck('name');
        if ($inactive->isNotEmpty()) {
            throw new InventoryException('Archived items cannot be requested: '.$inactive->implode(', '));
        }
    }

    private function syncLines(Requisition $requisition, array $lines): void
    {
        $requisition->items()->delete();
        foreach ($lines as $line) {
            $requisition->items()->create([
                'item_id' => (int) $line['item_id'],
                'quantity_requested' => (int) $line['quantity'],
                'remarks' => $line['remarks'] ?? null,
            ]);
        }
    }
}
