@extends('layouts.app')
@section('title', 'Stock Cards')

@section('content')
<x-page-header title="Stock Cards" subtitle="Select an item to view its complete receipt and issuance history with running balance." />

<form class="filter-bar" method="GET">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small mb-1" for="q">Search</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Stock no., item name or description">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1" for="category">Category</label>
            <select id="category" name="category" class="form-select form-select-sm" data-autosubmit>
                <option value="">All</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small mb-1" for="stock">Stock status</label>
            <select id="stock" name="stock" class="form-select form-select-sm" data-autosubmit>
                <option value="">All</option>
                <option value="ok" @selected(($filters['stock'] ?? '') === 'ok')>In stock</option>
                <option value="low" @selected(($filters['stock'] ?? '') === 'low')>Reorder</option>
                <option value="out" @selected(($filters['stock'] ?? '') === 'out')>Out of stock</option>
            </select>
        </div>
        <div class="col-md-1"><button class="btn btn-sm btn-primary w-100" type="submit"><i class="bi bi-search"></i><span class="visually-hidden">Search</span></button></div>
    </div>
</form>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Stock No.</th><th>Item</th><th>Unit</th><th class="num">Entries</th><th>Last movement</th><th class="num">Reorder Pt.</th><th class="num">Balance</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td class="fw-semibold">{{ $item->stock_number }}</td>
                    <td>{{ $item->name }} @unless($item->isActive())<span class="badge text-bg-secondary">Archived</span>@endunless</td>
                    <td>{{ $item->unit }}</td>
                    <td class="num">{{ $item->transactions_count }}</td>
                    <td>{{ $item->transactions_max_transaction_date ? \Illuminate\Support\Carbon::parse($item->transactions_max_transaction_date)->format('M d, Y') : '—' }}</td>
                    <td class="num">{{ $item->reorder_point }}</td>
                    <td class="num fw-bold">{{ number_format($item->current_balance) }}</td>
                    <td><x-stock-badge :item="$item" /></td>
                    <td class="text-end"><a href="{{ route('stock-cards.show', $item) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="9"><x-empty-state icon="journal-text" message="No items found." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($items->hasPages())<div class="card-footer bg-white">{{ $items->links() }}</div>@endif
</div>
@endsection
