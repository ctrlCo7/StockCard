@extends('layouts.app')
@section('title', 'User Management')

@section('content')
<x-page-header title="User Management" subtitle="Accounts, roles and office assignments.">
    <a href="{{ route('users.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-person-plus me-1"></i>Add User</a>
</x-page-header>

<form class="filter-bar" method="GET">
    <div class="row g-2 align-items-end">
        <div class="col-md-6"><label class="form-label small mb-1" for="q">Search</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Name or email"></div>
        <div class="col-md-4"><label class="form-label small mb-1" for="role">Role</label>
            <select id="role" name="role" class="form-select form-select-sm" data-autosubmit><option value="">All roles</option>
                @foreach($roles as $r)<option value="{{ $r->name }}" @selected(($filters['role'] ?? '') === $r->name)>{{ $r->label }}</option>@endforeach
            </select></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100" type="submit"><i class="bi bi-search me-1"></i>Search</button></div>
    </div>
</form>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Name</th><th>Email</th><th>Designation</th><th>Office</th><th>Roles</th><th>Status</th><th>Last login</th><th></th></tr></thead>
            <tbody>
            @forelse($users as $u)
                <tr>
                    <td class="fw-semibold">{{ $u->name }}</td>
                    <td>{{ $u->email }}</td>
                    <td>{{ $u->designation ?: '—' }}</td>
                    <td>{{ $u->office?->name ?? '—' }}</td>
                    <td>@foreach($u->roles as $role)<span class="badge text-bg-light border me-1">{{ $role->label }}</span>@endforeach</td>
                    <td><span class="badge text-bg-{{ $u->is_active ? 'success' : 'secondary' }}">{{ $u->is_active ? 'Active' : 'Inactive' }}</span></td>
                    <td class="small">{{ $u->last_login_at?->format('M d, Y g:i A') ?? 'Never' }}</td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('users.edit', $u) }}" class="btn btn-sm btn-light border">Edit</a>
                        @unless($u->is(auth()->user()))
                            <form method="POST" action="{{ route('users.toggle', $u) }}" class="d-inline" data-confirm="{{ $u->is_active ? 'Deactivate' : 'Activate' }} {{ $u->name }}?" data-confirm-variant="{{ $u->is_active ? 'danger' : 'success' }}" data-confirm-button="{{ $u->is_active ? 'Deactivate' : 'Activate' }}">
                                @csrf @method('PATCH')
                                <button class="btn btn-sm btn-outline-{{ $u->is_active ? 'danger' : 'success' }}" type="submit">{{ $u->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-empty-state icon="people" message="No users found." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())<div class="card-footer bg-white">{{ $users->links() }}</div>@endif
</div>
@endsection
