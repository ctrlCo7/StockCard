<?php

namespace App\Http\Controllers;

use App\Http\Requests\InventoryItemRequest;
use App\Models\ApplicationSetting;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Office;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    private const SORTABLE = ['stock_number', 'name', 'unit', 'current_balance', 'reorder_point', 'updated_at'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'unit' => ['nullable', 'string', 'max:30'],
            'office' => ['nullable', 'integer'],
            'stock' => ['nullable', 'in:ok,low,out'],
            'status' => ['nullable', 'in:active,inactive,all'],
            'sort' => ['nullable', 'in:'.implode(',', self::SORTABLE)],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);

        $status = $filters['status'] ?? 'active';
        $items = InventoryItem::query()
            ->with(['category', 'office'])
            ->search($filters['q'] ?? null)
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['unit'] ?? null, fn ($q, $v) => $q->where('unit', $v))
            ->when($filters['office'] ?? null, fn ($q, $v) => $q->where('office_id', $v))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->stockStatus($filters['stock'] ?? null)
            ->orderBy($filters['sort'] ?? 'name', $filters['dir'] ?? 'asc')
            ->paginate((int) ApplicationSetting::get('items_per_page', '15'))
            ->withQueryString();

        return view('items.index', [
            'items' => $items,
            'filters' => $filters + ['status' => $status],
            ...$this->formOptions(),
        ]);
    }

    public function create(): View
    {
        return view('items.form', ['item' => new InventoryItem(['reorder_point' => 0]), ...$this->formOptions()]);
    }

    public function store(InventoryItemRequest $request, InventoryService $inventory): RedirectResponse
    {
        $item = $inventory->createItemWithOpeningBalance(
            $request->itemAttributes(),
            (int) $request->validated('opening_quantity'),
            $request->validated('opening_date'),
            $request->user(),
            $request->validated('opening_reference'),
        );

        return redirect()->route('items.show', $item)->with('success', "Item {$item->stock_number} created with an opening balance of {$item->current_balance} {$item->unit}.");
    }

    public function show(Request $request, InventoryItem $item): View
    {
        $item->load(['category', 'office', 'creator']);

        return view('items.show', [
            'item' => $item,
            'recent' => $request->user()->can('stockcards.view')
                ? $item->transactions()->with('poster')->latest('id')->limit(10)->get()
                : collect(),
        ]);
    }

    public function edit(InventoryItem $item): View
    {
        return view('items.form', ['item' => $item, ...$this->formOptions()]);
    }

    public function update(InventoryItemRequest $request, InventoryItem $item): RedirectResponse
    {
        $old = $item->only($item->getFillable());
        $item->update($request->itemAttributes());
        AuditLogger::log('item.updated', "Inventory item {$item->stock_number} updated", $item, $old, $item->only(array_keys($item->getChanges())));

        return redirect()->route('items.show', $item)->with('success', 'Item details updated.');
    }

    /** Archive instead of delete: history and Stock Card remain intact. */
    public function archive(InventoryItem $item): RedirectResponse
    {
        $item->update(['status' => InventoryItem::STATUS_INACTIVE]);
        AuditLogger::log('item.archived', "Inventory item {$item->stock_number} archived", $item);

        return back()->with('success', "{$item->name} has been archived. Its Stock Card history is preserved.");
    }

    public function restore(InventoryItem $item): RedirectResponse
    {
        $item->update(['status' => InventoryItem::STATUS_ACTIVE]);
        AuditLogger::log('item.restored', "Inventory item {$item->stock_number} restored", $item);

        return back()->with('success', "{$item->name} is active again.");
    }

    /** JSON item search used by the RIS and receipt item pickers. */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $items = InventoryItem::active()->search($request->query('q'))
            ->orderBy('name')->limit(20)
            ->get(['id', 'stock_number', 'name', 'description', 'unit', 'current_balance', 'reorder_point']);

        return response()->json($items);
    }

    /** @return array<string, mixed> */
    private function formOptions(): array
    {
        return [
            'categories' => InventoryCategory::orderBy('name')->get(),
            'offices' => Office::where('is_active', true)->orderBy('name')->get(),
            'units' => InventoryItem::query()->distinct()->orderBy('unit')->pluck('unit')
                ->merge(['Box', 'Can', 'Cartridge', 'Piece', 'Ream', 'Pack', 'Bottle', 'Roll', 'Set'])->unique()->sort()->values(),
        ];
    }
}
