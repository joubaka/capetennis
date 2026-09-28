@extends('layouts/layoutMaster')
@section('title', 'Interprovincial Trials invitation')
@section('content')
<div class="card"><div class="card-body"><h4>{{ $invitation->event->name }}</h4><p>{{ $invitation->player->name }} {{ $invitation->player->surname }} has been invited for {{ $invitation->categoryEvent->category->name }}.</p>
@php($registrationOpen = $invitation->event->published && $invitation->event->hasOpenRegistrationLifecycle() && (int) $invitation->event->signUp === 1 && (!$invitation->event->registrationClosesAt() || now()->lte($invitation->event->registrationClosesAt()->endOfDay())))
@if($invitation->status === \App\Models\InterprovincialTrialInvitation::PAID_CONFIRMED)<div class="alert alert-success">Registration confirmed.</div>
@elseif($invitation->status === \App\Models\InterprovincialTrialInvitation::WITHDRAWN)<div class="alert alert-secondary">Registration withdrawn.</div>
@elseif($registrationOpen)<form method="POST" action="{{ route('interprovincial-trials.invitations.register', $invitation) }}">@csrf<button class="btn btn-primary" type="submit">Register {{ $invitation->player->name }}</button></form>
@else<div class="alert alert-info">Registration closed.</div>@endif
</div></div>
@endsection
