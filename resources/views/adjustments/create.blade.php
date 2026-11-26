@extends('layouts.app')
@section('title', 'New Stock Adjustment')

@section('content')
<x-page-header title="New Stock Adjustment" :crumbs="['Stock Adjustments' => route('adjustments.index')]" />

<div class="row">
    <div class="col-xl-8">
        <div class="alert alert-info small"><i class="bi bi-info-circle me-1"></i>
            Adjustments never overwrite a balance. They post a new, clearly labelled Stock Card entry that is attributed to you and recorded in the audit log.
            To undo a specific wrong entry, use <strong>Reverse</strong> on the item's Stock Card instead.</div>
        <form method="POST" action="{{ route('adjustments.store') }}" class="card"
              data-confirm="Post this adjustment? It will change the item's balance and cannot be edited afterwards." data-confirm-title="Post stock adjustment" data-confirm-variant="warning" data-confirm-button="Post adjustment">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
            <div class="card-body row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="item_id">Item <span class="text-danger">*</span></label>
                    <select id="item_id" name="item_id" class="form-select @error('item_id') is-invalid @enderror" required>
                        <option value="">Select an item…</option>
                        @foreach($items as $it)
                            <option value="{{ $it->id }}" @selected(old('item_id', $selected) == $it->id)>{{ $it->stock_number }} — {{ $it->name }} ({{ $it->current_balance }} {{ $it->unit }} on hand)</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="adjustment_date">Date <span class="text-danger">*</span></label>
                    <input type="date" id="adjustment_date" name="adjustment_date" value="{{ old('adjustment_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <span class="form-label d-block">Direction <span class="text-danger">*</span></span>
                    <div class="btn-group w-100" role="group" aria-label="Direction">
                        <input type="radio" class="btn-check" name="direction" id="dirDec" value="decrease" @checked(old('direction', 'decrease') === 'decrease') required>
                        <label class="btn btn-outline-danger" for="dirDec"><i class="bi bi-dash-lg"></i> Decrease</label>
                        <input type="radio" class="btn-check" name="direction" id="dirInc" value="increase" @checked(old('direction') === 'increase')>
                        <label class="btn btn-outline-success" for="dirInc"><i class="bi bi-plus-lg"></i> Increase</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="quantity">Quantity <span class="text-danger">*</span></label>
                    <input type="number" min="1" id="quantity" name="quantity" value="{{ old('quantity') }}" class="form-control @error('quantity') is-invalid @enderror" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="reason">Reason <span class="text-danger">*</span></label>
                    <select id="reason" name="reason" class="form-select" required>
                        @foreach($reasons as $k => $l)<option value="{{ $k }}" @selected(old('reason') === $k)>{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="reference_no">Reference / authority <span class="text-danger">*</span></label>
                    <input id="reference_no" name="reference_no" value="{{ old('reference_no') }}" class="form-control" required maxlength="80" placeholder="e.g. Physical count memo no., incident report no.">
                </div>
                <div class="col-12">
                    <label class="form-label" for="remarks">Explanation <span class="text-danger">*</span></label>
                    <textarea id="remarks" name="remarks" rows="3" class="form-control @error('remarks') is-invalid @enderror" required minlength="5" maxlength="1000">{{ old('remarks') }}</textarea>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>Post adjustment</button>
                <a href="{{ route('adjustments.index') }}" class="btn btn-light border">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
