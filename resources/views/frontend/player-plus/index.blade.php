@extends('layouts/contentNavbarLayout')
@section('title', 'Player Plus')
@section('content')
<div id="player-plus-page" class="container-xxl container-p-y">
  <style>
    #player-plus-page { max-width: 1120px; }
    #player-plus-page .plus-page-heading { display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1.25rem; }
    #player-plus-page .plus-page-heading h1 { font-size:1rem;margin:0;color:#14675c; }
    #player-plus-page .plus-page-intro { padding:clamp(1.25rem,4vw,2.5rem);background:linear-gradient(120deg,#f5f9f6,#e9f4ef);border-radius:18px;margin-bottom:1.25rem; }
    #player-plus-page .plus-page-intro h2 { font-size:clamp(1.8rem,4vw,2.8rem);letter-spacing:-.035em;color:#183c45;max-width:24ch; }
    #player-plus-page .plus-page-intro p { color:#536873;max-width:60ch;line-height:1.6; }
    #player-plus-page .plus-player-choice { background:#fff;border:1px solid #e5ece9;border-radius:16px;padding:clamp(1rem,3vw,1.5rem);margin-top:1.5rem; }
    #player-plus-page .plus-player-choice .list-group-item { border-color:#e5ece9;overflow-wrap:anywhere; }
    #player-plus-page .plus-player-choice .btn-primary, #player-plus-page .plus-page-intro .btn { background:#146f63;border-color:#146f63;color:#fff;min-height:44px; }
    #player-plus-page .plus-page-intro .btn:hover, #player-plus-page .plus-player-choice .btn-primary:hover { background:#10594f;border-color:#10594f; }
    @media(max-width:575.98px) { #player-plus-page .plus-player-choice form .form-control { max-width:none !important; } }
  </style>
  <div class="plus-page-heading"><h1>Player Plus</h1><a class="d-inline-block py-2" href="{{ route('player-plus.orders.index') }}" style="min-height:44px">My Plus orders</a></div>
  <header class="plus-page-intro">
    <h2>See the bigger picture of their tennis.</h2>
    <p>Understand their playing strength, follow 12 months of progress and explore the results behind their Cape Tennis Rating.</p>
    <a class="btn" href="#choose-plus-player">{{ $enabled ? 'Explore Plus for a player' : 'View player membership details' }} <span aria-hidden="true">→</span></a>
    <p class="small mt-3 mb-0">Optional membership · Essential tournament information remains free.</p>
  </header>
  <x-player-plus-benefits />
  @unless($enabled)<p class="alert alert-info">New purchases are currently paused. Existing verified memberships keep their benefits until expiry.</p>@endunless
  <section class="plus-player-choice" aria-labelledby="choose-plus-player">
  <h2 id="choose-plus-player" class="h4" style="scroll-margin-top:6rem">Choose a player</h2>
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
  </section>
</div>
@endsection
