@extends('layouts.app')
@section('title', 'Stock Adjustments')

@section('content')
<x-page-header title="Stock Adjustments" subtitle="Authorized corrections for damage, loss, physical count discrepancies and recording errors.">
    <a href="{{ route('adjustments.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg me-1"></i>New Adjustment</a>
</x-page-header>

<form class="filter-bar" method="GET">
    <div class="row g-2 align-items-end">
        <div class="col-md-6"><label class="form-label small mb-1" for="q">Search</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Adjustment no., reference, item"></div>
        <div class="col-md-4"><label class="form-label small mb-1" for="reason">Reason</label>
            <select id="reason" name="reason" class="form-select form-select-sm" data-autosubmit>
                <option value="">All</option>
                @foreach($reasons as $k => $l)<option value="{{ $k }}" @selected(($filters['reason'] ?? '') === $k)>{{ $l }}</option>@endforeach
            </select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button></div>
    </div>
</form>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>No.</th><th>Date</th><th>Item</th><th>Direction</th><th class="num">Qty.</th><th>Reason</th><th>Reference</th><th>By</th></tr></thead>
            <tbody>
            @forelse($adjustments as $a)
                <tr>
                    <td class="fw-semibold"><a href="{{ route('adjustments.show', $a) }}">{{ $a->adjustment_no }}</a></td>
                    <td>{{ $a->adjustment_date->format('M d, Y') }}</td>
                    <td>{{ $a->item->name }}<div class="small text-muted">{{ $a->item->stock_number }}</div></td>
                    <td><span class="badge text-bg-{{ $a->isIncrease() ? 'success' : 'danger' }}">{{ $a->isIncrease() ? 'Increase' : 'Decrease' }}</span></td>
                    <td class="num">{{ $a->quantity }}</td>
                    <td>{{ $a->reason->label() }}</td>
                    <td>{{ $a->reference_no }}</td>
                    <td class="small">{{ $a->adjuster?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state icon="sliders" message="No adjustments recorded." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($adjustments->hasPages())<div class="card-footer bg-white">{{ $adjustments->links() }}</div>@endif
</div>
@endsection
