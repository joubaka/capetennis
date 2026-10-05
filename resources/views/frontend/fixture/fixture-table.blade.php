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
      <span class="badge {{ $draw->published ? 'bg-label-success' : 'bg-label-warning' }}">{{ $draw->published ? 'Draw published' : 'Draft preview · Draw not published' }}</span>
      <span class="badge {{ $draw->oop_published ? 'bg-label-success' : 'bg-label-secondary' }}">
        {{ $draw->oop_published ? 'Match times published' : 'Match times to follow' }}
      </span>
    </div>
    <a href="{{ route('events.show', $event) }}" class="btn btn-sm btn-outline-secondary">
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

    <div class="table-responsive">
      <table class="table table-bordered align-middle fixtures-table" id="{{ $fixtureTableId ?? 'fixturesTable' }}">
        <thead class="table-dark">
          <tr>
            <th class="d-table-cell d-md-none text-center" style="width:5%">+</th>
            <th style="width:25%">Player/Team 1</th>
            <th style="width:25%">Player/Team 2</th>
            <th style="width:15%">Score</th>
            <th style="width:15%">Time</th>
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
                {{ $fxPlayer1($fx) }}
              @endif
            </td>
            <td class="{{ $awayClass }}">
              @if($fx instanceof \App\Models\TeamFixture)
                @include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']])
              @else
                {{ $fxPlayer2($fx) }}
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
                <strong>Player/Team 1:</strong> @if($fx instanceof \App\Models\TeamFixture)@include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['home']])@else{{ $fxPlayer1($fx) }}@endif<br>
                <strong>Player/Team 2:</strong> @if($fx instanceof \App\Models\TeamFixture)@include('frontend.fixture.lineup-side', ['lineup' => $fx->lineup_display['away']])@else{{ $fxPlayer2($fx) }}@endif<br>
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
$(document).on('click', '.toggle-details', function () {
  const target = $(this).data('target');
  const $row = $(target);
  $row.toggleClass('d-none');
  $(this).find('i').toggleClass('ti-plus ti-minus');
  const expanded = !$row.hasClass('d-none');
  $(this).attr('aria-expanded', expanded ? 'true' : 'false')
    .attr('aria-label', expanded ? 'Hide match details' : 'Show match details');
});
</script>
@endonce
