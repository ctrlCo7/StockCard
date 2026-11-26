<?php

namespace App\Models;

use App\Enums\RequisitionStatus;
use App\Services\SignatureService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'ris_no', 'entity_name', 'fund_cluster', 'division', 'office_id',
    'responsibility_center_code', 'requisition_date', 'purpose', 'status',
    'requested_by', 'requested_by_designation', 'requested_signature_path', 'submitted_at',
    'approved_by', 'approved_by_designation', 'approved_signature_path', 'approved_at', 'approval_remarks',
    'issued_by', 'issued_by_designation', 'issued_signature_path', 'issued_at',
    'received_by', 'received_by_designation', 'received_signature_path', 'received_at',
])]
class Requisition extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => RequisitionStatus::class,
            'requisition_date' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RequisitionItem::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(RequisitionApproval::class)->orderBy('id');
    }

    public function issuances(): HasMany
    {
        return $this->hasMany(Issuance::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('ris_no', 'like', "%{$term}%")
                ->orWhere('purpose', 'like', "%{$term}%")
                ->orWhereHas('requester', fn (Builder $u) => $u->where('name', 'like', "%{$term}%"));
        });
    }

    /**
     * Inline image of the signature applied at a signatory step.
     *
     * @param  'requested'|'approved'|'issued'|'received'  $step
     */
    public function signatureDataUri(string $step): ?string
    {
        return app(SignatureService::class)->dataUri($this->{"{$step}_signature_path"});
    }

    public function totalRemaining(): int
    {
        return $this->items->sum(fn (RequisitionItem $i) => $i->remainingToIssue());
    }
}
