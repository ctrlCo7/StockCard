@extends('layouts.app')
@section('title', 'Messages')

@section('content')
<x-page-header title="Messages" :subtitle="auth()->user()->isAdmin() ? 'Conversations with requesting personnel and other administrators.' : 'Conversations with the supply office.'">
    <a href="{{ route('messages.create') }}" class="btn btn-sm btn-teal"><i class="bi bi-pencil-square me-1"></i>New message</a>
</x-page-header>

<form class="filter-bar" method="GET">
    <div class="row g-2 align-items-end">
        <div class="col-md-7"><label class="form-label small mb-1" for="q">Search</label>
            <input type="search" id="q" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control form-control-sm" placeholder="Subject or message text"></div>
        <div class="col-md-3 form-check ms-2 mb-1">
            <input class="form-check-input" type="checkbox" id="unread" name="unread" value="1" @checked($filters['unread'] ?? false) data-autosubmit>
            <label class="form-check-label small" for="unread">Unread only</label>
        </div>
        <div class="col-md-auto"><button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-search me-1"></i>Search</button></div>
    </div>
</form>

<div class="card table-card">
    <div class="list-group list-group-flush">
        @forelse($conversations as $c)
            @php $unread = $c->isUnreadFor(auth()->user()); @endphp
            <a href="{{ route('messages.show', $c) }}" class="list-group-item list-group-item-action d-flex gap-3 align-items-start py-3 {{ $unread ? 'conversation-unread' : '' }}">
                <span class="stat-icon tone-{{ $unread ? 'teal' : 'slate' }} flex-none" style="width:38px;height:38px"><i class="bi bi-chat{{ $unread ? '-dots-fill' : '' }}"></i></span>
                <span class="flex-fill" style="min-width:0">
                    <span class="d-flex justify-content-between gap-2">
                        <span class="text-truncate {{ $unread ? 'fw-bold' : 'fw-semibold' }}">{{ $c->subject }}</span>
                        <small class="text-muted text-nowrap">{{ $c->last_message_at?->diffForHumans() }}</small>
                    </span>
                    <span class="d-block small text-muted">With {{ $c->otherParticipantNames(auth()->user()) }}
                        @if($c->requisition)· <i class="bi bi-file-earmark-text"></i> {{ $c->requisition->ris_no }}@endif</span>
                    @if($c->latestMessage)
                        <span class="d-block small text-truncate {{ $unread ? 'text-body' : 'text-muted' }}">
                            <strong>{{ $c->latestMessage->sender->is(auth()->user()) ? 'You' : $c->latestMessage->sender->name }}:</strong>
                            {{ \Illuminate\Support\Str::limit($c->latestMessage->body, 140) }}
                        </span>
                    @endif
                </span>
                @if($unread)<span class="badge rounded-pill text-bg-danger align-self-center">New</span>@endif
            </a>
        @empty
            <div class="list-group-item"><x-empty-state icon="chat-dots" message="No conversations yet.">
                <a href="{{ route('messages.create') }}" class="btn btn-sm btn-teal">Start a conversation</a>
            </x-empty-state></div>
        @endforelse
    </div>
    @if($conversations->hasPages())<div class="card-footer bg-white">{{ $conversations->links() }}</div>@endif
</div>
@endsection
