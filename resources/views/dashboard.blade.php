@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
<x-page-header title="Dashboard" :subtitle="'Welcome, '.auth()->user()->name.' — '.now()->format('l, F j, Y')">
    @can('requisitions.create')
        <a href="{{ route('requisitions.create') }}" class="btn btn-teal btn-sm"><i class="bi bi-plus-lg me-1"></i>New Requisition</a>
    @endcan
    @can('stock.receive')
        <a href="{{ route('receipts.create') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-in-down me-1"></i>Receive Stock</a>
    @endcan
</x-page-header>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3"><x-stat-card label="Active inventory items" :value="number_format($stats['active_items'])" icon="box" tone="navy" :href="auth()->user()->can('items.view') ? route('items.index') : null" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Total units in stock" :value="number_format($stats['units_in_stock'])" icon="stack" tone="teal" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Items at / below reorder point" :value="number_format($stats['low_stock'])" icon="exclamation-triangle" tone="amber" :href="auth()->user()->can('items.view') ? route('items.index', ['stock' => 'low']) : null" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Out-of-stock items" :value="number_format($stats['out_of_stock'])" icon="x-octagon" tone="red" :href="auth()->user()->can('items.view') ? route('items.index', ['stock' => 'out']) : null" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Pending requisitions" :value="number_format($stats['pending_requisitions'])" icon="hourglass-split" tone="slate" :href="auth()->user()->can('requisitions.view-all') ? route('requisitions.index', ['scope' => 'all', 'status' => 'pending_approval']) : null" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Requisitions awaiting issuance" :value="number_format($stats['awaiting_issuance'])" icon="truck" tone="navy" :href="auth()->user()->can('requisitions.issue') ? route('requisitions.index', ['scope' => 'queue']) : null" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Stock receipts (last 30 days)" :value="number_format($stats['recent_receipts'])" icon="box-arrow-in-down" tone="green" /></div>
    <div class="col-6 col-xl-3"><x-stat-card label="Stock issuances (last 30 days)" :value="number_format($stats['recent_issuances'])" icon="box-arrow-up" tone="teal" /></div>
</div>

