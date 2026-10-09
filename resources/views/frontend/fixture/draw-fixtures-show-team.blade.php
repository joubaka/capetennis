@extends('layouts/layoutMaster')

@section('title', ($draw->drawName ?? 'Tournament') . ' matches')

@section('content')
<style>.fixture-round > summary .when-open,.fixture-tie > summary .when-open{display:none}.fixture-round[open] > summary .when-open,.fixture-tie[open] > summary .when-open{display:inline}.fixture-round[open] > summary .when-closed,.fixture-tie[open] > summary .when-closed{display:none}.fixture-tie > summary{padding:.75rem;border:1px solid #d9dee3;border-radius:.4rem;background:#f5f7fa}.fixture-toggle{font-size:.8rem;color:#315785;font-weight:600}</style>
<style>
  .public-fixture-page .badge.bg-label-success { background: #e4f1e7 !important; color: #235c31 !important; }
  .public-fixture-page .badge.bg-label-secondary { background: #edf0f4 !important; color: #35465b !important; }
  @media (max-width: 767.98px) {
    .public-fixture-page .fixture-round > summary,
    .public-fixture-page .fixture-tie > summary { min-height: 44px; }
    .public-fixture-page .fixture-round > .card-body { padding: .75rem; }
    .public-fixture-page .fixture-tie > .card > .card-body { padding: .5rem; }
    .public-fixture-page .fixture-tie h5 { overflow-wrap: anywhere; font-size: 1rem; }
    .public-fixture-page .btn { min-height: 44px; display: inline-flex; align-items: center; }
  }
</style>
<style>
  .public-fixture-page summary { cursor: pointer; }
  .public-fixture-page summary:hover { background: #e8eff8; }
  .public-fixture-page summary:focus-visible { outline: 3px solid #173f7a; outline-offset: 3px; }
  .public-fixture-page .fixture-round > summary { border-left: 5px solid #173f7a; background: #eef3fa; }
  .fixture-team-chip { display: inline-block; background: #fff; color: #26394d; border: 1px solid var(--region-color, #475569); padding: .3rem .55rem; border-radius: .35rem; line-height: 1.5; overflow-wrap: anywhere; }
</style>
@if($draw->published)
  @if($event->standings_published)
  <div class="d-flex flex-wrap gap-2 mb-3">
    <a class="btn btn-outline-primary" style="min-height:44px" href="{{ route('frontend.team-draw.standings', $draw) }}">Draw standings</a>
    <a class="btn btn-outline-primary" style="min-height:44px" href="{{ route('frontend.events.standings', $event) }}">Full event standings</a>
  </div>
  @endif
  @include('frontend.fixtures.partials.live-results-status')
@endif
<div class="public-fixture-page" @if($draw->published) data-live-results="team-fixtures" @endif>
  <div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <h3 class="mb-2">{{ $draw->drawName }}</h3>
        <span class="badge {{ $draw->published ? 'bg-label-success' : 'bg-label-warning' }}">{{ $draw->published ? 'Draw published' : 'Draft preview · Draw not published' }}</span>
        <span class="badge {{ $draw->scheduleIsPublished() ? 'bg-label-success' : 'bg-label-secondary' }}">{{ $draw->scheduleIsPublished() ? 'Match times published' : 'Match times to follow' }}</span>
      </div>
      <a href="{{ route('events.show', $event) }}" class="btn btn-sm btn-outline-secondary public-fixture-back">Back to tournament</a>
    </div>
  </div>
  @if((int) $event->id === 241 && $draw->scheduleIsPublished())
    <div class="alert mb-3" style="background:#fff3cd;color:#664d03;border:1px solid #e6c76a;" role="note">
      <strong class="d-block mb-1">NB: NOT BEFORE times</strong>
      All scheduled times are NOT BEFORE times. A match will not start before its listed time, but it may start later if earlier matches are still being played. Please be at your assigned venue and ready to play by the listed time, and check Cape Tennis for schedule updates.
    </div>
  @endif
  @unless($draw->scheduleIsPublished())
    <div class="alert alert-info" role="status">The draw is available, but match times and venues have not been published yet.</div>
  @endunless
  @foreach($fixtures->groupBy(fn ($fixture) => (int) $fixture->round_nr) as $round => $roundFixtures)
    @php
      $ties = $roundFixtures->groupBy(fn ($fixture) => implode('-', [$fixture->draw_id, $fixture->team_tie_id ?: implode('-', [$fixture->tie_nr, $fixture->region1, $fixture->region2])]));
    @endphp
    <details class="fixture-round card mb-3" data-live-key="round-{{ $round }}">
      <summary class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap" style="cursor: pointer;">
        <h4 class="mb-0">Round {{ $round ?: '—' }}</h4>
        <span class="fixture-toggle"><span class="when-closed">▸ Click to show ties</span><span class="when-open">▾ Click to hide ties</span></span>
        <span class="text-muted">{{ $ties->count() }} {{ $ties->count() === 1 ? 'tie' : 'ties' }} · {{ $roundFixtures->count() }} matches</span>
      </summary>
      <div class="card-body">
        @foreach($ties as $tieKey => $tieFixtures)
          @php $firstFixture = $tieFixtures->first(); [$tieHomeClass, $tieAwayClass] = \App\Support\ResultPresentation::tieClasses($tieFixtures); @endphp
          <details class="fixture-tie mb-3" data-live-key="tie-{{ $round }}-{{ $tieKey }}">
            <summary class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2" style="cursor: pointer;">
            <h5 class="mb-0" id="fixture-tie-{{ $round }}-{{ $tieKey }}">
              @if($fixtures->pluck('draw_id')->unique()->count() > 1)<span class="badge bg-label-secondary">{{ $firstFixture->draw->drawName }}</span> @endif
              <span class="text-muted">Tie {{ $firstFixture->tie_nr ?: $loop->iteration }} ·</span>
              @foreach(['home', 'away'] as $side)
                @if(!$loop->first)<span class="text-muted">vs</span>@endif
                <span class="fixture-team-chip {{ $side === 'home' ? $tieHomeClass : $tieAwayClass }}" style="--region-color: {{ $firstFixture->lineup_display[$side]['region_color'] ?? '#475569' }}">
                  <span class="d-none d-md-inline">{{ $firstFixture->tie_display[$side] }}</span>
                  <span class="d-md-none">{{ $firstFixture->tie_mobile_display[$side] }}</span><x-result-label :outcome="$side === 'home' ? $tieHomeClass : $tieAwayClass" />
                </span>
              @endforeach
            </h5>
              <span class="text-muted small">{{ $tieFixtures->count() }} matches <span class="fixture-toggle"><span class="when-closed">▸ Click to show matches</span><span class="when-open">▾ Click to hide matches</span></span></span>
            </summary>
            @include('frontend.fixture.fixture-table', ['fixtures' => $tieFixtures,
              'hideFixtureHeader' => true, 'fixtureTableId' => 'fixturesTable-'.$round.'-'.$tieKey])
          </details>
        @endforeach
      </div>
    </details>
  @endforeach
</div>
@include('frontend.fixtures.partials.live-results-script')
@endsection
