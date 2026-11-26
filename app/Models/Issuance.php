<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['issuance_no', 'requisition_id', 'issue_date', 'issued_by', 'received_by_name', 'received_by_user_id', 'remarks', 'idempotency_key'])]
class Issuance extends Model
{
    protected function casts(): array
    {
        return ['issue_date' => 'date'];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(IssuanceItem::class);
    }
}