<div class="row g-3">
    @if($canSeeStock)
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header flex-wrap">
                    <h2 class="card-title">Stock movement</h2>
                    <form class="d-flex gap-2 align-items-center" id="trendForm">
                        <label class="visually-hidden" for="trendFrom">From</label>
                        <input type="date" class="form-control form-control-sm" id="trendFrom" value="{{ today()->subDays(29)->toDateString() }}">
                        <span class="text-muted small">to</span>
                        <label class="visually-hidden" for="trendTo">To</label>
                        <input type="date" class="form-control form-control-sm" id="trendTo" value="{{ today()->toDateString() }}">
                        <button class="btn btn-sm btn-outline-primary" type="submit">Apply</button>
                    </form>
                </div>
                <div class="card-body">
                    <div style="height:260px"><canvas id="trendChart" aria-label="Units received and issued per day" role="img"></canvas></div>
                </div>
            </div>

            <div class="card table-card">
                <div class="card-header"><h2 class="card-title">Recent transactions</h2>
                    <a href="{{ route('reports.show', 'ledger') }}" class="small">View all</a></div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle">
                        <thead><tr><th>Date</th><th>Item</th><th>Type</th><th>Reference</th><th class="num">Received</th><th class="num">Issued</th><th class="num">Balance</th></tr></thead>
                        <tbody>
                        @forelse($recentTransactions as $t)
                            <tr>
                                <td class="text-nowrap">{{ $t->transaction_date->format('M d, Y') }}</td>
                                <td><a href="{{ route('stock-cards.show', $t->item) }}">{{ $t->item->name }}</a><div class="small text-muted">{{ $t->item->stock_number }}</div></td>
                                <td><span class="badge text-bg-{{ $t->type->badge() }}">{{ $t->type->label() }}</span></td>
                                <td class="small">{{ $t->reference_no }}</td>
                                <td class="num">{{ $t->quantity_received ?: '' }}</td>
                                <td class="num">{{ $t->quantity_issued ?: '' }}</td>
                                <td class="num fw-semibold">{{ $t->balance_after }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-empty-state icon="journal" message="No stock transactions have been posted yet." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="{{ $canSeeStock ? 'col-xl-4' : 'col-xl-6' }}">
        <div class="card table-card mb-3">
            <div class="card-header"><h2 class="card-title"><i class="bi bi-exclamation-triangle text-warning me-1"></i>Low-stock alerts</h2>
                <span class="d-flex gap-2 align-items-center">
                    @if($lowStockItems->isNotEmpty())
                        @can('notifications.send')<button type="button" class="btn btn-sm btn-light border py-0" data-bs-toggle="modal" data-bs-target="#emailLowStock"><i class="bi bi-envelope me-1"></i>Email</button>@endcan
                    @endif
                    @can('reports.view')<a href="{{ route('reports.show', 'low-stock') }}" class="small">Report</a>@endcan
                </span></div>
            <ul class="list-group list-group-flush">
                @forelse($lowStockItems as $item)
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                        <div>
                            <a href="{{ auth()->user()->can('stockcards.view') ? route('stock-cards.show', $item) : route('items.show', $item) }}" class="fw-semibold">{{ $item->name }}</a>
                            <div class="small text-muted">{{ $item->stock_number }} · reorder at {{ $item->reorder_point }} {{ $item->unit }}</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">{{ $item->current_balance }}</div>
                            <x-stock-badge :item="$item" />
                        </div>
                    </li>
                @empty
                    <li class="list-group-item"><x-empty-state icon="check2-circle" message="All items are above their reorder points." /></li>
                @endforelse
            </ul>
        </div>
        @if($lowStockItems->isNotEmpty())
            @can('notifications.send')
                <x-email-modal id="emailLowStock" :action="route('notifications.low-stock')" title="Email low-stock report" :emails="implode(', ', $adminEmails)"
                               description="Sends the current list of every item at or below its re-order point, with a link to the Low-Stock Report." />
            @endcan
        @endif
    </div>

    <div class="{{ $canSeeStock ? 'col-xl-4' : 'col-xl-6' }}">
        <div class="card table-card">
            @php $filer = auth()->user()->can('requisitions.create'); @endphp
            <div class="card-header"><h2 class="card-title">{{ $filer ? 'My recent requisitions' : 'Requisitions awaiting action' }}</h2>
                <a href="{{ route('requisitions.index', ['scope' => $filer ? 'mine' : 'queue']) }}" class="small">View all</a></div>
            <ul class="list-group list-group-flush">
                @forelse($myRequisitions as $r)
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div><a href="{{ route('requisitions.show', $r) }}" class="fw-semibold">{{ $r->ris_no }}</a>
                            <div class="small text-muted">{{ $r->requisition_date->format('M d, Y') }}@unless($filer) · {{ $r->requester?->name }}@endunless</div></div>
                        <x-ris-status :status="$r->status" />
                    </li>
                @empty
                    <li class="list-group-item"><x-empty-state icon="file-earmark" :message="$filer ? 'You have not filed any requisitions.' : 'Nothing is waiting for approval or issuance.'" /></li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection

@if($canSeeStock)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function () {
    var chart;
    var url = @json(route('dashboard.trend'));
    function load() {
        var from = document.getElementById('trendFrom').value, to = document.getElementById('trendTo').value;
        fetch(url + '?' + new URLSearchParams({ from: from, to: to }), { headers: { 'Accept': 'application/json' } })
            .then(function (r) { if (!r.ok) throw new Error('Unable to load chart data'); return r.json(); })
            .then(function (d) {
                var data = {
                    labels: d.labels,
                    datasets: [
                        { label: 'Received', data: d.received, backgroundColor: '#2a7f7a', borderRadius: 3 },
                        { label: 'Issued', data: d.issued, backgroundColor: '#1b2f4e', borderRadius: 3 }
                    ]
                };
                if (chart) { chart.data = data; chart.update(); return; }
                chart = new Chart(document.getElementById('trendChart'), {
                    type: 'bar', data: data,
                    options: { maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { grid: { display: false } } },
                        plugins: { legend: { position: 'bottom' } } }
                });
            })
            .catch(function (e) { document.getElementById('trendChart').parentElement.innerHTML = '<div class="text-danger small">' + e.message + '</div>'; });
    }
    document.getElementById('trendForm').addEventListener('submit', function (e) { e.preventDefault(); load(); });
    load();
})();
</script>
@endpush
@endif
