@extends('layouts.app')
@section('title', $requisition->exists ? 'Edit '.$requisition->ris_no : 'New Requisition')

@section('content')
<x-page-header :title="$requisition->exists ? 'Edit '.$requisition->ris_no : 'New Requisition and Issue Slip'"
               subtitle="Stock is not deducted until the Inventory Officer posts the issuance."
               :crumbs="['Requisitions' => route('requisitions.index')]" />

@if($requisition->exists && $requisition->status === \App\Enums\RequisitionStatus::Returned)
    @php $returnNote = $requisition->approvals()->where('action', 'returned')->latest('id')->first(); @endphp
    <div class="alert alert-warning"><i class="bi bi-arrow-return-left me-1"></i><strong>Returned for correction</strong>
        @if($returnNote) by {{ $returnNote->user->name }}: “{{ $returnNote->remarks }}”@endif</div>
@endif

<form method="POST" action="{{ $requisition->exists ? route('requisitions.update', $requisition) : route('requisitions.store') }}">
    @csrf
    @if($requisition->exists) @method('PUT') @endif

    <div class="form-sheet mb-3">
        <div class="sheet-title"><h2>REQUISITION AND ISSUE SLIP</h2></div>
        <div class="sheet-grid">
            <div><div class="lbl">Entity Name</div><div class="val">{{ $requisition->entity_name ?: $settings['entity_name'] }}</div></div>
            <div><div class="lbl">Division</div><div class="val">{{ $requisition->division ?: $settings['division'] }}</div></div>
            <div><div class="lbl">RIS No.</div><div class="val">{{ $requisition->ris_no ?: 'Assigned on save' }}</div></div>
        </div>
        <div class="card-body row g-3">
            <div class="col-md-4">
                <label class="form-label" for="office_id">Office <span class="text-danger">*</span></label>
                <select id="office_id" name="office_id" class="form-select @error('office_id') is-invalid @enderror" required>
                    <option value="">Select office…</option>
                    @foreach($offices as $o)<option value="{{ $o->id }}" data-rcc="{{ $o->responsibility_center_code }}" @selected(old('office_id', $requisition->office_id) == $o->id)>{{ $o->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="requisition_date">Date <span class="text-danger">*</span></label>
                <input type="date" id="requisition_date" name="requisition_date" value="{{ old('requisition_date', $requisition->requisition_date?->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control" required>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="fund_cluster">Fund Cluster</label>
                <input id="fund_cluster" name="fund_cluster" value="{{ old('fund_cluster', $requisition->fund_cluster) }}" class="form-control" maxlength="50">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="responsibility_center_code">Responsibility Center Code</label>
                <input id="responsibility_center_code" name="responsibility_center_code" value="{{ old('responsibility_center_code', $requisition->responsibility_center_code) }}" class="form-control" maxlength="50">
            </div>
        </div>
    </div>

    <div class="card mb-3" data-lines>
        <div class="card-header"><h2 class="card-title">Requisition</h2>
            <button type="button" class="btn btn-sm btn-outline-primary" data-add-line><i class="bi bi-plus-lg me-1"></i>Add item</button></div>
        <div class="table-responsive">
            <table class="table line-table align-middle mb-0">
                <thead><tr><th style="min-width:300px">Stock No. / Description</th><th>Unit</th><th style="width:130px">Quantity</th><th>Stock Available?</th><th>Remarks</th><th></th></tr></thead>
                <tbody data-line-body>
                @foreach($lines as $i => $line)
                    @include('requisitions._line', ['index' => $i, 'line' => $line])
                @endforeach
                </tbody>
            </table>
        </div>
        <template data-line-template>@include('requisitions._line', ['index' => '__INDEX__', 'line' => []])</template>
        <div class="card-footer bg-white small text-muted">Availability shown is the current balance and is rechecked by the Inventory Officer before approval and again at issuance.</div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <label class="form-label" for="purpose">Purpose <span class="text-danger">*</span></label>
            <textarea id="purpose" name="purpose" rows="2" class="form-control @error('purpose') is-invalid @enderror" required minlength="5" maxlength="2000" placeholder="e.g. For use in the daily operations of the Assessment Section">{{ old('purpose', $requisition->purpose) }}</textarea>
        </div>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <button type="submit" name="action" value="submit" class="btn btn-teal"><i class="bi bi-send me-1"></i>Save &amp; submit</button>
        <button type="submit" name="action" value="draft" class="btn btn-outline-primary"><i class="bi bi-save me-1"></i>Save as draft</button>
        <a href="{{ $requisition->exists ? route('requisitions.show', $requisition) : route('requisitions.index') }}" class="btn btn-light border">Cancel</a>
        <x-signature-notice class="ms-auto" />
    </div>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('office_id').addEventListener('change', function () {
    var rcc = this.options[this.selectedIndex].dataset.rcc, input = document.getElementById('responsibility_center_code');
    if (rcc && !input.value) input.value = rcc;
});
</script>
@endpush
