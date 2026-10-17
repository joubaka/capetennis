@extends('layouts.backend')
@section('title', 'Check existing profiles')
@section('content')
<div class="container-xxl py-4">
  <h1 class="h4">Check existing profiles · {{ $team->name }}</h1>
  <p>{{ $event->name }} · Choose an earlier event you manage, review the identities, then confirm the links. Already-linked players are skipped.</p>
  <a class="btn btn-outline-secondary mb-3" href="{{ route('admin.events.teams', ['event' => $event->id, 'roster_tab' => 'players', 'selected_region' => $team->region_id]) }}">Back to teams</a>
  @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<p class="mb-1">{{ $error }}</p>@endforeach</div>@endif
  <div class="card mb-4"><div class="card-body">
    <form method="GET" class="d-flex flex-wrap align-items-end gap-2 mb-3">
      <div><label for="event-search" class="form-label">Find an earlier event</label><input id="event-search" name="q" value="{{ request('q') }}" maxlength="100" class="form-control" type="search"></div>
      <button class="btn btn-outline-primary">Search events</button>
    </form>
    <form method="GET">
      <label for="source-event" class="form-label">Previous event</label>
      <select name="source_event_id" id="source-event" required class="form-select mb-3">
        <option value="">Choose an event</option>
        @if($source && !$events->contains('id', $source->id))<option value="{{ $source->id }}" selected>{{ $source->name }} · {{ $source->start_date }}</option>@endif
        @foreach($events as $option)<option value="{{ $option->id }}" @selected($source?->id === $option->id)>{{ $option->name }} · {{ $option->start_date }}</option>@endforeach
      </select>
      <button class="btn btn-primary">Check matching players</button>
    </form>
    @if($events->isEmpty())<p class="text-muted mt-3">No earlier events found that you can manage. Try another search.</p>@endif
    <div class="mt-3">{{ $events->links('pagination::bootstrap-5') }}</div>
  </div></div>
  @if($preview)
    <h2 class="h5">Matches from {{ $source->name }}</h2>
    <p>Names are matched exactly, ignoring case and spacing. Check date of birth, email and gender before choosing. Leave “Skip” selected if uncertain. Confirm this page before moving to the next page. No profiles are created.</p>
    <form method="POST" action="{{ route('backend.team-selection.profile-matches.store', [$event, $team]) }}">
      @csrf
      <input type="hidden" name="source_event_id" value="{{ $source->id }}">
      <input type="hidden" name="roster_page" value="{{ $page }}">
      <input type="hidden" name="fingerprint" value="{{ $fingerprint }}">
      @forelse($preview['rows'] as $row)
        @php($slot = $row['slot'])
        <fieldset class="card mb-3"><div class="card-body">
          <legend class="h6">Rank {{ $slot->rank }} · {{ $slot->name }} {{ $slot->surname }}</legend>
          @if($slot->player_profile)<p class="mb-0">Already linked · Profile #{{ $slot->player_profile }}. Skipped.</p>
          @elseif($row['overflow'])<p class="mb-0">More than 20 matching profiles. Skip this player and use individual profile search to review.</p>
          @elseif($row['candidates']->isEmpty())<p class="mb-0 text-muted">No matching participant in this event. Skipped. Try another event or the individual profile search.</p>
          @else
            @if($row['candidates']->count() > 1)<p class="text-warning">Multiple profiles match this name. Choose only after checking their details.</p>@endif
            <label class="d-flex gap-2 mb-3"><input type="radio" name="links[{{ $slot->id }}]" value="" checked><span>Skip this player</span></label>
            @foreach($row['candidates'] as $candidate)
              <label class="d-flex gap-2 border rounded p-3 mb-2" style="overflow-wrap:anywhere">
                <input type="radio" name="links[{{ $slot->id }}]" value="{{ $candidate->id }}">
                <span><strong>{{ $candidate->full_name }} · Profile #{{ $candidate->id }}</strong><br>
                  Born: {{ $candidate->dateOfBirth ?: 'Not recorded' }} · Gender: {{ $candidate->gender ?: 'Not recorded' }}<br>
                  Email: {{ $candidate->email ?: 'Not recorded' }}<br>
                  @foreach($candidate->source_teams as $context)<span class="d-block">{{ $context }}</span>@endforeach
                  @foreach($candidate->source_categories as $context)<span class="d-block">{{ $context }}</span>@endforeach
                </span>
              </label>
            @endforeach
          @endif
        </div></fieldset>
      @empty<p>No imported players on this page.</p>@endforelse
      <label class="d-flex gap-2 mb-3"><input type="checkbox" name="confirm_links" value="1" required><span>I have checked these identities and confirm the selected profile links.</span></label>
      <button class="btn btn-primary">Confirm selected profile links</button>
    </form>
    <div class="mt-3">{{ $preview['slots']->links('pagination::bootstrap-5') }}</div>
  @endif
</div>
@endsection
