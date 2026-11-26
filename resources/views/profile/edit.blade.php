@extends('layouts.app')
@section('title', 'My Profile')

@section('content')
<x-page-header title="My Profile" />

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card"><div class="card-body">
            <dl class="kv">
                <dt>Name</dt><dd>{{ $user->name }}</dd>
                <dt>Email</dt><dd>{{ $user->email }}</dd>
                <dt>Designation</dt><dd>{{ $user->designation ?: '—' }}</dd>
                <dt>Office</dt><dd>{{ $user->office?->name ?? '—' }}</dd>
                <dt>Roles</dt><dd>{{ $user->roleLabels() }}</dd>
                <dt>Last login</dt><dd>{{ $user->last_login_at?->format('M d, Y g:i A') ?? '—' }}</dd>
            </dl>
            <p class="small text-muted mb-0 mt-2">Contact an administrator to change your name, designation, office or roles.</p>
        </div></div>
    </div>
    <div class="col-lg-5">
        <form method="POST" action="{{ route('profile.password') }}" class="card">
            @csrf @method('PUT')
            <div class="card-header"><h2 class="card-title">Change password</h2></div>
            <div class="card-body">
                <label class="form-label" for="current_password">Current password</label>
                <input id="current_password" type="password" name="current_password" class="form-control mb-2" required autocomplete="current-password">
                <label class="form-label" for="password">New password</label>
                <input id="password" type="password" name="password" class="form-control mb-2" required autocomplete="new-password">
                <label class="form-label" for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
            </div>
            <div class="card-footer bg-white"><button class="btn btn-primary btn-sm" type="submit">Update password</button></div>
        </form>
    </div>

    <div class="col-lg-10" id="signature">
        <div class="card">
            <div class="card-header"><h2 class="card-title"><i class="bi bi-pen me-1"></i>Electronic signature</h2>
                @if($user->signature_updated_at)<span class="small text-muted">Last updated {{ $user->signature_updated_at->format('M d, Y g:i A') }}</span>@endif</div>
            <div class="card-body">
                <p class="small text-muted">Your signature is applied to a Requisition and Issue Slip when you submit, approve, issue or acknowledge it, and it appears on the printed form and PDF.
                    Each document keeps the signature as it was when you signed; changing it here does not alter documents you already signed.</p>

                <div class="row g-4">
                    <div class="col-md-5">
                        <div class="small fw-semibold mb-1">Signature on file</div>
                        <div class="signature-current">
                            @if($signature)
                                <img src="{{ $signature }}" alt="Your current signature">
                            @else
                                <span class="text-muted small">No signature yet. Draw one on the right.</span>
                            @endif
                        </div>
                        @if($signature)
                            <form method="POST" action="{{ route('profile.signature.destroy') }}" class="mt-2"
                                  data-confirm="Remove your electronic signature? Requisitions you sign afterwards will show no signature image until you add a new one." data-confirm-variant="danger" data-confirm-button="Remove">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-link text-danger p-0" type="submit"><i class="bi bi-trash me-1"></i>Remove signature</button>
                            </form>
                        @endif
                    </div>

                    <div class="col-md-7">
                        <form method="POST" action="{{ route('profile.signature') }}" enctype="multipart/form-data" id="signatureForm">
                            @csrf @method('PUT')
                            <input type="hidden" name="signature_data" id="signatureData">
                            <div class="small fw-semibold mb-1">{{ $signature ? 'Draw a new signature' : 'Draw your signature' }}</div>
                            <canvas id="signaturePad" class="signature-pad" aria-label="Signature drawing area. Sign with your mouse, finger or stylus." role="img"></canvas>
                            <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                                <button type="button" class="btn btn-sm btn-light border" id="signatureClear"><i class="bi bi-eraser me-1"></i>Clear</button>
                                <span class="small text-muted">or</span>
                                <label class="btn btn-sm btn-light border mb-0" for="signatureFile"><i class="bi bi-upload me-1"></i>Upload image</label>
                                <input type="file" id="signatureFile" name="signature_file" accept="image/png,image/jpeg" class="d-none">
                                <span class="small text-muted" id="signatureFileName"></span>
                                <button type="submit" class="btn btn-sm btn-teal ms-auto" id="signatureSave" disabled><i class="bi bi-check2 me-1"></i>Save signature</button>
                            </div>
                            <div class="form-text">Sign inside the box with your mouse, finger or stylus. An uploaded image should be a PNG or JPG of your signature on a white background (max 1 MB).</div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var canvas = document.getElementById('signaturePad');
    var ctx = canvas.getContext('2d');
    var save = document.getElementById('signatureSave');
    var file = document.getElementById('signatureFile');
    var drawing = false, hasInk = false, last = null;
    var bounds = null;

    function resize() {
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.lineWidth = 2.4; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0b1f4a';
        clear();
    }
    function clear() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        hasInk = false; bounds = null; update();
    }
    function update() { save.disabled = !hasInk && !file.files.length; }
    function point(e) {
        var r = canvas.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    }
    function grow(p) {
        bounds = bounds ? { x1: Math.min(bounds.x1, p.x), y1: Math.min(bounds.y1, p.y), x2: Math.max(bounds.x2, p.x), y2: Math.max(bounds.y2, p.y) }
                        : { x1: p.x, y1: p.y, x2: p.x, y2: p.y };
    }

    canvas.addEventListener('pointerdown', function (e) {
        e.preventDefault(); canvas.setPointerCapture(e.pointerId);
        drawing = true; last = point(e); grow(last);
        ctx.beginPath(); ctx.arc(last.x, last.y, ctx.lineWidth / 2, 0, Math.PI * 2); ctx.fillStyle = ctx.strokeStyle; ctx.fill();
    });
    canvas.addEventListener('pointermove', function (e) {
        if (!drawing) return;
        var p = point(e);
        ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke();
        last = p; grow(p);
        if (!hasInk) { hasInk = true; file.value = ''; document.getElementById('signatureFileName').textContent = ''; update(); }
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (t) { canvas.addEventListener(t, function () { drawing = false; }); });

    document.getElementById('signatureClear').addEventListener('click', clear);
    file.addEventListener('change', function () {
        document.getElementById('signatureFileName').textContent = file.files.length ? file.files[0].name : '';
        if (file.files.length) { ctx.clearRect(0, 0, canvas.width, canvas.height); hasInk = false; bounds = null; }
        update();
    });

    // Export only the inked area (with a small margin) so the signature prints at a sensible size.
    document.getElementById('signatureForm').addEventListener('submit', function () {
        if (!hasInk || !bounds) { document.getElementById('signatureData').value = ''; return; }
        var ratio = canvas.width / canvas.offsetWidth, pad = 10;
        var x = Math.max(0, (bounds.x1 - pad) * ratio), y = Math.max(0, (bounds.y1 - pad) * ratio);
        var w = Math.min(canvas.width - x, (bounds.x2 - bounds.x1 + pad * 2) * ratio);
        var h = Math.min(canvas.height - y, (bounds.y2 - bounds.y1 + pad * 2) * ratio);
        var out = document.createElement('canvas');
        out.width = Math.max(100, Math.round(w)); out.height = Math.max(30, Math.round(h));
        out.getContext('2d').drawImage(canvas, x, y, w, h, 0, 0, w, h);
        document.getElementById('signatureData').value = out.toDataURL('image/png');
    }, true);

    window.addEventListener('resize', function () { if (!hasInk) resize(); });
    resize();
})();
</script>
@endpush
