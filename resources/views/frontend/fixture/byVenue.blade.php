@extends('layouts/layoutMaster')

@section('title', $venue->name . ' – Fixtures')

@section('content')
<style>
  .winner-home {
    background-color: rgba(40, 167, 69, 0.25) !important;
    color: #155724 !important;
  }
  .loser-home {
    background-color: rgba(220, 53, 69, 0.25) !important;
    color: #721c24 !important;
  }
  .draw-cell {
    background-color: rgba(255, 193, 7, 0.25) !important;
    color: #856404 !important;
  }

  @media (max-width: 768px) {
    /* Hide Draw and Status columns on mobile to save space since we added Time to the front */
    th:nth-child(2), td:nth-child(2), 
    th:nth-child(7), td:nth-child(7) { 
      display: none !important;
    }
    
    .btn-sm {
      padding: 0.15rem 0.3rem !important;
    }

    .badge {
      font-size: 0.7rem !important;
    }
  }
</style>

@php
if (!function_exists('region_badge_class')) {
    function region_badge_class(?string $short): string {
        if (!$short) return 'bg-label-secondary';

        $map = [
            'Plat' => 'bg-label-primary',
            'Wine' => 'bg-label-info',
            'Drak' => 'bg-label-success',
            'Eden' => 'bg-label-warning',
            'BO' => 'bg-label-danger',
            'WP' => 'bg-label-dark',
        ];

        $palette = [
            'bg-label-primary','bg-label-success','bg-label-warning',
            'bg-label-danger','bg-label-info','bg-label-dark','bg-label-secondary'
        ];

        return $map[$short] ?? $palette[abs(crc32($short)) % count($palette)];
    }
}

if (!function_exists('team_label')) {
    function team_label($team, $noProfileTeam) {
        $names = [];
        if ($team && $team->count()) {
            foreach ($team as $player) {
                $names[] = $player->full_name;
            }
        }
        if ($noProfileTeam && $noProfileTeam->count()) {
            foreach ($noProfileTeam as $np) {
                $names[] = trim($np->name . ' ' . $np->surname);
            }
        }
        return count($names) ? implode(' + ', $names) : 'TBD';
    }
}
@endphp

@if(isset($venues) && $venues->count())
  <div class="mb-3">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <span class="fw-bold me-2">Jump to venue:</span>
      @foreach($venues as $v)
        <a href="{{ route('fixtures.venue', ['event_id' => $event->id, 'venue_id' => $v->id]) }}"
           class="btn btn-sm {{ $venue->id == $v->id ? 'btn-primary' : 'btn-outline-primary' }}">
          {{ $v->name }}
        </a>
      @endforeach
    </div>
  </div>
@endif

<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h3 class="mb-0">
      {{ $event->name }} – Fixtures at {{ $venue->name }}
    </h3>
    <div class="d-flex gap-2">
      <a href="{{ route('events.show', $event->id) }}" class="btn btn-sm btn-danger">Back to Event</a>
    </div>
  </div>

  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-bordered align-middle" style="min-width: 900px;">
        <thead class="table-dark sticky-top">
          <tr>
            <th>Time</th>
            <th>Draw</th>
            <th>Team 1</th>
            <th>Team 2</th>
            <th class="text-center">Score</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          @forelse($fixtures as $fx)
            @php
              $homeClass = $awayClass = '';
              $status = 'Pending';
              if ($fx->fixtureResults && $fx->fixtureResults->count()) {
                  $lastSet = $fx->fixtureResults->last();
                  if ($lastSet->team1_score > $lastSet->team2_score) {
                      $homeClass = 'winner-home'; $awayClass = 'loser-home'; $status = 'Home Win';
                  } elseif ($lastSet->team2_score > $lastSet->team1_score) {
                      $homeClass = 'loser-home'; $awayClass = 'winner-home'; $status = 'Away Win';
                  } else {
                      $homeClass = $awayClass = 'draw-cell'; $status = 'Draw';
                  }
              }

              $homeNames = [];
              $awayNames = [];
              $homeRegionShort = $fx->region1Name?->short_name ?? null;
              $awayRegionShort = $fx->region2Name?->short_name ?? null;

              foreach($fx->fixturePlayers as $fpRow) {
                  if ($fpRow->team1_id && $fpRow->player1) {
                      $name = $fpRow->player1->full_name;
                      if($homeRegionShort) $name .= " ({$homeRegionShort})";
                      $homeNames[] = $name;
                  } elseif ($fpRow->team1_no_profile_id) {
                      $np = $fpRow->noProfile1;
                      if($np){
                          $name = trim($np->name.' '.$np->surname);
                          if($homeRegionShort) $name .= " ({$homeRegionShort})";
                          $homeNames[] = $name;
                      }
                  }
                  if ($fpRow->team2_id && $fpRow->player2) {
                      $name = $fpRow->player2->full_name;
                      if($awayRegionShort) $name .= " ({$awayRegionShort})";
                      $awayNames[] = $name;
                  } elseif ($fpRow->team2_no_profile_id) {
                      $np2 = $fpRow->noProfile2;
                      if($np2){
                          $name = trim($np2->name.' '.$np2->surname);
                          if($awayRegionShort) $name .= " ({$awayRegionShort})";
                          $awayNames[] = $name;
                      }
                  }
              }
              $homeLabel = count($homeNames) ? collect($homeNames)->implode(' + ') : 'TBD';
              $awayLabel = count($awayNames) ? collect($awayNames)->implode(' + ') : 'TBD';
            @endphp
            <tr id="row-{{ $fx->id }}">
              <td class="fw-bold">
                @if($fx->scheduled_at)
                  {{ \Carbon\Carbon::parse($fx->scheduled_at)->format('D H:i') }}
                @else
                  <span class="text-muted">—</span>
                @endif
              </td>
              <td>{{ optional($fx->draw)->drawName ?? '-' }}</td>
              <td class="home-cell {{ $homeClass }}">
                ({{ $fx->home_rank_nr }}) {{ $homeLabel }}
              </td>
              <td class="away-cell {{ $awayClass }}">
                ({{ $fx->away_rank_nr }}) {{ $awayLabel }}
              </td>
              <td id="result-col-{{ $fx->id }}" class="text-center">
                @forelse($fx->fixtureResults as $r)
                  <span class="badge bg-info text-dark me-1" style="font-size: 0.75rem;">
                    {{ $r->team1_score }} - {{ $r->team2_score }}
                  </span>
                @empty
                  <span class="text-muted">No Score</span>
                @endforelse
              </td>
              <td>
                <span class="badge {{ $status == 'Pending' ? 'bg-secondary' : ($status == 'Draw' ? 'bg-warning' : ($status == 'Home Win' ? 'bg-success' : 'bg-primary')) }}">
                  {{ $status }}
                </span>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="6" class="text-center text-muted py-4">No fixtures scheduled for this venue.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

@endsection
