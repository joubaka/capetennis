@extends('layouts/layoutMaster')

@section('title', 'Venue scoring — ' . $event->name)

@section('page-style')
<style>
  .scoring-shell { max-width: 1280px; margin-inline: auto; }
  .scoring-shell.is-refreshing { opacity: .65; pointer-events: none; }
  .scoring-hero {
    position: relative;
    overflow: hidden;
    color: #fff;
    background: linear-gradient(135deg, var(--ct-ink, #172e45) 0%, #0e5360 72%, var(--ct-accent, #14796e) 145%);
    border: 0;
  }
  .scoring-hero::after {
    position: absolute;
    top: -7rem;
    right: -4.5rem;
    width: 13rem;
    height: 13rem;
    border: 1px solid rgba(255, 255, 255, .16);
    border-radius: 50%;
    content: '';
  }
  .scoring-hero-main { position: relative; z-index: 1; display: grid; grid-template-columns: minmax(0, 1fr) minmax(12rem, 17rem) auto; align-items: center; gap: 1.5rem; }
  .scoring-hero-copy { min-width: 0; }
  .scoring-hero-copy .scoring-title { color: #fff !important; }
  .scoring-hero-eyebrow { color: rgba(255, 255, 255, .72); font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
  .scoring-hero-context { color: rgba(255, 255, 255, .8); }
  .scoring-venue { display: block; color: #fff; font-size: 1.05rem; font-weight: 700; overflow-wrap: anywhere; }
  .scoring-hero-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .5rem; }
  .scoring-hero-actions .btn { min-height: 44px; white-space: nowrap; }
  .scoring-hero .btn-light { color: var(--ct-ink, #172e45); }
  .scoring-hero .btn-outline-light { color: #fff; border-color: rgba(255, 255, 255, .55); background: rgba(255, 255, 255, .06); }
  .scoring-hero .btn-outline-light:hover { color: var(--ct-ink, #172e45) !important; background: #fff !important; border-color: #fff !important; }
  .scoring-progress { height: .45rem; background: rgba(255,255,255,.22); }
  .scoring-progress .progress-bar { background: #78d8b2; }
  .scoring-section-heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; }
  .scoring-filter { min-height: 44px; flex: 0 0 auto; white-space: nowrap; padding-inline: .9rem; padding-block: .45rem; }
  .scoring-select-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
  .scoring-select-grid > div { min-width: 0; }
  .scoring-context-summary { display: flex; align-items: center; justify-content: space-between; gap: .75rem; min-height: 52px; cursor: pointer; list-style: none; }
  .scoring-context-summary::-webkit-details-marker { display: none; }
  .scoring-context-summary:focus-visible { outline: 2px solid var(--ct-accent, #14796e); outline-offset: -2px; }
  .scoring-context-summary .ti-chevron-down { transition: transform .2s ease; }
  .scoring-filter-card[open] > .scoring-context-summary .ti-chevron-down { transform: rotate(180deg); }
  .scoring-filter-label { color: var(--ct-muted, #66788a); font-size: .72rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; }
  .scoring-select { min-height: 44px; font-weight: 600; color: var(--ct-ink, #172e45); }
  .scoring-status-strip { display: flex; flex-wrap: wrap; gap: .4rem; min-width: 0; }
  .scoring-operator { border-top: 1px solid var(--ct-border, #e1e8ee); }
  .scoring-operator .operator-summary { min-height: 52px; padding: .7rem 1rem; }
  .operator-change { margin-left: auto; color: var(--ct-accent, #14796e); font-size: .82rem; font-weight: 700; }
  .scoring-operator .operator-summary > span { min-width: 0; overflow-wrap: anywhere; }
  .scoring-operator .operator-change { flex-shrink: 0; }
  .scoring-operator .form-control, .scoring-operator .btn { min-height: 44px; }
  .scoring-queue-toolbar { display: flex; align-items: center; gap: .75rem; padding: .55rem; background: var(--ct-soft, #eef3f6); border: 1px solid var(--ct-border, #e1e8ee); border-radius: 12px; }
  .scoring-queue-summary { color: var(--ct-muted, #66788a); font-size: .875rem; white-space: nowrap; }
  .match-card { border-left: 5px solid #8aa0b2; transition: background-color .2s ease, border-color .2s ease; }
  .match-card.is-playing { background: #fff8ed; border-left-color: #d98b10; }
  .match-card.is-completed { background: #f0f8f5; border-left-color: var(--ct-accent, #14796e); }
  .match-card.is-waiting { border-left-color: #aebbc6; }
  .match-card .card-body { padding: .85rem 1rem; }
  .match-card-header { display: flex; justify-content: space-between; align-items: center; gap: .75rem; margin-bottom: .4rem; }
  .match-meta { display: flex; flex-wrap: wrap; gap: .35rem .8rem; color: var(--ct-muted, #66788a); font-size: .86rem; }
  .match-status { flex: 0 0 auto; }
  .match-status-light { display: inline-block; width: .55rem; height: .55rem; margin-right: .3rem; border-radius: 50%; background: currentColor; vertical-align: .03rem; }
  .match-card-main { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; align-items: center; gap: 1rem; }
  .match-identity { min-width: 0; }
  .match-players { display: flex; align-items: baseline; gap: .55rem; min-width: 0; }
  .match-player { font-size: .96rem; font-weight: 650; min-width: 0; overflow-wrap: anywhere; }
  .match-versus { color: var(--ct-muted, #66788a); font-size: .8rem; font-weight: 700; text-transform: uppercase; }
  .match-score { min-width: 90px; font-size: .96rem; font-weight: 750; color: var(--ct-ink, #172e45); text-align: right; }
  .match-score.is-empty { color: var(--ct-muted, #66788a); font-size: .82rem; font-weight: 600; }
  .score-action { min-height: 44px; white-space: normal; }
  .match-actions { display: flex; align-items: center; justify-content: flex-end; gap: .45rem; }
  .court-label.is-playing { color: #8b5600; font-weight: 750; }
  .court-label.is-completed { color: var(--ct-accent, #14796e); font-weight: 750; }
  .score-input { min-height: 48px; font-size: 1.05rem; text-align: center; }
  .operator-summary { cursor: pointer; list-style: none; }
  .operator-summary::-webkit-details-marker { display: none; }
  #score-filter-empty { border: 1px dashed var(--ct-border, #e1e8ee); background: var(--ct-surface, #fff); }
  .next-court-panel { width: min(92vw, 390px) !important; }
  .next-court-match { border-left: 4px solid var(--ct-accent, #14796e); }
  .next-court-match .card-body, .next-court-panel .offcanvas-header > div { min-width: 0; overflow-wrap: anywhere; }
  .next-court-match .badge { flex-shrink: 0; align-self: flex-start; }
  #score-entry-modal .modal-header > div { min-width: 0; }
  #score-match-title, #score-home-label, #score-away-label { overflow-wrap: anywhere; }
  #score-entry-modal .btn { min-height: 44px; white-space: normal; }
  #score-entry-modal .btn-close, .next-court-panel .btn-close { min-width: 44px; min-height: 44px; padding: 0; margin: 0; flex-shrink: 0; }
  .scoring-activity summary { min-height: 54px; cursor: pointer; list-style: none; }
  .scoring-activity summary::-webkit-details-marker { display: none; }
  @media (max-width: 991.98px) {
    .scoring-hero-main { grid-template-columns: minmax(0, 1fr) minmax(12rem, 16rem); }
    .scoring-hero-actions { grid-column: 1 / -1; justify-content: flex-start; }
  }
  @media (max-width: 575.98px) {
    .scoring-shell { margin-inline: 0; }
    .scoring-title { font-size: 1rem; line-height: 1.35; overflow-wrap: anywhere; }
    .scoring-hero-eyebrow { display: none; }
    .scoring-venue { font-size: 1.2rem; line-height: 1.3; margin-bottom: .2rem; }
    .scoring-hero { margin-bottom: 1rem !important; }
    .scoring-hero .card-body { padding: 1rem !important; }
    .scoring-hero-main { grid-template-columns: minmax(0, 1fr); gap: .75rem; }
    .scoring-hero-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .scoring-hero-actions .btn { min-width: 0; white-space: normal; padding-inline: .5rem; }
    .scoring-hero-actions .scoring-next-action { grid-column: 1 / -1; grid-row: 1; }
    .scoring-hero-actions .btn:only-child { grid-column: 1 / -1; }
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
    .scoring-section-heading { display: block; }
    .scoring-queue-summary { margin-top: .35rem; white-space: normal; }
    .scoring-queue-toolbar { margin-inline: -.75rem; padding-inline: .75rem; overflow: hidden; border-inline: 0; border-radius: 0; }
    .match-card-header { align-items: flex-start; flex-wrap: wrap; }
    .match-meta { min-width: 0; overflow-wrap: anywhere; }
    .match-card-main { grid-template-columns: minmax(0, 1fr); gap: .65rem; }
    .match-identity { grid-column: 1 / -1; }
    .match-players { display: grid; grid-template-columns: minmax(0, 1fr); gap: .15rem; }
    .match-score { min-width: 0; text-align: left; }
    .match-actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); width: 100%; }
    .match-actions .btn { min-width: 0; padding-inline: .5rem; }
    .match-actions .btn:only-child { grid-column: 1 / -1; }
    #score-entry-modal .modal-dialog { margin: 0; padding: 0; width: 100%; height: 100vh; height: 100dvh; min-height: 0; }
    #score-entry-modal .modal-content { width: 100%; height: 100%; min-height: 0; border: 0; border-radius: 0; }
    #score-entry-modal .modal-header { padding: .75rem; flex-shrink: 0; align-items: flex-start; }
    #score-entry-modal .modal-title { font-size: 1rem; line-height: 1.35; }
    #score-entry-modal .modal-body { min-height: 0; overflow-y: auto; padding: .75rem; }
    #score-entry-modal .modal-footer { display: grid !important; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem; padding: .75rem; padding-bottom: max(.75rem, env(safe-area-inset-bottom)); flex-shrink: 0; }
    #score-entry-modal .modal-footer > * { margin: 0 !important; min-width: 0; }
    #score-clear { grid-column: 1 / -1; grid-row: 2; }
  }
</style>
@endsection

@section('content')
<meta name="csrf-token" content="{{ csrf_token() }}">

<div class="container-xxl py-3 py-md-4">
  <div class="scoring-shell"
       data-selected-venue="{{ $selectedVenue?->id }}"
       data-available-venues='@json($venues->pluck('id')->map(fn($id) => (int) $id)->values())'
       data-force-all-venues="{{ request()->boolean('all_venues') ? '1' : '0' }}">
    <div class="offcanvas offcanvas-end next-court-panel" tabindex="-1" id="next-on-court-panel"
         aria-labelledby="next-on-court-title">
      <div class="offcanvas-header border-bottom">
        <div>
          <div class="small text-muted text-uppercase fw-semibold">{{ $selectedVenue?->name ?? 'All venues' }}</div>
          <h2 class="offcanvas-title h5 mb-0" id="next-on-court-title">Next two matches</h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
      <div class="offcanvas-body bg-light">
        @forelse($nextMatches as $nextMatch)
          @php
            $nextIsTeam = $nextMatch instanceof \App\Models\TeamFixture;
            if ($nextIsTeam) {
              $nextHomePlayers = collect($nextMatch->lineup_display['home']['players'])->pluck('name');
              $nextAwayPlayers = collect($nextMatch->lineup_display['away']['players'])->pluck('name');
              $nextHome = $nextHomePlayers->isNotEmpty() ? $nextHomePlayers->implode(' + ') : ($nextMatch->homeTeam?->name ?? $nextMatch->region1Name?->name ?? 'To be decided');
              $nextAway = $nextAwayPlayers->isNotEmpty() ? $nextAwayPlayers->implode(' + ') : ($nextMatch->awayTeam?->name ?? $nextMatch->region2Name?->name ?? 'To be decided');
              $nextTime = $nextMatch->scheduled_at;
              $nextVenue = $nextMatch->venue?->name;
            } else {
              $nextHome = $nextMatch->registration1?->players?->first()?->full_name ?? 'Player 1';
              $nextAway = $nextMatch->registration2?->players?->first()?->full_name ?? 'Player 2';
              $nextTime = $nextMatch->orderOfPlay?->time;
              $nextVenue = $nextMatch->orderOfPlay?->venue?->name;
            }
            $nextNumber = $nextMatch->match_nr ?: ($nextIsTeam ? $nextMatch->home_rank_nr : null) ?: $nextMatch->id;
          @endphp
          <article class="card next-court-match shadow-sm mb-3" data-next-fixture="{{ $nextMatch->id }}">
            <div class="card-body">
              <div class="d-flex justify-content-between gap-2 mb-2">
                <strong>{{ $nextMatch->draw?->drawName }}</strong>
                <span class="badge bg-label-primary">Match {{ $nextNumber }}</span>
              </div>
              <div class="fw-semibold">{{ $nextHome }}</div>
              <div class="small text-muted my-1">versus</div>
              <div class="fw-semibold">{{ $nextAway }}</div>
              <div class="small text-muted mt-3 d-flex flex-wrap gap-2">
                @if($nextTime)<span><i class="ti ti-clock"></i> {{ \Carbon\Carbon::parse($nextTime)->format('D H:i') }}</span>@endif
                @if($nextVenue && !$selectedVenue)<span><i class="ti ti-map-pin"></i> {{ $nextVenue }}</span>@endif
              </div>
            </div>
          </article>
        @empty
          <div class="text-center py-5">
            <i class="ti ti-circle-check fs-1 text-success" aria-hidden="true"></i>
            <h3 class="h6 mt-3">No matches waiting to go on</h3>
            <p class="small text-muted mb-0">Playing, completed, unresolved and unscheduled matches are excluded.</p>
          </div>
        @endforelse
      </div>
    </div>

    <header class="card scoring-hero mb-4" aria-labelledby="scoring-workspace-title">
      <div class="card-body p-4">
        @php
          $progress = $matches->count() ? (int) round(($completed / $matches->count()) * 100) : 0;
        @endphp
        <div class="scoring-hero-main">
          <div class="scoring-hero-copy">
            <div class="scoring-hero-eyebrow mb-2">Tournament operations</div>
            <h1 class="scoring-title h3 mb-2" id="scoring-workspace-title">{{ $event->name }}</h1>
            <div class="scoring-hero-context small">
              <strong class="scoring-venue">{{ $selectedVenue?->name ?? ($selectedDraw ? $selectedDraw->drawName : 'All scheduled venues') }}</strong>
              <i class="ti ti-device-mobile me-1" aria-hidden="true"></i> {{ $selectedDraw && !$selectedVenue ? 'Draw scoring' : 'Venue scoring' }}
              @if($selectedDraw && $selectedVenue) · {{ $selectedDraw->drawName }} @endif
              @if($selectedDraw && !$selectedVenue) · No schedule required @endif
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
          <div class="scoring-hero-actions">
            @if($selectedVenue)
              <a href="{{ route('frontend.scoring.print', ['event' => $event, 'venue' => $selectedVenue, 'source' => $scheduleSource, 'date' => $scheduleDate]) }}"
                 class="btn btn-outline-light" target="_blank" rel="noopener" aria-label="Print options for {{ $selectedVenue->name }}">
                <i class="ti ti-printer me-1" aria-hidden="true"></i> Print options
              </a>
            @endif
            <a href="{{ route('events.show', $event) }}" class="btn btn-light">
              <i class="ti ti-arrow-left me-1" aria-hidden="true"></i> Tournament
            </a>
            <button type="button" class="btn btn-outline-light scoring-next-action d-flex align-items-center justify-content-center gap-2"
                    data-bs-toggle="offcanvas" data-bs-target="#next-on-court-panel"
                    aria-controls="next-on-court-panel">
              <i class="ti ti-player-play" aria-hidden="true"></i>
              <span>Next on court</span>
              <span class="badge bg-white text-primary">{{ $nextMatches->count() }}</span>
            </button>
          </div>
        </div>
      </div>
    </header>

    @if(session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <details class="card scoring-filter-card mb-4" aria-labelledby="scoring-context-title" @if($errors->has('operator')) open @endif>
      <summary class="card-header scoring-context-summary py-3">
        <span class="fw-semibold" id="scoring-context-title">Scoring context</span>
        <i class="ti ti-chevron-down" aria-hidden="true"></i>
      </summary>
      <div class="card-body p-3 border-top">
          <p class="small text-muted mb-0">{{ ($scheduleSource ?? 'working') === 'published' ? 'Published order of play · matches the public venue page and printed packs.' : 'Working schedule · includes saved changes awaiting publication.' }}</p>
          @if($scheduleDate ?? null)<p class="small mb-0">{{ \Carbon\Carbon::parse($scheduleDate)->format('l, d M Y') }}</p>@endif
      </div>
      <div class="card-body p-3">
        <div class="scoring-select-grid">
        <div>
          <label class="scoring-filter-label mb-1" for="venue-filter">Venue</label>
          <select class="form-select scoring-select" id="venue-filter" data-nav-select>
            @unless($venueRestricted ?? false)
              <option value="{{ route('frontend.scoring.workspace', ['event' => $event, 'schedule_source' => $scheduleSource ?? 'working', 'date' => $scheduleDate ?? null, 'draw_ids' => $scheduleDrawIds ?? [], 'draw' => $selectedDraw?->id, 'all_venues' => 1]) }}" @selected(!$selectedVenue)>{{ $selectedDraw ? 'All venues / unscheduled' : 'All venues' }}</option>
            @endunless
            @foreach($venues as $venue)
              <option value="{{ route('frontend.scoring.workspace', ['event' => $event, 'schedule_source' => $scheduleSource ?? 'working', 'date' => $scheduleDate ?? null, 'draw_ids' => $scheduleDrawIds ?? [], 'venue' => $venue->id, 'draw' => $selectedDraw?->id]) }}" @selected($selectedVenue?->id === $venue->id)>{{ $venue->name }}</option>
            @endforeach
          </select>
        </div>

        <div>
          <label class="scoring-filter-label mb-1" for="draw-filter">Draw</label>
          <select class="form-select scoring-select" id="draw-filter" data-nav-select>
            <option value="{{ route('frontend.scoring.workspace', ['event' => $event, 'schedule_source' => $scheduleSource ?? 'working', 'date' => $scheduleDate ?? null, 'draw_ids' => $scheduleDrawIds ?? [], 'venue' => $selectedVenue?->id]) }}" @selected(!$selectedDraw)>All draws</option>
            @foreach($draws as $draw)
              <option value="{{ route('frontend.scoring.workspace', ['event' => $event, 'schedule_source' => $scheduleSource ?? 'working', 'date' => $scheduleDate ?? null, 'draw_ids' => $scheduleDrawIds ?? [], 'venue' => $selectedVenue?->id, 'draw' => $draw->id]) }}" @selected($selectedDraw?->id === $draw->id)>{{ $draw->drawName }}</option>
            @endforeach
          </select>
        </div>
        </div>
      </div>
      <details class="scoring-operator" @if(!$operatorName || $errors->has('operator')) open @endif>
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
    </details>

    <section aria-labelledby="match-queue-title">
    <div class="scoring-section-heading mb-3 px-1">
      <div>
        <h2 class="h5 mb-1" id="match-queue-title">Match queue</h2>
        <p class="small text-muted mb-0">Start play, enter results, or review completed matches.</p>
      </div>
      <div class="scoring-queue-summary" aria-live="polite">
        <strong id="score-visible-count">{{ $matches->filter(fn($match) => $match->fixtureResults->isEmpty())->count() }}</strong>
        <span id="score-visible-label">outstanding</span>
        <span> · {{ $ready }} ready to score</span>
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
            $homePlayers = collect($match->lineup_display['home']['players'])->pluck('name');
            $awayPlayers = collect($match->lineup_display['away']['players'])->pluck('name');
            $home = $homePlayers->isNotEmpty() ? $homePlayers->implode(' + ') : ($match->homeTeam?->name ?? $match->region1Name?->name ?? 'To be decided');
            $away = $awayPlayers->isNotEmpty() ? $awayPlayers->implode(' + ') : ($match->awayTeam?->name ?? $match->region2Name?->name ?? 'To be decided');
            $hasPlayers = $match->fixturePlayers->isNotEmpty() || ($match->homeTeam && $match->awayTeam);
            $sets = $match->fixtureResults->sortBy('set_nr')->map(fn($set) => [(int) $set->team1_score, (int) $set->team2_score])->values();
            $scheduleTime = $match->scheduled_at;
            $venueName = $match->venue?->name;
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
          $canWrite = $canWrite && !$match->scoring_venue_changed;
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
                @unless($hasScore)
                <button type="button" class="btn btn-sm {{ $isPlaying ? 'btn-outline-secondary' : 'btn-outline-warning' }} score-action js-toggle-match"
                        data-playing-url="{{ $playingUrl }}" data-participant-revision="{{ $engine === 'team' ? app(\App\Services\TeamParticipantHistoryService::class)->revision($match) : '' }}"
                        data-playing="{{ $isPlaying ? 'false' : 'true' }}"
                        aria-label="Mark {{ $home }} versus {{ $away }} as {{ $isPlaying ? 'off' : 'on' }} court">
                  {{ $isPlaying ? 'Mark off court' : 'Mark as on court' }}
                </button>
                @endunless
                <button type="button" class="btn btn-sm btn-primary score-action js-open-score"
                      data-fixture="{{ $match->id }}" data-home="{{ $home }}" data-away="{{ $away }}"
                      data-engine="{{ $engine }}" data-participant-revision="{{ $engine === 'team' ? app(\App\Services\TeamParticipantHistoryService::class)->revision($match) : '' }}"
                      data-store="{{ $isFlexible ? $flexibleUrl : $normalStore }}"
                      data-delete="{{ $isFlexible ? $flexibleUrl : $normalDelete }}"
                      data-revision="{{ $draw->flexibleMonrad?->revision ?? 0 }}"
                      data-num-sets="{{ max(1, min(5, (int) ($draw->settings?->num_sets ?: 3))) }}"
                      data-score-guidance="{{ $draw->settings?->score_format
                        ? \App\Domain\Draws\Services\TennisScoreFormat::rules($draw->settings->score_format)
                        : 'Enter completed sets' }}"
                      data-require-full-sets="{{ $isFlexible && ($draw->settings?->requiresFullSets() ?? true) ? '1' : '0' }}"
                      data-scores='@json($sets)'>
                  {{ $hasScore ? 'Correct score' : 'Enter score' }}
                </button>
              </div>
              @elseif($match->scoring_venue_changed)
                <span class="small text-warning">Venue assignment changed. Ask the tournament organiser to confirm scoring access.</span>
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
    </section>

    @if($recentActivity->isNotEmpty())
      <details class="card scoring-activity mt-4">
        <summary class="card-header d-flex align-items-center gap-2 fw-semibold">
          <i class="ti ti-history text-primary" aria-hidden="true"></i>
          <span>Recent scoring activity</span>
          <i class="ti ti-chevron-down ms-auto text-muted" aria-hidden="true"></i>
        </summary>
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

<div class="modal fade" id="score-entry-modal" tabindex="-1" aria-labelledby="score-match-title" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <form class="modal-content" id="score-entry-form">
      <div class="modal-header">
        <div>
          <div class="small text-muted" id="score-entry-guidance">Enter completed sets</div>
          <h2 class="modal-title h5" id="score-match-title">Match score</h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="score-entry-error" class="alert alert-danger d-none" role="alert"></div>
        <div class="row g-2 text-center fw-semibold mb-1"><div class="col" id="score-home-label">Player 1</div><div class="col" id="score-away-label">Player 2</div></div>
        @for($set = 1; $set <= 5; $set++)
          <div class="row g-2 align-items-center mb-2 score-set-row">
            <div class="col"><input type="number" min="0" @if($set !== 3) max="20" @endif inputmode="numeric" class="form-control score-input" data-side="home" data-set="{{ $set }}" aria-label="Set {{ $set }} home score"></div>
            <div class="col-auto text-muted small">Set {{ $set }}</div>
            <div class="col"><input type="number" min="0" @if($set !== 3) max="20" @endif inputmode="numeric" class="form-control score-input" data-side="away" data-set="{{ $set }}" aria-label="Set {{ $set }} away score"></div>
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
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const modalElement = document.getElementById('score-entry-modal');
  const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
  const form = document.getElementById('score-entry-form');
  const error = document.getElementById('score-entry-error');
  const clearButton = document.getElementById('score-clear');
  const saveButton = document.getElementById('score-save');
  let active = null;
  let workspaceRequestSequence = 0;

  function currentFilter() {
    return document.querySelector('[data-score-filter][aria-pressed="true"]')?.dataset.scoreFilter || 'outstanding';
  }

  function applyScoreFilter(filter, scrollButton) {
    let selectedButton = null;
    document.querySelectorAll('[data-score-filter]').forEach(function (item) {
      const isActive = item.dataset.scoreFilter === filter;
      item.classList.toggle('btn-primary', isActive);
      item.classList.toggle('btn-outline-primary', !isActive);
      item.setAttribute('aria-pressed', String(isActive));
      if (isActive) selectedButton = item;
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
    if (scrollButton && selectedButton) selectedButton.scrollIntoView({behavior: 'smooth', block: 'nearest', inline: 'center'});
  }

  function syncRememberedVenue(allowRedirect) {
    const shell = document.querySelector('.scoring-shell');
    const selectedVenue = Number(shell.dataset.selectedVenue || 0);
    const availableVenues = JSON.parse(shell.dataset.availableVenues || '[]');
    if (shell.dataset.forceAllVenues === '1') {
      localStorage.removeItem(venueStorageKey);
    } else if (selectedVenue) {
      localStorage.setItem(venueStorageKey, String(selectedVenue));
    } else if (allowRedirect) {
      const rememberedVenue = Number(localStorage.getItem(venueStorageKey));
      if (rememberedVenue && availableVenues.includes(rememberedVenue)) {
        const target = new URL(window.location.href);
        target.searchParams.set('venue', rememberedVenue);
        refreshWorkspace(target.toString(), {pushState: false});
      }
    }
  }

  function showWorkspaceNotice(message, level) {
    document.querySelector('.js-workspace-notice')?.remove();
    const alert = document.createElement('div');
    alert.className = 'alert alert-' + (level || 'danger') + ' js-workspace-notice';
    alert.setAttribute('role', 'alert');
    alert.textContent = message;
    document.querySelector('.scoring-shell')?.prepend(alert);
  }

  function showWorkspaceError(message) {
    showWorkspaceNotice(message, 'danger');
  }

  async function refreshWorkspace(url, options) {
    options = options || {};
    const sequence = ++workspaceRequestSequence;
    const filter = options.filter || currentFilter();
    const shell = document.querySelector('.scoring-shell');
    shell.classList.add('is-refreshing');
    shell.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(url, {
        method: options.method || 'GET',
        credentials: 'same-origin',
        headers: {'Accept': 'text/html', 'X-Requested-With': 'XMLHttpRequest'},
        body: options.body
      });
      const html = await response.text();
      if (!response.ok) throw new Error('The scoring workspace could not be refreshed.');
      if (sequence !== workspaceRequestSequence) return;
      const replacement = new DOMParser().parseFromString(html, 'text/html').querySelector('.scoring-shell');
      if (!replacement) throw new Error('The scoring workspace returned an unexpected response.');
      shell.replaceWith(replacement);
      if (options.pushState) history.pushState({}, '', options.historyUrl || url);
      syncRememberedVenue(false);
      applyScoreFilter(filter, false);
    } catch (failure) {
      if (sequence !== workspaceRequestSequence) return;
      shell.classList.remove('is-refreshing');
      shell.removeAttribute('aria-busy');
      showWorkspaceError(failure.message);
      throw failure;
    }
  }

  document.addEventListener('change', function (event) {
    const select = event.target.closest('[data-nav-select]');
    if (!select || !select.value) return;
    refreshWorkspace(select.value, {pushState: true}).catch(function () {});
  });

  document.addEventListener('click', function (event) {
    const filterButton = event.target.closest('[data-score-filter]');
    if (filterButton) {
      applyScoreFilter(filterButton.dataset.scoreFilter, true);
      return;
    }

    const scoreButton = event.target.closest('.js-open-score');
    if (scoreButton) {
      active = scoreButton.dataset;
      saveButton.disabled = false;
      clearButton.disabled = false;
      error.classList.add('d-none');
      document.getElementById('score-entry-guidance').textContent =
        active.engine === 'flexible' && active.requireFullSets === '0'
          ? 'Custom set scores allowed'
          : (active.scoreGuidance || 'Enter completed sets');
      document.getElementById('score-match-title').textContent = active.home + ' vs ' + active.away;
      document.getElementById('score-home-label').textContent = active.home;
      document.getElementById('score-away-label').textContent = active.away;
      const numberOfSets = Math.max(1, Math.min(5, Number(active.numSets || 3)));
      document.querySelectorAll('.score-set-row').forEach(function (row, index) {
        const hidden = index >= numberOfSets;
        row.classList.toggle('d-none', hidden);
        row.querySelectorAll('input').forEach(input => input.disabled = hidden);
      });
      const scores = JSON.parse(active.scores || '[]');
      document.querySelectorAll('.score-input').forEach(input => input.value = '');
      scores.forEach(function (set, index) {
        const row = index + 1;
        document.querySelector('[data-side="home"][data-set="' + row + '"]').value = set[0];
        document.querySelector('[data-side="away"][data-set="' + row + '"]').value = set[1];
      });
      clearButton.classList.toggle('d-none', scores.length === 0);
      modal.show();
      return;
    }

    const toggleButton = event.target.closest('.js-toggle-match');
    if (toggleButton) {
      const playing = toggleButton.dataset.playing === 'true';
      if (!playing && !confirm('Mark these players off court? The match will return to Awaiting court.')) return;
      toggleButton.disabled = true;
      request(toggleButton.dataset.playingUrl, 'POST', {playing: playing, participant_revision: toggleButton.dataset.participantRevision || undefined})
        .then(async function (result) {
          await refreshWorkspace(window.location.href);
          showWorkspaceNotice(result.message, playing ? 'success' : 'warning');
        })
        .catch(function (failure) {
          showWorkspaceError(failure.message);
          toggleButton.disabled = false;
        });
    }
  });

  document.addEventListener('submit', function (event) {
    const operatorForm = event.target.closest('.scoring-operator form');
    if (!operatorForm) return;
    event.preventDefault();
    const submit = operatorForm.querySelector('[type="submit"]');
    submit.disabled = true;
    refreshWorkspace(operatorForm.action, {
      method: 'POST',
      body: new FormData(operatorForm),
      historyUrl: window.location.href
    }).catch(function () {
      submit.disabled = false;
    });
  });

  window.addEventListener('popstate', function () {
    refreshWorkspace(window.location.href, {pushState: false}).catch(function () {});
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
    saveButton.disabled = true;
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
        const teamPayload = {participant_revision: active.participantRevision};
        sets.forEach(function (set, index) {
          teamPayload['set' + (index + 1) + '_home'] = set[0];
          teamPayload['set' + (index + 1) + '_away'] = set[1];
        });
        await request(active.store, 'POST', teamPayload);
      } else {
        await request(active.store, 'POST', {sets: sets.map(set => set[0] + '-' + set[1])});
      }
      modal.hide();
      active = null;
      await refreshWorkspace(window.location.href);
    } catch (failure) {
      error.textContent = failure.message;
      error.classList.remove('d-none');
      saveButton.disabled = false;
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
      modal.hide();
      active = null;
      await refreshWorkspace(window.location.href);
    } catch (failure) {
      error.textContent = failure.message;
      error.classList.remove('d-none');
      clearButton.disabled = false;
    }
  });

  syncRememberedVenue(true);
  applyScoreFilter(currentFilter(), false);
});
</script>
@endsection
