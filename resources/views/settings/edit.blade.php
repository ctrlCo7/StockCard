@extends('layouts.app')
@section('title', 'Settings')

@section('content')
<x-page-header title="Settings" subtitle="Organization details printed on Requisition and Issue Slips, Stock Cards and reports." />

<div class="row g-3">
    <div class="col-lg-7">
        <form method="POST" action="{{ route('settings.update') }}" class="card">
            @csrf @method('PUT')
            <div class="card-header"><h2 class="card-title">Organization</h2></div>
            <div class="card-body row g-3">
                <div class="col-12"><label class="form-label" for="entity_name">Entity name <span class="text-danger">*</span></label>
                    <input id="entity_name" name="entity_name" value="{{ old('entity_name', $settings['entity_name']) }}" class="form-control" required maxlength="150"></div>
                <div class="col-12"><label class="form-label" for="division">Default division <span class="text-danger">*</span></label>
                    <input id="division" name="division" value="{{ old('division', $settings['division']) }}" class="form-control" required maxlength="150">
                    <div class="form-text">Used on an RIS when the requesting office has no division set.</div></div>
                <div class="col-md-6"><label class="form-label" for="fund_cluster">Default fund cluster</label>
                    <input id="fund_cluster" name="fund_cluster" value="{{ old('fund_cluster', $settings['fund_cluster']) }}" class="form-control" maxlength="50"></div>
                <div class="col-md-6"><label class="form-label" for="responsibility_center_code">Default responsibility center code</label>
                    <input id="responsibility_center_code" name="responsibility_center_code" value="{{ old('responsibility_center_code', $settings['responsibility_center_code']) }}" class="form-control" maxlength="50"></div>
                <div class="col-md-6"><label class="form-label" for="stock_card_form_code">Stock Card form code</label>
                    <input id="stock_card_form_code" name="stock_card_form_code" value="{{ old('stock_card_form_code', $settings['stock_card_form_code']) }}" class="form-control" maxlength="50">
                    <div class="form-text">Printed under the “STOCK CARD” title. Leave blank to omit.</div></div>
                <div class="col-md-6"></div>
                <div class="col-md-8"><label class="form-label" for="system_title">System title <span class="text-danger">*</span></label>
                    <input id="system_title" name="system_title" value="{{ old('system_title', $settings['system_title']) }}" class="form-control" required maxlength="100"></div>
                <div class="col-md-4"><label class="form-label" for="items_per_page">Rows per page <span class="text-danger">*</span></label>
                    <input id="items_per_page" type="number" min="5" max="100" name="items_per_page" value="{{ old('items_per_page', $settings['items_per_page']) }}" class="form-control" required></div>
            </div>
            <div class="card-body border-top row g-3">
                <div class="col-12"><h3 class="h6 mb-0 text-primary"><i class="bi bi-envelope me-1"></i>Email notifications</h3></div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="notify_requisition_emails" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="notify_requisition_emails" name="notify_requisition_emails" value="1" @checked(old('notify_requisition_emails', $settings['notify_requisition_emails']) === '1')>
                        <label class="form-check-label" for="notify_requisition_emails">Requisition emails
                            <span class="d-block small text-muted">Administrators are emailed when an RIS is submitted; requesters are emailed when it is approved, returned, rejected, cancelled or issued.</span></label>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-check form-switch">
                        <input type="hidden" name="notify_low_stock_emails" value="0">
                        <input class="form-check-input" type="checkbox" role="switch" id="notify_low_stock_emails" name="notify_low_stock_emails" value="1" @checked(old('notify_low_stock_emails', $settings['notify_low_stock_emails']) === '1')>
                        <label class="form-check-label" for="notify_low_stock_emails">Low-stock emails
                            <span class="d-block small text-muted">Administrators are alerted when an item reaches its re-order point or runs out, plus a weekday summary at {{ config('app.low_stock_digest_time') }}.</span></label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label" for="notification_extra_emails">Also send to</label>
                    <textarea id="notification_extra_emails" name="notification_extra_emails" rows="2" class="form-control @error('notification_extra_emails') is-invalid @enderror" maxlength="1000" placeholder="supply.office@example.com, chief@example.com">{{ old('notification_extra_emails', $settings['notification_extra_emails']) }}</textarea>
                    @error('notification_extra_emails')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">Optional extra addresses (comma-separated) that receive new-requisition and low-stock emails, such as a shared office mailbox. Administrators always receive them at their account email.</div>
                </div>
            </div>
            <div class="card-footer bg-white"><button class="btn btn-primary" type="submit"><i class="bi bi-check-lg me-1"></i>Save settings</button></div>
        </form>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title">Reference data</h2></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><a href="{{ route('categories.index') }}"><i class="bi bi-tags me-2"></i>Inventory categories</a><span class="badge text-bg-light border">{{ $categories->count() }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><a href="{{ route('offices.index') }}"><i class="bi bi-building me-2"></i>Offices &amp; divisions</a><span class="badge text-bg-light border">{{ $offices->count() }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><a href="{{ route('users.index') }}"><i class="bi bi-people me-2"></i>Users &amp; roles</a></li>
            </ul>
        </div>
        <div class="card">
            <div class="card-header"><h2 class="card-title">System</h2></div>
            <div class="card-body small">
                <dl class="kv">
                    <dt>Timezone</dt><dd>{{ config('app.timezone') }} <span class="text-muted">(APP_TIMEZONE in .env)</span></dd>
                    <dt>Database</dt><dd>{{ config('database.default') }}</dd>
                    <dt>Environment</dt><dd>{{ app()->environment() }}</dd>
                    <dt>Mail driver</dt><dd>{{ config('mail.default') }}
                        @if(in_array(config('mail.default'), ['log', 'array'], true))
                            <span class="badge text-bg-warning">Emails are not delivered</span>
                            <div class="text-muted">Written to storage/logs/laravel.log. Set MAIL_MAILER=smtp in .env to send real email.</div>
                        @endif</dd>
                    <dt>Mail from</dt><dd>{{ config('mail.from.address') }}</dd>
                    <dt>Queue</dt><dd>{{ config('queue.default') }}@if(config('queue.default') !== 'sync') <span class="text-muted">(run php artisan queue:work)</span>@endif</dd>
                </dl>
            </div>
        </div>
    </div>
</div>
@endsection
