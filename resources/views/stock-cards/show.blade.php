@extends('layouts.app')
@section('title', 'Stock Card — '.$item->name)

@section('content')
<x-page-header title="Stock Card" :subtitle="$item->name.' · '.$item->stock_number" :crumbs="['Stock Cards' => route('stock-cards.index')]">
    <a href="{{ route('stock-cards.print', request()->query() + ['item' => $item->id]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><i class="bi bi-printer me-1"></i>Print</a>
    <a href="{{ route('stock-cards.pdf', request()->query() + ['item' => $item->id]) }}" class="btn btn-sm btn-light border"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
    @if($item->isActive())
        @can('stock.receive')<a href="{{ route('receipts.create', ['item' => $item->id]) }}" class="btn btn-sm btn-teal"><i class="bi bi-box-arrow-in-down me-1"></i>Receive Stock</a>@endcan
        @can('stock.adjust')<a href="{{ route('adjustments.create', ['item' => $item->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-sliders me-1"></i>Adjustment</a>@endcan
    @endif
</x-page-header>

@if($item->isLowStock())
    <div class="alert alert-{{ $item->isOutOfStock() ? 'danger' : 'warning' }} d-flex gap-2 align-items-center" role="alert">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div><strong>{{ $item->isOutOfStock() ? 'Out of stock' : 'Re-order point reached' }}:</strong>
            balance is {{ $item->current_balance }} {{ $item->unit }}; re-order point is {{ $item->reorder_point }}.</div>
    </div>
@endif

<div class="card mb-3">
    <div class="card-body">
        <div class="sc-doc sc-screen">
            @include('stock-cards._header')
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
            <span class="text-muted small">Current balance:</span>
            <span class="fs-5 fw-bold">{{ number_format($item->current_balance) }} {{ $item->unit }}</span>
            <x-stock-badge :item="$item" />
        </div>
    </div>
</div>

