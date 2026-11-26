<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\Requisition;
use App\Models\StockReceipt;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function __invoke(Request $request): View
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $q = trim((string) $request->query('q', ''));
        $user = $request->user();
        $results = ['items' => collect(), 'requisitions' => collect(), 'receipts' => collect()];

        if (mb_strlen($q) >= 2) {
            if ($user->can('items.view')) {
                $results['items'] = InventoryItem::search($q)->orderBy('name')->limit(15)->get();
            }

            $results['requisitions'] = Requisition::search($q)
                ->when(! $user->can('requisitions.view-all'), fn ($query) => $query->where('requested_by', $user->id))
                ->latest('id')->limit(15)->get();

            if ($user->can('stock.receive')) {
                $results['receipts'] = StockReceipt::query()
                    ->where(fn ($w) => $w->where('receipt_no', 'like', "%{$q}%")
                        ->orWhere('reference_no', 'like', "%{$q}%")
                        ->orWhere('supplier', 'like', "%{$q}%"))
                    ->latest('id')->limit(15)->get();
            }
        }

        return view('search', ['q' => $q, 'results' => $results]);
    }
}
