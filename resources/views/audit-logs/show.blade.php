@extends('layouts.app')
@section('title', 'Audit entry #'.$log->id)

@section('content')
<x-page-header :title="'Audit entry #'.$log->id" :crumbs="['Audit Logs' => route('audit-logs.index')]" />

<div class="card mb-3" style="max-width:900px">
    <div class="card-body">
        <dl class="kv">
            <dt>Date / time</dt><dd>{{ $log->created_at->format('M d, Y g:i:s A') }}</dd>
            <dt>User</dt><dd>{{ $log->user?->name ?? 'System' }}</dd>
            <dt>Action</dt><dd><code>{{ $log->action }}</code></dd>
            <dt>Description</dt><dd>{{ $log->description }}</dd>
            <dt>Record</dt><dd>{{ $log->subjectLabel() }}</dd>
            <dt>IP address</dt><dd>{{ $log->ip_address ?? '—' }}</dd>
            <dt>User agent</dt><dd class="small">{{ $log->user_agent ?? '—' }}</dd>
        </dl>
    </div>
</div>
<div class="row g-3" style="max-width:930px">
    @foreach(['Before' => $log->old_values, 'After' => $log->new_values] as $label => $values)
        <div class="col-md-6">
            <div class="card h-100"><div class="card-header"><h2 class="card-title">{{ $label }}</h2></div>
                <div class="card-body"><pre class="small mb-0" style="white-space:pre-wrap">{{ $values ? json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—' }}</pre></div></div>
        </div>
    @endforeach
</div>
@endsection
