@extends('layouts.backend')
@section('title', 'Registration open email')
@section('content')
<div class="container-xl py-3">
<h3>{{ $event->name }} — Registration open email</h3>
<p>Congratulate the regional team and help players and parents register. Review the exact addresses and email before approving the queue.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@unless(app(\App\Domain\Teams\Services\ExternalTeamRosterService::class)->registrationIsOpen($event))<div class="alert alert-warning">Event registration is closed. Publish the event and open registration in the event settings before reviewing this registration-open email.</div>@endunless
<form method="post" action="{{ route('backend.regional-registration-mail.preview', $event) }}">@csrf
<input type="hidden" name="registration_fingerprint" value="{{ $draft['registration_fingerprint'] }}">
<div class="card card-body mb-3"><h5>1. Region and deadlines</h5>
<label class="d-block mb-3">Region<select name="event_region_id" class="form-select" id="registration-mail-region">@foreach($regions as $region)<option value="{{ $region->id }}" @selected((int)old('event_region_id', $options['event_region_id']) === (int)$region->id)>{{ $region->region->region_name }}</option>@endforeach</select></label>
<div class="row"><label class="col-md-3 mb-3">Registration deadline in email<input type="date" class="form-control" name="registration_deadline" value="{{ old('registration_deadline', $options['registration_deadline']) }}"></label>
<label class="col-md-3 mb-3">Registration closing time<input type="time" class="form-control" name="registration_deadline_time" value="{{ old('registration_deadline_time', $options['registration_deadline_time'] ?? null) }}"></label>
<label class="col-md-3 mb-3">Clothing deadline in email<input type="date" class="form-control" name="clothing_deadline" value="{{ old('clothing_deadline', $options['clothing_deadline']) }}"></label>
<label class="col-md-3 mb-3">Clothing closing time<input type="time" class="form-control" name="clothing_deadline_time" value="{{ old('clothing_deadline_time', $options['clothing_deadline_time'] ?? null) }}"></label></div>
<p class="form-text">Times use South African time. Leave a time blank to show only the date.</p>
<p class="form-text">These dates are for the email only. They do not change the event or clothing closing settings.</p>
<label class="d-block mb-2"><input type="checkbox" name="include_clothing" value="1" @checked(old('include_clothing', $options['include_clothing']))> Clothing catalogue checked and ready — include clothing ordering instructions, prices and sizes</label>
<p class="form-text">Clothing is included only when online ordering is enabled and open for the selected region. Review the catalogue before checking this box.</p>
<button class="btn btn-outline-primary" name="intent" value="generate">Update suggested email</button>
<p class="form-text mb-0">Changing the region, dates, times or clothing choice requires updating the suggested email before reviewing it. This replaces the message below; keep any edits you want to reuse.</p></div>
<div class="card card-body mb-3"><h5>2. Edit email</h5>
<p>Current audience: {{ $context['rows']->count() }} main-team players from published teams and their available parent contacts. Reserves are excluded. Shared addresses receive one email. Missing contacts appear at review.</p>
<label class="d-block mb-3">Subject<input class="form-control" name="subject" maxlength="200" value="{{ old('subject', $draft['subject']) }}" required></label>
<label class="d-block mb-3">Message<textarea class="form-control" name="body" rows="18" maxlength="30000" required>{{ old('body', $draft['body']) }}</textarea></label>
<label class="d-block mb-3">Sender name<input class="form-control" name="from_name" maxlength="150" value="{{ old('from_name', $draft['from_name'] ?? auth()->user()->name) }}" required></label>
<label class="d-block">Reply-to email<input class="form-control" type="email" name="reply_to" value="{{ old('reply_to', $draft['reply_to'] ?? auth()->user()->email) }}" required></label></div>
<p class="form-text">Sender details default to the event administrator where assigned. You can edit them before reviewing the email.</p>
<button class="btn btn-primary" name="intent" value="preview">Review addresses and email</button>
<a class="btn btn-outline-secondary" href="{{ route('backend.event-communications.index', $event) }}">Back to messages</a>
<p class="mt-2">Nothing is sent until you review and explicitly approve the next screen.</p>
</form></div>
@endsection
