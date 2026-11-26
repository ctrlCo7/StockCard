@props(['id', 'action', 'title', 'description', 'emails' => ''])
@php $reopen = old('_email_modal') === $id && ($errors->has('emails') || $errors->has('recipients') || $errors->has('recipients.*') || $errors->has('note')); @endphp
<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Title" aria-hidden="true" @if($reopen) data-autoshow @endif>
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="POST" action="{{ $action }}">
            @csrf
            <input type="hidden" name="_email_modal" value="{{ $id }}">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $id }}Title"><i class="bi bi-envelope me-2"></i>{{ $title }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">{{ $description }}</p>
                <label class="form-label" for="{{ $id }}Emails">Send to <span class="text-danger">*</span></label>
                <input id="{{ $id }}Emails" name="emails" class="form-control @if($reopen && ($errors->has('emails') || $errors->has('recipients') || $errors->has('recipients.*'))) is-invalid @endif"
                       value="{{ $reopen ? old('emails') : $emails }}" required maxlength="1000" placeholder="name@example.com, another@example.com" autocomplete="email">
                @if($reopen)
                    @foreach(['emails', 'recipients', 'recipients.*'] as $field)
                        @error($field)<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    @endforeach
                @endif
                <div class="form-text">Separate several addresses with commas (up to 10).</div>
                <label class="form-label mt-3" for="{{ $id }}Note">Message <span class="text-muted small">(optional)</span></label>
                <textarea id="{{ $id }}Note" name="note" rows="3" class="form-control" maxlength="1000" placeholder="e.g. Please collect your supplies at the Supply Room.">{{ $reopen ? old('note') : '' }}</textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" data-loading-text="Sending…"><i class="bi bi-send me-1"></i>Send email</button>
            </div>
        </form>
    </div>
</div>
