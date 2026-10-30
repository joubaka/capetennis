@extends('layouts.backend')
@section('title', 'Replace with alternate')
@section('content')
<div class="container py-3" style="max-width:800px">
  <a href="{{ route('draw.guide.show', [$draw->event_id, $draw]) }}">← Back to draw</a>
  <h1 class="h3 mt-3">Replace with alternate</h1>
  <p><strong>{{ $draw->drawName }}</strong></p>
  <p>The alternate takes over the selected player’s positions before the first point of that player’s first match. A bye does not count as a played match. Matches elsewhere in the draw, including the opponent’s feeder match, do not prevent a replacement. Event entries and payments stay unchanged.</p>
  <p class="small text-muted">Cutoff follows <a href="https://www.itftennis.com/media/16911/2026-itf-world-tennis-tour-juniors-regulations.pdf" target="_blank" rel="noopener">ITF Juniors 2026, regulation 48(b)(iii)(d)</a>. The organiser must separately verify alternate/lucky-loser order and sign-in requirements.</p>
  @if($draw->event->isInterprovincialTrials())<p class="alert alert-info">Trials alternates must already have an active paid entry in this category. Add or resolve their entry through the existing Entries workflow first.</p>@endif
  @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  <form method="GET" class="mb-4">
    <label for="alternate-search" class="form-label">Find an existing player by name</label>
    <div class="d-flex gap-2"><input id="alternate-search" class="form-control" name="q" value="{{ $query }}" minlength="2" required maxlength="100"><button class="btn btn-outline-primary">Search</button></div>
    <small class="text-muted">Search returns up to 30 players. Refine the name if needed.</small>
  </form>
  <form method="POST" action="{{ route('draw.alternate.store', $draw) }}">
    @csrf
    <label for="outgoing-registration" class="form-label">Current player to replace</label>
    <select id="outgoing-registration" name="outgoing_registration_id" class="form-select mb-3" required>
      <option value="">Choose current player</option>
      @foreach($registrations as $registration)
        @php($unavailable = $unavailableIds->contains($registration->id))
        <option value="{{ $registration->id }}" @disabled($unavailable) @selected(old('outgoing_registration_id') == $registration->id)>{{ $registration->display_name }}{{ $unavailable ? ' — first match already started' : '' }}</option>
      @endforeach
    </select>
    <label for="alternate-player" class="form-label">Incoming alternate</label>
    <select id="alternate-player" name="player_id" class="form-select mb-3" required>
      <option value="">{{ $query ? 'Choose search result' : 'Search for the alternate above' }}</option>
      @foreach($players as $player)<option value="{{ $player->id }}">{{ $player->full_name }}{{ $player->dateOfBirth ? ' · '.\Carbon\Carbon::parse($player->dateOfBirth)->format('Y') : '' }}</option>@endforeach
    </select>
    @if($query && $players->isEmpty())<p role="status">No matching players found.</p>@endif
    <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="show_alternate" value="1" @checked(old('show_alternate', true))><span class="form-check-label">Show “(alternate)” next to the incoming player’s name</span></label>
    <button class="btn btn-primary" @disabled($players->isEmpty())>Replace player with alternate</button>
  </form>
</div>
@endsection
