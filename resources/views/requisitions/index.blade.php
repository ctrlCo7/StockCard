@extends('layouts.app')
@section('title', 'Requisitions & Issue Slips')

@section('content')
<x-page-header title="Requisition and Issue Slips" subtitle="Request supplies, track approval and issuance.">
    @can('requisitions.create')
        <a href="{{ route('requisitions.create') }}" class="btn btn-sm btn-teal"><i class="bi bi-plus-lg me-1"></i>New RIS</a>
    @endcan
</x-page-header>

@if($canViewAll)
    <ul class="nav nav-tabs mb-3">
        @foreach(array_filter(['all' => 'All requisitions', 'queue' => 'Awaiting my action', 'mine' => auth()->user()->can('requisitions.create') ? 'My requisitions' : null]) as $k => $label)
            <li class="nav-item"><a class="nav-link {{ $scope === $k ? 'active' : '' }}" href="{{ route('requisitions.index', ['scope' => $k]) }}">{{ $label }}</a></li>
        @endforeach
    </ul>
@endif

<form class="filter-bar" method="GET">
    <input type="hidden" name="scope" value="{{ $scope }}">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1" for="q">Search</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="RIS no., purpose, requester"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="status">Status</label>
            <select id="status" name="status" class="form-select form-select-sm" data-autosubmit>
                <option value="">All</option>
                @foreach($statuses as $k => $l)<option value="{{ $k }}" @selected(($filters['status'] ?? '') === $k)>{{ $l }}</option>@endforeach
            </select></div>
        @if($canViewAll)
            <div class="col-6 col-md-2"><label class="form-label small mb-1" for="office">Office</label>
                <select id="office" name="office" class="form-select form-select-sm" data-autosubmit>
                    <option value="">All</option>
                    @foreach($offices as $o)<option value="{{ $o->id }}" @selected(($filters['office'] ?? '') == $o->id)>{{ $o->name }}</option>@endforeach
                </select></div>
        @endif
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="from">From</label><input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="to">To</label><input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-md-1"><button class="btn btn-sm btn-primary w-100" type="submit"><i class="bi bi-funnel"></i><span class="visually-hidden">Filter</span></button></div>
    </div>
</form>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>RIS No.</th><th>Date</th><th>Office</th><th>Requested by</th><th>Purpose</th><th class="num">Items</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse($requisitions as $r)
                <tr>
                    <td class="fw-semibold text-nowrap"><a href="{{ route('requisitions.show', $r) }}">{{ $r->ris_no }}</a></td>
                    <td class="text-nowrap">{{ $r->requisition_date->format('M d, Y') }}</td>
                    <td>{{ $r->office?->name }}</td>
                    <td>{{ $r->requester?->name }}</td>
                    <td class="small"><span class="d-inline-block text-truncate" style="max-width:260px">{{ $r->purpose }}</span></td>
                    <td class="num">{{ $r->items_count }}</td>
                    <td><x-ris-status :status="$r->status" /></td>
                    <td class="text-end text-nowrap">
                        @can('issue', $r)<a href="{{ route('requisitions.issue', $r) }}" class="btn btn-sm btn-teal">Issue</a>@endcan
                        <a href="{{ route('requisitions.show', $r) }}" class="btn btn-sm btn-light border">Open</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state icon="file-earmark-text" message="No requisitions found.">
                    @can('requisitions.create')<a href="{{ route('requisitions.create') }}" class="btn btn-sm btn-teal">Create a requisition</a>@endcan
                </x-empty-state></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($requisitions->hasPages())<div class="card-footer bg-white">{{ $requisitions->links() }}</div>@endif
</div>
@endsection
