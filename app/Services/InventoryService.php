<?php

namespace App\Services;

use App\Enums\RequisitionStatus;
use App\Enums\TransactionType;
use App\Exceptions\InventoryException;
use App\Models\InventoryItem;
use App\Models\RequisitionItem;
use App\Models\StockAdjustment;
use App\Models\StockReceipt;
use App\Models\StockTransaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only code path that writes to the stock ledger or to an item's cached balance.
 *
 * Every posting runs inside a database transaction and locks the item row
 * (SELECT ... FOR UPDATE) before reading the balance, so concurrent postings for
 * the same item are serialized and can never overdraw stock.
 *
 * Rows are appended in posting order and a transaction may not be dated earlier
 * than the item's latest posted transaction, so posting order == chronological
 * order and every balance_after snapshot stays valid.
 */
class InventoryService
{
    public function __construct(
        private DocumentNumberService $numbers,
        private InventoryNotifier $notifier,
    ) {}

    /**
     * Post a single ledger row. Callers supply quantities; the balance is always computed here.
     *
     * @param  array{
     *     type: TransactionType, date: CarbonInterface|string, reference: string, party?: ?string,
     *     received?: int, issued?: int, remarks?: ?string, stock_receipt_id?: ?int,
     *     requisition_id?: ?int, issuance_item_id?: ?int, stock_adjustment_id?: ?int,
     *     reverses_transaction_id?: ?int, allow_inactive?: bool
     * }  $data
     */
    public function post(InventoryItem|int $item, array $data, User $user): StockTransaction
    {
        $itemId = $item instanceof InventoryItem ? $item->getKey() : $item;
        $received = (int) ($data['received'] ?? 0);
        $issued = (int) ($data['issued'] ?? 0);
        $type = $data['type'];
        $reference = trim((string) ($data['reference'] ?? ''));
        $date = Carbon::parse($data['date'])->startOfDay();

        if ($received < 0 || $issued < 0) {
            throw new InventoryException('Quantities cannot be negative.');
        }
        if ($received > 0 && $issued > 0) {
            throw new InventoryException('A transaction cannot both receive and issue stock.');
        }
        if ($received === 0 && $issued === 0 && $type !== TransactionType::Opening) {
            throw new InventoryException('The transaction quantity must be greater than zero.');
        }
        if ($reference === '') {
            throw new InventoryException('A reference number is required.');
        }
        if ($date->isAfter(today())) {
            throw new InventoryException('Transactions cannot be dated in the future.');
        }

        return DB::transaction(function () use ($itemId, $data, $received, $issued, $type, $reference, $date, $user) {
            /** @var InventoryItem $locked */
            $locked = InventoryItem::query()->whereKey($itemId)->lockForUpdate()->firstOrFail();

            if (! $locked->isActive() && empty($data['allow_inactive'])) {
                throw new InventoryException("Item {$locked->stock_number} is archived; no new stock movements can be posted.");
            }

            $hasHistory = $locked->transactions()->exists();
            if ($type === TransactionType::Opening && $hasHistory) {
                throw new InventoryException("Item {$locked->stock_number} already has an opening balance.");
            }

            $lastDate = $locked->transactions()->max('transaction_date');
            if ($lastDate && $date->lt(Carbon::parse($lastDate)->startOfDay())) {
                throw new InventoryException(sprintf(
                    'The transaction date cannot be earlier than the last posted transaction for %s (%s).',
                    $locked->stock_number, Carbon::parse($lastDate)->format('M d, Y')
                ));
            }

            $previous = $this->ledgerBalance($locked);
            $new = $previous + $received - $issued;

            if ($new < 0) {
                throw new InventoryException(sprintf(
                    'Insufficient stock for %s (%s): available %d %s, requested %d.',
                    $locked->name, $locked->stock_number, $previous, $locked->unit, $issued
                ));
            }

            try {
                $transaction = StockTransaction::create([
                    'item_id' => $locked->id,
                    'type' => $type,
                    'transaction_date' => $date,
                    'reference_no' => $reference,
                    'party' => $data['party'] ?? null,
                    'quantity_received' => $received,
                    'quantity_issued' => $issued,
                    'balance_after' => $new,
                    'stock_receipt_id' => $data['stock_receipt_id'] ?? null,
                    'requisition_id' => $data['requisition_id'] ?? null,
                    'issuance_item_id' => $data['issuance_item_id'] ?? null,
                    'stock_adjustment_id' => $data['stock_adjustment_id'] ?? null,
                    'reverses_transaction_id' => $data['reverses_transaction_id'] ?? null,
                    'remarks' => $data['remarks'] ?? null,
                    'posted_by' => $user->id,
                    'posted_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new InventoryException('This transaction has already been reversed.');
            }

            $attributes = ['current_balance' => $new];
            if ($type === TransactionType::Opening) {
                $attributes['initial_quantity'] = $received;
            }
            $locked->forceFill($attributes)->save();
            $this->notifier->stockLevelChanged($locked, $previous, $new, $reference);

            AuditLogger::log("stock.{$type->value}", sprintf(
                '%s posted for %s: +%d / -%d, balance %d → %d (ref %s)',
                $type->label(), $locked->stock_number, $received, $issued, $previous, $new, $reference
            ), $transaction, ['balance' => $previous], ['balance' => $new, 'received' => $received, 'issued' => $issued], $user->id);

            return $transaction;
        });
    }

    /**
     * The authoritative balance, recomputed from the ledger. Also repairs the cached
     * balance if it has drifted (which should never happen) and records the event.
     */
    public function ledgerBalance(InventoryItem $item): int
    {
        $sums = $item->transactions()
            ->selectRaw('COALESCE(SUM(quantity_received), 0) AS r, COALESCE(SUM(quantity_issued), 0) AS i')
            ->first();
        $balance = (int) $sums->r - (int) $sums->i;

        if ($balance !== (int) $item->current_balance) {
            Log::warning("Cached balance drift for item {$item->id}: cached {$item->current_balance}, ledger {$balance}");
            AuditLogger::log('stock.balance_resync', "Cached balance for {$item->stock_number} resynchronized from ledger", $item,
                ['current_balance' => $item->current_balance], ['current_balance' => $balance]);
            $item->forceFill(['current_balance' => $balance])->save();
        }

        return $balance;
    }

    /** Create an item together with its opening-balance ledger row. */
    public function createItemWithOpeningBalance(array $attributes, int $openingQuantity, CarbonInterface|string $date, User $user, ?string $reference = null): InventoryItem
    {
        return DB::transaction(function () use ($attributes, $openingQuantity, $date, $user, $reference) {
            $item = new InventoryItem($attributes);
            $item->created_by = $user->id;
            $item->save();

            $this->post($item, [
                'type' => TransactionType::Opening,
                'date' => $date,
                'reference' => $reference ?: 'Beginning Inventory',
                'party' => 'Opening balance',
                'received' => $openingQuantity,
            ], $user);

            AuditLogger::log('item.created', "Inventory item {$item->stock_number} ({$item->name}) created with opening balance {$openingQuantity}", $item, null, $item->only($item->getFillable()));

            return $item->refresh();
        });
    }

    /**
     * Post a (possibly multi-line) stock receipt.
     *
     * @param  array<int, array{item_id: int, quantity: int, remarks?: ?string}>  $lines
     * @return array{0: StockReceipt, 1: bool} The receipt and whether it was newly created.
     */
    public function receive(array $header, array $lines, User $user): array
    {
        if ($existing = StockReceipt::where('idempotency_key', $header['idempotency_key'])->first()) {
            return [$existing, false];
        }
        if (count($lines) === 0) {
            throw new InventoryException('Add at least one item to the receipt.');
        }
        if (count(array_unique(array_column($lines, 'item_id'))) !== count($lines)) {
            throw new InventoryException('Each item may appear only once per receipt.');
        }

        try {
            $receipt = DB::transaction(function () use ($header, $lines, $user) {
                $receipt = StockReceipt::create([
                    ...$header,
                    'receipt_no' => $this->numbers->next(DocumentNumberService::RECEIPT),
                    'posted_by' => $user->id,
                    'posted_at' => now(),
                ]);

                foreach ($lines as $line) {
                    $item = InventoryItem::findOrFail($line['item_id']);
                    $receipt->items()->create([
                        'item_id' => $item->id,
                        'quantity' => (int) $line['quantity'],
                        'unit' => $item->unit,
                        'remarks' => $line['remarks'] ?? null,
                    ]);

                    $this->post($item, [
                        'type' => TransactionType::Receipt,
                        'date' => $receipt->date_received,
                        'reference' => $receipt->ledgerReference(),
                        'party' => $receipt->supplier,
                        'received' => (int) $line['quantity'],
                        'remarks' => $line['remarks'] ?? $receipt->remarks,
                        'stock_receipt_id' => $receipt->id,
                    ], $user);
                }

                AuditLogger::log('receipt.posted', "Stock receipt {$receipt->receipt_no} posted ({$receipt->ledgerReference()}, ".count($lines).' line(s))', $receipt);

                return $receipt;
            });
        } catch (UniqueConstraintViolationException) {
            // A concurrent double-submit won the race; return the receipt it created.
            return [StockReceipt::where('idempotency_key', $header['idempotency_key'])->firstOrFail(), false];
        }

        return [$receipt, true];
    }

    /**
     * Post an authorized stock adjustment.
     *
     * @return array{0: StockAdjustment, 1: bool}
     */
    public function adjust(array $data, User $user): array
    {
        if ($existing = StockAdjustment::where('idempotency_key', $data['idempotency_key'])->first()) {
            return [$existing, false];
        }
        if (blank($data['remarks'] ?? null) || blank($data['reason'] ?? null) || blank($data['reference_no'] ?? null)) {
            throw new InventoryException('An adjustment requires a reason, a reference and remarks.');
        }

        try {
            $adjustment = DB::transaction(function () use ($data, $user) {
                $adjustment = StockAdjustment::create([
                    ...$data,
                    'adjustment_no' => $this->numbers->next(DocumentNumberService::ADJUSTMENT),
                    'adjusted_by' => $user->id,
                ]);
                $increase = $adjustment->isIncrease();

                $this->post($adjustment->item_id, [
                    'type' => TransactionType::Adjustment,
                    'date' => $adjustment->adjustment_date,
                    'reference' => $adjustment->adjustment_no.' / '.$adjustment->reference_no,
                    'party' => 'Adjustment: '.$adjustment->reason->label(),
                    'received' => $increase ? $adjustment->quantity : 0,
                    'issued' => $increase ? 0 : $adjustment->quantity,
                    'remarks' => $adjustment->remarks,
                    'stock_adjustment_id' => $adjustment->id,
                ], $user);

                AuditLogger::log('adjustment.posted', sprintf('Adjustment %s: %s %d for item #%d (%s)',
                    $adjustment->adjustment_no, $adjustment->direction, $adjustment->quantity, $adjustment->item_id, $adjustment->reason->label()), $adjustment);

                return $adjustment;
            });
        } catch (UniqueConstraintViolationException) {
            return [StockAdjustment::where('idempotency_key', $data['idempotency_key'])->firstOrFail(), false];
        }

        return [$adjustment, true];
    }

    /**
     * Reverse a posted transaction by posting its mirror image. The original row is untouched.
     * Reversing an RIS issuance also returns the quantity to the requisition line.
     */
    public function reverse(StockTransaction $original, string $reason, User $user): StockTransaction
    {
        if ($original->type === TransactionType::Reversal) {
            throw new InventoryException('A reversal cannot itself be reversed. Post an adjustment instead.');
        }
        if (blank($reason)) {
            throw new InventoryException('A reason is required to reverse a transaction.');
        }

        return DB::transaction(function () use ($original, $reason, $user) {
            // Lock the item first so the "already reversed?" check and the posting are atomic.
            InventoryItem::query()->whereKey($original->item_id)->lockForUpdate()->first();

            if (StockTransaction::where('reverses_transaction_id', $original->id)->exists()) {
                throw new InventoryException('This transaction has already been reversed.');
            }

            $requisitionItem = null;
            if ($original->issuance_item_id) {
                $requisitionItem = RequisitionItem::query()
                    ->whereKey($original->issuanceItem->requisition_item_id)->lockForUpdate()->firstOrFail();
                $requisition = $requisitionItem->requisition;
                if ($requisition->status === RequisitionStatus::Completed) {
                    throw new InventoryException("RIS {$requisition->ris_no} has already been received and completed; record a stock adjustment instead.");
                }
            }

            $reversal = $this->post($original->item_id, [
                'type' => TransactionType::Reversal,
                'date' => today(),
                'reference' => 'REV '.$original->reference_no,
                'party' => $original->party,
                'received' => $original->quantity_issued,
                'issued' => $original->quantity_received,
                'remarks' => "Reversal of transaction #{$original->id}: {$reason}",
                'requisition_id' => $original->requisition_id,
                'stock_receipt_id' => $original->stock_receipt_id,
                'stock_adjustment_id' => $original->stock_adjustment_id,
                'reverses_transaction_id' => $original->id,
                'allow_inactive' => true,
            ], $user);

            if ($requisitionItem) {
                $requisitionItem->decrement('quantity_issued', $original->quantity_issued);
                app(RequisitionService::class)->refreshIssueStatus($requisitionItem->requisition()->first(), $user, "Issuance reversed: {$reason}");
            }

            AuditLogger::log('stock.reversed', "Transaction #{$original->id} ({$original->reference_no}) reversed: {$reason}", $original, null, ['reversal_id' => $reversal->id]);

            return $reversal;
        });
    }
}
