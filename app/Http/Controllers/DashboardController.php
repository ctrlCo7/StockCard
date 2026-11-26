<?php

namespace App\Http\Controllers;

use App\Enums\RequisitionStatus as Status;
use App\Enums\TransactionType;
use App\Models\InventoryItem;
use App\Models\Requisition;
use App\Models\StockTransaction;
use App\Services\InventoryNotifier;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $since = today()->subDays(30);
        $active = InventoryItem::active();

        $stats = [
            'active_items' => (clone $active)->count(),
            'units_in_stock' => (int) (clone $active)->sum('current_balance'),
            'low_stock' => (clone $active)->lowStock()->count(),
            'out_of_stock' => (clone $active)->outOfStock()->count(),
            'pending_requisitions' => Requisition::whereIn('status', [Status::Submitted, Status::PendingApproval])->count(),
            'awaiting_issuance' => Requisition::whereIn('status', [Status::Approved, Status::PartiallyIssued])->count(),
            'recent_receipts' => StockTransaction::where('type', TransactionType::Receipt)->whereDate('transaction_date', '>=', $since)->count(),
            'recent_issuances' => StockTransaction::where('type', TransactionType::Issue)->whereDate('transaction_date', '>=', $since)->count(),
        ];

        $canSeeStock = $user->can('stockcards.view');

        return view('dashboard', [
            'stats' => $stats,
            'canSeeStock' => $canSeeStock,
            'recentTransactions' => $canSeeStock
                ? StockTransaction::with(['item', 'poster'])->latest('id')->limit(10)->get()
                : collect(),
            'lowStockItems' => InventoryItem::active()->whereColumn('current_balance', '<=', 'reorder_point')
                ->orderBy('current_balance')->limit(8)->get(),
            // Requesters see their own requests; administrators see what is waiting on them.
            'myRequisitions' => $user->can('requisitions.create')
                ? Requisition::where('requested_by', $user->id)->latest('id')->limit(5)->get()
                : Requisition::with('requester')->whereIn('status', [Status::Submitted, Status::PendingApproval, Status::Approved, Status::PartiallyIssued])
                    ->oldest('submitted_at')->limit(6)->get(),
            'adminEmails' => $user->can('notifications.send') ? app(InventoryNotifier::class)->administratorEmails() : [],
        ]);
    }

    /** Daily received/issued totals for the movement chart. */
    public function trend(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('stockcards.view'), 403);

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $to = Carbon::parse($data['to'] ?? today());
        $from = Carbon::parse($data['from'] ?? $to->copy()->subDays(29));
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subDays(366);
        }

        $rows = StockTransaction::query()
            ->whereDate('transaction_date', '>=', $from->toDateString())
            ->whereDate('transaction_date', '<=', $to->toDateString())
            ->whereIn('type', [TransactionType::Receipt, TransactionType::Issue])
            ->selectRaw('DATE(transaction_date) AS transaction_date, SUM(quantity_received) AS received, SUM(quantity_issued) AS issued')
            ->groupByRaw('DATE(transaction_date)')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->transaction_date)->toDateString());

        $labels = $received = $issued = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $key = $day->toDateString();
            $labels[] = $day->format('M d');
            $received[] = (int) ($rows[$key]->received ?? 0);
            $issued[] = (int) ($rows[$key]->issued ?? 0);
        }

        return response()->json(compact('labels', 'received', 'issued'));
    }
}
