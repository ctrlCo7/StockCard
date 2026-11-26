@props(['label', 'value', 'icon', 'tone' => 'navy', 'href' => null])
<div class="card h-100">
    @if($href)
        <a href="{{ $href }}" class="stat-card">
    @else
        <div class="stat-card">
    @endif
        <span class="stat-icon tone-{{ $tone }}"><i class="bi bi-{{ $icon }}"></i></span>
        <span>
            <span class="stat-value d-block">{{ $value }}</span>
            <span class="stat-label">{{ $label }}</span>
        </span>
    @if($href)
        </a>
    @else
        </div>
    @endif
</div>
