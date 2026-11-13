@extends('layouts/layoutMaster')
@section('title', 'Confirm nomination decline')
@section('content')
<div class="card mx-auto" style="max-width:42rem"><div class="card-body">
<h3>Decline nomination?</h3><p><strong>{{ $review['name'] }}</strong> · {{ $categoryEvent->category->name }}</p><p>{{ $event->name }}</p>
<p>Confirming removes this player from the public nomination list and records their decline for the event manager. This does not cancel a checkout, withdraw a paid entry or request a refund.</p>
<form method="POST" action="{{ route('cavaliers.nominations.decline.confirm', [$event, $categoryEvent, $review['nomination_id']]) }}">@csrf
<input type="hidden" name="token" value="{{ $token }}"><label class="d-flex gap-2 mb-3"><input type="checkbox" name="confirmed" value="1" required> I confirm that this player declines the nomination.</label>
@if ($errors->any())<p class="text-danger">{{ $errors->first() }}</p>@endif
<div class="d-flex flex-wrap gap-2"><a href="{{ route('events.show', $event) }}" class="btn btn-outline-secondary">Keep nomination</a><button type="submit" class="btn btn-danger">Confirm decline</button></div>
</form></div></div>
@endsection
