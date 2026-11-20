@extends('layouts/contentNavbarLayout')
@section('title','Camp invitation')
@section('content')
@php($campDeadline = app(\App\Services\Camps\CampAdmission::class)->configuration((int) $nominee->event_id)?->registration_closes_at)
<div data-camp-workflow class="container-xxl container-p-y" style="max-width:760px;overflow-wrap:anywhere"><div class="card"><div class="card-body"><h1 class="h3">{{ $nominee->event->name }}</h1><h2 class="h5">Invitation for {{ $nominee->player?->full_name }}</h2><p>{{ $nominee->categoryEvent?->category?->name }} · {{ $nominee->event->start_date?->format('j F Y') }} – {{ $nominee->event->end_date?->format('j F Y') }}</p><p>{{ $nominee->event->venue_notes }}</p><p>Entry fee: R{{ number_format($nominee->categoryEvent->effectiveEntryFee(),2) }}</p><p>Status: <strong>{{ str_replace('_',' ',$participation) }}</strong></p>
@if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if($campDeadline)<p>Entry deadline: {{ $campDeadline->copy()->timezone('Africa/Johannesburg')->format('j F Y H:i') }} SAST</p>@endif
@if($nominee->status==='approved' && !in_array($participation,['paid','checkout_pending','withdrawn']) && $nominee->event->registrationIsOpen() && (!$campDeadline || now()->lte($campDeadline)))<a class="btn btn-primary mb-3" href="{{ route('register.register',[$nominee->event_id,'camp_nominee'=>$nominee->id]) }}">Enter and pay for the camp</a><p>Sign in to enter. An eligible parent or sponsor may pay without gaining ownership of the player profile.</p>
<form method="POST" action="{{ route('camp.invitation.decline',$token) }}">@csrf<label><input type="checkbox" name="confirmed" value="1" required> Confirm that this player will not attend.</label><br><button class="btn btn-outline-secondary mt-2">Decline camp invitation</button></form>@endif
<a class="d-block mt-3" href="{{ route('events.show',$nominee->event_id) }}">View camp information</a></div></div></div>
@endsection

@section('page-script')
@include('backend.practice-camps.feedback')
@endsection
