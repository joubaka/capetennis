<style>
  .winner-home { background-color: rgba(40,167,69,0.25) !important; color:#155724 !important; }
  .loser-home  { background-color: rgba(220,53,69,0.25) !important; color:#721c24 !important; }
  .draw-cell   { background-color: rgba(255,193,7,0.25) !important;  color:#856404 !important; }

  @media (max-width: 768px) {
      .fixtures-table td, .fixtures-table th {
          font-size: 0.85rem;
          padding: 0.4rem;
      }
  }
  .public-match-cards { display: none; }
  .fixtures-table .fixture-player-label { color: #26394d; font-size: .95rem; font-weight: 600; line-height: 1.6; white-space: normal; }
  .fixtures-table .fixture-roster-rank { display: inline-block; color: #536479; font-size: .8rem; font-weight: 500; margin-right: .25rem; }
  .fixtures-table.fixture-table-tie thead { border-color: #d9e2ed; }
  .fixtures-table.fixture-table-tie thead th { background: #eef3fa; color: #26394d; border-color: #d9e2ed; text-transform: none; font-size: .9rem; letter-spacing: normal; padding: .85rem 1rem; }
  .fixture-table-tie .fixture-team-heading { display: inline-block; border-bottom: 2px solid var(--region-color, #475569); padding-bottom: .25rem; }
  .fixture-table-tie td { padding: .7rem 1rem; }
  .fixture-table-tie .fixture-region-badge, .fixture-table-tie .fixture-region { display: none; }
  .fixture-table-tie tbody tr:hover > td:not(.winner-home):not(.loser-home):not(.draw-cell) { background: #f8fafc; }
  .public-fixture-back { background: #fff !important; color: #26394d !important; border-color: #66788d !important; }
  .public-fixture-back:hover, .public-fixture-back:focus { background: #edf0f4 !important; color: #172e45 !important; }
  .public-fixture-status { background: #e4f1e7 !important; color: #235c31 !important; }
  @media (max-width: 767.98px) {
    .public-match-desktop { display: none; }
    .public-match-cards { display: grid; gap: 1rem; }
    .public-match-card { border: 1px solid #d9dee3; border-radius: .6rem; padding: 1rem; overflow-wrap: anywhere; min-width: 0; }
    .public-match-venue { font-weight: 700; color: #26394d; margin-bottom: .5rem; }
    .public-match-time { font-size: 1.9rem; font-weight: 700; line-height: 1.2; color: #12358f; }
    .public-match-date { margin-top: .25rem; color: #49576a; }
    .public-match-players { border-top: 1px solid #d9dee3; margin-top: .75rem; padding-top: .75rem; font-size: 1rem; line-height: 1.6; }
    .public-match-versus { font-size: .8rem; color: #697a8d; margin: .3rem 0; }
    .public-match-score { margin-top: .75rem; font-size: .85rem; color: #697a8d; }
    .public-match-card .fixture-player-label { white-space: normal; color: #172e45; font-weight: 600; }
    .public-match-card .fixture-region, .public-match-card .fixture-roster-rank { color: #49576a; font-weight: 400; }
  }
</style>

@php
$fxPlayer1 = function ($fx) {
    if ($fx instanceof \App\Models\TeamFixture && $fx->team1) {
        return $fx->team1->pluck('full_name')->implode(' + ');
    }
    if ($fx->registration1) {
        return $fx->registration1->players->pluck('full_name')->implode(' + ');
    }
    return 'TBD';
};

$fxPlayer2 = function ($fx) {
    if ($fx instanceof \App\Models\TeamFixture && $fx->team2) {
        return $fx->team2->pluck('full_name')->implode(' + ');
    }
    if ($fx->registration2) {
        return $fx->registration2->players->pluck('full_name')->implode(' + ');
    }
    return 'TBD';
};

/* ============================================================
   SCORE HELPERS — SUPPORT BOTH TEAM & INDIVIDUAL
   ============================================================ */
$fxScoreDisplay = function ($r) {
    if (isset($r->team1_score)) {
        return $r->team1_score . ' - ' . $r->team2_score;
    }
    return $r->registration1_score . ' - ' . $r->registration2_score;
};

/* Determine winner for highlight */
$fxWinnerClasses = function ($fx) {
    if ($fx instanceof \App\Models\TeamFixture) {
        return match ($fx->winnerSide()) { 'home' => ['winner-home','loser-home'], 'away' => ['loser-home','winner-home'], default => ['',''] };
    }
    if ($fx->fixtureResults->isEmpty()) {
        return ['',''];
    }

    $last = $fx->fixtureResults->last();

    // TEAM
    if (isset($last->team1_score)) {
        $h = $last->team1_score;
        $a = $last->team2_score;
    }
    // INDIVIDUAL
    else {
        $h = $last->registration1_score;
        $a = $last->registration2_score;
    }

    if ($h > $a) return ['winner-home','loser-home'];
    if ($a > $h) return ['loser-home','winner-home'];
    return ['draw-cell','draw-cell'];
};
@endphp


<div class="card">
  @unless($hideFixtureHeader ?? false)
  <div class="card-header d-flex justify-content-between align-items-center">
    <div>
      <h3 class="mb-1">{{ $draw->drawName }} {{ $draw->age }}</h3>
      <span class="badge {{ $draw->published ? 'public-fixture-status' : 'bg-label-warning' }}">{{ $draw->published ? 'Draw published' : 'Draft preview · Draw not published' }}</span>
      <span class="badge {{ $draw->oop_published ? 'public-fixture-status' : 'bg-label-secondary' }}">
        {{ $draw->oop_published ? 'Match times published' : 'Match times to follow' }}
      </span>
    </div>
    <a href="{{ route('events.show', $event) }}" class="btn btn-sm btn-outline-secondary public-fixture-back">
      <i class="ti ti-arrow-left me-1" aria-hidden="true"></i>Back to tournament
    </a>
  </div>

  @endunless
  <div class="card-body">

    @unless($draw->oop_published || ($hideFixtureHeader ?? false))
      <div class="alert alert-info" role="status">
        The draw is available, but match times and venues have not been published yet.
      </div>
    @endunless

    <div class="public-match-cards">
      @forelse($fixtures as $fx)
        <article class="public-match-card" aria-label="Match {{ $fx->match_nr }}">
          @php $matchCourt = $fx instanceof \App\Models\TeamFixture ? $fx->court_label : $fx->orderOfPlay?->court; @endphp
          <div class="public-match-venue">{{ $fx->venue?->name ?? 'Venue to follow' }}@if($matchCourt) · Court {{ $matchCourt }}@endif</div>
          @if($fx->scheduled_at)
            <div class="public-match-time">{{ \Carbon\Carbon::parse($fx->scheduled_at)->format('H:i') }}</div>
            <div class="public-match-date">{{ \Carbon\Carbon::parse($fx->scheduled_at)->format('l, j F Y') }}</div>
          @else
            <div class="public-match-date">Match time to follow</div>
          @endif
          <div class="public-match-players">
            <div>@if($fx instanceof \App\Models\TeamFixture)@include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['home']])@else<x-player-name :players="$fx->registration1?->players ?? []" :context="$draw" separator=" + " />@endif</div>
            <div class="public-match-versus">vs</div>
            <div>@if($fx instanceof \App\Models\TeamFixture)@include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']])@else<x-player-name :players="$fx->registration2?->players ?? []" :context="$draw" separator=" + " />@endif</div>
          </div>
          <div class="public-match-score">Match {{ $fx->match_nr }} · @forelse($fx->fixtureResults as $r){{ $fxScoreDisplay($r) }}@if(!$loop->last), @endif @empty No score yet @endforelse</div>
        </article>
      @empty
        <p class="text-muted mb-0">No matches found.</p>
      @endforelse
    </div>
    <div class="table-responsive public-match-desktop">
      <table class="table table-bordered align-middle fixtures-table {{ ($hideFixtureHeader ?? false) ? 'fixture-table-tie' : '' }}" id="{{ $fixtureTableId ?? 'fixturesTable' }}">
        <thead class="table-dark">
          <tr>
            <th class="d-table-cell d-md-none text-center" style="width:5%">+</th>
            @foreach(['home', 'away'] as $side)
              <th style="width:30%">
                @if(($hideFixtureHeader ?? false) && $fixtures->first() instanceof \App\Models\TeamFixture)
                  <span class="fixture-team-heading" style="--region-color: {{ $fixtures->first()->lineup_display[$side]['region_color'] ?? '#475569' }}" title="{{ $fixtures->first()->tie_display[$side] }}">{{ $fixtures->first()->tie_mobile_display[$side] }}</span>
                @else
                  Player/Team {{ $loop->iteration }}
                @endif
              </th>
            @endforeach
            <th style="width:12%">Score</th>
            <th style="width:13%">Time</th>
            <th style="width:15%">Venue</th>
          </tr>
        </thead>

        <tbody>

          @forelse($fixtures as $fx)

          @php [$homeClass, $awayClass] = $fxWinnerClasses($fx); @endphp

          <tr id="row-{{ $fx->id }}">

            {{-- mobile toggle --}}
            <td class="d-table-cell d-md-none text-center">
              <button class="btn btn-xs btn-outline-primary rounded-circle toggle-details"
                type="button" data-target="#details-{{ $fx->id }}"
                aria-expanded="false" aria-controls="details-{{ $fx->id }}"
                aria-label="Show match details"
                style="width:1.5rem;height:1.5rem;line-height:1;font-size:0.75rem;">
                <i class="ti ti-plus"></i>
              </button>
            </td>

            {{-- PLAYER / TEAM LABELS --}}
            <td class="{{ $homeClass }}">
              @if($fx instanceof \App\Models\TeamFixture)
                @include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['home']])
              @else
                <x-player-name :players="$fx->registration1?->players ?? []" :context="$draw" separator=" + " />
              @endif
            </td>
            <td class="{{ $awayClass }}">
              @if($fx instanceof \App\Models\TeamFixture)
                @include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']])
              @else
                <x-player-name :players="$fx->registration2?->players ?? []" :context="$draw" separator=" + " />
              @endif
            </td>

            {{-- SCORE --}}
            <td class="text-center" id="result-col-{{ $fx->id }}">
                @forelse($fx->fixtureResults as $r)
                    <span class="badge bg-info text-dark me-1">
                        {{ $fxScoreDisplay($r) }}
                    </span>
                @empty
                    <span class="text-muted">No score</span>
                @endforelse
            </td>

            {{-- TIME --}}
            <td>
              @if($fx->scheduled_at)
                {{ \Carbon\Carbon::parse($fx->scheduled_at)->format('Y-m-d H:i') }}
              @else
                <span class="text-muted">—</span>
              @endif
            </td>

            {{-- VENUE --}}
            <td>{{ optional($fx->venue)->name ?? '—' }}</td>

          </tr>

          {{-- MOBILE DETAILS --}}
          <tr id="details-{{ $fx->id }}" class="d-none d-md-none bg-light">
            <td colspan="6">
              <div class="p-2">
                <strong>Player/Team 1:</strong> @if($fx instanceof \App\Models\TeamFixture)@include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['home']])@else<x-player-name :players="$fx->registration1?->players ?? []" :context="$draw" separator=" + " />@endif<br>
                <strong>Player/Team 2:</strong> @if($fx instanceof \App\Models\TeamFixture)@include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']])@else<x-player-name :players="$fx->registration2?->players ?? []" :context="$draw" separator=" + " />@endif<br>
                <strong>Score:</strong>
                @forelse($fx->fixtureResults as $r)
                    {{ $fxScoreDisplay($r) }}
                @empty
                    No score
                @endforelse<br>

                <strong>Venue:</strong> {{ optional($fx->venue)->name ?? '—' }}<br>
                <strong>Time:</strong>
                {{ $fx->scheduled_at ? \Carbon\Carbon::parse($fx->scheduled_at)->format('D H:i') : '—' }}
              </div>
            </td>
          </tr>

          @empty
          <tr><td colspan="6" class="text-center text-muted py-4">No matches found.</td></tr>
          @endforelse

        </tbody>
      </table>
    </div>

  </div>
</div>

@once
<script>
// Expand/Collapse details on mobile
document.addEventListener('DOMContentLoaded', function () {
$(document).on('click', '.toggle-details', function () {
  const target = $(this).data('target');
  const $row = $(target);
  $row.toggleClass('d-none');
  $(this).find('i').toggleClass('ti-plus ti-minus');
  const expanded = !$row.hasClass('d-none');
  $(this).attr('aria-expanded', expanded ? 'true' : 'false')
    .attr('aria-label', expanded ? 'Hide match details' : 'Show match details');
});
});
</script>
@endonce
