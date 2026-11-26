<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['requisition_id', 'item_id', 'quantity_requested', 'quantity_approved', 'quantity_issued', 'stock_available', 'remarks'])]
class RequisitionItem extends Model
{
    protected function casts(): array
    {
        return [
            'quantity_requested' => 'integer',
            'quantity_approved' => 'integer',
            'quantity_issued' => 'integer',
            'stock_available' => 'boolean',
        ];
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function issuanceItems(): HasMany
    {
        return $this->hasMany(IssuanceItem::class);
    }

    public function remainingToIssue(): int
    {
        return max(0, (int) $this->quantity_approved - $this->quantity_issued);
    }
}
