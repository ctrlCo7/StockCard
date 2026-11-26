{{-- Printable report body, shared by print view and PDF. --}}
<div class="doc">
    <table class="hdr-table">
        <tr><td class="entity">{{ $settings['entity_name'] }}<br><span class="sub">{{ $settings['division'] }}</span></td></tr>
        <tr><td class="doc-title" style="letter-spacing:1px;font-size:13px;padding-bottom:4px">{{ mb_strtoupper($title) }}</td></tr>
    </table>
    <div class="report-meta">
        @if(!empty($filters['from']) || !empty($filters['to']))
            Period: {{ !empty($filters['from']) ? \Illuminate\Support\Carbon::parse($filters['from'])->format('m/d/Y') : 'Beginning' }} –
            {{ !empty($filters['to']) ? \Illuminate\Support\Carbon::parse($filters['to'])->format('m/d/Y') : 'Present' }} ·
        @endif
        Generated {{ $generatedAt->format('m/d/Y g:i A') }} by {{ $generatedBy->name }}
    </div>
    @if($summary)
        <table class="summary"><tr>@foreach($summary as $label => $value)<td>{{ $label }}: <strong>{{ $value }}</strong></td>@endforeach</tr></table>
    @endif
    <table class="grid">
        <thead><tr>@foreach($columns as $k => $label)<th>{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr>@foreach($columns as $k => $label)<td class="{{ in_array($k, $numeric) ? 'num' : '' }}">{{ $row[$k] ?? '' }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ max(count($columns), 1) }}" class="center"><em>No records match the selected filters.</em></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
