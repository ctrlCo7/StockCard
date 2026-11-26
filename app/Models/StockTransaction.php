<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * One row of the inventory ledger (one line on a Stock Card). Append-only.
 */
#[Fillable([
    'item_id', 'type', 'transaction_date', 'reference_no', 'party',
    'quantity_received', 'quantity_issued', 'balance_after',
    'stock_receipt_id', 'requisition_id', 'issuance_item_id', 'stock_adjustment_id',
    'reverses_transaction_id', 'remarks', 'posted_by', 'posted_at',
])]
class StockTransaction extends Model
{
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'transaction_date' => 'date',
            'posted_at' => 'datetime',
            'quantity_received' => 'integer',
            'quantity_issued' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Posted ledger rows are immutable; corrections go through reversals/adjustments.
        static::updating(fn () => throw new LogicException('Posted stock transactions cannot be modified. Post a reversal or adjustment instead.'));
        static::deleting(fn () => throw new LogicException('Posted stock transactions cannot be deleted. Post a reversal or adjustment instead.'));
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'stock_receipt_id');
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function issuanceItem(): BelongsTo
    {
        return $this->belongsTo(IssuanceItem::class);
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
    }

    public function reversedTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_transaction_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_transaction_id');
    }

    public function isReversible(): bool
    {
        return $this->type !== TransactionType::Reversal && ! $this->reversal()->exists();
    }

    /** URL of the source document behind this ledger row, if any. */
    public function sourceUrl(): ?string
    {
        return match (true) {
            $this->stock_receipt_id !== null => route('receipts.show', $this->stock_receipt_id),
            $this->requisition_id !== null => route('requisitions.show', $this->requisition_id),
            $this->stock_adjustment_id !== null => route('adjustments.show', $this->stock_adjustment_id),
            default => null,
        };
    }
}
