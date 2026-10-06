@extends('layouts/layoutMaster')

@section('title', ($draw->drawName ?? 'Tournament') . ' matches')

@section('content')
<style>.fixture-round > summary .when-open,.fixture-tie > summary .when-open{display:none}.fixture-round[open] > summary .when-open,.fixture-tie[open] > summary .when-open{display:inline}.fixture-round[open] > summary .when-closed,.fixture-tie[open] > summary .when-closed{display:none}.fixture-tie > summary{padding:.75rem;border:1px solid #d9dee3;border-radius:.4rem;background:#f5f7fa}.fixture-toggle{font-size:.8rem;color:#315785;font-weight:600}</style>
<style>
  .public-fixture-page .badge.bg-label-success { background: #e4f1e7 !important; color: #235c31 !important; }
  .public-fixture-page .badge.bg-label-secondary { background: #edf0f4 !important; color: #35465b !important; }
  @media (max-width: 767.98px) {
    .public-fixture-page .fixture-round > .card-body,
    .public-fixture-page .fixture-tie > .card { display: block !important; }
    .public-fixture-page .fixture-toggle { display: none; }
    .public-fixture-page .fixture-round > summary,
    .public-fixture-page .fixture-tie > summary { min-height: 44px; cursor: default !important; pointer-events: none; }
    .public-fixture-page .fixture-round > .card-body { padding: .75rem; }
    .public-fixture-page .fixture-tie > .card > .card-body { padding: .5rem; }
    .public-fixture-page .fixture-tie h5 { overflow-wrap: anywhere; font-size: 1rem; }
    .public-fixture-page .btn { min-height: 44px; display: inline-flex; align-items: center; }
  }
</style>
<div class="public-fixture-page">
  <div class="card mb-3">
    <div class="card-body d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <h3 class="mb-2">{{ $draw->drawName }}</h3>
        <span class="badge {{ $draw->published ? 'bg-label-success' : 'bg-label-warning' }}">{{ $draw->published ? 'Draw published' : 'Draft preview · Draw not published' }}</span>
        <span class="badge {{ $draw->oop_published ? 'bg-label-success' : 'bg-label-secondary' }}">{{ $draw->oop_published ? 'Match times published' : 'Match times to follow' }}</span>
      </div>
      <a href="{{ route('events.show', $event) }}" class="btn btn-sm btn-outline-secondary public-fixture-back">Back to tournament</a>
    </div>
  </div>
  @unless($draw->oop_published)
    <div class="alert alert-info" role="status">The draw is available, but match times and venues have not been published yet.</div>
  @endunless
  @foreach($fixtures->groupBy(fn ($fixture) => (int) $fixture->round_nr)->sortKeys() as $round => $roundFixtures)
    @php
      $ties = $roundFixtures->groupBy(fn ($fixture) => implode('-', [$fixture->draw_id, $fixture->team_tie_id ?: implode('-', [$fixture->tie_nr, $fixture->region1, $fixture->region2])]))
        ->sortBy(fn ($matches) => (int) $matches->first()->tie_nr);
    @endphp
    <details class="fixture-round card mb-3" @if($loop->first) open @endif>
      <summary class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap" style="cursor: pointer;">
        <h4 class="mb-0">Round {{ $round ?: '—' }} <span class="text-muted small">▾</span></h4>
        <span class="fixture-toggle"><span class="when-closed">▸ Click to show ties</span><span class="when-open">▾ Click to hide ties</span></span>
        <span class="text-muted">{{ $ties->count() }} {{ $ties->count() === 1 ? 'tie' : 'ties' }} · {{ $roundFixtures->count() }} matches</span>
      </summary>
      <div class="card-body">
        @foreach($ties as $tieKey => $tieFixtures)
          @php $firstFixture = $tieFixtures->first(); @endphp
          <details class="fixture-tie mb-3" @if($loop->first) open @endif>
            <summary class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2" style="cursor: pointer;">
            <h5 class="mb-0" id="fixture-tie-{{ $round }}-{{ $tieKey }}">
              @if($fixtures->pluck('draw_id')->unique()->count() > 1)<span class="badge bg-label-secondary">{{ $firstFixture->draw->drawName }}</span> @endif
              <span class="text-muted">Tie {{ $firstFixture->tie_nr ?: $loop->iteration }} ·</span>
              <span class="d-none d-md-inline">{{ $firstFixture->tie_display['home'] }} <span class="text-muted">vs</span> {{ $firstFixture->tie_display['away'] }}</span>
              <span class="d-md-none">{{ $firstFixture->tie_mobile_display['home'] }} <span class="text-muted">vs</span> {{ $firstFixture->tie_mobile_display['away'] }}</span>
            </h5>
              <span class="text-muted small">{{ $tieFixtures->count() }} matches <span class="fixture-toggle"><span class="when-closed">▸ Click to show matches</span><span class="when-open">▾ Click to hide matches</span></span></span>
            </summary>
            @include('frontend.fixture.fixture-table', ['fixtures' => $tieFixtures->sortBy(fn ($fixture) => (int) ($fixture->rubber_sequence ?: $fixture->match_nr)),
              'hideFixtureHeader' => true, 'fixtureTableId' => 'fixturesTable-'.$round.'-'.$tieKey])
          </details>
        @endforeach
      </div>
    </details>
  @endforeach
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  const phone = window.matchMedia('(max-width: 767.98px)');
  const sections = document.querySelectorAll('.public-fixture-page details');
  const desktopState = new Map();
  const syncSections = function () {
    sections.forEach(function (section) {
      if (phone.matches) {
        if (!desktopState.has(section)) desktopState.set(section, section.open);
        section.open = true;
      } else if (desktopState.has(section)) {
        section.open = desktopState.get(section);
        desktopState.delete(section);
      }
    });
  };
  sections.forEach(function (section) {
    section.querySelector('summary').addEventListener('click', function (event) {
      if (phone.matches) event.preventDefault();
    });
  });
  phone.addEventListener('change', syncSections);
  syncSections();
});
</script>
@endsection
