<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class ApplicationSetting extends Model
{
    /** Defaults used when a key has never been saved. */
    public const DEFAULTS = [
        'entity_name' => 'Bureau of Internal Revenue',
        'division' => 'Large Taxpayers Division – Cebu',
        'fund_cluster' => '01',
        'responsibility_center_code' => '',
        'stock_card_form_code' => 'QF-03-0102023-00',
        'system_title' => 'Stock Card & Inventory Management System',
        'items_per_page' => '15',
        'notify_requisition_emails' => '1',
        'notify_low_stock_emails' => '1',
        'notification_extra_emails' => '',
    ];

    private const CACHE_KEY = 'application_settings';

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, string|null> */
    public static function allWithDefaults(): array
    {
        return collect(self::DEFAULTS)->map(fn ($v, $k) => static::get($k))->all();
    }
}
