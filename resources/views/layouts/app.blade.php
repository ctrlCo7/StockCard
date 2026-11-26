<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ $appSettings['system_title'] }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" crossorigin="anonymous">
    <link href="{{ asset('css/app.css') }}?v=2" rel="stylesheet">
    @stack('styles')
</head>
<body>
<a class="visually-hidden-focusable position-absolute p-2 bg-white" href="#main">Skip to main content</a>

<aside class="app-sidebar" aria-label="Main navigation">
    <a href="{{ route('dashboard') }}" class="brand">
        <span class="brand-mark"><i class="bi bi-box-seam"></i></span>
        <span class="brand-text">Stock Card &amp; Inventory<small>{{ $appSettings['entity_name'] }}</small></span>
    </a>
    <nav class="nav flex-column">
        <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="nav-link {{ request()->routeIs('messages.*') ? 'active' : '' }}" href="{{ route('messages.index') }}">
            <i class="bi bi-chat-dots"></i> Messages
            @if($navUnreadMessages > 0)<span class="badge rounded-pill text-bg-danger" title="Unread conversations">{{ $navUnreadMessages }}</span>@endif
        </a>

        <div class="nav-section">Inventory</div>
        @can('items.view')
            <a class="nav-link {{ request()->routeIs('items.*') ? 'active' : '' }}" href="{{ route('items.index') }}"><i class="bi bi-box"></i> Inventory Items</a>
        @endcan
        @can('stockcards.view')
            <a class="nav-link {{ request()->routeIs('stock-cards.*') ? 'active' : '' }}" href="{{ route('stock-cards.index') }}"><i class="bi bi-journal-text"></i> Stock Cards</a>
        @endcan
        @can('stock.receive')
            <a class="nav-link {{ request()->routeIs('receipts.*') ? 'active' : '' }}" href="{{ route('receipts.index') }}"><i class="bi bi-box-arrow-in-down"></i> Stock Receipts</a>
        @endcan
        @can('stock.adjust')
            <a class="nav-link {{ request()->routeIs('adjustments.*') ? 'active' : '' }}" href="{{ route('adjustments.index') }}"><i class="bi bi-sliders"></i> Stock Adjustments</a>
        @endcan

        <div class="nav-section">Requisitions</div>
        <a class="nav-link {{ request()->routeIs('requisitions.*') ? 'active' : '' }}" href="{{ route('requisitions.index') }}">
            <i class="bi bi-file-earmark-text"></i> Requisitions &amp; Issue Slips
            @if($navQueueCount > 0)<span class="badge rounded-pill text-bg-warning" title="Awaiting your action">{{ $navQueueCount }}</span>@endif
        </a>
        @can('issuances.view')
            <a class="nav-link {{ request()->routeIs('issuances.*') ? 'active' : '' }}" href="{{ route('issuances.index') }}"><i class="bi bi-box-arrow-up"></i> Issuance History</a>
        @endcan
        @can('reports.view')
            <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}"><i class="bi bi-bar-chart-line"></i> Reports</a>
        @endcan

        @if(auth()->user()->can('users.manage') || auth()->user()->can('audit.view') || auth()->user()->can('settings.manage') || auth()->user()->can('masterdata.manage'))
            <div class="nav-section">Administration</div>
        @endif
        @can('users.manage')
            <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><i class="bi bi-people"></i> User Management</a>
        @endcan
        @can('masterdata.manage')
            <a class="nav-link {{ request()->routeIs('categories.*') ? 'active' : '' }}" href="{{ route('categories.index') }}"><i class="bi bi-tags"></i> Categories</a>
            <a class="nav-link {{ request()->routeIs('offices.*') ? 'active' : '' }}" href="{{ route('offices.index') }}"><i class="bi bi-building"></i> Offices &amp; Divisions</a>
        @endcan
        @can('audit.view')
            <a class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}" href="{{ route('audit-logs.index') }}"><i class="bi bi-shield-check"></i> Audit Logs</a>
        @endcan
        @can('settings.manage')
            <a class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" href="{{ route('settings.edit') }}"><i class="bi bi-gear"></i> Settings</a>
        @endcan

        <form method="POST" action="{{ route('logout') }}" class="mt-2">
            @csrf
            <button type="submit" class="nav-link w-100 border-0 bg-transparent text-start"><i class="bi bi-box-arrow-left"></i> Logout</button>
        </form>
    </nav>
    <div class="sidebar-foot">{{ $appSettings['division'] }}</div>
