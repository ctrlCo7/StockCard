@extends('layouts.app')
@section('title', 'Inventory Items')

@section('content')
<x-page-header title="Inventory Items" subtitle="Office supplies and consumables. Balances are computed from posted Stock Card transactions.">
    @can('items.manage')
        <a href="{{ route('items.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add Item</a>
    @endcan
</x-page-header>

<form class="filter-bar" method="GET" action="{{ route('items.index') }}">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small mb-1" for="q">Search</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Stock no. or item name">
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="category">Category</label>
            <select id="category" name="category" class="form-select form-select-sm" data-autosubmit>
                <option value="">All</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected(($filters['category'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label small mb-1" for="unit">Unit</label>
            <select id="unit" name="unit" class="form-select form-select-sm" data-autosubmit>
                <option value="">All</option>
                @foreach($units as $u)<option @selected(($filters['unit'] ?? '') === $u)>{{ $u }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="office">Office / Location</label>
            <select id="office" name="office" class="form-select form-select-sm" data-autosubmit>
                <option value="">All</option>
                @foreach($offices as $o)<option value="{{ $o->id }}" @selected(($filters['office'] ?? '') == $o->id)>{{ $o->name }}</option>@endforeach
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small mb-1" for="stock">Stock status</label>
            <select id="stock" name="stock" class="form-select form-select-sm" data-autosubmit>
                <option value="">All</option>
                <option value="ok" @selected(($filters['stock'] ?? '') === 'ok')>In stock</option>
                <option value="low" @selected(($filters['stock'] ?? '') === 'low')>At / below reorder point</option>
                <option value="out" @selected(($filters['stock'] ?? '') === 'out')>Out of stock</option>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label small mb-1" for="status">Record</label>
            <select id="status" name="status" class="form-select form-select-sm" data-autosubmit>
                <option value="active" @selected($filters['status'] === 'active')>Active</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>Archived</option>
                <option value="all" @selected($filters['status'] === 'all')>All</option>
            </select>
        </div>
        <div class="col-md-1 d-flex gap-1">
            <button class="btn btn-sm btn-primary flex-fill" type="submit"><i class="bi bi-funnel"></i><span class="visually-hidden">Filter</span></button>
            <a class="btn btn-sm btn-light border" href="{{ route('items.index') }}" title="Reset filters"><i class="bi bi-x-lg"></i></a>
        </div>
    </div>
</form>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
            <tr>
                <th><x-sort-link column="stock_number" label="Stock No." /></th>
                <th><x-sort-link column="name" label="Item" /></th>
                <th>Category</th>
                <th><x-sort-link column="unit" label="Unit" /></th>
                <th>Office / Location</th>
                <th class="num"><x-sort-link column="reorder_point" label="Reorder Pt." /></th>
                <th class="num"><x-sort-link column="current_balance" label="Balance" /></th>
                <th>Status</th>
                <th class="text-end no-print">Actions</th>
            </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td class="fw-semibold text-nowrap">{{ $item->stock_number }}</td>
                    <td><a href="{{ route('items.show', $item) }}">{{ $item->name }}</a>
                        @if($item->description)<div class="small text-muted text-truncate" style="max-width:280px">{{ $item->description }}</div>@endif</td>
                    <td>{{ $item->category?->name ?? '—' }}</td>
                    <td>{{ $item->unit }}</td>
                    <td class="small">{{ $item->office?->name ?? '—' }}@if($item->location)<div class="text-muted">{{ $item->location }}</div>@endif</td>
                    <td class="num">{{ number_format($item->reorder_point) }}</td>
                    <td class="num fw-bold">{{ number_format($item->current_balance) }}</td>
                    <td>
                        <x-stock-badge :item="$item" />
                        @unless($item->isActive())<span class="badge text-bg-secondary">Archived</span>@endunless
                    </td>
                    <td class="text-end text-nowrap no-print">
                        @can('stockcards.view')
                            <a href="{{ route('stock-cards.show', $item) }}" class="btn btn-sm btn-light border" title="Stock Card" data-bs-toggle="tooltip"><i class="bi bi-journal-text"></i></a>
                        @endcan
                        @can('items.manage')
                            <a href="{{ route('items.edit', $item) }}" class="btn btn-sm btn-light border" title="Edit" data-bs-toggle="tooltip"><i class="bi bi-pencil"></i></a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="9">
                    <x-empty-state icon="box" message="No inventory items match your filters.">
                        @can('items.manage')<a href="{{ route('items.create') }}" class="btn btn-sm btn-primary">Add the first item</a>@endcan
                    </x-empty-state>
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($items->hasPages())<div class="card-footer bg-white">{{ $items->links() }}</div>@endif
</div>
<div class="small text-muted mt-2">Showing {{ $items->firstItem() ?? 0 }}–{{ $items->lastItem() ?? 0 }} of {{ $items->total() }} items</div>
@endsection
