@extends('layouts.app')
@section('title', $title)

@php
    // Which filters apply to which report.
    $uses = [
        'dates' => ! in_array($key, ['current-inventory', 'low-stock', 'out-of-stock', 'pending-requisitions']),
        'item' => ! in_array($key, ['audit']),
        'category' => in_array($key, ['current-inventory', 'low-stock', 'out-of-stock', 'receipts', 'issuances', 'stock-movement', 'ledger', 'adjustments']),
        'office' => ! in_array($key, ['audit', 'stock-card']),
        'type' => $key === 'ledger',
        'status' => $key === 'ris-register',
        'user' => $key === 'audit',
    ];
    $query = array_filter($filters, fn ($v) => $v !== null && $v !== '');
@endphp

@section('content')
<x-page-header :title="$title" :crumbs="['Reports' => route('reports.index')]">
    <a href="{{ route('reports.show', [$key, ...$query, 'format' => 'print']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><i class="bi bi-printer me-1"></i>Print</a>
    <a href="{{ route('reports.show', [$key, ...$query, 'format' => 'pdf']) }}" class="btn btn-sm btn-light border"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
    <a href="{{ route('reports.show', [$key, ...$query, 'format' => 'csv']) }}" class="btn btn-sm btn-light border"><i class="bi bi-filetype-csv me-1"></i>CSV / Excel</a>
    @if(in_array($key, ['low-stock', 'out-of-stock'], true))
        @can('notifications.send')
            <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#emailLowStock"><i class="bi bi-envelope me-1"></i>Send email</button>
        @endcan
    @endif
</x-page-header>

<form class="filter-bar" method="GET">
    <div class="row g-2 align-items-end">
        @if($uses['dates'])
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="from">From</label><input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="to">To</label><input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
        @endif
        @if($uses['item'])
            <div class="col-md-3"><label class="form-label small mb-1" for="item">Item{{ $key === 'stock-card' ? ' *' : '' }}</label>
                <select id="item" name="item" class="form-select form-select-sm" @required($key === 'stock-card')>
                    <option value="">{{ $key === 'stock-card' ? 'Select an item…' : 'All items' }}</option>
                    @foreach($options['items'] as $i)<option value="{{ $i->id }}" @selected(($filters['item'] ?? '') == $i->id)>{{ $i->stock_number }} — {{ $i->name }}</option>@endforeach
                </select></div>
            @if($key !== 'stock-card')
                <div class="col-6 col-md-2"><label class="form-label small mb-1" for="stock_number">Stock No.</label>
                    <input id="stock_number" name="stock_number" value="{{ $filters['stock_number'] ?? '' }}" class="form-control form-control-sm"></div>
            @endif
        @endif
        @if($uses['category'])
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="category">Category</label>
                <select id="category" name="category" class="form-select form-select-sm"><option value="">All</option>
                    @foreach($options['categories'] as $c)<option value="{{ $c->id }}" @selected(($filters['category'] ?? '') == $c->id)>{{ $c->name }}</option>@endforeach
                </select></div>
        @endif
        @if($uses['office'])
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="office">Office</label>
                <select id="office" name="office" class="form-select form-select-sm"><option value="">All</option>
                    @foreach($options['offices'] as $o)<option value="{{ $o->id }}" @selected(($filters['office'] ?? '') == $o->id)>{{ $o->name }}</option>@endforeach
                </select></div>
        @endif
        @if($uses['type'])
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="type">Transaction type</label>
                <select id="type" name="type" class="form-select form-select-sm"><option value="">All</option>
                    @foreach($options['types'] as $k => $l)<option value="{{ $k }}" @selected(($filters['type'] ?? '') === $k)>{{ $l }}</option>@endforeach
                </select></div>
        @endif
        @if($uses['status'])
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="status">Status</label>
                <select id="status" name="status" class="form-select form-select-sm"><option value="">All</option>
                    @foreach($options['statuses'] as $k => $l)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>@endforeach
                </select></div>
        @endif
        @if($uses['user'])
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="user">User</label>
                <select id="user" name="user" class="form-select form-select-sm"><option value="">All</option>
                    @foreach($options['users'] as $u)<option value="{{ $u->id }}" @selected(($filters['user'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach
                </select></div>
        @endif
        <div class="col-md-auto d-flex gap-1">
            <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Generate</button>
            <a href="{{ route('reports.show', $key) }}" class="btn btn-sm btn-light border">Reset</a>
        </div>
    </div>
</form>

@if($summary)
    <div class="d-flex flex-wrap gap-3 mb-2 small">
        @foreach($summary as $label => $value)<span><span class="text-muted">{{ $label }}:</span> <strong>{{ $value }}</strong></span>@endforeach
    </div>
@endif

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead><tr>@foreach($columns as $k => $label)<th @class(['num' => in_array($k, $numeric)])>{{ $label }}</th>@endforeach</tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>@foreach($columns as $k => $label)<td @class(['num' => in_array($k, $numeric)])>{{ $row[$k] ?? '' }}</td>@endforeach</tr>
            @empty
                <tr><td colspan="{{ max(count($columns), 1) }}"><x-empty-state icon="clipboard-data" message="No records match the selected filters." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="small text-muted mt-2">Generated {{ $generatedAt->format('M d, Y g:i A') }} by {{ $generatedBy->name }}</div>

@if(in_array($key, ['low-stock', 'out-of-stock'], true))
    @can('notifications.send')
        <x-email-modal id="emailLowStock" :action="route('notifications.low-stock')" title="Email low-stock report" :emails="implode(', ', $adminEmails)"
                       description="Sends the current list of every active item at or below its re-order point (including out-of-stock items), with a link to the Low-Stock Report." />
    @endcan
@endif
@endsection
