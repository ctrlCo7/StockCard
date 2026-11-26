@props(['title', 'subtitle' => null, 'crumbs' => []])
<div class="page-header">
    <div>
        @if($crumbs)
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    @foreach($crumbs as $label => $url)
                        <li class="breadcrumb-item"><a href="{{ $url }}">{{ $label }}</a></li>
                    @endforeach
                    <li class="breadcrumb-item active" aria-current="page">{{ $title }}</li>
                </ol>
            </nav>
        @endif
        <h1>{{ $title }}</h1>
        @if($subtitle)<div class="subtitle">{{ $subtitle }}</div>@endif
    </div>
    @if($slot->isNotEmpty())
        <div class="d-flex flex-wrap gap-2 no-print">{{ $slot }}</div>
    @endif
</div>
