{{-- Paper-form Stock Card, shared by the print view and the PDF (dompdf-compatible CSS only).
     Mirrors the office's printed form; blank ruled lines fill the rest of the first page. --}}
@use('App\Enums\TransactionType')
@php
    $minRows = 32;
    $rowCount = $transactions->count() + ($broughtForward !== null ? 1 : 0);
@endphp
<div class="sc-doc">
    @include('stock-cards._header')

    <table class="sc-grid">
        <thead>
        <tr>
            <th rowspan="2" style="width:13%">Date</th>
            <th rowspan="2" style="width:16%">Reference</th>
            <th rowspan="2" style="width:27%">From Whom Received or<br>To Whom Issued</th>
            <th class="sc-big" style="width:14%">RECEIVED</th>
            <th class="sc-big" style="width:14%">ISSUED</th>
            <th class="sc-big" style="width:16%">BALANCE</th>
        </tr>
        <tr><th>Qty.</th><th>Qty.</th><th>Qty.</th></tr>
        </thead>
        <tbody>
        @if($broughtForward !== null)
            <tr>
                <td colspan="2" class="sc-asper">Balance forwarded</td>
                <td class="sc-sub">as of {{ \Illuminate\Support\Carbon::parse($filters['from'])->format('n-j-Y') }}</td>
                <td></td><td></td>
                <td class="q"><strong>{{ $broughtForward }}</strong></td>
            </tr>
        @endif
        @foreach($transactions as $t)
            @if($t->type === TransactionType::Opening)
                <tr>
                    <td colspan="2" class="sc-asper">As per Inventory <span class="sc-sub">({{ $t->transaction_date->format('n-j-Y') }})</span></td>
                    <td>{{ in_array($t->reference_no, ['Beginning Inventory', 'As per Inventory'], true) ? '' : $t->reference_no }}</td>
                    <td></td>
                    <td></td>
                    <td class="q">{{ $t->balance_after }}</td>
                </tr>
            @else
                <tr @class(['sc-in' => $t->type === TransactionType::Receipt])>
                    <td>{{ $t->transaction_date->format('n-j-Y') }}</td>
                    <td>{{ $t->reference_no }}</td>
                    <td>{{ $t->party }}@if($t->type === TransactionType::Reversal) <span class="sc-sub">(reversal)</span>@elseif($t->type === TransactionType::Adjustment) <span class="sc-sub">(adjustment)</span>@endif</td>
                    <td class="q">{{ $t->quantity_received ?: '' }}</td>
                    <td class="q">{{ $t->quantity_issued ?: '' }}</td>
                    <td class="q">{{ $t->balance_after }}</td>
                </tr>
            @endif
        @endforeach
        @for($i = $rowCount; $i < $minRows; $i++)
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>
        @endfor
        </tbody>
    </table>

    <table class="sc-foot">
        <tr>
            <td>
                Current balance: <strong>{{ number_format($item->current_balance) }} {{ $item->unit }}</strong>
                @if(!empty($filters['from']) || !empty($filters['to']))
                    · Period: {{ !empty($filters['from']) ? \Illuminate\Support\Carbon::parse($filters['from'])->format('n-j-Y') : 'beginning' }}
                    to {{ !empty($filters['to']) ? \Illuminate\Support\Carbon::parse($filters['to'])->format('n-j-Y') : 'present' }}
                @endif
            </td>
            <td style="text-align:right">Generated {{ now()->format('n-j-Y g:i A') }} by {{ $generatedBy->name }}</td>
        </tr>
    </table>
</div>
