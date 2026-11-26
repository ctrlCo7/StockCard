<?php

namespace App\Console\Commands;

use App\Services\InventoryNotifier;
use Illuminate\Console\Command;

/** Emails administrators a summary of items at or below their re-order point (scheduled daily). */
class SendLowStockDigest extends Command
{
    protected $signature = 'inventory:low-stock-digest';

    protected $description = 'Email administrators the list of items at or below their re-order point';

    public function handle(InventoryNotifier $notifier): int
    {
        $count = $notifier->lowStockDigest();

        $this->info($count > 0
            ? "Low-stock report sent for {$count} item(s)."
            : 'Nothing sent: no items are low on stock, or low-stock emails are turned off in Settings.');

        return self::SUCCESS;
    }
}
