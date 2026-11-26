<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Services\AuditLogger;
use App\Support\PdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StockCardController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'stock' => ['nullable', 'in:ok,low,out'],
        ]);

        $items = InventoryItem::query()
            ->withCount('transactions')
            ->withMax('transactions', 'transaction_date')
            ->search($filters['q'] ?? null)
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->stockStatus($filters['stock'] ?? null)
            ->orderBy('name')
            ->paginate((int) ApplicationSetting::get('items_per_page', '15'))
            ->withQueryString();

        return view('stock-cards.index', [
            'items' => $items,
            'filters' => $filters,
            'categories' => InventoryCategory::orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, InventoryItem $item): View
    {
        [$filters, $query, $broughtForward] = $this->ledger($request, $item);

        return view('stock-cards.show', [
            'item' => $item->load('office', 'category'),
            'transactions' => $query->with(['poster', 'reversal', 'reversedTransaction'])->paginate(25)->withQueryString(),
            'filters' => $filters,
            'broughtForward' => $broughtForward,
        ]);
    }

    public function print(Request $request, InventoryItem $item): View
    {
        return view('stock-cards.print', $this->printData($request, $item));
    }

    public function pdf(Request $request, InventoryItem $item): Response
    {
        AuditLogger::log('stockcard.exported', "Stock Card for {$item->stock_number} exported as PDF", $item);

        return PdfRenderer::make('stock-cards.pdf', $this->printData($request, $item))
            ->download("stock-card-{$item->stock_number}.pdf");
    }

    private function printData(Request $request, InventoryItem $item): array
    {
        [$filters, $query, $broughtForward] = $this->ledger($request, $item);

        return [
            'item' => $item->load('office'),
            'transactions' => $query->get(),
            'filters' => $filters,
            'broughtForward' => $broughtForward,
            'settings' => ApplicationSetting::allWithDefaults(),
            'generatedBy' => $request->user(),
        ];
    }

    /**
     * Chronological ledger for an item. When a start date is given, the balance
     * carried in from before that date is returned so the running balance reads correctly.
     */
    private function ledger(Request $request, InventoryItem $item): array
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:opening,receipt,issue,adjustment,reversal'],
        ]);

        $query = $item->transactions()
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('transaction_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('transaction_date', '<=', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('reference_no', 'like', "%{$v}%")->orWhere('party', 'like', "%{$v}%")))
            ->orderBy('id');

        $broughtForward = null;
        if (! empty($filters['from'])) {
            $broughtForward = $item->transactions()->whereDate('transaction_date', '<', $filters['from'])->orderByDesc('id')->value('balance_after') ?? 0;
        }

        return [$filters, $query, $broughtForward];
    }
}
