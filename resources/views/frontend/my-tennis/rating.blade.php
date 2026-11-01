<section class="card ctr-history" aria-labelledby="player-rating-heading"><div class="card-body">
  <style>
    .ctr-history .ctr-heading { display:flex;flex-wrap:wrap;justify-content:space-between;gap:.75rem;align-items:center; }
    .ctr-history .ctr-stats { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:1rem;margin:1.25rem 0; }
    .ctr-history .ctr-stat { border:1px solid #dce6e2;border-radius:14px;padding:1rem;min-width:0; }
    .ctr-history .ctr-stat h3 { font-size:.8rem;color:#5e716c;margin:0 0 .6rem; }
    .ctr-history .ctr-stat-value { color:#233d50;font-size:1.6rem;line-height:1.3;font-weight:700; }
    .ctr-history .ctr-filters { display:grid;grid-template-columns:1.5fr 1fr;gap:.75rem; }
    .ctr-history .ctr-filters label { font-size:.8rem;display:block;margin-bottom:.3rem; }
    .ctr-history .ctr-result { border-top:1px solid #e0e7e3;padding:1rem 0;overflow-wrap:anywhere; }
    .ctr-history .ctr-result-top { display:flex;flex-wrap:wrap;justify-content:space-between;gap:.5rem;align-items:center; }
    .ctr-history .ctr-result h4 { font-size:1rem;margin:.5rem 0; }
    .ctr-history .ctr-legend { display:flex;flex-wrap:wrap;gap:.5rem 1.25rem;color:#5e716c;font-size:.8rem; }
    .ctr-history .ctr-line-key { display:inline-block;vertical-align:middle;width:24px;border-top:3px solid #167b70;margin-right:.4rem; }
    .ctr-history .ctr-line-key-dashed { border-top-style:dashed; }
    .ctr-history [data-history-refresh]:disabled::before { content:'';display:inline-block;width:1rem;height:1rem;margin-right:.5rem;vertical-align:middle;border:.15em solid currentColor;border-right-color:transparent;border-radius:50%;animation:ctr-update-spin .75s linear infinite; }
    @keyframes ctr-update-spin { to { transform:rotate(360deg); } }
    @media(prefers-reduced-motion:reduce) { .ctr-history [data-history-refresh]:disabled::before { animation:none; } }
    .ctr-history .ctr-chart-scroll { overflow-x:auto;max-width:100%;border:1px solid #e2e9e6;border-radius:10px; }
    .ctr-history .ctr-method { border-top:1px solid #e0e7e3;margin-top:1.5rem;padding-top:1rem; }
    @media(max-width:991.98px) { .ctr-history .ctr-stats { grid-template-columns:1fr;gap:.65rem; } }
    @media(max-width:767.98px) { .ctr-history .ctr-filters { grid-template-columns:1fr; } }
  </style>
  <div class="ctr-heading">
    <div><h2 id="player-rating-heading" class="h4 mb-1">{{ $ratingPlayer->full_name }} · Player rating</h2><p class="small text-muted mb-0">12-month progress · provisional ability estimates</p>
      <a class="small" href="{{ route('my.tennis.event-history', $ratingPlayer) }}">Past-event ratings and badges</a>
    </div>
    @if(auth()->user()?->hasRole('super-user'))<span class="badge bg-label-primary">Super Admin preview</span>@endif
  </div>
  <a class="btn btn-outline-primary mt-3" href="{{ route('my.tennis.pathway', $ratingPlayer) }}" style="min-height:44px;white-space:normal" data-pathway-jump>What should I play next?</a>
  @include('frontend.my-tennis.pathway', ['pathway' => $ratingPathway ?? \App\Services\Performance\PlayerPathwayService::unrecorded()])
  <x-rating-explanation class="mt-3" />
  @if(auth()->user()?->hasRole('super-user'))
    <div class="d-flex flex-wrap align-items-center gap-2 mt-3" data-history-refresh-controls data-refresh-url="{{ route('my.tennis.rating.refresh') }}" data-status-url="{{ route('my.tennis.rating.refresh-status') }}">
      <button class="btn btn-outline-primary" type="button" data-history-refresh>Update saved ratings</button>
      <span class="small text-muted" role="status" data-history-refresh-status>Recalculates saved rating history for all players in the background.</span>
    </div>
  @endif
  @if($ratingHistory['reason'])
    <p role="status" class="alert alert-warning mt-3">{{ $ratingHistory['reason'] }}</p>
  @elseif(!$ratingHistory['series'])
    <p role="status" class="mt-3">No eligible rating evidence was available in the last 12 months. Missing months are unrated, not zero.</p>
  @else
    @php
      $points = $ratingHistory['points'];
      $latest = end($points) ?: null;
      $point = $ratingHistory['point'];
      $previous = $ratingHistory['previous'];
      $evidence = $ratingHistory['evidence'];
      $comparable = $point && $previous && $point['group'] === $previous['group'];
      $axis = $ratingHistory['axis'];
      $plotY = fn ($score) => 225 - (($score - $axis['min']) / ($axis['max'] - $axis['min'])) * 200;
      $query = ['cohort' => $ratingHistory['cohort'], 'month' => $ratingHistory['month'], 'scope' => $ratingHistory['scope'], 'scale' => $ratingHistory['scale']];
      $ratingUrl = fn ($changes = []) => route('my.tennis.rating', ['player' => $ratingPlayer->id] + array_replace($query, $changes));
    @endphp
    <p class="small text-muted mt-3 mb-3">Saved through {{ $ratingHistory['as_of'] }} · updated nightly at 03:00 SAST.@if($ratingHistory['stale']) Showing the latest saved calculation until the next update is ready.@endif</p>
    @if(!$points)<p role="status" class="alert alert-info">No comparable shared rating is available for this player. Suitable recent singles results must connect the player to the reference group.</p>@endif
    @if(!empty($point['historical_connection']))<p role="status" class="alert alert-warning"><strong>Low confidence — historical connection.</strong> The selected estimate needs an older match link dated {{ $point['connection_reference_date'] ?? 'unknown' }} ({{ $point['connection_age_days'] ?? '?' }} days old), even if this player has recent results.</p>@endif
    @if(($point['connection_status'] ?? '') === 'disconnected')<p role="status" class="alert alert-warning"><strong>Provisional rating · low confidence.</strong> This estimate is based on {{ ($point['evidence_basis'] ?? null) === 'finish_only' ? 'published final placings only' : 'this player’s connected evidence group' }}. It is not yet calibrated against other comparison groups; the same number in another group does not establish equal ability.</p>@endif
    <form method="get" action="{{ route('my.tennis.rating', $ratingPlayer) }}" data-rating-filters class="ctr-filters">
      <div><label for="ctr-ball">Rating level</label><select class="form-select" name="cohort" id="ctr-ball">
        @foreach(\App\Services\Performance\BallRatingModel::COHORTS as $ball => $cohort)<option value="{{ $cohort }}" @selected($ratingHistory['cohort'] === $cohort)>{{ \App\Services\Performance\BallRatingModel::LABELS[$ball] }}</option>@endforeach
        <option value="{{ \App\Services\Performance\AllAgeRatingModel::COHORT }}" @selected($ratingHistory['cohort'] === \App\Services\Performance\AllAgeRatingModel::COHORT)>Overall CTR · all ball levels</option>
      </select></div>
      <div><label for="ctr-month">Explore a month</label><select class="form-select" name="month" id="ctr-month">
        @foreach($ratingHistory['months'] as $index => $month)<option value="{{ $index }}" @selected($ratingHistory['month'] === $index)>{{ \Carbon\CarbonImmutable::parse($month['date'])->format('M Y') }} · {{ $month['date'] }}</option>@endforeach
      </select></div>
      <div><label for="ctr-scale">Graph scale</label><select class="form-select" name="scale" id="ctr-scale"><option value="focused" @selected($ratingHistory['scale'] === 'focused')>Focus on changes</option><option value="full" @selected($ratingHistory['scale'] === 'full')>Full 0–100 scale</option></select></div>
      <input type="hidden" name="scope" value="{{ $ratingHistory['scope'] }}">
      <noscript><button class="btn btn-primary" type="submit">Show selection</button></noscript>
    </form>
    <div class="ctr-stats">
      <div class="ctr-stat"><h3>Latest {{ ucfirst($ratingHistory['cohort']) }} estimate</h3>
        @if($latest)
          <x-ctr-badge :stage="$ratingHistory['ball_stage'] ?? null" :overall="empty($ratingHistory['ball_stage'])" :score="$latest['score']" :local="($latest['connection_status'] ?? '') === 'disconnected'" />
          @if(($latest['evidence_basis'] ?? null) === 'finish_only')<p class="small mt-2 mb-0">Low confidence · final placings only</p>@endif
          <p class="small text-muted mt-2 mb-0">{{ $latest['date'] }} · 0–100 ability scale</p>
          @if(!empty($latest['historical_connection']))<p class="small mt-2 mb-0"><strong>Low confidence — historical connection</strong> · link dated {{ $latest['connection_reference_date'] ?? 'unknown' }}.</p>@endif
        @else<div class="ctr-stat-value">Unrated</div>@endif
      </div>
      <div class="ctr-stat"><h3>Selected month · {{ \Carbon\CarbonImmutable::parse($ratingHistory['months'][$ratingHistory['month']]['date'])->format('M Y') }}</h3>
        <div>@if($point)<x-ctr-badge :stage="$ratingHistory['ball_stage'] ?? null" :overall="empty($ratingHistory['ball_stage'])" :score="$point['score']" :local="($point['connection_status'] ?? '') === 'disconnected'" />@else<span class="ctr-stat-value">Unrated</span>@endif</div>
        @if(($point['evidence_basis'] ?? null) === 'finish_only')<p class="small mt-2 mb-0">Low confidence · final placings only</p>@endif
        @if($comparable)<p class="mt-2 mb-0"><strong>{{ sprintf('%+.1f', $point['score'] - $previous['score']) }}</strong> from the previous month · same comparison group</p><p class="small text-muted mt-1 mb-0">Results, opponents’ results and older matches losing weight can all move your rating.</p>
        @elseif($point && $previous)<p class="small text-muted mt-2 mb-0">Comparison group changed. A like-for-like monthly change is unavailable. The difference does not reliably show improvement or decline.</p>
        @else<p class="small text-muted mt-2 mb-0">No comparable previous-month estimate.</p>@endif
      </div>
      <div class="ctr-stat"><h3>Your singles results included by this month</h3><div class="ctr-stat-value">{{ $evidence['matches'] }} {{ \Illuminate\Support\Str::plural('match', $evidence['matches']) }}</div><p class="mt-2 mb-0">{{ $evidence['wins'] }} won · {{ $evidence['losses'] }} lost</p>
        @if($evidence['matches'] < 5)<p class="small text-muted mt-1 mb-0">Small sample; treat this estimate cautiously.@if($evidence['finishes']) Also includes finishing-order evidence.@endif</p>@endif
      </div>
    </div>
    @if($point)
      <div class="small mb-3" aria-label="Selected month evidence summary">
        <p class="mb-1"><strong>Evidence confidence: {{ $point['confidence_label'] ?? ((!empty($point['historical_connection']) || ($point['connection_status'] ?? '') === 'disconnected') ? 'Low' : 'Not recorded') }}</strong> · as of {{ $point['date'] }}<br><span class="text-muted">Strength and freshness of the supporting results, not an accuracy percentage.</span></p>
        @if(array_key_exists('last_direct_match', $point))
          <p class="mb-1">Last included singles result: {{ $point['last_direct_match'] ? \Carbon\CarbonImmutable::parse($point['last_direct_match'])->format('j M Y') : 'None recorded' }}@if(isset($point['recent_played'])) · {{ $point['recent_played'] }} {{ \Illuminate\Support\Str::plural('match', $point['recent_played']) }} in the 90 days before this estimate @endif</p>
          @if(isset($point['direct_opponents'], $point['played_events']))<p class="mb-1 text-muted">Included results cover {{ $point['direct_opponents'] }} distinct {{ \Illuminate\Support\Str::plural('opponent', $point['direct_opponents']) }} across {{ $point['played_events'] }} {{ \Illuminate\Support\Str::plural('event', $point['played_events']) }}.</p>@endif
          @if(!empty($point['proxy_dated_matches']))<p class="mb-1 text-muted">Some result dates use the event date where a match date was unavailable.</p>@endif
        @else
          <p class="mb-1 text-muted">Detailed confidence and freshness were not recorded in this saved calculation. They will be included in the next nightly update.</p>
        @endif
      </div>
    @endif
    <div class="ctr-heading mb-2"><h3 class="h5 mb-0">{{ ucfirst($ratingHistory['cohort']) }} progress</h3><span class="small text-muted">Axis {{ $axis['min'] }}–{{ $axis['max'] }} · {{ $axis['step'] }}-point steps · overall scale 0–100</span></div>
    <p class="small text-muted d-sm-none mb-2">Swipe the chart to see all months. Tap a point to explore its results.</p>
    <div class="ctr-chart-scroll" role="region" aria-label="Rating chart; scroll horizontally on small screens" tabindex="0">
      <svg viewBox="0 0 660 270" role="group" aria-label="{{ ucfirst($ratingHistory['cohort']) }} monthly rating history; axis {{ $axis['min'] }} to {{ $axis['max'] }}" style="width:100%;min-width:660px;max-height:360px">
        <title>{{ ucfirst($ratingHistory['cohort']) }} monthly rating history</title>
        <desc>Choose a point to view the saved personal results included in that month. Solid lines retain the same comparison group; dashed lines show changed groups.</desc>
        @foreach($axis['ticks'] as $tick)<line x1="45" x2="635" y1="{{ $plotY($tick) }}" y2="{{ $plotY($tick) }}" stroke="#d9e2e6"/><text x="35" y="{{ $plotY($tick) + 4 }}" text-anchor="end" font-size="12" fill="#334e60">{{ $tick }}</text>@endforeach
        @foreach($points as $chartPoint)
          @php($before = $points[$loop->index - 1] ?? null)
          @if($before && $before['index'] + 1 === $chartPoint['index'])
            <line x1="{{ 45 + $before['index'] * 53 }}" y1="{{ $plotY($before['score']) }}" x2="{{ 45 + $chartPoint['index'] * 53 }}" y2="{{ $plotY($chartPoint['score']) }}" stroke="#167b70" stroke-width="3" @if($before['group'] !== $chartPoint['group']) stroke-dasharray="6 5" @endif><title>{{ $before['group'] === $chartPoint['group'] ? 'Same comparison group' : 'Comparison group or reference changed; monthly change is not directly comparable' }}</title></line>
          @endif
          <a href="{{ $ratingUrl(['month' => $chartPoint['index']]) }}" data-rating-link aria-label="Explore {{ $chartPoint['date'] }} rating {{ number_format($chartPoint['score'], 1) }}"><circle cx="{{ 45 + $chartPoint['index'] * 53 }}" cy="{{ $plotY($chartPoint['score']) }}" r="22" fill="transparent" aria-hidden="true"/><circle cx="{{ 45 + $chartPoint['index'] * 53 }}" cy="{{ $plotY($chartPoint['score']) }}" r="{{ $chartPoint['index'] === $ratingHistory['month'] ? 7 : 5 }}" fill="{{ $chartPoint['index'] === $ratingHistory['month'] ? '#d58a19' : '#167b70' }}"><title>{{ $chartPoint['date'] }}: {{ number_format($chartPoint['score'], 1) }} / 100</title></circle></a>
        @endforeach
        @foreach($ratingHistory['months'] as $index => $month)<text x="{{ 45 + $index * 53 }}" y="250" text-anchor="middle" font-size="11" fill="#334e60">{{ \Carbon\CarbonImmutable::parse($month['date'])->format('M') }}</text>@endforeach
      </svg>
    </div>
    <div class="ctr-legend mt-2"><span><i class="ctr-line-key" aria-hidden="true"></i>Same comparison group</span><span><i class="ctr-line-key ctr-line-key-dashed" aria-hidden="true"></i>Group or reference changed</span><span>Missing months remain gaps</span></div>
    <p class="small text-muted mt-2 mb-0">Dashed lines show a changed comparison group or reference. An upward or downward jump across them does not reliably show improvement or decline.</p>
    <details class="mt-3"><summary>Monthly values and changes</summary><div class="table-responsive mt-2"><table class="table table-sm" style="white-space:nowrap"><thead><tr><th>Date</th><th>CTR / 100</th><th>Monthly change</th><th>Comparison</th></tr></thead><tbody>
      @foreach($points as $tablePoint)
        @php($before = $points[$loop->index - 1] ?? null)
        @php($same = $before && $before['index'] + 1 === $tablePoint['index'] && $before['group'] === $tablePoint['group'])
        <tr><td><a href="{{ $ratingUrl(['month' => $tablePoint['index']]) }}" data-rating-link>{{ $tablePoint['date'] }}</a></td><td><x-ctr-badge size="small" :score="$tablePoint['score']" :local="($tablePoint['connection_status'] ?? '') === 'disconnected'" /></td><td>{{ $same ? sprintf('%+.1f', $tablePoint['score'] - $before['score']) : '—' }}</td><td>{{ !$before ? 'First estimate' : ($same ? 'Same group' : 'Group changed or gap') }}</td></tr>
      @endforeach
    </tbody></table></div></details>
    <section class="mt-4" aria-labelledby="ctr-results-heading">
      <div class="ctr-heading"><h3 id="ctr-results-heading" class="h5 mb-0">Your results behind this estimate</h3><span class="small text-muted">{{ $evidence['included'] }} personal evidence records included</span></div>
      <p class="small text-muted mt-2 mb-3">These saved results contributed to the model. Opponents’ results, network connections and time weighting also matter; a match does not award a fixed number of CTR points.</p>
      @if($evidence['restricted'] ?? 0)
          <p class="small text-muted">{{ $evidence['restricted'] }} TeamSheet match details restricted to administrators. Their contributions remain included in your rating and match totals.</p>
      @endif
      <div class="d-flex flex-wrap gap-2 mb-3" aria-label="Evidence scope">
        <a class="btn btn-sm {{ $ratingHistory['scope'] === 'new' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ $ratingUrl(['scope' => 'new']) }}" data-rating-link @if($ratingHistory['scope'] === 'new') aria-current="true" @endif>{{ $ratingHistory['month'] === 0 ? 'Included at chart start' : 'Added since previous month' }} ({{ $evidence['new'] }})</a>
        <a class="btn btn-sm {{ $ratingHistory['scope'] === 'all' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ $ratingUrl(['scope' => 'all']) }}" data-rating-link @if($ratingHistory['scope'] === 'all') aria-current="true" @endif>All included ({{ $evidence['included'] }})</a>
      </div>
      @if(!$point)<p role="status">No rating was available for this category in the selected month.</p>
      @elseif(!$evidence['rows'])<p role="status">{{ $ratingHistory['scope'] === 'new' ? 'No new personal results were added since the previous snapshot. A rating can still change through time weighting or opponents’ results.' : 'No direct singles results or retained finishing-order evidence were recorded for this player in this snapshot.' }}</p>
      @else
        <p class="small text-muted">Showing {{ ($evidence['page'] - 1) * 20 + 1 }}–{{ min($evidence['page'] * 20, $evidence['total']) }} of {{ $evidence['total'] }} {{ $ratingHistory['scope'] === 'all' ? 'included' : 'newly included' }} records. Eligible completed matches enter even while an event is running; finishing-order evidence waits until the event end date.</p>
        @foreach($evidence['rows'] as $result)
          <article class="ctr-result">
            <div class="ctr-result-top"><time datetime="{{ $result['date'] }}" class="small text-muted">{{ \Carbon\CarbonImmutable::parse($result['date'])->format('d M Y') }}</time><span class="badge {{ $result['kind'] === 'match' ? ($result['won'] ? 'bg-label-success' : 'bg-label-secondary') : 'bg-label-info' }}">{{ $result['kind'] === 'match' ? ($result['won'] ? 'Won' : 'Lost') : 'Finishing order' }}</span></div>
            @if($result['kind'] === 'match')<h4>vs {{ $result['opponent'] }} <strong class="ms-2">{{ $result['score'] }}</strong></h4>
            @else<h4>Model order #{{ $result['position'] }} of {{ $result['field_size'] }}</h4><p class="small text-muted mb-1">Finishing-order evidence; this model order may combine A/B divisions and is not an official finishing position.</p>@endif
            <p class="mb-1">
                @if(str_starts_with($result['source_id'], 'teamsheet:'))
                    {{ $result['event'] }} · TeamSheet result
                @else
                    <a href="{{ route('event.tab.results', ['id' => $result['event_id']]) }}">{{ $result['event'] }}</a>
                @endif
            </p>
            <p class="small text-muted mb-0">{{ $result['role'] }} · {{ $result['date_basis'] }} · {{ str_starts_with($result['source_id'], 'teamsheet:') ? 'match date' : 'event end date' }} {{ $result['completed_at'] }}</p>
          </article>
        @endforeach
        @if($evidence['pages'] > 1)<nav class="d-flex justify-content-between gap-2 mt-3" aria-label="Rating evidence pages">
          @if($evidence['page'] > 1)<a class="btn btn-outline-primary" href="{{ $ratingUrl(['page' => $evidence['page'] - 1]) }}" data-rating-link>Previous results</a>@else<span></span>@endif
          <span class="align-self-center small text-muted">{{ $evidence['page'] }} / {{ $evidence['pages'] }}</span>
          @if($evidence['page'] < $evidence['pages'])<a class="btn btn-outline-primary" href="{{ $ratingUrl(['page' => $evidence['page'] + 1]) }}" data-rating-link>More results</a>@endif
        </nav>@endif
      @endif
    </section>
    <details class="ctr-method"><summary>How to read CTR and its evidence</summary><div class="small text-muted mt-2">
      <p>CTR is a provisional ability estimate, not an official ranking or an accuracy percentage. The all-age singles rating uses played results across ages; compare players connected to the same shared reference group. Solid lines retain the same comparison group; dashed lines join consecutive snapshots whose group or baseline changed. Those changes are not like-for-like performance gains or losses.</p>
      <p>Each point refits the model using eligible completed matches dated on or before that date, including matches from ongoing events. The shared model uses up to two years of singles results with a six-month weight half-life. Comparisons needing links older than one year are marked Low confidence — historical connection. All ball colours and court formats are included without category-based rating offsets. Disconnected groups receive provisional within-group ratings with low confidence; scores are not calibrated against other groups. Recorded publication and player identity are checked as they exist now, so this is a retrospective reconstruction rather than archived historical ratings. This shared rating uses played singles results only; finishing orders and category baselines do not add points.</p>
      <p>Premium membership access remains hidden until enabled and a verified premium entitlement is connected. Super Admin can preview any player.</p>
      @if($point)<details><summary>Technical comparison reference</summary><p class="mt-2 mb-0">Cohort: {{ $ratingHistory['cohort'] }} · comparison group {{ $point['component'] }} · saved {{ $ratingHistory['built_at'] }}</p></details>@endif
    </div></details>
  @endif
</div></section>
