@extends('layouts.app')
@section('title', 'Search')

@section('content')
<x-page-header title="Search results" :subtitle="$q ? 'Results for “'.$q.'”' : 'Enter at least 2 characters in the search bar.'" />

@php $total = collect($results)->sum(fn ($c) => $c->count()); @endphp
@if($q && $total === 0)
    <div class="card"><x-empty-state icon="search" message="Nothing matched your search." /></div>
@endif

@if($results['items']->isNotEmpty())
    <div class="card table-card mb-3">
        <div class="card-header"><h2 class="card-title">Inventory items ({{ $results['items']->count() }})</h2></div>
        <div class="list-group list-group-flush">
            @foreach($results['items'] as $item)
                <a href="{{ route('items.show', $item) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <span><strong>{{ $item->stock_number }}</strong> — {{ $item->name }} <span class="text-muted small">{{ $item->unit }}</span></span>
                    <span>{{ $item->current_balance }} <x-stock-badge :item="$item" /></span>
                </a>
            @endforeach
        </div>
    </div>
@endif

@if($results['requisitions']->isNotEmpty())
    <div class="card table-card mb-3">
        <div class="card-header"><h2 class="card-title">Requisitions ({{ $results['requisitions']->count() }})</h2></div>
        <div class="list-group list-group-flush">
            @foreach($results['requisitions'] as $r)
                <a href="{{ route('requisitions.show', $r) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <span><strong>{{ $r->ris_no }}</strong> — <span class="small">{{ \Illuminate\Support\Str::limit($r->purpose, 80) }}</span></span>
                    <x-ris-status :status="$r->status" />
                </a>
            @endforeach
        </div>
    </div>
@endif

@if($results['receipts']->isNotEmpty())
    <div class="card table-card mb-3">
        <div class="card-header"><h2 class="card-title">Stock receipts ({{ $results['receipts']->count() }})</h2></div>
        <div class="list-group list-group-flush">
            @foreach($results['receipts'] as $rc)
                <a href="{{ route('receipts.show', $rc) }}" class="list-group-item list-group-item-action">
                    <strong>{{ $rc->receipt_no }}</strong> — {{ $rc->supplier }} <span class="text-muted small">({{ $rc->ledgerReference() }}, {{ $rc->date_received->format('M d, Y') }})</span>
                </a>
            @endforeach
        </div>
    </div>
@endif
@endsection
