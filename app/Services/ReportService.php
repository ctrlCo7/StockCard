<?php

namespace App\Services;

use App\Enums\RequisitionStatus as Status;
use App\Enums\TransactionType;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use App\Models\Requisition;
use App\Models\StockAdjustment;
use App\Models\StockTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds every report as a uniform structure (title, columns, rows, summary) so that
 * screen, print, PDF and CSV outputs all share one definition.
 */
class ReportService
{
    /** key => [title, description, ability, landscape?] */
    public const REPORTS = [
        'current-inventory' => ['Current Inventory Report', 'Balance on hand for every item.', 'reports.view', false],
        'stock-card' => ['Stock Card Report', 'Full ledger for a single item (choose an item).', 'reports.view', false],
        'receipts' => ['Stock Receipts Report', 'All stock received within the period.', 'reports.view', true],
        'issuances' => ['Stock Issuances Report', 'All stock issued within the period.', 'reports.view', true],
        'low-stock' => ['Low-Stock Report', 'Items at or below their reorder point.', 'reports.view', false],
        'out-of-stock' => ['Out-of-Stock Report', 'Items with a zero balance.', 'reports.view', false],
        'ris-register' => ['Requisition and Issue Slip Register', 'All RIS documents within the period.', 'reports.view', true],
        'pending-requisitions' => ['Pending Requisitions Report', 'RIS awaiting forwarding, approval or issuance.', 'reports.view', true],
        'stock-movement' => ['Stock Movement Report', 'Beginning balance, receipts, issuances and ending balance per item.', 'reports.view', true],
        'ledger' => ['Stock Transactions Report', 'All ledger postings, filterable by transaction type.', 'reports.view', true],
        'adjustments' => ['Stock Adjustment Report', 'Authorized adjustments within the period.', 'reports.view', true],
        'audit' => ['User Activity and Audit Report', 'User actions recorded in the audit log.', 'audit.view', true],
    ];

    /**
     * @param  array<string, mixed>  $f  validated filters
     * @return array{title: string, columns: array<string, string>, rows: Collection, summary: array<string, string|int>, numeric: string[], landscape: bool}
     */
    public function build(string $key, array $f): array
    {
        [$title, , , $landscape] = self::REPORTS[$key];
        $report = match ($key) {
            'current-inventory' => $this->inventory($f),
            'low-stock' => $this->inventory($f, 'low'),
            'out-of-stock' => $this->inventory($f, 'out'),
            'stock-card' => $this->stockCard($f),
            'receipts' => $this->ledger($f, TransactionType::Receipt),
            'issuances' => $this->ledger($f, TransactionType::Issue),
            'ledger' => $this->ledger($f, isset($f['type']) ? TransactionType::from($f['type']) : null),
            'ris-register' => $this->requisitions($f),
            'pending-requisitions' => $this->requisitions($f, [Status::Submitted, Status::PendingApproval, Status::Approved, Status::PartiallyIssued]),
            'stock-movement' => $this->movement($f),
            'adjustments' => $this->adjustments($f),
            'audit' => $this->audit($f),
        };

        return ['title' => $report['title'] ?? $title, 'landscape' => $landscape, 'summary' => [], 'numeric' => [], ...$report];
    }

    private function itemFilters(Builder $q, array $f, string $prefix = ''): Builder
    {
        return $q
            ->when($f['item'] ?? null, fn ($q, $v) => $q->where($prefix.'id', $v))
            ->when($f['stock_number'] ?? null, fn ($q, $v) => $q->where($prefix.'stock_number', 'like', "%{$v}%"))
            ->when($f['category'] ?? null, fn ($q, $v) => $q->where($prefix.'category_id', $v))
            ->when($f['office'] ?? null, fn ($q, $v) => $q->where($prefix.'office_id', $v));
    }

    private function inventory(array $f, ?string $stock = null): array
    {
        $items = $this->itemFilters(InventoryItem::query()->active()->with(['category', 'office']), $f)
            ->stockStatus($stock)->orderBy('name')->get();

        return [
            'columns' => ['stock_number' => 'Stock No.', 'name' => 'Item', 'description' => 'Description', 'unit' => 'Unit', 'category' => 'Category', 'location' => 'Office / Location', 'reorder_point' => 'Re-order Point', 'balance' => 'Balance', 'status' => 'Status'],
            'numeric' => ['reorder_point', 'balance'],
            'rows' => $items->map(fn (InventoryItem $i) => [
                'stock_number' => $i->stock_number, 'name' => $i->name, 'description' => $i->description,
                'unit' => $i->unit, 'category' => $i->category?->name, 'location' => trim(($i->office?->name ?? '').' '.($i->location ? "({$i->location})" : '')),
                'reorder_point' => $i->reorder_point, 'balance' => $i->current_balance, 'status' => $i->stockStatusLabel(),
            ]),
            'summary' => ['Items' => $items->count(), 'Total units on hand' => number_format($items->sum('current_balance'))],
        ];
    }

