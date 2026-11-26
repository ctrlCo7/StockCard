@extends('layouts.app')
@section('title', 'Stock Receipts')

@section('content')
<x-page-header title="Stock Receipts" subtitle="Deliveries, purchases, transfers and other authorized additions to stock.">
    <a href="{{ route('receipts.create') }}" class="btn btn-sm btn-teal"><i class="bi bi-plus-lg me-1"></i>Receive Stock</a>
</x-page-header>

<form class="filter-bar" method="GET">
    <div class="row g-2 align-items-end">
        <div class="col-md-6"><label class="form-label small mb-1" for="q">Search</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Receipt no., DR/PO no., supplier, item"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="from">From</label><input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="to">To</label><input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-md-2 d-flex gap-1"><button class="btn btn-sm btn-primary flex-fill" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
            <a href="{{ route('receipts.index') }}" class="btn btn-sm btn-light border" title="Reset"><i class="bi bi-x-lg"></i></a></div>
    </div>
</form>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Receipt No.</th><th>Date Received</th><th>Supplier / Source</th><th>Reference</th><th class="num">Lines</th><th class="num">Total Qty.</th><th>Posted by</th><th></th></tr></thead>
            <tbody>
            @forelse($receipts as $r)
                <tr>
                    <td class="fw-semibold"><a href="{{ route('receipts.show', $r) }}">{{ $r->receipt_no }}</a></td>
                    <td>{{ $r->date_received->format('M d, Y') }}</td>
                    <td>{{ $r->supplier }}</td>
                    <td>{{ $r->ledgerReference() }} @if($r->attachment_path)<i class="bi bi-paperclip text-muted" title="Has attachment"></i>@endif</td>
                    <td class="num">{{ $r->items_count }}</td>
                    <td class="num">{{ number_format($r->items_sum_quantity) }}</td>
                    <td class="small">{{ $r->poster?->name }}</td>
                    <td class="text-end"><a href="{{ route('receipts.show', $r) }}" class="btn btn-sm btn-light border">View</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state icon="box-arrow-in-down" message="No stock receipts found." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($receipts->hasPages())<div class="card-footer bg-white">{{ $receipts->links() }}</div>@endif
</div>
@endsection
