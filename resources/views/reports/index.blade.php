@extends('layouts.app')
@section('title', 'Reports')

@php
    $icons = ['current-inventory' => 'boxes', 'stock-card' => 'journal-text', 'receipts' => 'box-arrow-in-down', 'issuances' => 'box-arrow-up',
        'low-stock' => 'exclamation-triangle', 'out-of-stock' => 'x-octagon', 'ris-register' => 'file-earmark-text', 'pending-requisitions' => 'hourglass-split',
        'stock-movement' => 'arrow-left-right', 'ledger' => 'list-columns', 'adjustments' => 'sliders', 'audit' => 'shield-check'];
@endphp

@section('content')
<x-page-header title="Reports" subtitle="Filter, print, export to PDF or download as CSV (opens in Excel)." />

<div class="row g-3">
    @foreach($reports as $key => [$title, $description])
        <div class="col-md-6 col-xl-4">
            <a href="{{ route('reports.show', $key) }}" class="card h-100 text-decoration-none text-reset">
                <div class="stat-card">
                    <span class="stat-icon tone-{{ in_array($key, ['low-stock']) ? 'amber' : (in_array($key, ['out-of-stock']) ? 'red' : 'navy') }}"><i class="bi bi-{{ $icons[$key] ?? 'file-text' }}"></i></span>
                    <span><span class="fw-semibold d-block">{{ $title }}</span><span class="stat-label">{{ $description }}</span></span>
                </div>
            </a>
        </div>
    @endforeach
</div>
@endsection
