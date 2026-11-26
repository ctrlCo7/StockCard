@props(['column', 'label', 'default' => 'name'])
@php
    $current = request('sort', $default);
    $dir = request('dir', 'asc');
    $active = $current === $column;
    $next = $active && $dir === 'asc' ? 'desc' : 'asc';
@endphp
<a class="sort-link" href="{{ request()->fullUrlWithQuery(['sort' => $column, 'dir' => $next, 'page' => null]) }}"
   @if($active) aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
    {{ $label }}
    @if($active)<i class="bi bi-caret-{{ $dir === 'asc' ? 'up' : 'down' }}-fill small"></i>@endif
</a>
