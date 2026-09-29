@extends('layouts/layoutMaster')
@section('title', 'Interprovincial Trials invitation')
@section('content')
@php($registrationOpen = $invitation->event->published && $invitation->event->hasOpenRegistrationLifecycle() && (int) $invitation->event->signUp === 1 && (!$invitation->event->registrationClosesAt() || now()->lte($invitation->event->registrationClosesAt()->endOfDay())))
<div class="card"><div class="card-body">
<h4>{{ $invitation->event->name }}</h4><p>{{ $invitation->player->name }} {{ $invitation->player->surname }} has been invited for {{ $invitation->categoryEvent->category->name }}.</p>
@if($invitation->status === \App\Models\InterprovincialTrialInvitation::PAID_CONFIRMED)<div class="alert alert-success">Registration confirmed.</div>
@elseif($invitation->status === \App\Models\InterprovincialTrialInvitation::WITHDRAWN)
  @if($registrationOpen)<div class="alert alert-secondary">Not registered.</div><form class="d-inline-block" method="POST" action="{{ route('interprovincial-trials.nominations.register', [$invitation->event, $invitation->categoryEvent, $invitation->nomination]) }}">@csrf<button class="btn btn-primary" type="submit">Register {{ $invitation->player->name }}</button></form>
  @else<div class="alert alert-info">Registration closed.</div>@endif
@elseif($invitation->status === \App\Models\InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT)
  @if($registrationOpen)<form class="d-inline-block" method="POST" action="{{ route('interprovincial-trials.invitations.register', $invitation) }}">@csrf<button class="btn btn-primary" type="submit">Register {{ $invitation->player->name }}</button></form>
  @else<div class="alert alert-info">Registration closed.</div>@endif
@elseif(in_array($invitation->status, ['queued', 'sent'], true) && $registrationOpen)
  <form class="d-inline-block" method="POST" action="{{ route('interprovincial-trials.invitations.register', $invitation) }}">@csrf<button class="btn btn-primary" type="submit">Register {{ $invitation->player->name }}</button></form>
  @if($canDecline)<form class="d-inline-block" method="POST" action="{{ route('interprovincial-trials.invitations.decline', $invitation) }}">@csrf<button class="btn btn-outline-danger" type="submit">Decline</button></form>@endif
@elseif(!$registrationOpen)<div class="alert alert-info">Registration closed.</div>
@else<div class="alert alert-info">This invitation is no longer available.</div>@endif
</div></div>
@endsection
