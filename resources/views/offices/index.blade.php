@extends('layouts.app')
@section('title', 'Offices & Divisions')

@section('content')
<x-page-header title="Offices &amp; Divisions" subtitle="Requesting offices, their divisions and responsibility center codes." />

<div class="row g-3">
    <div class="col-xl-4">
        <form method="POST" action="{{ route('offices.store') }}" class="card">
            @csrf
            <div class="card-header"><h2 class="card-title">Register office</h2></div>
            <div class="card-body row g-2">
                <div class="col-5"><label class="form-label" for="code">Code <span class="text-danger">*</span></label><input id="code" name="code" value="{{ old('code') }}" class="form-control" required maxlength="30"></div>
                <div class="col-7"><label class="form-label" for="oname">Name <span class="text-danger">*</span></label><input id="oname" name="name" value="{{ old('name') }}" class="form-control" required maxlength="150"></div>
                <div class="col-12"><label class="form-label" for="division">Division</label><input id="division" name="division" value="{{ old('division', $appSettings['division']) }}" class="form-control" maxlength="150"></div>
                <div class="col-6"><label class="form-label" for="rcc">Resp. center code</label><input id="rcc" name="responsibility_center_code" value="{{ old('responsibility_center_code') }}" class="form-control" maxlength="50"></div>
                <div class="col-6"><label class="form-label" for="location">Location</label><input id="location" name="location" value="{{ old('location') }}" class="form-control" maxlength="150"></div>
                <input type="hidden" name="is_active" value="1">
            </div>
            <div class="card-footer bg-white"><button class="btn btn-primary btn-sm" type="submit"><i class="bi bi-plus-lg me-1"></i>Add office</button></div>
        </form>
    </div>
    <div class="col-xl-8">
        <div class="card table-card">
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Code</th><th>Name / Division</th><th>RCC</th><th>Location</th><th class="num">Users</th><th class="num">RIS</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @forelse($offices as $o)
                        <tr>
                            <td class="fw-semibold">{{ $o->code }}</td>
                            <td>{{ $o->name }}<div class="small text-muted">{{ $o->division }}</div></td>
                            <td>{{ $o->responsibility_center_code ?: '—' }}</td>
                            <td>{{ $o->location ?: '—' }}</td>
                            <td class="num">{{ $o->users_count }}</td>
                            <td class="num">{{ $o->requisitions_count }}</td>
                            <td><span class="badge text-bg-{{ $o->is_active ? 'success' : 'secondary' }}">{{ $o->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-end text-nowrap">
                                <button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#office{{ $o->id }}">Edit</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8"><x-empty-state icon="building" message="No offices registered." /></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@foreach($offices as $o)
    <div class="modal fade" id="office{{ $o->id }}" tabindex="-1" aria-labelledby="office{{ $o->id }}Title" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="{{ route('offices.update', $o) }}">
                    @csrf @method('PUT')
                    <div class="modal-header"><h5 class="modal-title" id="office{{ $o->id }}Title">Edit {{ $o->code }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                    <div class="modal-body row g-2">
                        <div class="col-5"><label class="form-label">Code<input name="code" value="{{ $o->code }}" class="form-control" required maxlength="30"></label></div>
                        <div class="col-7"><label class="form-label w-100">Name<input name="name" value="{{ $o->name }}" class="form-control" required maxlength="150"></label></div>
                        <div class="col-12"><label class="form-label w-100">Division<input name="division" value="{{ $o->division }}" class="form-control" maxlength="150"></label></div>
                        <div class="col-6"><label class="form-label w-100">Resp. center code<input name="responsibility_center_code" value="{{ $o->responsibility_center_code }}" class="form-control" maxlength="50"></label></div>
                        <div class="col-6"><label class="form-label w-100">Location<input name="location" value="{{ $o->location }}" class="form-control" maxlength="150"></label></div>
                        <div class="col-12 form-check form-switch ms-2"><input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="act{{ $o->id }}" @checked($o->is_active)><label class="form-check-label" for="act{{ $o->id }}">Active</label></div>
                    </div>
                    <div class="modal-footer"><button class="btn btn-primary" type="submit">Save</button></div>
                </form>
                <form method="POST" action="{{ route('offices.destroy', $o) }}" class="px-3 pb-3 text-end" data-confirm="Remove office {{ $o->code }}? If it is referenced by records it will be deactivated instead." data-confirm-variant="danger" data-confirm-button="Remove">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-link text-danger" type="submit">Remove office</button>
                </form>
            </div>
        </div>
    </div>
@endforeach
@endsection
