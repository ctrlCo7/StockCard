<?php

namespace App\Models;

use App\Enums\RequisitionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One workflow event in a requisition's history. */
#[Fillable(['requisition_id', 'user_id', 'action', 'from_status', 'to_status', 'remarks'])]
class RequisitionApproval extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_status' => RequisitionStatus::class,
            'to_status' => RequisitionStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }
}
