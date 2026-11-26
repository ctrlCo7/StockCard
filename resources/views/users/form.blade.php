@extends('layouts.app')
@section('title', $user->exists ? 'Edit User' : 'Add User')

@section('content')
<x-page-header :title="$user->exists ? 'Edit '.$user->name : 'Add User'" :crumbs="['User Management' => route('users.index')]" />

<form method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" class="row g-3">
    @csrf
    @if($user->exists) @method('PUT') @endif
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Account</h2></div>
            <div class="card-body row g-3">
                <div class="col-md-6"><label class="form-label" for="name">Full name <span class="text-danger">*</span></label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" class="form-control" required maxlength="150"></div>
                <div class="col-md-6"><label class="form-label" for="email">Email <span class="text-danger">*</span></label>
                    <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" class="form-control @error('email') is-invalid @enderror" required maxlength="150" autocomplete="off"></div>
                <div class="col-md-6"><label class="form-label" for="designation">Designation</label>
                    <input id="designation" name="designation" value="{{ old('designation', $user->designation) }}" class="form-control" maxlength="150" placeholder="e.g. Supply Officer II"></div>
                <div class="col-md-6"><label class="form-label" for="office_id">Office</label>
                    <select id="office_id" name="office_id" class="form-select"><option value="">— None —</option>
                        @foreach($offices as $o)<option value="{{ $o->id }}" @selected(old('office_id', $user->office_id) == $o->id)>{{ $o->name }}</option>@endforeach
                    </select></div>
                <div class="col-md-6"><label class="form-label" for="password">{{ $user->exists ? 'New password' : 'Password' }} @unless($user->exists)<span class="text-danger">*</span>@endunless</label>
                    <input id="password" type="password" name="password" class="form-control @error('password') is-invalid @enderror" @required(! $user->exists) autocomplete="new-password">
                    <div class="form-text">At least 8 characters with letters and numbers.{{ $user->exists ? ' Leave blank to keep the current password.' : '' }}</div></div>
                <div class="col-md-6"><label class="form-label" for="password_confirmation">Confirm password</label>
                    <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" autocomplete="new-password"></div>
                <div class="col-12 form-check form-switch ms-2">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1" @checked(old('is_active', $user->is_active))>
                    <label class="form-check-label" for="is_active">Account is active</label>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Roles <span class="text-danger">*</span></h2></div>
            <div class="card-body">
                @php $selected = collect(old('roles', $user->exists ? $user->roles->pluck('id')->all() : []))->map(fn ($v) => (int) $v)->all(); @endphp
                @foreach($roles as $role)
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" id="role{{ $role->id }}" @checked(in_array($role->id, $selected))>
                        <label class="form-check-label" for="role{{ $role->id }}"><strong>{{ $role->label }}</strong><div class="small text-muted">{{ $role->description }}</div></label>
                    </div>
                @endforeach
                <p class="small text-muted mb-0">Supply-office staff get <strong>Administrator / Inventory Officer</strong>. Personnel from other departments get <strong>Requesting Personnel</strong>; they can only file and track their own requisitions.</p>
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Save user</button>
            <a href="{{ route('users.index') }}" class="btn btn-light border">Cancel</a>
        </div>
    </div>
</form>
@endsection
