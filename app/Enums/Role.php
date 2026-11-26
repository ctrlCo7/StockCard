<?php

namespace App\Enums;

/**
 * Two roles: the supply office (administrators, who do all inventory processing,
 * approval and issuance) and requesting personnel from other departments.
 */
enum Role: string
{
    case Admin = 'admin';
    case Requester = 'requester';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator / Inventory Officer',
            self::Requester => 'Requesting Personnel',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Full access: items, stock cards, receipts, adjustments, RIS approval and issuance, reports, users, settings and audit logs.',
            self::Requester => 'From a requesting department. Submits requisitions, tracks their status and acknowledges receipt of issued supplies.',
        };
    }
}
