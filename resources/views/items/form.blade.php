@extends('layouts.app')
@section('title', $item->exists ? 'Edit Item' : 'Add Item')

@section('content')
<x-page-header :title="$item->exists ? 'Edit '.$item->name : 'Add Inventory Item'" :crumbs="['Inventory Items' => route('items.index')]" />

<form method="POST" action="{{ $item->exists ? route('items.update', $item) : route('items.store') }}" class="row g-3">
    @csrf
    @if($item->exists) @method('PUT') @endif

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Item details</h2></div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="stock_number">Stock No. <span class="text-danger">*</span></label>
                    <input id="stock_number" name="stock_number" value="{{ old('stock_number', $item->stock_number) }}" class="form-control @error('stock_number') is-invalid @enderror" required maxlength="50">
                    @error('stock_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="name">Item name <span class="text-danger">*</span></label>
                    <input id="name" name="name" value="{{ old('name', $item->name) }}" class="form-control @error('name') is-invalid @enderror" required maxlength="150">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea id="description" name="description" rows="2" class="form-control @error('description') is-invalid @enderror" maxlength="2000">{{ old('description', $item->description) }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="form-select">
                        <option value="">— None —</option>
                        @foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $item->category_id) == $c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="unit">Unit of measurement <span class="text-danger">*</span></label>
                    <input id="unit" name="unit" list="unitList" value="{{ old('unit', $item->unit) }}" class="form-control @error('unit') is-invalid @enderror" required maxlength="30" placeholder="e.g. Ream, Box, Can">
                    <datalist id="unitList">@foreach($units as $u)<option value="{{ $u }}">@endforeach</datalist>
                    @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="reorder_point">Re-order point <span class="text-danger">*</span></label>
                    <input id="reorder_point" type="number" min="0" name="reorder_point" value="{{ old('reorder_point', $item->reorder_point) }}" class="form-control @error('reorder_point') is-invalid @enderror" required>
                    <div class="form-text">A low-stock warning appears when the balance reaches this level.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="office_id">Office</label>
                    <select id="office_id" name="office_id" class="form-select">
                        <option value="">— None —</option>
                        @foreach($offices as $o)<option value="{{ $o->id }}" @selected(old('office_id', $item->office_id) == $o->id)>{{ $o->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="location">Storage location</label>
                    <input id="location" name="location" value="{{ old('location', $item->location) }}" class="form-control" maxlength="150" placeholder="e.g. Supply Room, Cabinet B">
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        @if($item->exists)
            <div class="card">
                <div class="card-header"><h2 class="card-title">Stock balance</h2></div>
                <div class="card-body">
                    <div class="balance-hero mb-2">
                        <div><div class="figure">{{ number_format($item->current_balance) }}</div><div class="caption">{{ $item->unit }} on hand</div></div>
                    </div>
                    <p class="small text-muted mb-0"><i class="bi bi-lock me-1"></i>The balance cannot be edited directly. Record a stock receipt, an issuance or an authorized adjustment instead.</p>
                </div>
            </div>
        @else
            <div class="card">
                <div class="card-header"><h2 class="card-title">Opening balance</h2></div>
                <div class="card-body row g-3">
                    <div class="col-12">
                        <label class="form-label" for="opening_quantity">Initial stock quantity <span class="text-danger">*</span></label>
                        <input id="opening_quantity" type="number" min="0" name="opening_quantity" value="{{ old('opening_quantity', 0) }}" class="form-control @error('opening_quantity') is-invalid @enderror" required>
                        @error('opening_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="opening_date">As of date <span class="text-danger">*</span></label>
                        <input id="opening_date" type="date" name="opening_date" value="{{ old('opening_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control @error('opening_date') is-invalid @enderror" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="opening_reference">Reference</label>
                        <input id="opening_reference" name="opening_reference" value="{{ old('opening_reference') }}" class="form-control" maxlength="80" placeholder="Beginning Inventory">
                        <div class="form-text">Recorded as the first line of the item's Stock Card.</div>
                    </div>
                </div>
            </div>
        @endif
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>{{ $item->exists ? 'Save changes' : 'Create item' }}</button>
            <a class="btn btn-light border" href="{{ $item->exists ? route('items.show', $item) : route('items.index') }}">Cancel</a>
        </div>
    </div>
</form>
@endsection
