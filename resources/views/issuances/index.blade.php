@extends('layouts.app')
@section('title', 'Issuance History')

@section('content')
<x-page-header title="Issuance History" subtitle="Every posted issuance against an approved Requisition and Issue Slip." />

<form class="filter-bar" method="GET">
    <div class="row g-2 align-items-end">
        <div class="col-md-6"><label class="form-label small mb-1" for="q">Search</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Issuance no., RIS no., recipient"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="from">From</label><input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="to">To</label><input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button></div>
    </div>
</form>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Issuance No.</th><th>Date</th><th>RIS No.</th><th>Office</th><th>Issued to</th><th class="num">Lines</th><th class="num">Total Qty.</th><th>Issued by</th></tr></thead>
            <tbody>
            @forelse($issuances as $i)
                <tr>
                    <td class="fw-semibold"><a href="{{ route('issuances.show', $i) }}">{{ $i->issuance_no }}</a></td>
                    <td>{{ $i->issue_date->format('M d, Y') }}</td>
                    <td><a href="{{ route('requisitions.show', $i->requisition) }}">{{ $i->requisition->ris_no }}</a></td>
                    <td>{{ $i->requisition->office?->name }}</td>
                    <td>{{ $i->received_by_name }}</td>
                    <td class="num">{{ $i->items_count }}</td>
                    <td class="num">{{ number_format($i->items_sum_quantity) }}</td>
                    <td class="small">{{ $i->issuer?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state icon="box-arrow-up" message="No issuances have been posted." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($issuances->hasPages())<div class="card-footer bg-white">{{ $issuances->links() }}</div>@endif
</div>
@endsection
