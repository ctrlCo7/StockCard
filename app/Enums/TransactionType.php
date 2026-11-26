<?php

namespace App\Enums;

enum TransactionType: string
{
    case Opening = 'opening';
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Opening => 'Opening Balance',
            self::Receipt => 'Stock Receipt',
            self::Issue => 'Issuance',
            self::Adjustment => 'Adjustment',
            self::Reversal => 'Reversal',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Opening => 'secondary',
            self::Receipt => 'success',
            self::Issue => 'primary',
            self::Adjustment => 'warning',
            self::Reversal => 'danger',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $t) => [$t->value => $t->label()])->all();
    }
}
