<?php

namespace App\Http\Controllers;

use App\Models\StockTransaction;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StockTransactionController extends Controller
{
    public function reverse(Request $request, StockTransaction $transaction, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $reversal = $inventory->reverse($transaction, $data['reason'], $request->user());

        return redirect()->route('stock-cards.show', $transaction->item_id)
            ->with('success', "Transaction {$transaction->reference_no} reversed. New balance: {$reversal->balance_after}.");
    }
}
