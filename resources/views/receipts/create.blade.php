@extends('layouts.app')
@section('title', 'Receive Stock')

@php
    $lines = old('lines', [['item_id' => $preselected?->id, 'quantity' => '', 'remarks' => '']]);
@endphp

@section('content')
<x-page-header title="Receive Stock" subtitle="Posting a receipt increases each item's balance and adds a line to its Stock Card." :crumbs="['Stock Receipts' => route('receipts.index')]" />

<form method="POST" action="{{ route('receipts.store') }}" enctype="multipart/form-data"
      data-confirm="Post this receipt? Stock balances will be increased immediately. Posted receipts can only be corrected by reversal." data-confirm-title="Post stock receipt" data-confirm-button="Post receipt">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">

    <div class="card mb-3">
        <div class="card-header"><h2 class="card-title">Receipt details</h2></div>
        <div class="card-body row g-3">
            <div class="col-md-3">
                <label class="form-label" for="date_received">Date received <span class="text-danger">*</span></label>
                <input type="date" id="date_received" name="date_received" value="{{ old('date_received', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control @error('date_received') is-invalid @enderror" required>
            </div>
            <div class="col-md-5">
                <label class="form-label" for="supplier">Supplier / source <span class="text-danger">*</span></label>
                <input id="supplier" name="supplier" value="{{ old('supplier') }}" class="form-control @error('supplier') is-invalid @enderror" required maxlength="150" placeholder="Supplier name, transferring office, etc.">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="received_by_name">Received by <span class="text-danger">*</span></label>
                <input id="received_by_name" name="received_by_name" value="{{ old('received_by_name', auth()->user()->name) }}" class="form-control" required maxlength="150">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="reference_type">Reference document <span class="text-danger">*</span></label>
                <select id="reference_type" name="reference_type" class="form-select" required>
                    @foreach($referenceTypes as $k => $label)<option value="{{ $k }}" @selected(old('reference_type', 'DR') === $k)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="reference_no">Reference no. <span class="text-danger">*</span></label>
                <input id="reference_no" name="reference_no" value="{{ old('reference_no') }}" class="form-control @error('reference_no') is-invalid @enderror" required maxlength="80" placeholder="e.g. 2026-0142">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="attachment">Supporting document</label>
                <input type="file" id="attachment" name="attachment" class="form-control @error('attachment') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png">
                <div class="form-text">PDF, JPG or PNG, up to 5 MB. Stored privately.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="remarks">Remarks</label>
                <textarea id="remarks" name="remarks" class="form-control" rows="2" maxlength="1000">{{ old('remarks') }}</textarea>
            </div>
        </div>
    </div>

    <div class="card mb-3" data-lines>
        <div class="card-header"><h2 class="card-title">Items received</h2>
            <button type="button" class="btn btn-sm btn-outline-primary" data-add-line><i class="bi bi-plus-lg me-1"></i>Add item</button></div>
        <div class="table-responsive">
            <table class="table line-table align-middle mb-0">
                <thead><tr><th style="min-width:280px">Item</th><th>Unit</th><th>On hand</th><th style="width:140px">Qty. received</th><th>Remarks</th><th></th></tr></thead>
                <tbody data-line-body>
                @foreach($lines as $i => $line)
                    @include('receipts._line', ['index' => $i, 'line' => $line])
                @endforeach
                </tbody>
            </table>
        </div>
        <template data-line-template>@include('receipts._line', ['index' => '__INDEX__', 'line' => []])</template>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-teal" data-loading-text="Posting…"><i class="bi bi-check2-circle me-1"></i>Post receipt</button>
        <a href="{{ route('receipts.index') }}" class="btn btn-light border">Cancel</a>
    </div>
</form>
@endsection
