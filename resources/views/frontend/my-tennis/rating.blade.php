@php
  $ratingFragment = ($embeddedRating ?? false) || request()->ajax();
  $activeRatingSection = match (request()->query('rating_section')) {
    'history' => 'progress',
    'method' => 'understand',
    default => 'compare',
  };
  $ratingSections = ['compare' => 'Rating overview', 'progress' => 'Rating history', 'understand' => 'How ratings work'];
  $canBrowseRatings = $ratingTopTen['can_browse'] ?? false;
  $ratingBrowseFilters = $canBrowseRatings ? ($ratingTopTen['filters'] ?? []) : [];
  $ratingSectionFilters = request()->only(['month', 'scope', 'scale', 'page']);
  if ($canBrowseRatings) {
    $ratingSectionFilters += $ratingBrowseFilters + request()->only(['rating_page']);
  }
  $ratingSectionUrl = fn ($section) => route('my.tennis.rating', ['player' => $ratingPlayer->id, 'rating_section' => $section] + $ratingSectionFilters);
@endphp
<section class="card ctr-history ct-player-workspace" aria-labelledby="player-rating-heading"><div class="card-body">
  <style>
    .ctr-history { border:1px solid #dbe4ef;border-radius:18px;overflow:hidden; }
    .ctr-history > .card-body { padding:clamp(1rem,3vw,2rem); }
    .ctr-history .ct-workspace-header .badge { background:#f4f6f8;color:#172e45;border:1px solid #dce5ef; }
    .ctr-history h3.h5 { color:#172e45;font-weight:600; }
    .ctr-history a:focus-visible,.ctr-history button:focus-visible,.ctr-history select:focus-visible,.ctr-history summary:focus-visible { outline:3px solid #b85b00;outline-offset:3px; }
    .ctr-history .btn-primary { background:#004177;border-color:#004177;color:#fff; }
    .ctr-history .btn-primary:hover { background:#172e45;border-color:#172e45; }
    .ctr-history .btn-outline-primary { color:#004177;border-color:#a5bcd5; }
    .ctr-history .btn-outline-primary:hover { background:#eef3fa;color:#172e45;border-color:#004177; }
    .ctr-history .ctr-section-tabs { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));grid-auto-rows:1fr;gap:.5rem; }
    .ctr-history .ctr-section-tabs .nav-link { min-height:44px;text-align:center;overflow-wrap:anywhere;white-space:normal;color:#47645c;background:#f1f5fa;border:1px solid #dbe4ef;border-radius:10px;font-weight:600; }
    .ctr-history .ctr-section-tabs .nav-link:hover { background:#e4edf8; }
    .ctr-history .ctr-section-tabs .nav-link.active { background:#004177;border-color:#004177;color:#fff;box-shadow:0 3px 8px #172e4518; }
    .ctr-history .ctr-section-content { padding:0;background:transparent;box-shadow:none; }
    .ctr-history .ctr-section-content > .tab-pane:not(.active) { display:none; }
    .ctr-history .ctr-overview-snapshot .ct-rating-snapshot {
      padding:clamp(1rem,2vw,1.5rem);
      background:#172e45;
      border-color:#172e45;
      color:#fff;
    }
    .ctr-history .ctr-overview-snapshot h3 { color:#fff;font-size:1rem;margin:0 0 .5rem; }
    .ctr-history .ctr-overview-snapshot p { margin:.35rem 0 0;color:#e0ebf5;font-size:.875rem; }
    @media(max-width:767.98px) { .ctr-history .ctr-section-tabs .nav-link { padding:.4rem .25rem;font-size:.8rem; } }
    .ctr-history .ctr-heading { display:flex;flex-wrap:wrap;justify-content:space-between;gap:.75rem;align-items:center; }
    .ctr-history .ctr-stats { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;margin:1.25rem 0; }
    .ctr-history .ctr-stat { border:1px solid #dce6e2;border-radius:14px;padding:1.25rem;min-width:0; }
    .ctr-history .ctr-stats .ctr-stat { background:#f7f9fc;border-top:3px solid #004177; }
    .ctr-history .ctr-stats .ctr-stat:nth-child(2) { background:#fff6ed;border-top-color:#b85b00; }
    .ctr-history .ctr-stat h3 { font-size:.8rem;color:#5e716c;margin:0 0 .6rem; }
    .ctr-history .ctr-stat-value { color:#233d50;font-size:1.6rem;line-height:1.3;font-weight:700; }
    .ctr-history .ctr-filters { display:grid;grid-template-columns:1.5fr 1fr;gap:1rem;align-items:center;background:#f1f5fa;border:1px solid #dbe4ef;padding:1rem;border-radius:12px; }
    .ctr-history .ctr-filters label { font-size:.8rem;display:block;margin-bottom:.3rem; }
    .ctr-history .ctr-result { border-top:1px solid #e0e7e3;padding:1rem 0;overflow-wrap:anywhere; }
    .ctr-history .ctr-result-top { display:flex;flex-wrap:wrap;justify-content:space-between;gap:.5rem;align-items:center; }
    .ctr-history .ctr-result h4 { font-size:1rem;margin:.5rem 0; }
    .ctr-history .ctr-legend { display:flex;flex-wrap:wrap;gap:.5rem 1.25rem;color:#5e716c;font-size:.8rem; }
    .ctr-history .ctr-line-key { display:inline-block;vertical-align:middle;width:24px;border-top:3px solid #004177;margin-right:.4rem; }
    .ctr-history .ctr-line-key-dashed { border-top-style:dashed; }
    .ctr-history [data-history-refresh]:disabled::before { content:'';display:inline-block;width:1rem;height:1rem;margin-right:.5rem;vertical-align:middle;border:.15em solid currentColor;border-right-color:transparent;border-radius:50%;animation:ctr-update-spin .75s linear infinite; }
    @keyframes ctr-update-spin { to { transform:rotate(360deg); } }
    @media(prefers-reduced-motion:reduce) { .ctr-history [data-history-refresh]:disabled::before { animation:none; } }
    .ctr-history .ctr-chart-scroll { overflow-x:auto;max-width:100%;border:1px solid #dbe4ef;border-radius:14px;background:linear-gradient(#f7f9fc,#fff);padding:.75rem; }
    .ctr-history .ctr-method { border-top:1px solid #e0e7e3;margin-top:1.5rem;padding-top:1rem; }
    @media(max-width:991.98px) { .ctr-history .ctr-stats { grid-template-columns:1fr;gap:.65rem; } }
    @media(max-width:767.98px) { .ctr-history .ctr-filters { grid-template-columns:1fr; } }
  </style>
  @if($ratingFragment)
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
      <h2 id="player-rating-heading" class="h5 mb-0">Player rating</h2>
      <a class="btn btn-sm btn-outline-primary" data-my-tennis-link href="{{ route('my.tennis.event-history', $ratingPlayer) }}">Past-event ratings</a>
    </div>
    <template data-profile-rating-update>
      @include('frontend.my-tennis.profile-rating-badge', [
        'badgeSnapshot' => \App\Services\Performance\PlayerRatingHistoryDisplay::snapshot($ratingHistory),
        'badgeUrl' => route('my.tennis', ['player' => $ratingPlayer->id, 'tab' => 'rating']),
      ])
    </template>
  @else
  <x-player-workspace-header compact title="Player rating" :subtitle="$ratingPlayer->full_name" heading="h2" heading-id="player-rating-heading" data-rating-hero>
      @if(auth()->user()?->hasRole('super-user'))<span class="badge mt-3">Super Admin preview</span>@endif
    <x-slot:summary>
      @include('frontend.my-tennis.rating-snapshot')
    </x-slot:summary>
    <x-slot:navigation>
    <a href="{{ route('my.tennis', ['player' => $ratingPlayer->id]) }}">Back to My Tennis profile</a>
    @include('frontend.my-tennis.player-navigation', ['navigationPlayer' => $ratingPlayer, 'embeddedDashboard' => true, 'compactNavigation' => true])
    </x-slot:navigation>
  </x-player-workspace-header>
  @endif
  @if($ratingFragment)
    <div class="nav nav-pills ctr-section-tabs my-3" role="tablist" aria-label="Explore your rating">
      @foreach($ratingSections as $section => $label)
        <button class="nav-link {{ $section === $activeRatingSection ? 'active' : '' }}" id="ctr-tab-{{ $section }}" data-rating-section="{{ $section }}" data-bs-toggle="pill" data-bs-target="#ctr-panel-{{ $section }}" type="button" role="tab" aria-controls="ctr-panel-{{ $section }}" aria-selected="{{ $section === $activeRatingSection ? 'true' : 'false' }}">{{ $label }}</button>
      @endforeach
    </div>
    <noscript><p><a href="{{ $ratingSectionUrl('history') }}">Open rating history</a></p></noscript>
  @else
    <nav class="nav nav-pills ctr-section-tabs my-3" aria-label="Explore your rating">
      @foreach(['compare' => null, 'progress' => 'history', 'understand' => 'method'] as $section => $sectionQuery)
        <a class="nav-link {{ $section === $activeRatingSection ? 'active' : '' }}" id="ctr-tab-{{ $section }}" href="{{ $ratingSectionUrl($sectionQuery) }}" @if($section === $activeRatingSection) aria-current="page" @endif>{{ $ratingSections[$section] }}</a>
      @endforeach
    </nav>
  @endif
  <div class="tab-content ctr-section-content">
  <div class="tab-pane {{ $activeRatingSection === 'compare' ? 'active show' : '' }}" id="ctr-panel-compare" role="tabpanel" aria-labelledby="ctr-tab-compare" tabindex="0">
    @if($ratingFragment)
      <div class="ctr-overview-snapshot">
        @include('frontend.my-tennis.rating-snapshot')
      </div>
    @endif
    @include('frontend.my-tennis.rating-top-ten')
    @unless($ratingTopTen['visible'] ?? false)<p>Rating comparisons are not available for this account.</p>@endunless
  </div>
  <div class="tab-pane {{ $activeRatingSection === 'progress' ? 'active show' : '' }}" id="ctr-panel-progress" role="tabpanel" aria-labelledby="ctr-tab-progress" tabindex="0">
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
      $selectedIsLatest = $point && $latest && $point['index'] === $latest['index'] && $point['date'] === $latest['date'];
      $axis = $ratingHistory['axis'];
      $plotY = fn ($score) => 225 - (($score - $axis['min']) / ($axis['max'] - $axis['min'])) * 200;
      $query = ['month' => $ratingHistory['month'], 'scope' => $ratingHistory['scope'], 'scale' => $ratingHistory['scale'], 'rating_section' => 'history'] + $ratingBrowseFilters;
      if ($canBrowseRatings) {
          $query['rating_page'] = $ratingTopTen['lists'][$ratingBrowseFilters['rating_level'] ?? 'overall']['pagination']['page'] ?? 1;
      }
      $ratingUrl = fn ($changes = []) => route('my.tennis.rating', ['player' => $ratingPlayer->id] + array_replace($query, $changes));
    @endphp
    <p class="small text-muted mt-3 mb-3">Saved through {{ $ratingHistory['as_of'] }}.@if($ratingHistory['stale']) Update pending.@endif</p>
    @if(!$points)<p role="status" class="alert alert-info">No comparable shared rating is available for this player. Suitable recent singles results must connect the player to the reference group.</p>@endif
    @if(!empty($point['historical_connection']))<p role="status" class="alert alert-warning"><strong>Low confidence — historical connection.</strong> The selected estimate needs an older match link dated {{ $point['connection_reference_date'] ?? 'unknown' }} ({{ $point['connection_age_days'] ?? '?' }} days old), even if this player has recent results.</p>@endif
    @if(($point['connection_status'] ?? '') === 'disconnected')<p role="status" class="alert alert-warning"><strong>Provisional rating · low confidence.</strong> This estimate is based on {{ ($point['evidence_basis'] ?? null) === 'finish_only' ? 'published final placings only' : 'this player’s connected evidence group' }}. It is not yet calibrated against other comparison groups; the same number in another group does not establish equal ability.</p>@endif
    <div class="ctr-heading mb-2"><h3 class="h5 mb-0">{{ ucfirst($ratingHistory['cohort']) }} progress</h3><span class="small text-muted">CTR out of 100</span></div>
    <p class="small text-muted d-sm-none mb-2">Swipe the chart to see all months. Tap a point to explore its results.</p>
    <div class="ctr-chart-scroll" role="region" aria-label="Rating chart; scroll horizontally on small screens" tabindex="0">
      <svg viewBox="0 0 660 270" role="group" aria-label="{{ ucfirst($ratingHistory['cohort']) }} monthly rating history; axis {{ $axis['min'] }} to {{ $axis['max'] }}" style="width:100%;min-width:660px;max-height:360px">
        <title>{{ ucfirst($ratingHistory['cohort']) }} monthly rating history</title>
        <desc>Choose a point to view the saved personal results included in that month. Solid lines retain the same comparison group; dashed lines show changed groups.</desc>
        @foreach($axis['ticks'] as $tick)<line x1="45" x2="635" y1="{{ $plotY($tick) }}" y2="{{ $plotY($tick) }}" stroke="#d9e2e6"/><text x="35" y="{{ $plotY($tick) + 4 }}" text-anchor="end" font-size="12" fill="#334e60">{{ $tick }}</text>@endforeach
        @foreach($points as $chartPoint)
          @php($before = $points[$loop->index - 1] ?? null)
          @if($before && $before['index'] + 1 === $chartPoint['index'])
            <line x1="{{ 45 + $before['index'] * 53 }}" y1="{{ $plotY($before['score']) }}" x2="{{ 45 + $chartPoint['index'] * 53 }}" y2="{{ $plotY($chartPoint['score']) }}" stroke="#004177" stroke-width="3" @if($before['group'] !== $chartPoint['group']) stroke-dasharray="6 5" @endif><title>{{ $before['group'] === $chartPoint['group'] ? 'Same comparison group' : 'Comparison group or reference changed; monthly change is not directly comparable' }}</title></line>
          @endif
          <a href="{{ $ratingUrl(['month' => $chartPoint['index']]) }}" data-rating-link aria-label="Explore {{ $chartPoint['date'] }} rating {{ number_format($chartPoint['score'], 1) }}"><circle cx="{{ 45 + $chartPoint['index'] * 53 }}" cy="{{ $plotY($chartPoint['score']) }}" r="22" fill="transparent" aria-hidden="true"/><circle cx="{{ 45 + $chartPoint['index'] * 53 }}" cy="{{ $plotY($chartPoint['score']) }}" r="{{ $chartPoint['index'] === $ratingHistory['month'] ? 7 : 5 }}" fill="{{ $chartPoint['index'] === $ratingHistory['month'] ? '#b85b00' : '#004177' }}"><title>{{ $chartPoint['date'] }}: {{ number_format($chartPoint['score'], 1) }} / 100</title></circle></a>
        @endforeach
        @foreach($ratingHistory['months'] as $index => $month)<text x="{{ 45 + $index * 53 }}" y="250" text-anchor="middle" font-size="11" fill="#334e60">{{ \Carbon\CarbonImmutable::parse($month['date'])->format('M') }}</text>@endforeach
      </svg>
    </div>
    <div class="ctr-legend mt-2"><span><i class="ctr-line-key" aria-hidden="true"></i>Same comparison group</span><span><i class="ctr-line-key ctr-line-key-dashed" aria-hidden="true"></i>Group or reference changed</span><span>Missing months remain gaps</span></div>
    <p class="small text-muted mt-2 mb-0">Dashed lines mark changes in comparison group or reference. Compare changes within solid sections.</p>
    <form method="get" action="{{ route('my.tennis.rating', $ratingPlayer) }}" data-rating-filters class="ctr-filters mt-3">
      @foreach($ratingBrowseFilters as $filter => $value)<input type="hidden" name="{{ $filter }}" value="{{ $value }}">@endforeach
      @if($canBrowseRatings)<input type="hidden" name="rating_page" value="{{ $query['rating_page'] }}">@endif
      <input type="hidden" name="rating_section" value="history">
      <div><span class="small text-muted d-block">Current rating group</span><strong data-current-rating-group>{{ $ratingHistory['current_group_label'] ?? 'Not rated yet' }}</strong></div>
      <div><label for="ctr-month">Explore a month</label><select class="form-select" name="month" id="ctr-month">
        @foreach($ratingHistory['months'] as $index => $month)<option value="{{ $index }}" @selected($ratingHistory['month'] === $index)>{{ \Carbon\CarbonImmutable::parse($month['date'])->format('M Y') }}</option>@endforeach
      </select></div>
      <input type="hidden" name="scope" value="{{ $ratingHistory['scope'] }}">
      @if($ratingFragment)<noscript>@endif
      <button class="btn btn-primary" type="submit">Show selection</button>
      @if($ratingFragment)</noscript>@endif
    </form>
    <div class="ctr-stats">
      <div class="ctr-stat" data-selected-month-summary><h3>Selected month · {{ \Carbon\CarbonImmutable::parse($ratingHistory['months'][$ratingHistory['month']]['date'])->format('M Y') }}</h3>
        <div>@if($selectedIsLatest)<p class="mb-0">Latest saved month</p>@elseif($point)<x-ctr-badge :stage="$ratingHistory['ball_stage'] ?? null" :overall="empty($ratingHistory['ball_stage'])" :score="$point['score']" :local="($point['connection_status'] ?? '') === 'disconnected'" />@else<span class="ctr-stat-value">Unrated</span>@endif</div>
        @if(($point['evidence_basis'] ?? null) === 'finish_only')<p class="small mt-2 mb-0">Low confidence · final placings only</p>@endif
        @if($comparable)<p class="mt-2 mb-0"><strong>{{ sprintf('%+.1f', $point['score'] - $previous['score']) }}</strong> from the previous month · same comparison group</p>
        @elseif($point && $previous)<p class="small text-muted mt-2 mb-0">Comparison group changed. No like-for-like monthly change.</p>
        @else<p class="small text-muted mt-2 mb-0">No comparable previous-month estimate.</p>@endif
      </div>
      <div class="ctr-stat"><h3>Singles results included</h3><div class="ctr-stat-value">{{ $evidence['matches'] }} {{ \Illuminate\Support\Str::plural('match', $evidence['matches']) }}</div><p class="mt-2 mb-0">{{ $evidence['wins'] }} won · {{ $evidence['losses'] }} lost</p>
        @if($evidence['matches'] < 5)<p class="small text-muted mt-1 mb-0">Small sample. Use this estimate with care.@if($evidence['finishes']) Also includes finishing-order evidence.@endif</p>@endif
      </div>
    </div>
    @if($point)
      <details class="small mb-3">
        <summary>Rating evidence @if(($point['confidence_label'] ?? null) === 'Low') · Low confidence @endif</summary>
        <p class="mb-1"><strong>Evidence confidence: {{ $point['confidence_label'] ?? ((!empty($point['historical_connection']) || ($point['connection_status'] ?? '') === 'disconnected') ? 'Low' : 'Not recorded') }}</strong> · as of {{ $point['date'] }}<br><span class="text-muted">Strength and freshness of the supporting results, not an accuracy percentage.</span></p>
        @if(array_key_exists('last_direct_match', $point))
          <p class="mb-1">Last included singles result: {{ $point['last_direct_match'] ? \Carbon\CarbonImmutable::parse($point['last_direct_match'])->format('j M Y') : 'None recorded' }}@if(isset($point['recent_played'])) · {{ $point['recent_played'] }} {{ \Illuminate\Support\Str::plural('match', $point['recent_played']) }} in the 90 days before this estimate @endif</p>
          @if(isset($point['direct_opponents'], $point['played_events']))<p class="mb-1 text-muted">Included results cover {{ $point['direct_opponents'] }} distinct {{ \Illuminate\Support\Str::plural('opponent', $point['direct_opponents']) }} across {{ $point['played_events'] }} {{ \Illuminate\Support\Str::plural('event', $point['played_events']) }}.</p>@endif
          @if(!empty($point['proxy_dated_matches']))<p class="mb-1 text-muted">Some result dates use the event date where a match date was unavailable.</p>@endif
        @else
          <p class="mb-1 text-muted">Detailed confidence and freshness were not recorded in this saved calculation. They will be included in the next nightly update.</p>
        @endif
      </details>
    @endif
    <details class="mt-3"><summary>Monthly rating values</summary><div class="table-responsive mt-2"><table class="table table-sm" style="white-space:nowrap"><thead><tr><th>Date</th><th>CTR / 100</th>@if(auth()->user()?->hasRole('super-user'))<th>Monthly change</th><th>Comparison</th>@endif</tr></thead><tbody>
      @foreach($points as $tablePoint)
        @php($before = $points[$loop->index - 1] ?? null)
        @php($same = $before && $before['index'] + 1 === $tablePoint['index'] && $before['group'] === $tablePoint['group'])
        <tr><td><a href="{{ $ratingUrl(['month' => $tablePoint['index']]) }}" data-rating-link>{{ $tablePoint['date'] }}</a></td><td><x-ctr-badge size="small" :score="$tablePoint['score']" :local="($tablePoint['connection_status'] ?? '') === 'disconnected'" /></td>@if(auth()->user()?->hasRole('super-user'))<td>{{ $same ? sprintf('%+.1f', $tablePoint['score'] - $before['score']) : '—' }}</td><td>{{ !$before ? 'First estimate' : ($same ? 'Same group' : 'Group changed or gap') }}</td>@endif</tr>
      @endforeach
    </tbody></table></div></details>
    <section class="mt-4" aria-labelledby="ctr-results-heading">
      <div class="ctr-heading"><h3 id="ctr-results-heading" class="h5 mb-0">Results used in this estimate</h3><span class="small text-muted">{{ $evidence['included'] }} records</span></div>
      @if($evidence['restricted'] ?? 0)
          <p class="small text-muted">{{ $evidence['restricted'] }} TeamSheet match details restricted to administrators. Their contributions remain included in your rating and match totals.</p>
      @endif
      <div class="d-flex flex-wrap gap-2 mb-3" aria-label="Evidence scope">
        <a class="btn btn-sm {{ $ratingHistory['scope'] === 'new' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ $ratingUrl(['scope' => 'new']) }}" data-rating-link @if($ratingHistory['scope'] === 'new') aria-current="true" @endif>{{ $ratingHistory['month'] === 0 ? 'Included at chart start' : 'Added since previous month' }} ({{ $evidence['new'] }})</a>
        <a class="btn btn-sm {{ $ratingHistory['scope'] === 'all' ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ $ratingUrl(['scope' => 'all']) }}" data-rating-link @if($ratingHistory['scope'] === 'all') aria-current="true" @endif>All included ({{ $evidence['included'] }})</a>
      </div>
      @if(!$point)<p role="status">No rating was available for this category in the selected month.</p>
      @elseif(!$evidence['rows'])<p role="status">{{ $ratingHistory['scope'] === 'new' ? 'No new results since the previous month.' : 'No direct singles results or retained finishing-order evidence were recorded for this player in this snapshot.' }}</p>
      @else
        <p class="small text-muted">Showing {{ ($evidence['page'] - 1) * 20 + 1 }}–{{ min($evidence['page'] * 20, $evidence['total']) }} of {{ $evidence['total'] }} {{ $ratingHistory['scope'] === 'all' ? 'included' : 'newly included' }} records.</p>
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

  @endif
  </div>
  <div class="tab-pane {{ $activeRatingSection === 'understand' ? 'active show' : '' }}" id="ctr-panel-understand" role="tabpanel" aria-labelledby="ctr-tab-understand" tabindex="0">
    <h3 class="h5">What does my rating mean?</h3>
    <x-rating-explanation class="mt-3" />
    <details class="ctr-method"><summary>How to read CTR and its evidence</summary><div class="small text-muted mt-2">
      <p>CTR is a provisional ability estimate, not an official ranking or an accuracy percentage. The all-age singles rating uses played results across ages; compare players connected to the same shared reference group. Solid lines retain the same comparison group; dashed lines join consecutive snapshots whose group or baseline changed. Those changes are not like-for-like performance gains or losses.</p>
      <p>Ratings update nightly at 03:00 SAST. Opponents’ results and the age of your matches affect your estimate. Completed matches can count during an event. Finishing positions, where eligible, are considered after the event ends.</p>
      <p>Each point refits the model using eligible completed matches dated on or before that date, including matches from ongoing events. The shared model uses up to two years of singles results with a six-month weight half-life. Comparisons needing links older than one year are marked Low confidence — historical connection. All ball colours and court formats are included without category-based rating offsets. Disconnected groups receive provisional within-group ratings with low confidence; scores are not calibrated against other groups. Recorded publication and player identity are checked as they exist now, so this is a retrospective reconstruction rather than archived historical ratings. This shared rating uses played singles results only; finishing orders and category baselines do not add points.</p>
    </div></details>
  </div>
  </div>
  @if(auth()->user()?->hasRole('super-user'))
    <details class="mt-3" data-rating-admin-tools>
      <summary>Admin tools</summary>
    <div class="d-flex flex-wrap align-items-center gap-2 mt-3" data-history-refresh-controls data-refresh-url="{{ route('my.tennis.rating.refresh') }}" data-status-url="{{ route('my.tennis.rating.refresh-status') }}">
      <button class="btn btn-outline-primary" type="button" data-history-refresh>Update saved ratings</button>
      <span class="small text-muted" role="status" data-history-refresh-status>Recalculates saved rating history for all players in the background.</span>
      <span class="small text-muted w-100" data-history-last-updated>
        @if($ratingHistory['built_at'] ?? null)
          Latest saved update for this player: <time datetime="{{ $ratingHistory['built_at'] }}">{{ \Carbon\CarbonImmutable::parse($ratingHistory['built_at'])->setTimezone('Africa/Johannesburg')->format('j M Y, H:i') }} SAST</time>
        @else
          No saved update for this player yet.
        @endif
      </span>
    </div>
    </details>
  @endif
</div></section>
