<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['issuance_id', 'requisition_item_id', 'item_id', 'quantity'])]
class IssuanceItem extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'integer'];
    }

    public function issuance(): BelongsTo
    {
        return $this->belongsTo(Issuance::class);
    }

    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(RequisitionItem::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(StockTransaction::class);
    }
}
