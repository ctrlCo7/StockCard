@extends('layouts.app')
@section('title', $requisition->ris_no)

@use('App\Enums\RequisitionStatus', 'S')
@php
    $r = $requisition;
    $showApproved = $r->items->contains(fn ($l) => $l->quantity_approved !== null);
@endphp

@section('content')
<x-page-header :title="'RIS '.$r->ris_no" :crumbs="['Requisitions' => route('requisitions.index')]">
    <a href="{{ route('requisitions.print', $r) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><i class="bi bi-printer me-1"></i>Print</a>
    <a href="{{ route('requisitions.pdf', $r) }}" class="btn btn-sm btn-light border"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</a>
    @if(auth()->user()->isAdmin() && ! $r->requester?->is(auth()->user()))
        <a href="{{ route('messages.create', ['requisition' => $r->id, 'to' => [$r->requested_by]]) }}" class="btn btn-sm btn-light border"><i class="bi bi-chat-dots me-1"></i>Message requester</a>
    @elseif($r->requester?->is(auth()->user()) && ! auth()->user()->isAdmin())
        <a href="{{ route('messages.create', ['requisition' => $r->id, 'to' => 'office']) }}" class="btn btn-sm btn-light border"><i class="bi bi-chat-dots me-1"></i>Message supply office</a>
    @endif
    @can('notifications.send')
        <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#emailRis"><i class="bi bi-envelope me-1"></i>Send email</button>
    @endcan
    @can('update', $r)
        <a href="{{ route('requisitions.edit', $r) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>
    @endcan
    @can('submit', $r)
        <form method="POST" action="{{ route('requisitions.submit', $r) }}" data-confirm="Submit {{ $r->ris_no }} for processing? You will not be able to edit it unless it is returned." data-confirm-title="Submit requisition" data-confirm-button="Submit">
            @csrf <button class="btn btn-sm btn-teal" type="submit"><i class="bi bi-send me-1"></i>Submit</button>
        </form>
    @endcan
    @can('issue', $r)
        <a href="{{ route('requisitions.issue', $r) }}" class="btn btn-sm btn-teal"><i class="bi bi-box-arrow-up me-1"></i>Issue stock</a>
    @endcan
    @can('cancel', $r)
        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal"><i class="bi bi-x-circle me-1"></i>Cancel</button>
    @endcan
</x-page-header>

<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <span class="text-muted small">Status:</span> <x-ris-status :status="$r->status" class="fs-6" />
    @if($r->status->isIssuable() || $r->status === S::FullyIssued)
        <span class="small text-muted ms-2">{{ $r->totalRemaining() }} unit(s) approved but not yet issued</span>
    @endif
</div>

{{-- Action panels --}}
@can('forward', $r)
    <form method="POST" action="{{ route('requisitions.forward', $r) }}" class="card border-info mb-3">
        @csrf
        <div class="card-header"><h2 class="card-title"><i class="bi bi-clipboard-check me-1"></i>Verify stock availability and forward for approval</h2></div>
        <div class="card-body">
            <p class="small text-muted">“Stock Available” will be recorded from current balances. Add remarks for any shortages.</p>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Item</th><th class="num">Requested</th><th class="num">On hand</th><th>Available</th><th>Remarks</th></tr></thead>
                    <tbody>
                    @foreach($r->items as $line)
                        @php $ok = $line->item->current_balance >= $line->quantity_requested; @endphp
                        <tr>
                            <td>{{ $line->item->name }}</td>
                            <td class="num">{{ $line->quantity_requested }}</td>
                            <td class="num">{{ $line->item->current_balance }}</td>
                            <td><span class="badge text-bg-{{ $ok ? 'success' : 'danger' }}">{{ $ok ? 'Yes' : 'No' }}</span></td>
                            <td><input name="line_remarks[{{ $line->id }}]" value="{{ old('line_remarks.'.$line->id, $line->remarks) }}" class="form-control form-control-sm" maxlength="255" aria-label="Remarks for {{ $line->item->name }}"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <label class="form-label small" for="fwdRemarks">Remarks to approving officer</label>
            <input id="fwdRemarks" name="remarks" class="form-control form-control-sm" maxlength="1000">
        </div>
        <div class="card-footer bg-white d-flex gap-2">
            <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-arrow-right-circle me-1"></i>Forward for approval</button>
            <button class="btn btn-sm btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#returnModal">Return to requester</button>
        </div>
    </form>
