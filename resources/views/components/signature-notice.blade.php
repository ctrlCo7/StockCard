@php $image = app(\App\Services\SignatureService::class)->dataUri(auth()->user()->signature_path); @endphp
<div {{ $attributes->merge(['class' => 'd-flex align-items-center gap-2 small']) }}>
    @if($image)
        <span class="text-muted"><i class="bi bi-pen me-1"></i>You will sign as</span>
        <img src="{{ $image }}" alt="Your signature" style="max-height:32px;max-width:130px;background:#fff;border:1px solid var(--sc-border);border-radius:4px;padding:2px 4px">
    @else
        <span class="text-warning-emphasis"><i class="bi bi-exclamation-triangle me-1"></i>You have no e-signature on file, so none will appear on the form.</span>
        <a href="{{ route('profile.edit') }}#signature">Add one</a>
    @endif
</div>
