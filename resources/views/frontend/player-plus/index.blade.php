@extends('layouts/contentNavbarLayout')
@section('title', 'Player Plus')
@section('content')
<div class="container-xxl container-p-y">
  <h1>Player Plus</h1>
  <x-player-plus-benefits />
  @unless($enabled)<p class="alert alert-info">Membership purchases are not available yet.</p>@endunless
  <h2 class="h4 mt-4">Choose a player</h2>
  <p>Your linked players appear below. You can also sponsor another player without gaining access to their private information.</p>
  <form method="get" class="d-flex flex-wrap gap-2 mb-3">
    <label class="visually-hidden" for="plus-search">Search by player name</label>
    <input id="plus-search" class="form-control" style="max-width:24rem;min-height:44px" name="search" value="{{ $search }}" placeholder="Search by name (at least 3 characters)" minlength="3" maxlength="100">
    <button class="btn btn-primary" style="min-height:44px">Search players</button>
    @if($search)<a class="btn btn-outline-secondary" href="{{ route('player-plus.index') }}">My linked players</a>@endif
  </form>
  @foreach($errors->all() as $error)<p class="alert alert-danger">{{ $error }}</p>@endforeach
  <div class="list-group mb-3">
    @forelse($players as $player)
      <a class="list-group-item list-group-item-action py-3" href="{{ route('player-plus.player', $player) }}">{{ $player->full_name }} <small class="text-muted">· Profile #{{ $player->id }} @if($player->dateOfBirth) · Born {{ substr($player->dateOfBirth, 0, 4) }} @endif</small></a>
    @empty<p class="alert alert-info">No players found. Search for the player you would like to cover.</p>@endforelse
  </div>
  {{ $players->links() }}
</div>
@endsection