@endcan

@can('approve', $r)
    @if($r->status === S::PendingApproval)
        <form method="POST" action="{{ route('requisitions.approve', $r) }}" class="card border-primary mb-3"
              data-confirm="Approve {{ $r->ris_no }} with the quantities entered? Stock is deducted only when the Inventory Officer issues the items." data-confirm-title="Approve requisition" data-confirm-button="Approve">
            @csrf
            <div class="card-header"><h2 class="card-title"><i class="bi bi-patch-check me-1"></i>Review and approve</h2></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Item</th><th>Unit</th><th class="num">Requested</th><th class="num">On hand</th><th style="width:140px">Approve qty.</th></tr></thead>
                        <tbody>
                        @foreach($r->items as $line)
                            <tr>
                                <td>{{ $line->item->name }}@if($line->remarks)<div class="small text-muted">{{ $line->remarks }}</div>@endif</td>
                                <td>{{ $line->item->unit }}</td>
                                <td class="num">{{ $line->quantity_requested }}</td>
                                <td class="num">{{ $line->item->current_balance }}</td>
                                <td><input type="number" class="form-control form-control-sm" name="approved[{{ $line->id }}]" min="0" max="{{ $line->quantity_requested }}"
                                           value="{{ old('approved.'.$line->id, $line->quantity_requested) }}" required aria-label="Approved quantity for {{ $line->item->name }}"></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <label class="form-label small" for="approveRemarks">Remarks (optional)</label>
                <input id="approveRemarks" name="remarks" class="form-control form-control-sm" maxlength="1000">
            </div>
            <div class="card-footer bg-white d-flex flex-wrap gap-2">
                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-check2-circle me-1"></i>Approve</button>
                <button class="btn btn-sm btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#returnModal">Return for correction</button>
                <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
                <x-signature-notice class="ms-auto" />
            </div>
        </form>
    @elseif($r->status === S::Submitted && ! auth()->user()->can('forward', $r))
        <div class="alert alert-light border small mb-3 d-flex flex-wrap gap-2 align-items-center">
            <i class="bi bi-hourglass-split"></i> Awaiting stock verification by the Inventory Officer before approval.
            <button class="btn btn-sm btn-outline-warning ms-auto" type="button" data-bs-toggle="modal" data-bs-target="#returnModal">Return for correction</button>
            <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
        </div>
    @endif
@endcan

@can('acknowledge', $r)
    <form method="POST" action="{{ route('requisitions.acknowledge', $r) }}" class="card border-success mb-3"
          data-confirm="Confirm that you have received the issued items listed on this slip? This completes the requisition{{ $r->totalRemaining() > 0 ? ' and closes the remaining unissued quantity' : '' }}." data-confirm-title="Acknowledge receipt" data-confirm-variant="success" data-confirm-button="Acknowledge">
        @csrf
        <div class="card-body d-flex flex-wrap gap-2 align-items-center">
            <div class="me-auto"><strong><i class="bi bi-box2-heart me-1"></i>Items have been issued to you.</strong>
                <div class="small text-muted">Acknowledge receipt once you have physically received them.
                    @if($r->totalRemaining() > 0) {{ $r->totalRemaining() }} unit(s) remain unissued; acknowledging now closes the request with a partial issuance. @endif</div></div>
            <input name="remarks" class="form-control form-control-sm" style="max-width:260px" placeholder="Remarks (optional)" maxlength="1000" aria-label="Receipt remarks">
            <button class="btn btn-sm btn-success" type="submit"><i class="bi bi-check2-all me-1"></i>Acknowledge receipt</button>
            <x-signature-notice class="w-100 justify-content-end" />
        </div>
    </form>
