@extends('layouts.app')
@section('title', $item->name)

@section('content')
<x-page-header :title="$item->name" :subtitle="'Stock No. '.$item->stock_number" :crumbs="['Inventory Items' => route('items.index')]">
    @can('stockcards.view')
        <a href="{{ route('stock-cards.show', $item) }}" class="btn btn-sm btn-primary"><i class="bi bi-journal-text me-1"></i>Stock Card</a>
    @endcan
    @if($item->isActive())
        @can('stock.receive')<a href="{{ route('receipts.create', ['item' => $item->id]) }}" class="btn btn-sm btn-teal"><i class="bi bi-box-arrow-in-down me-1"></i>Receive</a>@endcan
        @can('stock.adjust')<a href="{{ route('adjustments.create', ['item' => $item->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-sliders me-1"></i>Adjust</a>@endcan
    @endif
    @can('items.manage')
        <a href="{{ route('items.edit', $item) }}" class="btn btn-sm btn-light border"><i class="bi bi-pencil me-1"></i>Edit</a>
        @if($item->isActive())
            <form method="POST" action="{{ route('items.archive', $item) }}" data-confirm="Archive {{ $item->name }}? It will no longer be available for receipts or requisitions, but its Stock Card history is preserved." data-confirm-title="Archive item" data-confirm-variant="warning" data-confirm-button="Archive">
                @csrf @method('PATCH')
                <button class="btn btn-sm btn-outline-warning" type="submit"><i class="bi bi-archive me-1"></i>Archive</button>
            </form>
        @else
            <form method="POST" action="{{ route('items.restore', $item) }}">@csrf @method('PATCH')
                <button class="btn btn-sm btn-outline-success" type="submit"><i class="bi bi-arrow-counterclockwise me-1"></i>Restore</button>
            </form>
        @endif
    @endcan
</x-page-header>

@if(! $item->isActive())
    <div class="alert alert-secondary"><i class="bi bi-archive me-2"></i>This item is archived. Its history is retained, but no new stock movements can be posted.</div>
@elseif($item->isLowStock())
    <div class="alert alert-{{ $item->isOutOfStock() ? 'danger' : 'warning' }} d-flex gap-2 align-items-center">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <div><strong>{{ $item->isOutOfStock() ? 'Out of stock.' : 'Reorder required.' }}</strong>
            The balance ({{ $item->current_balance }} {{ $item->unit }}) is at or below the re-order point of {{ $item->reorder_point }}.</div>
    </div>
@endif

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-body">
                <div class="balance-hero">
                    <div><div class="figure">{{ number_format($item->current_balance) }}</div><div class="caption">Current balance ({{ $item->unit }})</div></div>
                    <div><div class="figure text-muted">{{ number_format($item->reorder_point) }}</div><div class="caption">Re-order point</div></div>
                </div>
                <div class="mt-2"><x-stock-badge :item="$item" /></div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">Details</h2></div>
            <div class="card-body">
                <dl class="kv">
                    <dt>Item ID</dt><dd>#{{ $item->id }}</dd>
                    <dt>Stock No.</dt><dd>{{ $item->stock_number }}</dd>
                    <dt>Description</dt><dd>{{ $item->description ?: '—' }}</dd>
                    <dt>Category</dt><dd>{{ $item->category?->name ?? '—' }}</dd>
                    <dt>Unit</dt><dd>{{ $item->unit }}</dd>
                    <dt>Office</dt><dd>{{ $item->office?->name ?? '—' }}</dd>
                    <dt>Location</dt><dd>{{ $item->location ?: '—' }}</dd>
                    <dt>Initial stock</dt><dd>{{ number_format($item->initial_quantity) }}</dd>
                    <dt>Status</dt><dd>{{ $item->isActive() ? 'Active' : 'Inactive (archived)' }}</dd>
                    <dt>Created</dt><dd>{{ $item->created_at->format('M d, Y g:i A') }}@if($item->creator) by {{ $item->creator->name }}@endif</dd>
                    <dt>Updated</dt><dd>{{ $item->updated_at->format('M d, Y g:i A') }}</dd>
                </dl>
            </div>
        </div>
    </div>

    @can('stockcards.view')
    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header"><h2 class="card-title">Latest Stock Card entries</h2><a href="{{ route('stock-cards.show', $item) }}" class="small">Full Stock Card</a></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead><tr><th>Date</th><th>Reference</th><th>From / To</th><th class="num">Received</th><th class="num">Issued</th><th class="num">Balance</th></tr></thead>
                    <tbody>
                    @forelse($recent as $t)
                        <tr>
                            <td class="text-nowrap">{{ $t->transaction_date->format('M d, Y') }}</td>
                            <td>{{ $t->reference_no }}</td>
                            <td class="small">{{ $t->party }}</td>
                            <td class="num">{{ $t->quantity_received ?: '' }}</td>
                            <td class="num">{{ $t->quantity_issued ?: '' }}</td>
                            <td class="num fw-semibold">{{ $t->balance_after }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state message="No transactions yet." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endcan
</div>
@endsection
