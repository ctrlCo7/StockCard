<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentReason;
use App\Models\ApplicationSetting;
use App\Models\InventoryItem;
use App\Models\StockAdjustment;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', Rule::enum(AdjustmentReason::class)],
        ]);

        $adjustments = StockAdjustment::query()->with(['item', 'adjuster'])
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('adjustment_no', 'like', "%{$v}%")->orWhere('reference_no', 'like', "%{$v}%")
                ->orWhereHas('item', fn ($i) => $i->search($v))))
            ->when($filters['reason'] ?? null, fn ($q, $v) => $q->where('reason', $v))
            ->latest('id')
            ->paginate((int) ApplicationSetting::get('items_per_page', '15'))
            ->withQueryString();

        return view('adjustments.index', ['adjustments' => $adjustments, 'filters' => $filters, 'reasons' => AdjustmentReason::options()]);
    }

    public function create(Request $request): View
    {
        return view('adjustments.create', [
            'items' => InventoryItem::active()->orderBy('name')->get(['id', 'stock_number', 'name', 'unit', 'current_balance']),
            'selected' => $request->integer('item') ?: null,
            'reasons' => AdjustmentReason::options(),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'item_id' => ['required', 'integer', Rule::exists('inventory_items', 'id')->where('status', InventoryItem::STATUS_ACTIVE)],
            'adjustment_date' => ['required', 'date', 'before_or_equal:today'],
            'direction' => ['required', 'in:increase,decrease'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'reference_no' => ['required', 'string', 'max:80'],
            'remarks' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        [$adjustment, $created] = $inventory->adjust($data, $request->user());

        return redirect()->route('adjustments.show', $adjustment)->with(
            $created ? 'success' : 'warning',
            $created ? "Adjustment {$adjustment->adjustment_no} posted." : "This adjustment was already posted as {$adjustment->adjustment_no}."
        );
    }

    public function show(StockAdjustment $adjustment): View
    {
        return view('adjustments.show', ['adjustment' => $adjustment->load(['item', 'adjuster', 'transaction'])]);
    }
}
