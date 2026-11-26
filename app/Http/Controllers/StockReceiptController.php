<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Models\InventoryItem;
use App\Models\StockReceipt;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockReceiptController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $receipts = StockReceipt::query()
            ->withCount('items')->withSum('items', 'quantity')->with('poster')
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('receipt_no', 'like', "%{$v}%")->orWhere('reference_no', 'like', "%{$v}%")
                ->orWhere('supplier', 'like', "%{$v}%")
                ->orWhereHas('items.item', fn ($i) => $i->where('name', 'like', "%{$v}%")->orWhere('stock_number', 'like', "%{$v}%"))))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('date_received', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('date_received', '<=', $v))
            ->latest('id')
            ->paginate((int) ApplicationSetting::get('items_per_page', '15'))
            ->withQueryString();

        return view('receipts.index', compact('receipts', 'filters'));
    }

    public function create(Request $request): View
    {
        $preselected = $request->integer('item') ? InventoryItem::active()->find($request->integer('item')) : null;

        return view('receipts.create', [
            'items' => InventoryItem::active()->orderBy('name')->get(['id', 'stock_number', 'name', 'unit', 'current_balance']),
            'preselected' => $preselected,
            'referenceTypes' => StockReceipt::REFERENCE_TYPES,
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'uuid'],
            'date_received' => ['required', 'date', 'before_or_equal:today'],
            'supplier' => ['required', 'string', 'max:150'],
            'reference_type' => ['required', Rule::in(array_keys(StockReceipt::REFERENCE_TYPES))],
            'reference_no' => ['required', 'string', 'max:80'],
            'received_by_name' => ['required', 'string', 'max:150'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.item_id' => ['required', 'integer', 'distinct', Rule::exists('inventory_items', 'id')->where('status', InventoryItem::STATUS_ACTIVE)],
            'lines.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'lines.*.remarks' => ['nullable', 'string', 'max:255'],
        ], [
            'lines.*.item_id.distinct' => 'Each item may appear only once per receipt.',
            'lines.*.quantity.min' => 'Received quantities must be at least 1.',
        ]);

        $header = collect($data)->only(['idempotency_key', 'date_received', 'supplier', 'reference_type', 'reference_no', 'received_by_name', 'remarks'])->all();

        if ($file = $request->file('attachment')) {
            // Stored on the private disk; served only through the authorized attachment route.
            $header['attachment_path'] = $file->store('receipts', 'local');
            $header['attachment_name'] = Str::limit($file->getClientOriginalName(), 200, '');
        }

        try {
            [$receipt, $created] = $inventory->receive($header, array_values($data['lines']), $request->user());
        } catch (\Throwable $e) {
            isset($header['attachment_path']) && Storage::disk('local')->delete($header['attachment_path']);
            throw $e;
        }

        if (! $created && isset($header['attachment_path'])) {
            Storage::disk('local')->delete($header['attachment_path']);
        }

        return redirect()->route('receipts.show', $receipt)->with(
            $created ? 'success' : 'warning',
            $created ? "Receipt {$receipt->receipt_no} posted. Stock Cards have been updated." : "This receipt was already posted as {$receipt->receipt_no}; it was not posted again."
        );
    }

    public function show(StockReceipt $receipt): View
    {
        return view('receipts.show', ['receipt' => $receipt->load(['items.item', 'poster', 'transactions'])]);
    }

    public function attachment(StockReceipt $receipt): StreamedResponse
    {
        abort_unless($receipt->attachment_path && Storage::disk('local')->exists($receipt->attachment_path), 404);

        return Storage::disk('local')->download($receipt->attachment_path, $receipt->attachment_name ?: basename($receipt->attachment_path));
    }
}
