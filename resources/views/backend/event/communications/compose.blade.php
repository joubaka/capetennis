@extends('layouts.backend')
@section('title', 'Write email')
@section('content')
<div data-mail-compose-fragment>
<form method="POST" action="{{ route('backend.event-communications.preview', $event) }}" class="modal-content" data-mail-compose>
@csrf
<div class="modal-header"><h5 class="modal-title">{{ $event->name }} — Write email</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
<div class="modal-body">
@foreach($options as $field => $value)@if(is_scalar($value))<input type="hidden" name="{{ $field }}" value="{{ $value }}">@endif @endforeach
@include('backend.partials.email-sender-fields')
<label class="form-label w-100">Subject<input name="subject" class="form-control" value="{{ $subject }}" required maxlength="200"></label>
<label class="form-label w-100">Message<textarea name="body" class="form-control" required rows="7" maxlength="30000">{{ $body }}</textarea></label>
<p class="form-text">Review the exact recipients and one example before approving.</p>
</div>
<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Preview email</button></div>
</form>
</div>
@endsection
