@extends('layouts/layoutMaster')
@section('title', 'Private registration')
@section('content')
<div class="card mx-auto" style="max-width: 760px"><div class="card-body">
<h1 class="h4">Register for {{ $event->name }}</h1>
<p><strong>{{ $link->player->name }} {{ $link->player->surname }}</strong> · {{ $link->categoryEvent->category->name }}</p>
<p>Entry fee: <strong>R{{ number_format($link->categoryEvent->effectiveEntryFee(), 2) }}</strong></p>
<p class="text-muted">Registration is available to this player's linked account until {{ $link->expires_at->format('j M Y H:i') }}. Your place is confirmed after payment. Public registration stays closed.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('pay.now.payfast') }}">
@csrf
<input type="hidden" name="private_registration_link_id" value="{{ $link->id }}">
<input type="hidden" name="player[]" value="{{ $link->player_id }}">
<input type="hidden" name="category[]" value="{{ $link->category_event_id }}">
@include('frontend.partials.registration-terms', ['refundTermsEvent' => $event])
<div class="form-check my-3"><input class="form-check-input" type="checkbox" id="privateTerms" name="terms_accepted" value="1" required><label class="form-check-label" for="privateTerms">I accept the registration, withdrawal and refund terms for this event.</label></div>
<button class="btn btn-primary" type="submit">Continue to payment</button>
</form>
</div></div>
@endsection
