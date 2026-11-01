@extends('layouts/contentNavbarLayout')
@section('title', 'Player Plus registration')
@section('content')
<div class="container-xxl container-p-y" style="max-width:900px">
  <a href="{{ route('player-plus.index') }}">Choose another player</a>
  <h1 class="mt-3">Player Plus for {{ $player->full_name }}</h1>
  <p>Profile #{{ $player->id }} @if($player->dateOfBirth) · Born {{ substr($player->dateOfBirth, 0, 4) }} @endif</p>
  <x-player-plus-benefits />
  @foreach($errors->all() as $error)<p class="alert alert-danger">{{ $error }}</p>@endforeach
  @if($membership && $membership->ends_at->isFuture())
    <p class="alert alert-success">This player is covered until {{ $membership->ends_at->timezone('Africa/Johannesburg')->format('j M Y H:i') }}. Renew manually after expiry. No automatic renewal or charge.</p>
  @elseif($enabled)
    <form method="post" action="{{ route('player-plus.store', $player) }}" class="card card-body mt-3">
      @csrf
      <input type="hidden" name="request_token" value="{{ $token }}">
      <label class="d-flex gap-2 align-items-start"><input type="checkbox" name="confirm" value="1" required class="mt-1"><span>I am buying 12 months of Player Plus for {{ $player->full_name }} at R499. This is a once-off payment with manual renewal. Paying does not link this player to my account or give me private access.</span></label>
      <button class="btn btn-primary mt-3 align-self-start" style="min-height:44px">Continue to checkout · R499</button>
    </form>
  @else<p class="alert alert-info">Membership purchases are not available yet.</p>@endif
  @if($canViewRating)
    <a class="btn btn-primary mt-3" href="{{ route('my.tennis', ['player' => $player->id]) }}#rating">View rating and progress</a>
    <a class="btn btn-outline-primary mt-3" href="{{ route('rankings.index') }}">Explore published series and CTR Top 10</a>
  @endif
  @unless($linked)<p class="mt-3">You are sponsoring this player. Only the player and accounts already linked to them can use their private Player Plus benefits.</p>@endunless
  <p class="mt-3"><a href="{{ route('my.tennis') }}">Back to My Tennis</a></p>
</div>
@endsection