@endcan

<div class="row g-3">
    <div class="col-xl-8">
        <div class="form-sheet mb-3">
            <div class="sheet-title"><h2>REQUISITION AND ISSUE SLIP</h2></div>
            <div class="sheet-grid">
                <div><div class="lbl">Entity Name</div><div class="val">{{ $r->entity_name }}</div></div>
                <div><div class="lbl">Fund Cluster</div><div class="val">{{ $r->fund_cluster ?: '—' }}</div></div>
                <div><div class="lbl">Division</div><div class="val">{{ $r->division ?: '—' }}</div></div>
                <div><div class="lbl">Responsibility Center Code</div><div class="val">{{ $r->responsibility_center_code ?: '—' }}</div></div>
                <div><div class="lbl">Office</div><div class="val">{{ $r->office?->name }}</div></div>
                <div><div class="lbl">RIS No.</div><div class="val">{{ $r->ris_no }}</div></div>
                <div><div class="lbl">Date</div><div class="val">{{ $r->requisition_date->format('F d, Y') }}</div></div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                    <tr><th colspan="4" class="text-center border-end">Requisition</th><th colspan="1" class="text-center border-end">Stock Available?</th><th colspan="{{ $showApproved ? 3 : 2 }}" class="text-center">Issue</th></tr>
                    <tr><th>Stock No.</th><th>Unit</th><th>Description</th><th class="num border-end">Qty.</th><th class="text-center border-end">Yes / No</th>
                        @if($showApproved)<th class="num">Approved</th>@endif<th class="num">Issued</th><th>Remarks</th></tr>
                    </thead>
                    <tbody>
                    @foreach($r->items as $line)
                        @php $avail = $line->stock_available ?? ($line->item->current_balance >= $line->quantity_requested); @endphp
                        <tr>
                            <td class="text-nowrap">{{ $line->item->stock_number }}</td>
                            <td>{{ $line->item->unit }}</td>
                            <td>{{ $line->item->name }}@if($line->item->description)<div class="small text-muted">{{ $line->item->description }}</div>@endif</td>
                            <td class="num border-end fw-semibold">{{ $line->quantity_requested }}</td>
                            <td class="text-center border-end">
                                <span class="badge text-bg-{{ $avail ? 'success' : 'danger' }}">{{ $avail ? 'Yes' : 'No' }}</span>
                                @if($line->stock_available === null)<div class="small text-muted" title="Live balance; recorded when verified">{{ $line->item->current_balance }} on hand</div>@endif
                            </td>
                            @if($showApproved)<td class="num">{{ $line->quantity_approved ?? '—' }}</td>@endif
                            <td class="num fw-semibold">{{ $line->quantity_issued ?: '—' }}
                                @if($line->quantity_approved && $line->remainingToIssue() > 0 && $r->status->isIssuable())
                                    <div class="small text-warning">{{ $line->remainingToIssue() }} remaining</div>
                                @endif</td>
                            <td class="small">{{ $line->remarks }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-top"><div class="small text-muted text-uppercase">Purpose</div><div>{{ $r->purpose }}</div></div>
        </div>

        <div class="row g-2 mb-3">
            @foreach([
                ['Requested by', $r->requester, $r->requested_by_designation, $r->submitted_at, 'Submitted', 'requested'],
                ['Approved by', $r->status === S::Rejected ? null : $r->approver, $r->approved_by_designation, $r->status === S::Rejected ? null : $r->approved_at, 'Approved', 'approved'],
                ['Issued by', $r->issuer, $r->issued_by_designation, $r->issued_at, 'Last issuance', 'issued'],
                ['Received by', $r->receiver, $r->received_by_designation, $r->received_at, 'Acknowledged', 'received'],
            ] as [$role, $person, $designation, $at, $verb, $step])
                <div class="col-sm-6 col-lg-3">
                    <div class="signature-box">
                        <div class="role">{{ $role }}</div>
                        @if($person && $at)
                            @if($signatureImage = $r->signatureDataUri($step))<img src="{{ $signatureImage }}" alt="Signature of {{ $person->name }}" class="signature-img">@endif
                            <div class="name">{{ $person->name }}</div>
                            <div class="meta">{{ $designation ?: '—' }}</div>
                            <div class="meta"><i class="bi bi-shield-check text-teal"></i> {{ $verb }} {{ $at->format('M d, Y g:i A') }}</div>
                        @else
                            <div class="meta fst-italic mt-1">Pending</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
        <p class="small text-muted"><i class="bi bi-info-circle me-1"></i>Signatures are applied from each signatory's own account when they act on the RIS, and every action is recorded in the audit log. @if(! auth()->user()->signature_path)<a href="{{ route('profile.edit') }}#signature">Add your e-signature</a> so it appears when you sign.@endif</p>
    </div>

    <div class="col-xl-4">
        @if($r->issuances->isNotEmpty())
            <div class="card table-card mb-3">
                <div class="card-header"><h2 class="card-title">Issuances</h2></div>
                <ul class="list-group list-group-flush">
                    @foreach($r->issuances as $iss)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                @can('issuances.view')<a href="{{ route('issuances.show', $iss) }}" class="fw-semibold">{{ $iss->issuance_no }}</a>@else<span class="fw-semibold">{{ $iss->issuance_no }}</span>@endcan
                                <span class="small text-muted">{{ $iss->issue_date->format('M d, Y') }}</span>
                            </div>
                            <div class="small">Issued by {{ $iss->issuer?->name }} to {{ $iss->received_by_name }}</div>
                            <ul class="small mb-0 ps-3">
                                @foreach($iss->items as $ii)<li>{{ $ii->quantity }} {{ $ii->item->unit }} — {{ $ii->item->name }}</li>@endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <div class="card-header"><h2 class="card-title">Workflow history</h2></div>
            <div class="card-body">
                <ul class="timeline">
                    @foreach($r->approvals as $event)
                        <li>
                            <div class="fw-semibold">{{ $event->to_status->label() }}</div>
                            <div class="when">{{ $event->created_at->format('M d, Y g:i A') }} · {{ $event->user->name }}</div>
                            @if($event->remarks)<div class="small mt-1">“{{ $event->remarks }}”</div>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>

@can('notifications.send')
    <x-email-modal id="emailRis" :action="route('requisitions.email', $r)" :title="'Email '.$r->ris_no"
                   :emails="$r->requester?->email"
                   description="Sends a summary of this requisition (status, purpose and the requested, approved and issued quantities) with a link to open it." />
@endcan

{{-- Remark modals --}}
@foreach([
    'returnModal' => ['requisitions.return', 'Return for correction', 'Explain what needs to be corrected.', 'warning', 'Return'],
    'rejectModal' => ['requisitions.reject', 'Reject requisition', 'State the reason for rejection.', 'danger', 'Reject'],
    'cancelModal' => ['requisitions.cancel', 'Cancel requisition', 'Reason for cancelling (optional).', 'danger', 'Cancel requisition'],
] as $id => [$route, $title, $help, $variant, $button])
    <div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route($route, $r) }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="{{ $id }}Title">{{ $title }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <label class="form-label" for="{{ $id }}Remarks">{{ $help }}</label>
                    <textarea id="{{ $id }}Remarks" name="remarks" class="form-control" rows="3" maxlength="1000" @if($id !== 'cancelModal') required minlength="5" @endif></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-{{ $variant }}">{{ $button }}</button>
                </div>
            </form>
        </div>
    </div>
@endforeach
@endsection
