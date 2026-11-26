<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * current_balance and initial_quantity are intentionally NOT fillable: they are
 * written exclusively by App\Services\InventoryService from the ledger.
 */
#[Fillable(['stock_number', 'name', 'description', 'category_id', 'unit', 'office_id', 'location', 'reorder_point', 'status'])]
class InventoryItem extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected function casts(): array
    {
        return [
            'reorder_point' => 'integer',
            'initial_quantity' => 'integer',
            'current_balance' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class, 'item_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /** Balance at or below reorder point, but not zero. */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('current_balance', '>', 0)->whereColumn('current_balance', '<=', 'reorder_point');
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('current_balance', '<=', 0);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('stock_number', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /** Filter by computed stock status: ok | low | out. */
    public function scopeStockStatus(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'low' => $query->lowStock(),
            'out' => $query->outOfStock(),
            'ok' => $query->whereColumn('current_balance', '>', 'reorder_point'),
            default => $query,
        };
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isOutOfStock(): bool
    {
        return $this->current_balance <= 0;
    }

    public function isLowStock(): bool
    {
        return $this->current_balance <= $this->reorder_point;
    }

    public function stockStatus(): string
    {
        return match (true) {
            $this->isOutOfStock() => 'out',
            $this->isLowStock() => 'low',
            default => 'ok',
        };
    }

    public function stockStatusLabel(): string
    {
        return ['out' => 'Out of Stock', 'low' => 'Reorder', 'ok' => 'In Stock'][$this->stockStatus()];
    }

    public function stockStatusBadge(): string
    {
        return ['out' => 'danger', 'low' => 'warning', 'ok' => 'success'][$this->stockStatus()];
    }
}
