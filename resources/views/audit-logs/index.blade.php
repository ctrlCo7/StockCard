@extends('layouts.app')
@section('title', 'Audit Logs')

@section('content')
<x-page-header title="Audit Logs" subtitle="Read-only record of stock postings, approvals, user management and other sensitive actions.">
    @can('reports.view')<a href="{{ route('reports.show', 'audit') }}" class="btn btn-sm btn-light border"><i class="bi bi-file-earmark-text me-1"></i>Audit report</a>@endcan
</x-page-header>

<form class="filter-bar" method="GET">
    <div class="row g-2 align-items-end">
        <div class="col-md-3"><label class="form-label small mb-1" for="q">Search description</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="user">User</label>
            <select id="user" name="user" class="form-select form-select-sm"><option value="">All</option>
                @foreach($users as $u)<option value="{{ $u->id }}" @selected(($filters['user'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="action">Action</label>
            <select id="action" name="action" class="form-select form-select-sm"><option value="">All</option>
                @foreach($actions as $a)<option @selected(($filters['action'] ?? '') === $a)>{{ $a }}</option>@endforeach
            </select></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="from">From</label><input type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-6 col-md-2"><label class="form-label small mb-1" for="to">To</label><input type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}" class="form-control form-control-sm"></div>
        <div class="col-md-1"><button class="btn btn-sm btn-primary w-100" type="submit"><i class="bi bi-funnel"></i><span class="visually-hidden">Filter</span></button></div>
    </div>
</form>

<div class="card table-card">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead><tr><th>Date / time</th><th>User</th><th>Action</th><th>Description</th><th>Record</th><th>IP</th><th></th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="text-nowrap small">{{ $log->created_at->format('M d, Y g:i:s A') }}</td>
                    <td>{{ $log->user?->name ?? 'System' }}</td>
                    <td><code class="small">{{ $log->action }}</code></td>
                    <td class="small">{{ $log->description }}</td>
                    <td class="small text-muted">{{ $log->subjectLabel() }}</td>
                    <td class="small text-muted">{{ $log->ip_address }}</td>
                    <td><a href="{{ route('audit-logs.show', $log) }}" class="btn btn-sm btn-light border">Details</a></td>
                </tr>
            @empty
                <tr><td colspan="7"><x-empty-state icon="shield-check" message="No audit entries match the filters." /></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())<div class="card-footer bg-white">{{ $logs->links() }}</div>@endif
</div>
@endsection
