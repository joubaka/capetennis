@extends('layouts.backend')
@section('title', 'Email nominated players')
@section('content')
<div class="operational-page">
    @include('backend.partials.operational-controls')
    @include('backend.event.partials.header', ['event' => $event])

    <div class="card card-body">
        <h4>Email nominated players</h4>
        <p class="text-muted">Cape Tennis selects all current nominees for this event and finds their player and parent email contacts automatically.</p>
        @if($errors->any())
            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
        @endif
        <form method="post" action="{{ route('backend.interprovincial-trials.communications.nominees.preview', $event) }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="nominee-email-subject">Subject</label>
                <input class="form-control" id="nominee-email-subject" name="subject" required maxlength="255" value="{{ old('subject', $draft?->subject) }}">
            </div>
            <div class="mb-3">
                <label class="form-label" for="nominee-email-body">Message</label>
                <textarea class="form-control" id="nominee-email-body" name="body" rows="12" required maxlength="50000" placeholder="Paste your email here">{{ old('body', $draft?->body) }}</textarea>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-primary">Review email</button>
                @if(auth()->user()->hasRole('super-user') || (auth()->user()->hasRole('admin') && auth()->user()->is_event_admin($event->id)))
                    <a class="btn btn-outline-secondary" href="{{ route('backend.interprovincial-trials.invitations.index', $event) }}">Back to nominations</a>
                @else
                    <a class="btn btn-outline-secondary" href="{{ route('backend.interprovincial-trials.communications.index', $event) }}">Back to communications</a>
                @endif
            </div>
        </form>
    </div>
</div>
@endsection