</aside>
<div class="sidebar-backdrop"></div>

<div class="app-main">
    <header class="app-topbar">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" type="button" data-sidebar-toggle aria-label="Toggle navigation"><i class="bi bi-list"></i></button>
        <form class="global-search" method="GET" action="{{ route('search') }}" role="search">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="search" name="q" class="form-control" placeholder="Search items, stock no., RIS no., receipts…" value="{{ request()->routeIs('search') ? request('q') : '' }}" aria-label="Global search" minlength="2">
            </div>
        </form>
        <a href="{{ route('messages.index') }}" class="ms-auto btn btn-sm btn-light border position-relative" title="Messages" aria-label="Messages{{ $navUnreadMessages ? ", {$navUnreadMessages} unread" : '' }}">
            <i class="bi bi-chat-dots"></i>
            @if($navUnreadMessages > 0)
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger">{{ $navUnreadMessages }}</span>
            @endif
        </a>
        <div class="dropdown">
            <button class="btn btn-sm btn-light border d-flex align-items-center gap-2" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i>
                <span class="d-none d-sm-inline text-start lh-sm">
                    <span class="d-block fw-semibold">{{ auth()->user()->name }}</span>
                    <small class="text-muted">{{ auth()->user()->roleLabels() }}</small>
                </span>
                <i class="bi bi-chevron-down small"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><span class="dropdown-item-text small text-muted">{{ auth()->user()->email }}</span></li>
                <li><a class="dropdown-item" href="{{ route('profile.edit') }}"><i class="bi bi-person me-2"></i>My Profile</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-left me-2"></i>Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </header>

    <main id="main" class="app-content">
        @if($errors->any())
            <div class="alert alert-danger d-flex gap-2" role="alert">
                <i class="bi bi-exclamation-octagon-fill"></i>
                <div>
                    <strong>Please correct the following:</strong>
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach
                    </ul>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <footer class="app-footer d-flex flex-wrap justify-content-between gap-2">
        <span>{{ $appSettings['system_title'] }} · {{ $appSettings['entity_name'] }}</span>
        <span>{{ now()->format('F j, Y g:i A') }} ({{ config('app.timezone') }})</span>
    </footer>
</div>

{{-- Flash toasts --}}
<div class="toast-container position-fixed top-0 end-0 p-3">
    @foreach(['success' => ['success', 'check-circle-fill'], 'warning' => ['warning', 'exclamation-triangle-fill'], 'error' => ['danger', 'x-octagon-fill']] as $key => [$tone, $icon])
        @if(session($key))
            <div class="toast align-items-center text-bg-{{ $tone }} border-0" role="{{ $key === 'success' ? 'status' : 'alert' }}" aria-live="polite" data-autoshow>
                <div class="d-flex">
                    <div class="toast-body"><i class="bi bi-{{ $icon }} me-2"></i>{{ session($key) }}</div>
                    <button type="button" class="btn-close {{ $tone === 'warning' ? '' : 'btn-close-white' }} me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        @endif
    @endforeach
</div>

{{-- Shared confirmation dialog (see public/js/app.js) --}}
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="confirmModalTitle">Please confirm</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body"><p class="mb-0"></p></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" data-confirm-ok>Confirm</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script src="{{ asset('js/app.js') }}?v=2"></script>
@stack('scripts')
</body>
</html>
