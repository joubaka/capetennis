@extends('layouts/layoutMaster')

@section('title', $draw->drawName . ' Fixtures')

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
@endsection

@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('content')

<style>
  .winner-home { background-color: rgba(40,167,69,0.25) !important; color:#155724 !important; }
  .loser-home  { background-color: rgba(220,53,69,0.25) !important; color:#721c24 !important; }
  .draw-cell   { background-color: rgba(255,193,7,0.25) !important;  color:#856404 !important; }

  @media (max-width: 768px) {
      #fixturesTable td, #fixturesTable th {
          font-size: 0.85rem;
          padding: 0.4rem;
      }
  }
</style>

@php
/* ============================================================
   REGION BADGES — ONLY FOR TEAM FIXTURES
   ============================================================ */
if (!function_exists('region_badge_class')) {
    function region_badge_class(?string $short): string {
        if (!$short) return 'bg-label-secondary';

        $map = [
            'Plat' => 'bg-label-primary',
            'Wine' => 'bg-label-info',
            'Drak' => 'bg-label-success',
            'Eden' => 'bg-label-warning',
            'BO'   => 'bg-label-danger',
            'WP'   => 'bg-label-dark',
        ];

        $palette = [
            'bg-label-primary','bg-label-success','bg-label-warning',
            'bg-label-danger','bg-label-info','bg-label-dark','bg-label-secondary'
        ];

        return $map[$short] ?? $palette[abs(crc32($short)) % count($palette)];
    }
}

/* ============================================================
   PLAYER / TEAM NAME HELPERS — SUPPORT BOTH FIXTURE TYPES
   ============================================================ */
function fx_player1($fx) {
    if ($fx instanceof \App\Models\TeamFixture && $fx->team1) {
        return $fx->team1->pluck('full_name')->implode(' + ');
    }
    if ($fx->registration1) {
        return $fx->registration1->players->pluck('full_name')->implode(' + ');
    }
    return 'TBD';
}

function fx_player2($fx) {
    if ($fx instanceof \App\Models\TeamFixture && $fx->team2) {
        return $fx->team2->pluck('full_name')->implode(' + ');
    }
    if ($fx->registration2) {
        return $fx->registration2->players->pluck('full_name')->implode(' + ');
    }
    return 'TBD';
}

/* ============================================================
   SCORE HELPERS — SUPPORT BOTH TEAM & INDIVIDUAL
   ============================================================ */
function fx_score_display($r) {
    if (isset($r->team1_score)) {
        return $r->team1_score . ' - ' . $r->team2_score;
    }
    return $r->registration1_score . ' - ' . $r->registration2_score;
}

/* Determine winner for highlight */
function fx_winner_classes($fx) {
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
}
@endphp


<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="mb-0">{{ $draw->drawName }} {{ $draw->age }}</h3>
    <a href="{{ url()->previous() }}" class="btn btn-sm btn-danger">Back</a>
  </div>

  <div class="card-body">

    <div class="table-responsive">
      <table class="table table-bordered align-middle" id="fixturesTable">
        <thead class="table-dark">
          <tr>
            <th class="d-none d-md-table-cell" style="width:5%">#</th>
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

          @php [$homeClass, $awayClass] = fx_winner_classes($fx); @endphp

          <tr id="row-{{ $fx->id }}">

            {{-- ID (desktop only) --}}
            <td class="d-none d-md-table-cell">{{ $fx->id }}</td>

            {{-- mobile toggle --}}
            <td class="d-table-cell d-md-none text-center">
              <button class="btn btn-xs btn-outline-primary rounded-circle toggle-details"
                type="button" data-target="#details-{{ $fx->id }}"
                style="width:1.5rem;height:1.5rem;line-height:1;font-size:0.75rem;">
                <i class="ti ti-plus"></i>
              </button>
            </td>

            {{-- PLAYER / TEAM 1 --}}
            <td class="{{ $homeClass }}">
                @if($fx instanceof \App\Models\TeamFixture)
                    ({{ $fx->home_rank_nr }})
                @endif

                {{ fx_player1($fx) }}

                @if($fx instanceof \App\Models\TeamFixture && $fx->region1Name?->short_name)
                  <span class="badge rounded-pill {{ region_badge_class($fx->region1Name->short_name) }} ms-1">
                      {{ $fx->region1Name->short_name }}
                  </span>
                @endif
            </td>

            {{-- PLAYER / TEAM 2 --}}
            <td class="{{ $awayClass }}">
                @if($fx instanceof \App\Models\TeamFixture)
                    ({{ $fx->away_rank_nr }})
                @endif

                {{ fx_player2($fx) }}

                @if($fx instanceof \App\Models\TeamFixture && $fx->region2Name?->short_name)
                  <span class="badge rounded-pill {{ region_badge_class($fx->region2Name->short_name) }} ms-1">
                      {{ $fx->region2Name->short_name }}
                  </span>
                @endif
            </td>

            {{-- SCORE --}}
            <td class="text-center" id="result-col-{{ $fx->id }}">
                @forelse($fx->fixtureResults as $r)
                    <span class="badge bg-info text-dark me-1">
                        {{ fx_score_display($r) }}
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
                <strong>Player/Team 1:</strong> {{ fx_player1($fx) }}<br>
                <strong>Player/Team 2:</strong> {{ fx_player2($fx) }}<br>
                <strong>Score:</strong>
                @forelse($fx->fixtureResults as $r)
                    {{ fx_score_display($r) }}
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
          <tr><td colspan="10" class="text-center text-muted py-4">No fixtures found.</td></tr>
          @endforelse

        </tbody>
      </table>
    </div>

  </div>
</div>

<script>
// Expand/Collapse details on mobile
$(document).on('click', '.toggle-details', function () {
  const target = $(this).data('target');
  const $row = $(target);
  $row.toggleClass('d-none');
  $(this).find('i').toggleClass('ti-plus ti-minus');
});
</script>

@endsection
