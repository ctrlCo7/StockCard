{{-- Paper-form Stock Card header: title, Office/Location, Item | Description | Unit | Stock No. / Re-order Point.
     Column widths line up with the transaction grid (13/16/27/14/14/16 %). --}}
@php
    $cfg = $settings ?? $appSettings;
    $officeName = $item->office?->division ?: ($item->office?->name ?: $cfg['division']);
    $locationName = $item->location ?: $item->office?->location;
@endphp
<table class="sc-head">
    {{-- Invisible sizing row: fixes column widths so the boxes align with the grid below. --}}
    <tr class="sc-sizer"><td style="width:13%"></td><td style="width:16%"></td><td style="width:27%"></td><td style="width:14%"></td><td style="width:30%"></td></tr>
    <tr>
        <td colspan="5" class="sc-title-cell">
            <div class="sc-title">STOCK CARD</div>
            @if(filled($cfg['stock_card_form_code'] ?? null))<div class="sc-code">{{ $cfg['stock_card_form_code'] }}</div>@endif
        </td>
    </tr>
    <tr>
        <td colspan="5" class="sc-office">
            <div><span class="sc-lbl">Office:</span><span class="sc-val">{{ $officeName }}</span></div>
            <div><span class="sc-lbl">Location:</span><span class="sc-val">{{ $locationName }}</span></div>
        </td>
    </tr>
    <tr>
        <td class="sc-block sc-last" colspan="2"><span class="sc-lbl">Item:</span><span class="sc-val">{{ $item->name }}</span></td>
        <td class="sc-block sc-last"><span class="sc-lbl">Description:</span><span class="sc-val">{{ $item->description }}</span></td>
        <td class="sc-block sc-last"><span class="sc-lbl">Unit:</span><span class="sc-val">{{ $item->unit }}</span></td>
        <td class="sc-stockcell sc-last">
            <div><span class="sc-lbl">Stock No.</span><span class="sc-val">{{ $item->stock_number }}</span></div>
            <div class="sc-rule"><span class="sc-lbl">Re-order Point:</span><span class="sc-val">{{ $item->reorder_point }}</span></div>
        </td>
    </tr>
</table>
