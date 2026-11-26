@props(['status'])
<span {{ $attributes->merge(['class' => 'badge text-bg-'.$status->badge()]) }}>{{ $status->label() }}</span>
