{{-- Paper-form RIS, shared by print view and PDF (dompdf-compatible CSS only). --}}
@php
    $r = $requisition;
    $minRows = 12;
    $sig = fn ($user, $designation, $at, $step) => [$user?->name, $designation, $at?->format('m/d/Y'), $user && $at ? $r->signatureDataUri($step) : null];
    $rejected = $r->status === \App\Enums\RequisitionStatus::Rejected;
@endphp
<div class="doc">
    <table class="hdr-table">
        <tr><td class="doc-title" colspan="4">REQUISITION AND ISSUE SLIP</td></tr>
        <tr>
            <td class="lbl">Entity Name:</td><td class="line">{{ $r->entity_name }}</td>
            <td class="lbl">Fund Cluster:</td><td class="line">{{ $r->fund_cluster }}</td>
        </tr>
    </table>

    <table class="box-table" style="margin-top:6px">
        <tr>
            <td style="width:58%"><span class="lbl">Division:</span> {{ $r->division }}<br><span class="lbl">Office:</span> {{ $r->office?->name }}</td>
            <td><span class="lbl">Responsibility Center Code:</span> {{ $r->responsibility_center_code }}<br><span class="lbl">RIS No.:</span> {{ $r->ris_no }}
                &nbsp;&nbsp;<span class="lbl">Date:</span> {{ $r->requisition_date->format('m/d/Y') }}</td>
        </tr>
    </table>

    <table class="grid" style="margin-top:0">
        <thead>
        <tr>
            <th colspan="4">Requisition</th>
            <th colspan="2">Stock Available?</th>
            <th colspan="2">Issue</th>
        </tr>
        <tr>
            <th style="width:11%">Stock No.</th>
            <th style="width:8%">Unit</th>
            <th>Description</th>
            <th style="width:8%">Quantity</th>
            <th style="width:6%">Yes</th>
            <th style="width:6%">No</th>
            <th style="width:8%">Quantity</th>
            <th style="width:18%">Remarks</th>
        </tr>
        </thead>
        <tbody>
        @foreach($r->items as $line)
            @php $avail = $line->stock_available ?? ($line->item->current_balance >= $line->quantity_requested); @endphp
            <tr>
                <td>{{ $line->item->stock_number }}</td>
                <td class="center">{{ $line->item->unit }}</td>
                <td>{{ $line->item->name }}@if($line->item->description) — {{ $line->item->description }}@endif</td>
                <td class="num">{{ $line->quantity_requested }}</td>
                <td class="center">{{ $avail ? '✓' : '' }}</td>
                <td class="center">{{ $avail ? '' : '✓' }}</td>
                <td class="num">{{ $line->quantity_issued ?: '' }}</td>
                <td>{{ $line->remarks }}@if($line->quantity_approved !== null && $line->quantity_approved < $line->quantity_requested) Approved: {{ $line->quantity_approved }}@endif</td>
            </tr>
        @endforeach
        @for($i = $r->items->count(); $i < $minRows; $i++)
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
        @endfor
        <tr><td colspan="8"><span class="lbl" style="font-weight:bold">Purpose:</span> {{ $r->purpose }}</td></tr>
        </tbody>
    </table>

    @php
        $cols = [
            'Requested by:' => $sig($r->requester, $r->requested_by_designation, $r->submitted_at, 'requested'),
            'Approved by:' => $rejected ? [null, null, null, null] : $sig($r->approver, $r->approved_by_designation, $r->approved_at, 'approved'),
            'Issued by:' => $sig($r->issuer, $r->issued_by_designation, $r->issued_at, 'issued'),
            'Received by:' => $sig($r->receiver, $r->received_by_designation, $r->received_at, 'received'),
        ];
    @endphp
    <table class="sig">
        <tr><td class="rowlbl"></td>@foreach($cols as $label => $_)<td class="head">{{ $label }}</td>@endforeach</tr>
        <tr><td class="rowlbl" style="height:46px">Signature:</td>@foreach($cols as $c)<td class="center" style="height:46px">@if($c[3])<img src="{{ $c[3] }}" alt="Signature of {{ $c[0] }}" style="max-height:42px;max-width:150px">@elseif($c[0])<span class="muted" style="font-size:8px">Signed in system<br>(no e-signature on file)</span>@endif</td>@endforeach</tr>
        <tr><td class="rowlbl">Printed Name:</td>@foreach($cols as $c)<td class="center"><strong>{{ $c[0] }}</strong></td>@endforeach</tr>
        <tr><td class="rowlbl">Designation:</td>@foreach($cols as $c)<td class="center">{{ $c[1] }}</td>@endforeach</tr>
        <tr><td class="rowlbl">Date:</td>@foreach($cols as $c)<td class="center">{{ $c[2] }}</td>@endforeach</tr>
    </table>

    <table class="foot">
        <tr>
            <td>Status: <strong>{{ $r->status->label() }}</strong></td>
            <td class="right">Generated {{ now()->format('m/d/Y g:i A') }} by {{ $generatedBy->name }}</td>
        </tr>
    </table>
    <div class="watermark">Electronic signatures were drawn by each signatory in the system and applied from their own logged-in account at the time of the action; the date and time of each action are recorded in the audit log.</div>
</div>
