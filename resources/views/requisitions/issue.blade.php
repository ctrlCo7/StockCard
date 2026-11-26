@extends('layouts.app')
@section('title', 'Issue — '.$requisition->ris_no)

@section('content')
<x-page-header :title="'Issue stock for '.$requisition->ris_no"
               :subtitle="'Requested by '.$requisition->requester?->name.' · '.$requisition->office?->name"
               :crumbs="['Requisitions' => route('requisitions.index'), $requisition->ris_no => route('requisitions.show', $requisition)]" />

<div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>
    Posting deducts the quantities below from stock and adds an issuance line referenced <strong>{{ $requisition->ris_no }}</strong> to each item's Stock Card.
    You may issue less than the approved quantity; the remainder can be issued later.</div>

<form method="POST" action="{{ route('requisitions.issue.store', $requisition) }}"
      data-confirm="Post this issuance? Stock balances will be reduced immediately." data-confirm-title="Post issuance" data-confirm-button="Post issuance">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

    <div class="card table-card mb-3">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Stock No.</th><th>Item</th><th>Unit</th><th class="num">Requested</th><th class="num">Approved</th><th class="num">Already issued</th><th class="num">Remaining</th><th class="num">On hand</th><th style="width:140px">Issue now</th></tr></thead>
                <tbody>
                @foreach($requisition->items as $line)
                    @php
                        $remaining = $line->remainingToIssue();
                        $suggest = min($remaining, $line->item->current_balance);
                    @endphp
                    <tr @class(['table-light text-muted' => $remaining === 0])>
                        <td>{{ $line->item->stock_number }}</td>
                        <td>{{ $line->item->name }}</td>
                        <td>{{ $line->item->unit }}</td>
                        <td class="num">{{ $line->quantity_requested }}</td>
                        <td class="num">{{ $line->quantity_approved }}</td>
                        <td class="num">{{ $line->quantity_issued }}</td>
                        <td class="num fw-semibold">{{ $remaining }}</td>
                        <td class="num">
                            {{ $line->item->current_balance }}
                            @if($line->item->current_balance < $remaining)<i class="bi bi-exclamation-triangle-fill text-warning" title="Insufficient stock for the full remaining quantity"></i>@endif
                        </td>
                        <td>
                            @if($remaining > 0)
                                <input type="number" class="form-control form-control-sm" name="quantities[{{ $line->id }}]" min="0" max="{{ min($remaining, $line->item->current_balance) }}"
                                       value="{{ old('quantities.'.$line->id, $suggest) }}" aria-label="Quantity to issue for {{ $line->item->name }}">
                            @else
                                <span class="small">Complete</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label" for="issue_date">Issue date <span class="text-danger">*</span></label>
                <input type="date" id="issue_date" name="issue_date" class="form-control" value="{{ old('issue_date', today()->toDateString()) }}"
                       min="{{ $requisition->requisition_date->toDateString() }}" max="{{ today()->toDateString() }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="received_by_user_id">Issued to (requesting personnel) <span class="text-danger">*</span></label>
                <select id="received_by_user_id" name="received_by_user_id" class="form-select @error('received_by_user_id') is-invalid @enderror" required>
                    @foreach($recipients as $person)
                        <option value="{{ $person->id }}" @selected((int) old('received_by_user_id', $requisition->requested_by) === $person->id)>
                            {{ $person->name }}{{ $person->office ? ' — '.$person->office->name : '' }}{{ $person->id === $requisition->requested_by ? ' (requester)' : '' }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Supplies are released only to requesting personnel. The requester confirms receipt in the system.</div>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="remarks">Remarks</label>
                <input id="remarks" name="remarks" class="form-control" value="{{ old('remarks') }}" maxlength="1000" placeholder="e.g. Partial issuance; balance to follow upon delivery">
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button class="btn btn-teal" type="submit" data-loading-text="Posting…"><i class="bi bi-box-arrow-up me-1"></i>Post issuance</button>
        <a class="btn btn-light border" href="{{ route('requisitions.show', $requisition) }}">Cancel</a>
        <x-signature-notice class="ms-auto" />
    </div>
</form>
@endsection
