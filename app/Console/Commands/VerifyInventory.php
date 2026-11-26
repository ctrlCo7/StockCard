<?php

namespace App\Console\Commands;

use App\Models\InventoryItem;
use Illuminate\Console\Command;

/**
 * Integrity check: replays every item's ledger and confirms that each balance_after
 * snapshot and the cached current_balance match the recomputed running balance.
 */
class VerifyInventory extends Command
{
    protected $signature = 'inventory:verify';

    protected $description = 'Recompute running balances from the stock ledger and report any discrepancies';

    public function handle(): int
    {
        $problems = 0;

        InventoryItem::query()->orderBy('id')->each(function (InventoryItem $item) use (&$problems) {
            $running = 0;
            foreach ($item->transactions()->orderBy('id')->cursor() as $t) {
                $running += $t->quantity_received - $t->quantity_issued;
                if ($running < 0 || $running !== $t->balance_after) {
                    $problems++;
                    $this->error("{$item->stock_number}: transaction #{$t->id} has balance_after {$t->balance_after}, expected {$running}");
                }
            }
            if ($running !== $item->current_balance) {
                $problems++;
                $this->error("{$item->stock_number}: cached balance {$item->current_balance}, ledger balance {$running}");
            }
        });

        if ($problems === 0) {
            $this->info('All item balances match the ledger.');

            return self::SUCCESS;
        }

        return self::FAILURE;
    }
}
