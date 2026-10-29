<section class="card" aria-labelledby="player-rating-heading"><div class="card-body">
  <h2 id="player-rating-heading" class="h4">{{ $ratingPlayer->full_name }} · Player rating</h2>
  <p class="text-muted">Last 12 months · monthly recalculation · 0–100 scale</p>
  <p><strong>Provisional ability estimates.</strong> These ratings are not calibrated accuracy percentages or official rankings.</p>
  @if(auth()->user()?->hasRole('super-user'))
    <p class="alert alert-info">Super Admin preview. This is a planned premium membership benefit. Member access remains hidden until enabled and a premium entitlement is connected.</p>
  @endif
  @if(isset($ratingHistory['as_of']))<p class="small text-muted">Saved through {{ $ratingHistory['as_of'] }} · refreshed nightly at 03:00 SAST.@if($ratingHistory['stale'] ?? false) Awaiting the next scheduled update.@endif</p>@endif
  <p>Each point recalculates the existing rating model using events completed by that date. The latest month runs to the saved calculation date. Results, publication and player identity are checked as they exist now; these are retrospective estimates, not archived ratings.</p>
  <p class="text-muted">Higher means a stronger estimate within the same cohort and comparison group. Changing opponents, evidence and time weighting can move a rating even without a new match. Lines break when the comparison group changes or a month is missing; different cohorts and groups are not directly comparable.</p>
  @if($ratingHistory['reason'])
    <p role="status" class="alert alert-warning">{{ $ratingHistory['reason'] }}</p>
  @elseif(!$ratingHistory['series'])
    <p role="status">No eligible rating evidence was available in the last 12 months. Missing months are unrated, not zero.</p>
  @else
    @foreach($ratingHistory['series'] as $cohort => $points)
      @php($latest = end($points))
      <h3 class="h5 mt-4">{{ ucfirst($cohort) }} · {{ number_format($latest['score'], 1) }} / 100</h3>
      <p class="small text-muted">Latest estimate {{ $latest['date'] }} · comparison group {{ $latest['component'] }}. Missing months are unrated.</p>
      <p class="small text-muted d-sm-none mb-2">Swipe the chart to see all months, or open the monthly values below.</p>
      <div style="overflow-x:auto;max-width:100%" role="region" aria-label="{{ ucfirst($cohort) }} rating chart; scroll horizontally on small screens" tabindex="0">
      <svg viewBox="0 0 660 270" role="img" aria-label="{{ ucfirst($cohort) }} monthly rating history" style="width:100%;min-width:660px;max-height:360px">
        <title>{{ ucfirst($cohort) }} monthly rating history</title>
        @foreach([0, 25, 50, 75, 100] as $tick)
          <line x1="45" x2="635" y1="{{ 225 - $tick * 2 }}" y2="{{ 225 - $tick * 2 }}" stroke="#d9e2e6" />
          <text x="35" y="{{ 229 - $tick * 2 }}" text-anchor="end" font-size="12" fill="#334e60">{{ $tick }}</text>
        @endforeach
        @foreach($points as $point)
          @php($previous = $points[$loop->index - 1] ?? null)
          @if($previous && $previous['index'] + 1 === $point['index'] && $previous['group'] === $point['group'])
            <line x1="{{ 45 + $previous['index'] * 53 }}" y1="{{ 225 - $previous['score'] * 2 }}" x2="{{ 45 + $point['index'] * 53 }}" y2="{{ 225 - $point['score'] * 2 }}" stroke="#167b70" stroke-width="3" />
          @endif
          <circle cx="{{ 45 + $point['index'] * 53 }}" cy="{{ 225 - $point['score'] * 2 }}" r="5" fill="#167b70"><title>{{ $point['date'] }}: {{ number_format($point['score'], 1) }} / 100</title></circle>
        @endforeach
        @for($month = 0; $month < 12; $month++)
          <text x="{{ 45 + $month * 53 }}" y="250" text-anchor="middle" font-size="11" fill="#334e60">{{ \Carbon\CarbonImmutable::parse($ratingHistory['as_of'] ?? 'today', 'Africa/Johannesburg')->startOfMonth()->subMonths(11 - $month)->format('M') }}</text>
        @endfor
      </svg>
      </div>
      <details><summary class="mb-2">Monthly values and changes</summary><div class="table-responsive"><table class="table table-sm" style="white-space:nowrap"><thead><tr><th>Date</th><th>Rating / 100</th><th>Change</th><th>Group</th></tr></thead><tbody>
        @foreach($points as $point)
          @php($previous = $points[$loop->index - 1] ?? null)
          <tr><td>{{ $point['date'] }}</td><td>{{ number_format($point['score'], 1) }}</td><td>@if($previous && $previous['index'] + 1 === $point['index'] && $previous['group'] === $point['group']){{ sprintf('%+.1f', $point['score'] - $previous['score']) }}@else — @endif</td><td>{{ $point['component'] }}</td></tr>
        @endforeach
      </tbody></table></div></details>
    @endforeach
  @endif
</div></section>
