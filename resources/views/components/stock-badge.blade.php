@props(['item'])
<span {{ $attributes->merge(['class' => 'badge text-bg-'.$item->stockStatusBadge()]) }}>{{ $item->stockStatusLabel() }}</span>