    private function stockCard(array $f): array
    {
        $item = InventoryItem::find($f['item'] ?? 0);
        if (! $item) {
            return ['columns' => [], 'rows' => collect(), 'summary' => ['Note' => 'Select an item to generate its Stock Card report.']];
        }

        $rows = $item->transactions()
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('transaction_date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('transaction_date', '<=', $v))
            ->orderBy('id')->get();

        return [
            'title' => "Stock Card Report — {$item->name} ({$item->stock_number})",
            'columns' => ['date' => 'Date', 'reference' => 'Reference', 'party' => 'From Whom Received / To Whom Issued', 'received' => 'Received Qty.', 'issued' => 'Issued Qty.', 'balance' => 'Balance Qty.'],
            'numeric' => ['received', 'issued', 'balance'],
            'rows' => $rows->map(fn (StockTransaction $t) => [
                'date' => $t->transaction_date->format('m/d/Y'), 'reference' => $t->reference_no, 'party' => $t->party,
                'received' => $t->quantity_received ?: '', 'issued' => $t->quantity_issued ?: '', 'balance' => $t->balance_after,
            ]),
            'summary' => ['Unit' => $item->unit, 'Re-order point' => $item->reorder_point, 'Current balance' => $item->current_balance],
        ];
    }

    private function ledger(array $f, ?TransactionType $type): array
    {
        $rows = StockTransaction::query()
            ->with(['item', 'poster'])
            ->when($type, fn ($q) => $q->where('type', $type))
            ->whereHas('item', fn ($q) => $this->itemFilters($q, $f))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('transaction_date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('transaction_date', '<=', $v))
            ->orderBy('transaction_date')->orderBy('id')->get();

        $partyLabel = match ($type) {
            TransactionType::Receipt => 'Supplier / Source',
            TransactionType::Issue => 'Issued To',
            default => 'From / To',
        };

        return [
            'columns' => ['date' => 'Date', 'type' => 'Type', 'reference' => 'Reference', 'stock_number' => 'Stock No.', 'item' => 'Item', 'unit' => 'Unit', 'party' => $partyLabel, 'received' => 'Received', 'issued' => 'Issued', 'balance' => 'Balance', 'posted_by' => 'Posted By'],
            'numeric' => ['received', 'issued', 'balance'],
            'rows' => $rows->map(fn (StockTransaction $t) => [
                'date' => $t->transaction_date->format('m/d/Y'), 'type' => $t->type->label(), 'reference' => $t->reference_no,
                'stock_number' => $t->item->stock_number, 'item' => $t->item->name, 'unit' => $t->item->unit, 'party' => $t->party,
                'received' => $t->quantity_received ?: '', 'issued' => $t->quantity_issued ?: '', 'balance' => $t->balance_after,
                'posted_by' => $t->poster?->name,
            ]),
            'summary' => ['Transactions' => $rows->count(), 'Total received' => number_format($rows->sum('quantity_received')), 'Total issued' => number_format($rows->sum('quantity_issued'))],
        ];
    }

    private function requisitions(array $f, ?array $statuses = null): array
    {
        $rows = Requisition::query()
            ->with(['office', 'requester', 'approver'])
            ->withSum('items', 'quantity_requested')->withSum('items', 'quantity_issued')
            ->when($statuses, fn ($q) => $q->whereIn('status', $statuses))
            ->when(! $statuses && ($f['status'] ?? null), fn ($q) => $q->where('status', $f['status']))
            ->when($f['office'] ?? null, fn ($q, $v) => $q->where('office_id', $v))
            ->when($f['item'] ?? null, fn ($q, $v) => $q->whereHas('items', fn ($i) => $i->where('item_id', $v)))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('requisition_date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('requisition_date', '<=', $v))
            ->orderBy('requisition_date')->orderBy('id')->get();

        return [
            'columns' => ['ris_no' => 'RIS No.', 'date' => 'Date', 'office' => 'Office', 'requester' => 'Requested By', 'purpose' => 'Purpose', 'requested' => 'Qty. Requested', 'issued' => 'Qty. Issued', 'status' => 'Status', 'approver' => 'Approved / Acted By'],
            'numeric' => ['requested', 'issued'],
            'rows' => $rows->map(fn (Requisition $r) => [
                'ris_no' => $r->ris_no, 'date' => $r->requisition_date->format('m/d/Y'), 'office' => $r->office?->name,
                'requester' => $r->requester?->name, 'purpose' => $r->purpose, 'requested' => (int) $r->items_sum_quantity_requested,
                'issued' => (int) $r->items_sum_quantity_issued, 'status' => $r->status->label(), 'approver' => $r->approver?->name,
            ]),
            'summary' => ['Requisitions' => $rows->count()],
        ];
    }

    private function movement(array $f): array
    {
        $from = $f['from'] ?? null;
        $to = $f['to'] ?? null;

        $items = $this->itemFilters(InventoryItem::query(), $f)->orderBy('name')->get();
        $agg = StockTransaction::query()
            ->whereIn('item_id', $items->pluck('id'))
            ->select('item_id')
            ->selectRaw('SUM(CASE WHEN '.($from ? 'DATE(transaction_date) < ?' : '1 = 0').' THEN quantity_received - quantity_issued ELSE 0 END) AS beginning', $from ? [$from] : [])
            ->selectRaw('SUM(CASE WHEN '.$this->periodSql($from, $to).' THEN quantity_received ELSE 0 END) AS received', $this->periodBindings($from, $to))
            ->selectRaw('SUM(CASE WHEN '.$this->periodSql($from, $to).' THEN quantity_issued ELSE 0 END) AS issued', $this->periodBindings($from, $to))
            ->groupBy('item_id')->get()->keyBy('item_id');

        $rows = $items->map(function (InventoryItem $i) use ($agg) {
            $a = $agg->get($i->id);
            $begin = (int) ($a->beginning ?? 0);
            $in = (int) ($a->received ?? 0);
            $out = (int) ($a->issued ?? 0);

            return ['stock_number' => $i->stock_number, 'name' => $i->name, 'unit' => $i->unit, 'beginning' => $begin, 'received' => $in, 'issued' => $out, 'ending' => $begin + $in - $out];
        })->filter(fn ($r) => $r['beginning'] || $r['received'] || $r['issued'])->values();

        return [
            'columns' => ['stock_number' => 'Stock No.', 'name' => 'Item', 'unit' => 'Unit', 'beginning' => 'Beginning Balance', 'received' => 'Received', 'issued' => 'Issued', 'ending' => 'Ending Balance'],
            'numeric' => ['beginning', 'received', 'issued', 'ending'],
            'rows' => $rows,
            'summary' => ['Items with movement' => $rows->count(), 'Total received' => number_format($rows->sum('received')), 'Total issued' => number_format($rows->sum('issued'))],
        ];
    }

    private function periodSql(?string $from, ?string $to): string
    {
        $parts = array_filter([$from ? 'DATE(transaction_date) >= ?' : null, $to ? 'DATE(transaction_date) <= ?' : null]);

        return $parts ? implode(' AND ', $parts) : '1 = 1';
    }

    private function periodBindings(?string $from, ?string $to): array
    {
        return array_values(array_filter([$from, $to]));
    }

    private function adjustments(array $f): array
    {
        $rows = StockAdjustment::query()->with(['item', 'adjuster'])
            ->whereHas('item', fn ($q) => $this->itemFilters($q, $f))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('adjustment_date', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('adjustment_date', '<=', $v))
            ->orderBy('adjustment_date')->orderBy('id')->get();

        return [
            'columns' => ['no' => 'Adjustment No.', 'date' => 'Date', 'stock_number' => 'Stock No.', 'item' => 'Item', 'direction' => 'Direction', 'quantity' => 'Qty.', 'reason' => 'Reason', 'reference' => 'Reference', 'remarks' => 'Remarks', 'by' => 'Adjusted By'],
            'numeric' => ['quantity'],
            'rows' => $rows->map(fn (StockAdjustment $a) => [
                'no' => $a->adjustment_no, 'date' => $a->adjustment_date->format('m/d/Y'), 'stock_number' => $a->item->stock_number,
                'item' => $a->item->name, 'direction' => ucfirst($a->direction), 'quantity' => $a->quantity, 'reason' => $a->reason->label(),
                'reference' => $a->reference_no, 'remarks' => $a->remarks, 'by' => $a->adjuster?->name,
            ]),
            'summary' => ['Adjustments' => $rows->count()],
        ];
    }

    private function audit(array $f): array
    {
        $rows = AuditLog::query()->with('user')
            ->when($f['user'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($f['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($f['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderBy('id')->limit(5000)->get();

        return [
            'columns' => ['when' => 'Date / Time', 'user' => 'User', 'action' => 'Action', 'description' => 'Description', 'ip' => 'IP Address'],
            'rows' => $rows->map(fn (AuditLog $l) => [
                'when' => $l->created_at?->format('m/d/Y h:i A'), 'user' => $l->user?->name ?? 'System', 'action' => $l->action,
                'description' => $l->description, 'ip' => $l->ip_address,
            ]),
            'summary' => ['Events' => $rows->count().($rows->count() === 5000 ? ' (limited to 5,000; narrow the date range)' : '')],
        ];
    }

    /** Options for the report filter form. */
    public static function filterOptions(): array
    {
        return [
            'items' => InventoryItem::orderBy('name')->get(['id', 'stock_number', 'name']),
            'categories' => DB::table('inventory_categories')->orderBy('name')->get(['id', 'name']),
            'offices' => DB::table('offices')->orderBy('name')->get(['id', 'name']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'types' => TransactionType::options(),
            'statuses' => Status::options(),
        ];
    }
}
