<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'receipt_no', 'date_received', 'supplier', 'reference_type', 'reference_no',
    'received_by_name', 'remarks', 'attachment_path', 'attachment_name',
    'idempotency_key', 'posted_by', 'posted_at',
])]
class StockReceipt extends Model
{
    public const REFERENCE_TYPES = [
        'DR' => 'Delivery Receipt',
        'PO' => 'Purchase Order',
        'IAR' => 'Inspection & Acceptance Report',
        'PTR' => 'Property Transfer Report',
        'OTHER' => 'Other',
    ];

    protected function casts(): array
    {
        return [
            'date_received' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockReceiptItem::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** Ledger reference shown on the Stock Card, e.g. "DR# 2026-0142" (paper-form convention). */
    public function ledgerReference(): string
    {
        return $this->reference_type === 'OTHER' ? $this->reference_no : "{$this->reference_type}# {$this->reference_no}";
    }
}
