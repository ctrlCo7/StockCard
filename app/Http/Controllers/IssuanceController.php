<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Models\Issuance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IssuanceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $issuances = Issuance::query()
            ->with(['requisition.office', 'issuer'])->withCount('items')->withSum('items', 'quantity')
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w
                ->where('issuance_no', 'like', "%{$v}%")->orWhere('received_by_name', 'like', "%{$v}%")
                ->orWhereHas('requisition', fn ($r) => $r->where('ris_no', 'like', "%{$v}%"))))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('issue_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('issue_date', '<=', $v))
            ->latest('id')
            ->paginate((int) ApplicationSetting::get('items_per_page', '15'))
            ->withQueryString();

        return view('issuances.index', compact('issuances', 'filters'));
    }

    public function show(Issuance $issuance): View
    {
        return view('issuances.show', ['issuance' => $issuance->load(['requisition.office', 'issuer', 'items.item', 'items.transaction', 'items.requisitionItem'])]);
    }
}
