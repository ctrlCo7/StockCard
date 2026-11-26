<?php

namespace App\Enums;

enum AdjustmentReason: string
{
    case PhysicalCount = 'physical_count';
    case Damaged = 'damaged';
    case Lost = 'lost';
    case Expired = 'expired';
    case Correction = 'correction';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PhysicalCount => 'Physical inventory discrepancy',
            self::Damaged => 'Damaged goods',
            self::Lost => 'Loss',
            self::Expired => 'Expired / unusable',
            self::Correction => 'Correction of recording error',
            self::Other => 'Other approved reason',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }
}
