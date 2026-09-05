@extends('layouts/layoutMaster')

@section('title', 'Venue scoring — ' . $event->name)

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<style>
  .scoring-shell { max-width: 980px; margin-inline: auto; }
  .scoring-hero { background: linear-gradient(135deg, #004177, #087ea4); color: #fff; border: 0; }
  .scoring-progress { height: .65rem; background: rgba(255,255,255,.25); }
  .scoring-progress .progress-bar { background: #7ee2a8; }
  .scoring-filter { min-height: 42px; }
  .match-card { border-left: 5px solid #f0ad4e; }
  .match-card.is-completed { border-left-color: #28a745; }
  .match-card.is-waiting { border-left-color: #adb5bd; }
  .match-meta { display: flex; flex-wrap: wrap; gap: .4rem .8rem; color: #6c757d; font-size: .86rem; }
  .match-player { font-size: 1rem; font-weight: 650; min-width: 0; }
  .match-score { font-size: 1.08rem; font-weight: 750; color: #004177; }
  .score-action { min-height: 46px; }
  .score-input { min-height: 48px; font-size: 1.05rem; text-align: center; }
  .venue-pill, .draw-pill { min-height: 42px; display: inline-flex; align-items: center; }
  @media (max-width: 575.98px) {
    .scoring-shell { margin-inline: -.25rem; }
    .scoring-title { font-size: 1.35rem; }
    .match-card .card-body { padding: .9rem; }
    .modal-dialog { margin: 0; min-height: 100%; }
    .modal-content { min-height: 100vh; border: 0; border-radius: 0; }
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

        @php($progress = $fixtures->count() ? (int) round(($completed / $fixtures->count()) * 100) : 0)
        <div class="d-flex justify-content-between mt-3 mb-1 small">
          <span><strong>{{ $completed }}</strong> of {{ $fixtures->count() }} entered</span>
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
      <div class="card-body p-3">
        <form method="POST" action="{{ route('frontend.scoring.operator', $event) }}" class="row g-2 align-items-end">
          @csrf
          <div class="col-12 col-sm">
            <label for="scoring-operator" class="form-label fw-semibold mb-1">Who is using this telephone?</label>
            <input id="scoring-operator" name="operator" class="form-control" maxlength="80" required
                   value="{{ old('operator', $operatorName) }}" placeholder="Name or initials">
            @error('operator')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
          </div>
          <div class="col-12 col-sm-auto">
            <button class="btn btn-outline-primary w-100" type="submit">Remember on this telephone</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-body p-3">
        <div class="fw-semibold mb-2">Choose venue</div>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-sm venue-pill {{ !$selectedVenue ? 'btn-primary' : 'btn-outline-primary' }}"
             href="{{ route('frontend.scoring.workspace', ['event' => $event, 'draw' => $selectedDraw?->id]) }}">All venues</a>
          @foreach($venues as $venue)
            <a class="btn btn-sm venue-pill {{ $selectedVenue?->id === $venue->id ? 'btn-primary' : 'btn-outline-primary' }}"
               href="{{ route('frontend.scoring.workspace', ['event' => $event, 'venue' => $venue->id, 'draw' => $selectedDraw?->id]) }}">
              {{ $venue->name }}
            </a>
          @endforeach
        </div>

        <div class="fw-semibold mt-3 mb-2">Limit to draw</div>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-sm draw-pill {{ !$selectedDraw ? 'btn-dark' : 'btn-outline-dark' }}"
             href="{{ route('frontend.scoring.workspace', ['event' => $event, 'venue' => $selectedVenue?->id]) }}">All draws</a>
          @foreach($draws as $draw)
            <a class="btn btn-sm draw-pill {{ $selectedDraw?->id === $draw->id ? 'btn-dark' : 'btn-outline-dark' }}"
               href="{{ route('frontend.scoring.workspace', ['event' => $event, 'venue' => $selectedVenue?->id, 'draw' => $draw->id]) }}">
              {{ $draw->drawName }}
            </a>
          @endforeach
        </div>
      </div>
    </div>

    <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Filter matches">
      <button type="button" class="btn btn-primary scoring-filter" data-score-filter="outstanding">Outstanding</button>
      <button type="button" class="btn btn-outline-primary scoring-filter" data-score-filter="completed">Completed</button>
      <button type="button" class="btn btn-outline-primary scoring-filter" data-score-filter="all">All</button>
      <span class="ms-auto align-self-center small text-muted">{{ $ready }} matches have both players</span>
    </div>

    <div id="score-match-list" class="d-grid gap-3">
      @forelse($fixtures as $fixture)
        @php
          $schedule = $fixture->orderOfPlay;
          $hasScore = $fixture->fixtureResults->isNotEmpty();
          $hasPlayers = $fixture->registration1_id && $fixture->registration2_id;
          $home = $fixture->registration1?->players?->first()?->full_name ?? 'To be decided';
          $away = $fixture->registration2?->players?->first()?->full_name ?? 'To be decided';
          $sets = $fixture->fixtureResults->sortBy('set_nr')->map(fn($set) => [(int) $set->registration1_score, (int) $set->registration2_score])->values();
          $isFlexible = $fixture->draw->usesFlexibleMonrad();
          $canWrite = auth()->user()->can('saveScore', $fixture->draw)
            && !$fixture->draw->locked
            && ($isFlexible || !$fixture->draw->published || $fixture->stage === 'RR');
          $normalStore = route('api.draws.fixtures.score.store', ['draw' => $fixture->draw_id, 'fixture' => $fixture->id]);
          $normalDelete = route('api.draws.fixtures.score.delete', ['draw' => $fixture->draw_id, 'fixture' => $fixture->id]);
          $flexibleUrl = $isFlexible ? route('flexible-monrad.score', ['draw' => $fixture->draw_id, 'fixture' => $fixture->id]) : null;
        @endphp
        <article class="card match-card {{ $hasScore ? 'is-completed' : ($hasPlayers ? '' : 'is-waiting') }}"
                 data-score-state="{{ $hasScore ? 'completed' : 'outstanding' }}">
          <div class="card-body">
            <div class="d-flex justify-content-between gap-2 align-items-start mb-2">
              <div class="match-meta">
                @if($schedule?->time)<span><i class="ti ti-clock"></i> {{ \Carbon\Carbon::parse($schedule->time)->format('D H:i') }}</span>@endif
                @if($schedule?->venue)<span><i class="ti ti-map-pin"></i> {{ $schedule->venue->name }}</span>@endif
                @if($schedule?->court)<span>Court {{ $schedule->court }}</span>@endif
              </div>
              <span class="badge {{ $hasScore ? 'bg-label-success' : ($hasPlayers ? 'bg-label-warning' : 'bg-label-secondary') }}">
                {{ $hasScore ? 'Completed' : ($hasPlayers ? 'Awaiting score' : 'Waiting for players') }}
              </span>
            </div>
            <div class="small text-muted mb-2">{{ $fixture->draw->drawName }} · {{ $fixture->stage ?: 'Draw' }} · Match {{ $fixture->match_nr ?: $fixture->id }}</div>
            <div class="row g-2 align-items-center">
              <div class="col match-player">{{ $home }}</div>
              <div class="col-auto text-muted">vs</div>
              <div class="col match-player text-end">{{ $away }}</div>
            </div>
            <div class="match-score text-center mt-2">
              {{ $hasScore ? $sets->map(fn($set) => $set[0].'–'.$set[1])->implode('  ') : 'No score entered' }}
            </div>
            @if($canWrite && $hasPlayers)
              <button type="button" class="btn btn-primary score-action w-100 mt-3 js-open-score"
                      data-fixture="{{ $fixture->id }}" data-home="{{ $home }}" data-away="{{ $away }}"
                      data-engine="{{ $isFlexible ? 'flexible' : 'standard' }}"
                      data-store="{{ $isFlexible ? $flexibleUrl : $normalStore }}"
                      data-delete="{{ $isFlexible ? $flexibleUrl : $normalDelete }}"
                      data-revision="{{ $fixture->draw->flexibleMonrad?->revision ?? 0 }}"
                      data-scores='@json($sets)'>
                {{ $hasScore ? 'Correct score' : 'Enter score' }}
              </button>
            @elseif($fixture->draw->locked)
              <div class="alert alert-secondary py-2 px-3 mt-3 mb-0 small">This draw is locked.</div>
            @elseif($fixture->draw->published && !$isFlexible && $fixture->stage !== 'RR')
              <div class="alert alert-secondary py-2 px-3 mt-3 mb-0 small">Published bracket scores must be managed from the draw workspace.</div>
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
      document.querySelectorAll('[data-score-filter]').forEach(item => item.className = 'btn btn-outline-primary scoring-filter');
      button.className = 'btn btn-primary scoring-filter';
      document.querySelectorAll('[data-score-state]').forEach(function (card) {
        card.classList.toggle('d-none', filter !== 'all' && card.dataset.scoreState !== filter);
      });
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
