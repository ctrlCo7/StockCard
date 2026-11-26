@extends('layouts.app')
@section('title', $conversation->subject)

@section('content')
<x-page-header :title="$conversation->subject" :subtitle="'With '.$conversation->otherParticipantNames(auth()->user())" :crumbs="['Messages' => route('messages.index')]">
    @if($conversation->requisition && auth()->user()->can('view', $conversation->requisition))
        <a href="{{ route('requisitions.show', $conversation->requisition) }}" class="btn btn-sm btn-light border"><i class="bi bi-file-earmark-text me-1"></i>{{ $conversation->requisition->ris_no }}</a>
    @endif
</x-page-header>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card mb-3">
            <div class="card-body chat-thread">
                @foreach($conversation->messages as $message)
                    @php $mine = $message->sender->is(auth()->user()); @endphp
                    <div class="chat-row {{ $mine ? 'mine' : '' }}" @if($loop->last) id="latest" @endif>
                        <div class="chat-bubble">
                            <div class="chat-meta">{{ $mine ? 'You' : $message->sender->name }} · {{ $message->created_at->format('M d, Y g:i A') }}</div>
                            <div class="chat-body">{!! nl2br(e($message->body)) !!}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <form method="POST" action="{{ route('messages.reply', $conversation) }}" class="card">
            @csrf
            <div class="card-body">
                <label class="form-label" for="body">Reply</label>
                <textarea id="body" name="body" rows="3" class="form-control @error('body') is-invalid @enderror" required maxlength="5000" placeholder="Write a reply…">{{ old('body') }}</textarea>
                @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="card-footer bg-white">
                <button class="btn btn-teal" type="submit" data-loading-text="Sending…"><i class="bi bi-send me-1"></i>Send reply</button>
            </div>
        </form>
    </div>
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Participants</h2></div>
            <ul class="list-group list-group-flush">
                @foreach($conversation->participants as $p)
                    <li class="list-group-item">
                        <div class="fw-semibold">{{ $p->name }} @if($p->is(auth()->user()))<span class="text-muted small">(you)</span>@endif</div>
                        <div class="small text-muted">{{ collect([$p->designation, $p->office?->name, $p->roleLabels()])->filter()->implode(' · ') }}</div>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () { var t = document.querySelector('.chat-thread'); if (t) t.scrollTop = t.scrollHeight; })();
</script>
@endpush
