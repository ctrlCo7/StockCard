@extends('layouts.app')
@section('title', 'Adjustment '.$adjustment->adjustment_no)

@section('content')
<x-page-header :title="'Stock Adjustment '.$adjustment->adjustment_no" :crumbs="['Stock Adjustments' => route('adjustments.index')]">
    <a href="{{ route('stock-cards.show', $adjustment->item) }}" class="btn btn-sm btn-primary"><i class="bi bi-journal-text me-1"></i>View Stock Card</a>
</x-page-header>

<div class="card" style="max-width:760px">
    <div class="card-body">
        <dl class="kv">
            <dt>Adjustment no.</dt><dd>{{ $adjustment->adjustment_no }}</dd>
            <dt>Date</dt><dd>{{ $adjustment->adjustment_date->format('M d, Y') }}</dd>
            <dt>Item</dt><dd>{{ $adjustment->item->stock_number }} — {{ $adjustment->item->name }}</dd>
            <dt>Direction</dt><dd><span class="badge text-bg-{{ $adjustment->isIncrease() ? 'success' : 'danger' }}">{{ $adjustment->isIncrease() ? 'Increase' : 'Decrease' }}</span></dd>
            <dt>Quantity</dt><dd>{{ $adjustment->quantity }} {{ $adjustment->item->unit }}</dd>
            <dt>Reason</dt><dd>{{ $adjustment->reason->label() }}</dd>
            <dt>Reference</dt><dd>{{ $adjustment->reference_no }}</dd>
            <dt>Explanation</dt><dd>{{ $adjustment->remarks }}</dd>
            <dt>Authorized by</dt><dd>{{ $adjustment->adjuster?->name }} · {{ $adjustment->created_at->format('M d, Y g:i A') }}</dd>
            <dt>Balance after</dt><dd class="fw-bold">{{ $adjustment->transaction?->balance_after }}</dd>
        </dl>
    </div>
</div>
@endsection
