<?php

namespace App\Models;

use App\Enums\AdjustmentReason;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'adjustment_no', 'item_id', 'adjustment_date', 'direction', 'quantity',
    'reason', 'reference_no', 'remarks', 'idempotency_key', 'adjusted_by',
])]
class StockAdjustment extends Model
{
    protected function casts(): array
    {
        return [
            'adjustment_date' => 'date',
            'reason' => AdjustmentReason::class,
            'quantity' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function adjuster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(StockTransaction::class)->where('type', TransactionType::Adjustment);
    }

    public function isIncrease(): bool
    {
        return $this->direction === 'increase';
    }
}
