<x-mail::message>
# Low-stock report

@if($sender)
Sent by {{ $sender->name }}{{ $sender->designation ? ', '.$sender->designation : '' }}.

@endif
@if(filled($note))
**Message:** {{ $note }}

@endif
As of {{ now()->format('F j, Y g:i A') }}, these items are at or below their re-order point:

<x-mail::table>
| Stock No. | Item | On hand | Re-order Pt. | Status |
|:--|:--|--:|--:|:--|
@foreach($items as $item)
| {{ $item->stock_number }} | {{ $item->name }} | {{ $item->current_balance }} {{ $item->unit }} | {{ $item->reorder_point }} | {{ $item->stockStatusLabel() }} |
@endforeach
</x-mail::table>

<x-mail::button :url="$reportUrl">
Open Low-Stock Report
</x-mail::button>

{{ config('app.name') }}
</x-mail::message>
