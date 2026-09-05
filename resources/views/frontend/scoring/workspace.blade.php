@extends('layouts/layoutMaster')

@section('title', 'Venue scoring — ' . $event->name)

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
  .scoring-shell { max-width: 1100px; margin-inline: auto; }
  .scoring-hero { background: linear-gradient(135deg, #004177, #087ea4); color: #fff; border: 0; }
  .scoring-progress { height: .65rem; background: rgba(255,255,255,.25); }
  .scoring-progress .progress-bar { background: #7ee2a8; }
  .scoring-filter { min-height: 44px; flex: 0 0 auto; white-space: nowrap; }
  .scoring-filter-card .card-body { display: grid; gap: 1rem; min-width: 0; }
  .scoring-filter-card .card-body > div { min-width: 0; }
  .scoring-filter-label { color: #53657a; font-size: .78rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
  .scoring-option-strip, .scoring-status-strip { display: flex; flex-wrap: wrap; gap: .5rem; min-width: 0; }
  .scoring-option-strip > * { flex: 0 0 auto; white-space: nowrap; }
  .scoring-queue-toolbar { display: flex; align-items: center; gap: .75rem; }
  .scoring-queue-summary { margin-left: auto; color: #6c7a8c; font-size: .875rem; white-space: nowrap; }
  .match-card { border-left: 5px solid #f0ad4e; }
  .match-card.is-completed { border-left-color: #28a745; }
  .match-card.is-waiting { border-left-color: #adb5bd; }
  .match-card-header { display: flex; justify-content: space-between; align-items: flex-start; gap: .75rem; }
  .match-meta { display: flex; flex-wrap: wrap; gap: .35rem .8rem; color: #6c757d; font-size: .86rem; }
  .match-status { flex: 0 0 auto; }
  .match-players { display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); align-items: center; gap: .75rem; }
  .match-player { font-size: 1rem; font-weight: 650; min-width: 0; overflow-wrap: anywhere; }
  .match-player:last-child { text-align: right; }
  .match-versus { color: #7c8998; font-size: .8rem; font-weight: 700; text-transform: uppercase; }
  .match-score { font-size: 1.08rem; font-weight: 750; color: #004177; }
  .score-action { min-height: 46px; }
  .score-input { min-height: 48px; font-size: 1.05rem; text-align: center; }
  .venue-pill, .draw-pill { min-height: 44px; display: inline-flex; align-items: center; }
  .operator-summary { cursor: pointer; list-style: none; }
  .operator-summary::-webkit-details-marker { display: none; }
  .operator-summary::after { content: 'Change'; margin-left: auto; color: var(--bs-primary); font-size: .82rem; font-weight: 700; }
  #score-filter-empty { border: 1px dashed #c9d4df; background: #fff; }
  @media (max-width: 575.98px) {
    .container-xxl { padding-inline: .75rem; }
    .scoring-shell { margin-inline: 0; }
    .scoring-title { font-size: 1.3rem; }
    .scoring-hero .card-body { padding: 1rem !important; }
    .scoring-hero .btn { min-height: 40px; }
    .scoring-filter-card { margin-inline: -.75rem; border-radius: 0; border-inline: 0; }
    .scoring-filter-card .card-body { padding-inline: .75rem !important; }
    .scoring-option-strip, .scoring-status-strip {
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
    .match-card .card-body { padding: .9rem; }
    .match-card-header { display: block; }
    .match-status { display: inline-flex; margin-top: .65rem; }
    .match-players { grid-template-columns: minmax(0, 1fr); gap: .45rem; text-align: left; }
    .match-player:last-child { text-align: left; }
    .match-versus { display: flex; align-items: center; gap: .5rem; }
    .match-versus::before, .match-versus::after { content: ''; height: 1px; background: #dde3ea; flex: 1; }
    .match-score { text-align: left !important; }
    #score-entry-modal .modal-dialog { margin: 0; padding: 0; width: 100%; min-height: 100%; }
    #score-entry-modal .modal-content { width: 100%; min-height: 100vh; min-height: 100dvh; border: 0; border-radius: 0; }
    #score-entry-modal .modal-body { overflow-y: auto; }
    .modal-footer { padding-bottom: max(1rem, env(safe-area-inset-bottom)); }
  }
</style>

<div class="container-xxl py-3 py-md-4">
  <div class="scoring-shell">
    <div class="card scoring-hero shadow-sm mb-3">
      <div class="card-body p-3 p-md-4">
        <div class="d-flex justify-content-between align-items-start gap-3">
          <div>
            <div class="small text-white-50 text-uppercase fw-semibold">Venue scoring</div>
            <h1 class="scoring-title h3 mb-1">{{ $event->name }}</h1>
            <div class="small text-white-50">
              {{ $selectedVenue?->name ?? 'All scheduled venues' }}
              @if($selectedDraw) · {{ $selectedDraw->drawName }} @endif
            </div>
          </div>
          <a href="{{ route('events.show', $event) }}" class="btn btn-sm btn-light">Tournament</a>
        </div>

        @php
          $progress = $matches->count() ? (int) round(($completed / $matches->count()) * 100) : 0;
        @endphp
        <div class="d-flex justify-content-between mt-3 mb-1 small">
          <span><strong>{{ $completed }}</strong> of {{ $matches->count() }} entered</span>
          <span>{{ $progress }}%</span>
        </div>
        <div class="progress scoring-progress" role="progressbar" aria-label="Scoring progress" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar" style="width: {{ $progress }}%"></div>
        </div>
      </div>
    </div>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-3">
      @if($operatorName)
        <details>
          <summary class="operator-summary card-body p-3 d-flex align-items-center gap-2">
            <i class="ti ti-device-mobile text-primary" aria-hidden="true"></i>
            <span><span class="text-muted">Scoring as</span> <strong>{{ $operatorName }}</strong></span>
          </summary>
          <div class="card-body border-top p-3">
      @else
          <div class="card-body p-3">
      @endif
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
      @if($operatorName)
          </div>
        </details>
      @else
        </div>
      @endif
    </div>

    <div class="card scoring-filter-card mb-3">
      <div class="card-body p-3">
        <div>
          <div class="scoring-filter-label mb-2" id="venue-filter-label">Venue</div>
          <nav class="scoring-option-strip" aria-labelledby="venue-filter-label">
            <a class="btn btn-sm venue-pill {{ !$selectedVenue ? 'btn-primary' : 'btn-outline-primary' }}"
               @if(!$selectedVenue) aria-current="true" @endif
               href="{{ route('frontend.scoring.workspace', ['event' => $event, 'draw' => $selectedDraw?->id, 'all_venues' => 1]) }}">All venues</a>
            @foreach($venues as $venue)
              <a class="btn btn-sm venue-pill {{ $selectedVenue?->id === $venue->id ? 'btn-primary' : 'btn-outline-primary' }}"
                 @if($selectedVenue?->id === $venue->id) aria-current="true" @endif
                 href="{{ route('frontend.scoring.workspace', ['event' => $event, 'venue' => $venue->id, 'draw' => $selectedDraw?->id]) }}">
                {{ $venue->name }}
              </a>
            @endforeach
          </nav>
        </div>

        <div>
          <div class="scoring-filter-label mb-2" id="draw-filter-label">Draw</div>
          <nav class="scoring-option-strip" aria-labelledby="draw-filter-label">
            <a class="btn btn-sm draw-pill {{ !$selectedDraw ? 'btn-dark' : 'btn-outline-dark' }}"
               @if(!$selectedDraw) aria-current="true" @endif
               href="{{ route('frontend.scoring.workspace', ['event' => $event, 'venue' => $selectedVenue?->id]) }}">All draws</a>
            @foreach($draws as $draw)
              <a class="btn btn-sm draw-pill {{ $selectedDraw?->id === $draw->id ? 'btn-dark' : 'btn-outline-dark' }}"
                 @if($selectedDraw?->id === $draw->id) aria-current="true" @endif
                 href="{{ route('frontend.scoring.workspace', ['event' => $event, 'venue' => $selectedVenue?->id, 'draw' => $draw->id]) }}">
                {{ $draw->drawName }}
              </a>
            @endforeach
          </nav>
        </div>
      </div>
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

    <div id="score-match-list" class="d-grid gap-3">
      @forelse($matches as $match)
        @php
          $isTeamFixture = $match instanceof \App\Models\TeamFixture;
          $draw = $match->draw;
          $hasScore = $match->fixtureResults->isNotEmpty();
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
            $engine = $isFlexible ? 'flexible' : 'standard';
          }
          $flexibleUrl = $isFlexible ? route('flexible-monrad.score', ['draw' => $match->draw_id, 'fixture' => $match->id]) : null;
          $scheduledMoment = $scheduleTime ? \Carbon\Carbon::parse($scheduleTime) : null;
          $timing = $scheduledMoment && !$hasScore
            ? ($scheduledMoment->isFuture() ? 'upcoming' : ($scheduledMoment->copy()->addMinutes(120)->isFuture() ? 'now' : 'past'))
            : ($hasScore ? 'completed' : 'unscheduled');
        @endphp
        <article class="card match-card {{ $hasScore ? 'is-completed' : ($hasPlayers ? '' : 'is-waiting') }}"
                 data-score-state="{{ $hasScore ? 'completed' : 'outstanding' }}" data-score-timing="{{ $timing }}">
          <div class="card-body">
            <div class="match-card-header mb-2">
              <div class="match-meta">
                @if($scheduleTime)<span><i class="ti ti-clock"></i> {{ \Carbon\Carbon::parse($scheduleTime)->format('D H:i') }}</span>@endif
                @if($venueName)<span><i class="ti ti-map-pin"></i> {{ $venueName }}</span>@endif
                @if($court)<span>Court {{ $court }}</span>@endif
              </div>
              <span class="badge match-status {{ $hasScore ? 'bg-label-success' : ($hasPlayers ? 'bg-label-warning' : 'bg-label-secondary') }}">
                {{ $hasScore ? 'Completed' : ($hasPlayers ? 'Awaiting score' : 'Waiting for players') }}
              </span>
            </div>
            <div class="small text-muted mb-2">{{ $draw->drawName }} · {{ $stageLabel }} · Match {{ $matchNumber }}</div>
            <div class="match-players">
              <div class="match-player">{{ $home }}</div>
              <div class="match-versus">vs</div>
              <div class="match-player">{{ $away }}</div>
            </div>
            <div class="match-score text-center mt-2">
              {{ $hasScore ? $sets->map(fn($set) => $set[0].'–'.$set[1])->implode('  ') : 'No score entered' }}
            </div>
            @if($canWrite && $hasPlayers)
              <button type="button" class="btn btn-primary score-action w-100 mt-3 js-open-score"
                      data-fixture="{{ $match->id }}" data-home="{{ $home }}" data-away="{{ $away }}"
                      data-engine="{{ $engine }}"
                      data-store="{{ $isFlexible ? $flexibleUrl : $normalStore }}"
                      data-delete="{{ $isFlexible ? $flexibleUrl : $normalDelete }}"
                      data-revision="{{ $draw->flexibleMonrad?->revision ?? 0 }}"
                      data-scores='@json($sets)'>
                {{ $hasScore ? 'Correct score' : 'Enter score' }}
              </button>
            @elseif($draw->locked)
              <div class="alert alert-secondary py-2 px-3 mt-3 mb-0 small">This draw is locked.</div>
            @endif
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
