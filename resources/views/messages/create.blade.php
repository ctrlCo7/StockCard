@extends('layouts.app')
@section('title', 'New message')

@php $selected = array_map('intval', old('recipients', $preselected)); @endphp

@section('content')
<x-page-header title="New message" :crumbs="['Messages' => route('messages.index')]" />

<div class="row">
    <div class="col-xl-8">
        <form method="POST" action="{{ route('messages.store') }}" class="card">
            @csrf
            @if($requisition)<input type="hidden" name="requisition_id" value="{{ $requisition->id }}">@endif
            <div class="card-body row g-3">
                @if($requisition)
                    <div class="col-12">
                        <div class="alert alert-light border small mb-0"><i class="bi bi-link-45deg me-1"></i>This conversation will be linked to
                            <a href="{{ route('requisitions.show', $requisition) }}">{{ $requisition->ris_no }}</a> ({{ $requisition->status->label() }}).</div>
                    </div>
                @endif
                <div class="col-12">
                    <span class="form-label d-block">To <span class="text-danger">*</span></span>
                    @if($contacts->isEmpty())
                        <div class="text-muted small">There is no one you can message yet.</div>
                    @else
                        <div class="border rounded p-2 @error('recipients') border-danger @enderror @error('recipients.*') border-danger @enderror" style="max-height:240px;overflow:auto">
                            @foreach($contacts->groupBy(fn ($u) => $u->isAdmin() ? 'Supply office (administrators)' : 'Requesting personnel') as $group => $people)
                                <div class="small text-uppercase text-muted fw-semibold mt-1 mb-1">{{ $group }}</div>
                                @foreach($people as $person)
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="recipients[]" value="{{ $person->id }}" id="to{{ $person->id }}" @checked(in_array($person->id, $selected, true))>
                                        <label class="form-check-label" for="to{{ $person->id }}">{{ $person->name }}
                                            <span class="text-muted small">{{ collect([$person->designation, $person->office?->name])->filter()->implode(' · ') }}</span></label>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                        @error('recipients')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @error('recipients.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @unless(auth()->user()->isAdmin())
                            <div class="form-text">You can message the supply office administrators.</div>
                        @endunless
                    @endif
                </div>
                <div class="col-12">
                    <label class="form-label" for="subject">Subject <span class="text-danger">*</span></label>
                    <input id="subject" name="subject" value="{{ old('subject', $subject) }}" class="form-control @error('subject') is-invalid @enderror" required maxlength="150">
                </div>
                <div class="col-12">
                    <label class="form-label" for="body">Message <span class="text-danger">*</span></label>
                    <textarea id="body" name="body" rows="6" class="form-control @error('body') is-invalid @enderror" required maxlength="5000">{{ old('body') }}</textarea>
                </div>
            </div>
            <div class="card-footer bg-white d-flex gap-2">
                <button class="btn btn-teal" type="submit" @disabled($contacts->isEmpty()) data-loading-text="Sending…"><i class="bi bi-send me-1"></i>Send message</button>
                <a href="{{ $requisition ? route('requisitions.show', $requisition) : route('messages.index') }}" class="btn btn-light border">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
