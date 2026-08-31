@extends('layouts/contentNavbarLayout')
@section('title', 'Masters invitation')
@section('content')
<div class="container-xxl flex-grow-1 container-p-y"><a href="{{ url('/events/'.$invitation->batch->event_id) }}" class="btn btn-sm btn-outline-secondary mb-3">Back to event</a><div class="card"><div class="card-body"><h4>{{ $invitation->batch->event->name }}</h4><p class="text-muted">{{ $invitation->categoryEvent->category->name ?? 'Masters category' }} · Ranking position {{ $invitation->ranking_position }}</p>
@if($invitation->status === \App\Models\MastersInvitation::INVITED && ($invitation->batch->registration_open || (int) $invitation->batch->event->signUp === 1))
<p>Welcome, {{ $invitation->player?->full_name }}. Please confirm whether you will participate.</p><form method="POST" action="{{ route('masters.invitations.accept', $invitation) }}" class="d-inline">@csrf<button class="btn btn-primary">Register and pay with PayFast</button></form><form method="POST" action="{{ route('masters.invitations.decline', $invitation) }}" class="d-inline ms-2" onsubmit="return confirm('Confirm that you are unavailable for this event?');">@csrf<button class="btn btn-outline-secondary">I am unavailable</button></form>
@elseif($invitation->status === \App\Models\MastersInvitation::INVITED)
<div class="alert alert-info mb-0">The player list is published, but Masters registration is currently closed.</div>
@elseif($invitation->status === \App\Models\MastersInvitation::ACCEPTED_PENDING_PAYMENT)
<p>This registration was started but not completed. Cancel it to return to the beginning and restart the registration process.</p><a class="btn btn-warning" href="{{ route('registration.hybrid.cancel', ['orderId' => $invitation->order_id]) }}" onclick="return confirm('Cancel this unfinished registration and restart the registration process?');">Restart registration</a>
@elseif($invitation->status === \App\Models\MastersInvitation::PAID_CONFIRMED)<span class="badge bg-label-success">Payment confirmed — you are entered</span>
@elseif($invitation->status === \App\Models\MastersInvitation::DECLINED)<div class="alert alert-warning mb-0"><strong>Unavailability recorded.</strong><br>Confirm the cancellation using the link sent to your email. The next player will only be invited after email confirmation.</div>
@else<span class="badge bg-label-secondary">This invitation is no longer active.</span>@endif
</div></div></div>
@endsection
