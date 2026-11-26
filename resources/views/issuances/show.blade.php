@extends('layouts.app')
@section('title', 'Issuance '.$issuance->issuance_no)

@section('content')
<x-page-header :title="'Issuance '.$issuance->issuance_no" :crumbs="['Issuance History' => route('issuances.index')]">
    <a href="{{ route('requisitions.show', $issuance->requisition) }}" class="btn btn-sm btn-primary"><i class="bi bi-file-earmark-text me-1"></i>Open RIS {{ $issuance->requisition->ris_no }}</a>
</x-page-header>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <dl class="kv">
                <dt>Issuance no.</dt><dd>{{ $issuance->issuance_no }}</dd>
                <dt>Issue date</dt><dd>{{ $issuance->issue_date->format('M d, Y') }}</dd>
                <dt>RIS no.</dt><dd>{{ $issuance->requisition->ris_no }}</dd>
                <dt>Office</dt><dd>{{ $issuance->requisition->office?->name }}</dd>
                <dt>Issued by</dt><dd>{{ $issuance->issuer?->name }}</dd>
                <dt>Issued to</dt><dd>{{ $issuance->received_by_name }}</dd>
                <dt>Posted</dt><dd>{{ $issuance->created_at->format('M d, Y g:i A') }}</dd>
                <dt>Remarks</dt><dd>{{ $issuance->remarks ?: '—' }}</dd>
            </dl>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="card table-card">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Stock No.</th><th>Item</th><th>Unit</th><th class="num">Qty. issued</th><th class="num">Balance after</th><th></th></tr></thead>
                    <tbody>
                    @foreach($issuance->items as $line)
                        <tr>
                            <td>{{ $line->item->stock_number }}</td>
                            <td>{{ $line->item->name }}</td>
                            <td>{{ $line->item->unit }}</td>
                            <td class="num fw-semibold">{{ $line->quantity }}</td>
                            <td class="num">{{ $line->transaction?->balance_after }}</td>
                            <td class="text-end">@can('stockcards.view')<a href="{{ route('stock-cards.show', $line->item) }}" class="btn btn-sm btn-light border">Stock Card</a>@endcan</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
