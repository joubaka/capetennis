@extends('layouts/layoutMaster')

@section('title', 'Venue scoring — ' . $event->name)

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
  .scoring-shell { max-width: 1180px; margin-inline: auto; }
  .scoring-hero { background: linear-gradient(135deg, #004177, #087ea4); color: #fff; border: 0; }
  .scoring-hero-main { display: flex; align-items: center; gap: 1rem; }
  .scoring-hero-copy { min-width: 0; }
  .scoring-hero-progress { width: 210px; margin-left: auto; }
  .scoring-progress { height: .45rem; background: rgba(255,255,255,.25); }
  .scoring-progress .progress-bar { background: #7ee2a8; }
  .scoring-filter { min-height: 38px; flex: 0 0 auto; white-space: nowrap; padding-block: .45rem; }
  .scoring-select-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
  .scoring-select-grid > div { min-width: 0; }
  .scoring-filter-label { color: #53657a; font-size: .78rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
  .scoring-select { min-height: 40px; font-weight: 600; color: #243b53; }
  .scoring-status-strip { display: flex; flex-wrap: wrap; gap: .4rem; min-width: 0; }
  .scoring-operator { border-top: 1px solid #e6ebf0; }
  .scoring-operator .operator-summary { padding: .65rem 1rem; }
  .operator-change { margin-left: auto; color: var(--bs-primary); font-size: .82rem; font-weight: 700; }
  .scoring-queue-toolbar { display: flex; align-items: center; gap: .75rem; }
  .scoring-queue-summary { margin-left: auto; color: #6c7a8c; font-size: .875rem; white-space: nowrap; }
  .match-card { border-left: 5px solid #6c8aa6; transition: background-color .2s ease, border-color .2s ease; }
  .match-card.is-playing { background: #fff5e6; border-left-color: #f59f00; }
  .match-card.is-completed { background: #edf9f0; border-left-color: #28a745; }
  .match-card.is-waiting { border-left-color: #adb5bd; }
  .match-card .card-body { padding: .85rem 1rem; }
  .match-card-header { display: flex; justify-content: space-between; align-items: center; gap: .75rem; margin-bottom: .4rem; }
  .match-meta { display: flex; flex-wrap: wrap; gap: .35rem .8rem; color: #6c757d; font-size: .86rem; }
  .match-status { flex: 0 0 auto; }
  .match-status-light { display: inline-block; width: .55rem; height: .55rem; margin-right: .3rem; border-radius: 50%; background: currentColor; vertical-align: .03rem; }
  .match-card-main { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 1rem; }
  .match-identity { min-width: 0; }
  .match-players { display: flex; align-items: baseline; gap: .55rem; min-width: 0; }
  .match-player { font-size: .96rem; font-weight: 650; min-width: 0; overflow-wrap: anywhere; }
  .match-versus { color: #7c8998; font-size: .8rem; font-weight: 700; text-transform: uppercase; }
  .match-score { min-width: 90px; font-size: .96rem; font-weight: 750; color: #004177; text-align: right; }
  .match-score.is-empty { color: #7c8998; font-size: .82rem; font-weight: 600; }
  .score-action { min-height: 38px; white-space: nowrap; }
  .match-actions { display: flex; align-items: center; justify-content: flex-end; gap: .45rem; }
  .court-label.is-playing { color: #9a5b00; font-weight: 750; }
  .court-label.is-completed { color: #237a3b; font-weight: 750; }
  .score-input { min-height: 48px; font-size: 1.05rem; text-align: center; }
  .operator-summary { cursor: pointer; list-style: none; }
  .operator-summary::-webkit-details-marker { display: none; }
  #score-filter-empty { border: 1px dashed #c9d4df; background: #fff; }
  @media (max-width: 575.98px) {
    .container-xxl { padding-inline: .75rem; }
    .scoring-shell { margin-inline: 0; }
    .scoring-title { font-size: 1.3rem; }
    .scoring-hero .card-body { padding: 1rem !important; }
    .scoring-hero-main { align-items: flex-start; flex-wrap: wrap; }
    .scoring-hero-progress { order: 3; width: 100%; }
    .scoring-filter-card { margin-inline: -.75rem; border-radius: 0; border-inline: 0; }
    .scoring-filter-card .card-body { padding-inline: .75rem !important; }
    .scoring-select-grid { grid-template-columns: minmax(0, 1fr); gap: .65rem; }
    .scoring-status-strip {
      flex-wrap: nowrap;
      overflow-x: auto;
      max-width: calc(100% + 1.5rem);
      margin-inline: -.75rem;
      padding: .125rem .75rem .5rem;
      scroll-padding-inline: .75rem;
      scrollbar-width: thin;
      -webkit-overflow-scrolling: touch;
    }
    .scoring-queue-toolbar { display: block; margin-inline: -.75rem; }
    .scoring-queue-summary { display: block; margin: .25rem .75rem 0; white-space: normal; }
    .match-card-main { grid-template-columns: minmax(0, 1fr) auto; gap: .65rem; }
    .match-identity { grid-column: 1 / -1; }
    .match-players { align-items: center; }
    .match-score { min-width: 0; text-align: left; }
    .match-actions { justify-self: end; flex-wrap: wrap; }
    #score-entry-modal .modal-dialog { margin: 0; padding: 0; width: 100%; min-height: 100%; }
    #score-entry-modal .modal-content { width: 100%; min-height: 100vh; min-height: 100dvh; border: 0; border-radius: 0; }
    #score-entry-modal .modal-body { overflow-y: auto; }
    .modal-footer { padding-bottom: max(1rem, env(safe-area-inset-bottom)); }
  }
</style>

<div class="container-xxl py-3 py-md-4">
  <div class="scoring-shell">
    <div class="card scoring-hero shadow-sm mb-3">
      <div class="card-body p-3">
        @php
          $progress = $matches->count() ? (int) round(($completed / $matches->count()) * 100) : 0;
        @endphp
        <div class="scoring-hero-main">
          <div class="scoring-hero-copy">
            <div class="small text-white-50 text-uppercase fw-semibold">Venue scoring</div>
            <h1 class="scoring-title h4 mb-1 text-truncate">{{ $event->name }}</h1>
            <div class="small text-white-50 text-truncate">
              {{ $selectedVenue?->name ?? 'All scheduled venues' }}
              @if($selectedDraw) · {{ $selectedDraw->drawName }} @endif
            </div>
          </div>
          <div class="scoring-hero-progress">
            <div class="d-flex justify-content-between mb-1 small">
              <span><strong>{{ $completed }}</strong> of {{ $matches->count() }} scored</span>
              <span>{{ $progress }}%</span>
            </div>
            <div class="progress scoring-progress" role="progressbar" aria-label="Scoring progress" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
              <div class="progress-bar" style="width: {{ $progress }}%"></div>
            </div>
          </div>
          <a href="{{ route('events.show', $event) }}" class="btn btn-sm btn-light">Tournament</a>
        </div>
      </div>
    </div>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card scoring-filter-card mb-3">
      <div class="card-body p-3">
        <div class="scoring-select-grid">
        <div>
          <label class="scoring-filter-label mb-1" for="venue-filter">Venue</label>
          <select class="form-select scoring-select" id="venue-filter" data-nav-select>
            @unless($venueRestricted ?? false)
              <option value="{{ route('frontend.scoring.workspace', ['event' => $event, 'draw' => $selectedDraw?->id, 'all_venues' => 1]) }}" @selected(!$selectedVenue)>All venues</option>
            @endunless
            @foreach($venues as $venue)
              <option value="{{ route('frontend.scoring.workspace', ['event' => $event, 'venue' => $venue->id, 'draw' => $selectedDraw?->id]) }}" @selected($selectedVenue?->id === $venue->id)>{{ $venue->name }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="scoring-filter-label mb-1" for="draw-filter">Draw</label>
          <select class="form-select scoring-select" id="draw-filter" data-nav-select>
            <option value="{{ route('frontend.scoring.workspace', ['event' => $event, 'venue' => $selectedVenue?->id]) }}" @selected(!$selectedDraw)>All draws</option>
            @foreach($draws as $draw)
              <option value="{{ route('frontend.scoring.workspace', ['event' => $event, 'venue' => $selectedVenue?->id, 'draw' => $draw->id]) }}" @selected($selectedDraw?->id === $draw->id)>{{ $draw->drawName }}</option>
            @endforeach
          </select>
        </div>
        </div>
      </div>
      <details class="scoring-operator" @if(!$operatorName) open @endif>
        <summary class="operator-summary d-flex align-items-center gap-2">
          <i class="ti ti-device-mobile text-primary" aria-hidden="true"></i>
          <span><span class="text-muted">Scoring as</span> <strong>{{ $operatorName ?: 'Add operator name' }}</strong></span>
          <span class="operator-change">{{ $operatorName ? 'Change' : 'Add' }}</span>
        </summary>
        <div class="card-body border-top p-3">
          <form method="POST" action="{{ route('frontend.scoring.operator', $event) }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-12 col-sm">
              <label for="scoring-operator" class="form-label fw-semibold mb-1">Who is using this device?</label>
              <input id="scoring-operator" name="operator" class="form-control" maxlength="80" required
                     value="{{ old('operator', $operatorName) }}" placeholder="Name or initials">
              @error('operator')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-12 col-sm-auto">
              <button class="btn btn-outline-primary w-100" type="submit">Remember on this device</button>
            </div>
          </form>
        </div>
      </details>
    </div>

    <div class="scoring-queue-toolbar mb-3">
      <div class="scoring-status-strip" role="group" aria-label="Filter match queue">
        <button type="button" class="btn btn-outline-primary scoring-filter" data-score-filter="now" aria-pressed="false">Playing now</button>
        <button type="button" class="btn btn-outline-primary scoring-filter" data-score-filter="upcoming" aria-pressed="false">Upcoming</button>
        <button type="button" class="btn btn-primary scoring-filter" data-score-filter="outstanding" aria-pressed="true">Outstanding</button>
        <button type="button" class="btn btn-outline-primary scoring-filter" data-score-filter="completed" aria-pressed="false">Completed</button>
        <button type="button" class="btn btn-outline-primary scoring-filter" data-score-filter="all" aria-pressed="false">All</button>
      </div>
      <div class="scoring-queue-summary" aria-live="polite">
        <strong id="score-visible-count">{{ $matches->filter(fn($match) => $match->fixtureResults->isEmpty())->count() }}</strong>
        <span id="score-visible-label">outstanding</span>
        <span class="d-none d-md-inline"> · {{ $ready }} ready to score</span>
      </div>
    </div>

    <div id="score-match-list" class="d-grid gap-2">
      @forelse($matches as $match)
        @php
          $isTeamFixture = $match instanceof \App\Models\TeamFixture;
          $draw = $match->draw;
          $hasScore = $match->fixtureResults->isNotEmpty();
          $isPlaying = !$hasScore && (int) ($match->match_status ?? 0) === \App\Domain\Draws\Enums\FixtureState::STATUS_PARTIAL;
          $isFlexible = !$isTeamFixture && $draw->usesFlexibleMonrad();

          if ($isTeamFixture) {
            $homePlayers = $match->fixturePlayers->pluck('player1')->filter()->pluck('full_name')->filter();
            $awayPlayers = $match->fixturePlayers->pluck('player2')->filter()->pluck('full_name')->filter();
            $home = $homePlayers->isNotEmpty() ? $homePlayers->implode(' + ') : ($match->homeTeam?->name ?? $match->region1Name?->name ?? 'To be decided');
            $away = $awayPlayers->isNotEmpty() ? $awayPlayers->implode(' + ') : ($match->awayTeam?->name ?? $match->region2Name?->name ?? 'To be decided');
            $hasPlayers = $match->fixturePlayers->isNotEmpty() || ($match->homeTeam && $match->awayTeam);
            $sets = $match->fixtureResults->sortBy('set_nr')->map(fn($set) => [(int) $set->team1_score, (int) $set->team2_score])->values();
            $scheduleTime = $match->scheduled_at;
            $venueName = $match->venue?->name;
            $court = $match->court_label;
            $stageLabel = 'Team fixture';
            $matchNumber = $match->match_nr ?: $match->home_rank_nr ?: $match->id;
            $canWrite = auth()->user()->can('team-fixture.saveScore', $match) && !$draw->locked;
            $normalStore = route('frontend.fixtures.score.store', $match->id);
            $normalDelete = route('frontend.fixtures.score.delete', $match->id);
            $playingUrl = route('frontend.scoring.team-fixtures.playing', ['event' => $event, 'fixture' => $match->id]);
            $engine = 'team';
          } else {
            $schedule = $match->orderOfPlay;
            $home = $match->registration1?->players?->first()?->full_name ?? 'To be decided';
            $away = $match->registration2?->players?->first()?->full_name ?? 'To be decided';
            $hasPlayers = $match->registration1_id && $match->registration2_id;
            $sets = $match->fixtureResults->sortBy('set_nr')->map(fn($set) => [(int) $set->registration1_score, (int) $set->registration2_score])->values();
            $scheduleTime = $schedule?->time;
            $venueName = $schedule?->venue?->name;
            $court = $schedule?->court;
            $stageLabel = $match->stage ?: 'Draw';
            $matchNumber = $match->match_nr ?: $match->id;
            $canWrite = auth()->user()->can('saveScore', $draw)
              && !$draw->locked;
            $normalStore = route('api.draws.fixtures.score.store', ['draw' => $match->draw_id, 'fixture' => $match->id]);
            $normalDelete = route('api.draws.fixtures.score.delete', ['draw' => $match->draw_id, 'fixture' => $match->id]);
            $playingUrl = route('frontend.scoring.fixtures.playing', ['event' => $event, 'fixture' => $match->id]);
            $engine = $isFlexible ? 'flexible' : 'standard';
          }
          $flexibleUrl = $isFlexible ? route('flexible-monrad.score', ['draw' => $match->draw_id, 'fixture' => $match->id]) : null;
          $scheduledMoment = $scheduleTime ? \Carbon\Carbon::parse($scheduleTime) : null;
          $timing = $scheduledMoment && !$hasScore && !$isPlaying
            ? ($scheduledMoment->isFuture() ? 'upcoming' : 'past')
            : ($hasScore ? 'completed' : 'unscheduled');
          $state = $hasScore ? 'completed' : ($isPlaying ? 'playing' : 'outstanding');
        @endphp
        <article class="card match-card {{ $hasScore ? 'is-completed' : ($isPlaying ? 'is-playing' : ($hasPlayers ? '' : 'is-waiting')) }}"
                 data-score-state="{{ $state }}" data-score-timing="{{ $timing }}">
          <div class="card-body">
            <div class="match-card-header">
              <div class="match-meta">
                @if($scheduleTime)<span><i class="ti ti-clock"></i> {{ \Carbon\Carbon::parse($scheduleTime)->format('D H:i') }}</span>@endif
                @if($venueName)<span><i class="ti ti-map-pin"></i> {{ $venueName }}</span>@endif
                @if($court)<span class="court-label {{ $isPlaying ? 'is-playing' : ($hasScore ? 'is-completed' : '') }}">Court {{ $court }}</span>@endif
              </div>
              <span class="badge match-status {{ $hasScore ? 'bg-label-success' : ($isPlaying ? 'bg-label-warning' : ($hasPlayers ? 'bg-label-primary' : 'bg-label-secondary')) }}">
                <span class="match-status-light" aria-hidden="true"></span>{{ $hasScore ? 'Completed' : ($isPlaying ? 'Playing now' : ($hasPlayers ? 'Awaiting court' : 'Waiting for players')) }}
              </span>
            </div>
            <div class="match-card-main">
              <div class="match-identity">
                <div class="small text-muted mb-1">{{ $draw->drawName }} · {{ $stageLabel }} · Match {{ $matchNumber }}</div>
                <div class="match-players">
                  <div class="match-player">{{ $home }}</div>
                  <div class="match-versus">vs</div>
                  <div class="match-player">{{ $away }}</div>
                </div>
              </div>
              <div class="match-score {{ $hasScore ? '' : 'is-empty' }}">
                {{ $hasScore ? $sets->map(fn($set) => $set[0].'–'.$set[1])->implode('  ') : 'Not scored' }}
              </div>
              @if($canWrite && $hasPlayers)
              <div class="match-actions">
                @unless($hasScore || $isPlaying)
                <button type="button" class="btn btn-sm btn-outline-warning score-action js-start-match"
                        data-playing-url="{{ $playingUrl }}">
                  On court
                </button>
                @endunless
                <button type="button" class="btn btn-sm btn-primary score-action js-open-score"
                      data-fixture="{{ $match->id }}" data-home="{{ $home }}" data-away="{{ $away }}"
                      data-engine="{{ $engine }}"
                      data-store="{{ $isFlexible ? $flexibleUrl : $normalStore }}"
                      data-delete="{{ $isFlexible ? $flexibleUrl : $normalDelete }}"
                      data-revision="{{ $draw->flexibleMonrad?->revision ?? 0 }}"
                      data-scores='@json($sets)'>
                  {{ $hasScore ? 'Correct score' : 'Enter score' }}
                </button>
              </div>
              @elseif($draw->locked)
                <span class="badge bg-label-secondary">Draw locked</span>
              @endif
            </div>
          </div>
        </article>
      @empty
        <div class="card"><div class="card-body text-center py-5">
          <i class="ti ti-calendar-off fs-1 text-muted"></i>
          <h2 class="h5 mt-2">No matches in this queue</h2>
          <p class="text-muted mb-0">Choose another venue or draw, or publish and apply the order of play first.</p>
        </div></div>
      @endforelse
    </div>
    <div id="score-filter-empty" class="rounded text-center px-3 py-5 d-none" role="status">
      <i class="ti ti-filter-off fs-2 text-muted" aria-hidden="true"></i>
      <h2 class="h6 mt-2 mb-1">No matches in this view</h2>
      <p class="small text-muted mb-0">Try another queue filter, venue, or draw.</p>
    </div>

    @if($recentActivity->isNotEmpty())
      <details class="card mt-4">
        <summary class="card-header fw-semibold">Recent scoring activity</summary>
        <div class="list-group list-group-flush">
          @foreach($recentActivity as $activity)
            <div class="list-group-item small">
              <strong>{{ $activity->payload['operator'] ?? $activity->user?->name ?? 'Scorer' }}</strong>
              · {{ str_replace('_', ' ', $activity->action) }}
              · {{ $activity->draw?->drawName }}
              <span class="text-muted">{{ $activity->created_at?->diffForHumans() }}</span>
            </div>
          @endforeach
        </div>
      </details>
    @endif
  </div>
</div>

<div class="modal fade" id="score-entry-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <form class="modal-content" id="score-entry-form">
      <div class="modal-header">
        <div>
          <div class="small text-muted">Enter completed sets</div>
          <h2 class="modal-title h5" id="score-match-title">Match score</h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="score-entry-error" class="alert alert-danger d-none" role="alert"></div>
        <div class="row g-2 text-center fw-semibold mb-1"><div class="col" id="score-home-label">Player 1</div><div class="col" id="score-away-label">Player 2</div></div>
        @for($set = 1; $set <= 5; $set++)
          <div class="row g-2 align-items-center mb-2 score-set-row">
            <div class="col"><input type="number" min="0" max="20" inputmode="numeric" class="form-control score-input" data-side="home" data-set="{{ $set }}" aria-label="Set {{ $set }} home score"></div>
            <div class="col-auto text-muted small">Set {{ $set }}</div>
            <div class="col"><input type="number" min="0" max="20" inputmode="numeric" class="form-control score-input" data-side="away" data-set="{{ $set }}" aria-label="Set {{ $set }} away score"></div>
          </div>
        @endfor
      </div>
      <div class="modal-footer d-flex flex-nowrap">
        <button type="button" class="btn btn-outline-danger me-auto d-none" id="score-clear">Clear result</button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary" id="score-save">Save score</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-nav-select]').forEach(function (select) {
    select.addEventListener('change', function () {
      if (select.value) window.location.assign(select.value);
    });
  });

  const venueStorageKey = 'cape-tennis.scoring.venue.{{ $event->id }}';
  const selectedVenue = @json($selectedVenue?->id);
  const availableVenues = @json($venues->pluck('id')->map(fn($id) => (int) $id)->values());
  const forceAllVenues = @json(request()->boolean('all_venues'));
  if (forceAllVenues) {
    localStorage.removeItem(venueStorageKey);
  } else if (selectedVenue) {
    localStorage.setItem(venueStorageKey, String(selectedVenue));
  } else {
    const rememberedVenue = Number(localStorage.getItem(venueStorageKey));
    if (rememberedVenue && availableVenues.includes(rememberedVenue)) {
      const target = new URL(window.location.href);
      target.searchParams.set('venue', rememberedVenue);
      window.location.replace(target.toString());
      return;
    }
  }

  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const modalElement = document.getElementById('score-entry-modal');
  const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
  const form = document.getElementById('score-entry-form');
  const error = document.getElementById('score-entry-error');
  const clearButton = document.getElementById('score-clear');
  let active = null;

  document.querySelectorAll('[data-score-filter]').forEach(function (button) {
    button.addEventListener('click', function () {
      const filter = button.dataset.scoreFilter;
      document.querySelectorAll('[data-score-filter]').forEach(function (item) {
        const active = item === button;
        item.classList.toggle('btn-primary', active);
        item.classList.toggle('btn-outline-primary', !active);
        item.setAttribute('aria-pressed', String(active));
      });
      let visible = 0;
      document.querySelectorAll('[data-score-state]').forEach(function (card) {
        const matches = filter === 'all'
          || (filter === 'outstanding' && card.dataset.scoreState !== 'completed')
          || (filter === 'now' && card.dataset.scoreState === 'playing')
          || card.dataset.scoreState === filter
          || card.dataset.scoreTiming === filter;
        card.classList.toggle('d-none', !matches);
        if (matches) visible++;
      });
      document.getElementById('score-visible-count').textContent = visible;
      document.getElementById('score-visible-label').textContent = filter === 'all' ? 'matches' : filter.replace('now', 'playing now');
      document.getElementById('score-filter-empty').classList.toggle('d-none', visible !== 0);
      button.scrollIntoView({behavior: 'smooth', block: 'nearest', inline: 'center'});
    });
  });

  document.querySelectorAll('.js-open-score').forEach(function (button) {
    button.addEventListener('click', function () {
      active = button.dataset;
      error.classList.add('d-none');
      document.getElementById('score-match-title').textContent = active.home + ' vs ' + active.away;
      document.getElementById('score-home-label').textContent = active.home;
      document.getElementById('score-away-label').textContent = active.away;
      const scores = JSON.parse(active.scores || '[]');
      document.querySelectorAll('.score-input').forEach(input => input.value = '');
      scores.forEach(function (set, index) {
        const row = index + 1;
        document.querySelector('[data-side="home"][data-set="' + row + '"]').value = set[0];
        document.querySelector('[data-side="away"][data-set="' + row + '"]').value = set[1];
      });
      clearButton.classList.toggle('d-none', scores.length === 0);
      modal.show();
    });
  });

  document.querySelectorAll('.js-start-match').forEach(function (button) {
    button.addEventListener('click', async function () {
      button.disabled = true;
      try {
        await request(button.dataset.playingUrl, 'POST', {});
        window.location.reload();
      } catch (failure) {
        alert(failure.message);
        button.disabled = false;
      }
    });
  });

  function setsFromForm() {
    const sets = [];
    for (let set = 1; set <= 5; set++) {
      const home = document.querySelector('[data-side="home"][data-set="' + set + '"]').value.trim();
      const away = document.querySelector('[data-side="away"][data-set="' + set + '"]').value.trim();
      if (home === '' && away === '') continue;
      if (home === '' || away === '') throw new Error('Complete both scores for set ' + set + '.');
      sets.push([Number(home), Number(away)]);
    }
    if (!sets.length) throw new Error('Enter at least one completed set.');
    return sets;
  }

  async function request(url, method, body) {
    const response = await fetch(url, {
      method: method,
      credentials: 'same-origin',
      headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json'},
      body: body === undefined ? undefined : JSON.stringify(body)
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const validation = data.errors ? Object.values(data.errors).flat().join(' ') : null;
      const exception = new Error(validation || data.message || 'The score could not be saved.');
      exception.status = response.status;
      throw exception;
    }
    return data;
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (!active) return;
    error.classList.add('d-none');
    const save = document.getElementById('score-save');
    save.disabled = true;
    try {
      const sets = setsFromForm();
      if (active.engine === 'flexible') {
        try {
          await request(active.store, 'PUT', {sets: sets, revision: Number(active.revision)});
        } catch (failure) {
          if (failure.status !== 409 || !failure.message.includes('later scored matches') || !confirm(failure.message + '\n\nReset those later results and continue?')) throw failure;
          await request(active.store, 'PUT', {sets: sets, revision: Number(active.revision), reset_dependents: true});
        }
      } else if (active.engine === 'team') {
        const teamPayload = {};
        sets.forEach(function (set, index) {
          teamPayload['set' + (index + 1) + '_home'] = set[0];
          teamPayload['set' + (index + 1) + '_away'] = set[1];
        });
        await request(active.store, 'POST', teamPayload);
      } else {
        await request(active.store, 'POST', {sets: sets.map(set => set[0] + '-' + set[1])});
      }
      window.location.reload();
    } catch (failure) {
      error.textContent = failure.message;
      error.classList.remove('d-none');
      save.disabled = false;
    }
  });

  clearButton.addEventListener('click', async function () {
    if (!active || !confirm('Clear this result? The action will be recorded.')) return;
    clearButton.disabled = true;
    error.classList.add('d-none');
    try {
      if (active.engine === 'flexible') {
        await request(active.delete, 'PUT', {sets: null, revision: Number(active.revision)});
      } else {
        await request(active.delete, 'DELETE');
      }
      window.location.reload();
    } catch (failure) {
      error.textContent = failure.message;
      error.classList.remove('d-none');
      clearButton.disabled = false;
    }
  });
});
</script>
@endsection