@push('styles')
<style>
    @include('stock-cards._styles')
    .sc-screen-table > :not(caption) > * > * { border-color: #9aa4b2; }
    .sc-screen-table thead th { font-family: "Times New Roman", Times, serif; font-size: .85rem; text-transform: none; letter-spacing: 0; color: #111; vertical-align: middle; text-align: center; }
    .sc-screen-table tr.sc-in > td { color: #b3261e; }
    .sc-screen-table tr.sc-in > td a { color: #b3261e; }
    @media (max-width: 767.98px) { .sc-doc.sc-screen { font-size: 11px; } }
</style>
@endpush

<form class="filter-bar" method="GET">
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="from">From</label><input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="to">To</label><input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-md-2">
            <label class="form-label small mb-1" for="type">Type</label>
            <select id="type" name="type" class="form-select form-select-sm">
                <option value="">All</option>
                @foreach(\App\Enums\TransactionType::options() as $v => $l)<option value="{{ $v }}" @selected(($filters['type'] ?? '') === $v)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="col-md-4"><label class="form-label small mb-1" for="q">Reference / party</label><input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="e.g. RIS-2026-0001 or supplier"></div>
        <div class="col-md-2 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a href="{{ route('stock-cards.show', $item) }}" class="btn btn-sm btn-light border" title="Reset"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover table-bordered align-middle sc-screen-table">
            <thead>
            <tr>
                <th rowspan="2">Date</th>
                <th rowspan="2">Reference</th>
                <th rowspan="2">From Whom Received or<br>To Whom Issued</th>
                <th class="text-center">Received</th>
                <th class="text-center">Issued</th>
                <th class="text-center">Balance</th>
                <th rowspan="2">Posted by</th>
                <th rowspan="2" class="no-print"></th>
            </tr>
            <tr><th class="text-center">Qty.</th><th class="text-center">Qty.</th><th class="text-center">Qty.</th></tr>
            </thead>
            <tbody>
            @if($broughtForward !== null && $transactions->onFirstPage())
                <tr class="table-light">
                    <td colspan="3" class="fst-italic">Balance brought forward as of {{ \Illuminate\Support\Carbon::parse($filters['from'])->format('M d, Y') }}</td>
                    <td></td><td></td><td class="num fw-bold">{{ $broughtForward }}</td><td></td><td class="no-print"></td>
                </tr>
            @endif
            @forelse($transactions as $t)
                @php $isOpening = $t->type === \App\Enums\TransactionType::Opening; @endphp
                <tr @class(['row-reversed' => $t->reversal, 'sc-in' => $t->type === \App\Enums\TransactionType::Receipt])>
                    @if($isOpening)
                        <td colspan="2" class="fst-italic">As per Inventory
                            <div class="small text-muted fst-normal">{{ $t->transaction_date->format('n-j-Y') }} · {{ $t->reference_no }}</div></td>
                    @else
                        <td class="text-nowrap">{{ $t->transaction_date->format('n-j-Y') }}</td>
                        <td>
                            @if($url = $t->sourceUrl())<a href="{{ $url }}">{{ $t->reference_no }}</a>@else{{ $t->reference_no }}@endif
                            <div class="no-strike"><span class="badge text-bg-{{ $t->type->badge() }}">{{ $t->type->label() }}</span>
                                @if($t->reversal)<span class="badge text-bg-light border text-danger">Reversed</span>@endif</div>
                        </td>
                    @endif
                    <td>{{ $isOpening ? '' : $t->party }}@if($t->remarks)<div class="small text-muted">{{ $t->remarks }}</div>@endif</td>
                    <td class="text-center">{{ $isOpening ? '' : ($t->quantity_received ?: '') }}</td>
                    <td class="text-center">{{ $t->quantity_issued ?: '' }}</td>
                    <td class="text-center fw-bold no-strike">{{ $t->balance_after }}</td>
                    <td class="small no-strike">{{ $t->poster?->name }}<div class="text-muted">{{ $t->posted_at->format('m/d/Y g:i A') }}</div></td>
                    <td class="text-end no-print no-strike">
                        @can('stock.reverse')
                            @if($t->type !== \App\Enums\TransactionType::Reversal && ! $t->reversal)
                                <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#reverseModal"
                                        data-action="{{ route('transactions.reverse', $t) }}" data-ref="{{ $t->reference_no }}"
                                        data-qty="{{ $t->quantity_received ?: $t->quantity_issued }}" data-kind="{{ $t->quantity_received ? 'received' : 'issued' }}"
                                        title="Reverse this entry"><i class="bi bi-arrow-counterclockwise"></i><span class="visually-hidden">Reverse</span></button>
                            @endif
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state icon="journal" message="No transactions match the selected filters." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($transactions->hasPages())<div class="card-footer bg-white">{{ $transactions->links() }}</div>@endif
</div>
<p class="small text-muted mt-2"><i class="bi bi-info-circle me-1"></i>Balance = previous balance + quantity received − quantity issued. Posted entries are never edited; corrections appear as reversal or adjustment entries.</p>

@can('stock.reverse')
<div class="modal fade" id="reverseModal" tabindex="-1" aria-labelledby="reverseTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="">
            @csrf
            <div class="modal-header"><h5 class="modal-title" id="reverseTitle">Reverse transaction</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="small">This posts a new, opposite entry for <strong data-ref></strong> (<span data-summary></span>). The original entry is kept for audit purposes.</p>
                <label for="reverseReason" class="form-label">Reason for reversal <span class="text-danger">*</span></label>
                <textarea id="reverseReason" name="reason" class="form-control" rows="3" required minlength="5" maxlength="500"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Post reversal</button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
document.getElementById('reverseModal').addEventListener('show.bs.modal', function (e) {
    var b = e.relatedTarget, f = this.querySelector('form');
    f.action = b.dataset.action;
    this.querySelector('[data-ref]').textContent = b.dataset.ref;
    this.querySelector('[data-summary]').textContent = b.dataset.qty + ' ' + b.dataset.kind + ' will be ' + (b.dataset.kind === 'received' ? 'deducted' : 'returned to stock');
});
</script>
@endpush
@endcan
@endsection
