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
    [data-match-progress-chart] button:focus-visible { outline:3px solid #b85b00;outline-offset:3px; }
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
    .ctr-history .ctr-overview-snapshot .ct-rating-snapshot-trend { width:100%;max-width:42rem;margin:1rem 0 .5rem; }
    .ctr-history .ctr-overview-snapshot .ct-rating-snapshot-trend svg { height:auto; }
    .ctr-history .ctr-overview-snapshot figcaption { color:#e0ebf5;font-size:.8125rem; }
    .ctr-history .ctr-rating-status { color:#52677b;margin:0;padding:.5rem .75rem;background:#f1f5fa;border-radius:8px; }
    .ctr-history .ctr-overview-snapshot .ct-rating-snapshot:has([data-rating-mini-trend]) { display:block; }
    .ctr-history .ctr-overview-snapshot .ct-rating-snapshot-score { flex-direction:row;flex-wrap:wrap;align-items:center; }
    .ctr-history .ctr-match-panel { border:1px solid #dbe4ef;border-radius:14px;overflow:hidden;background:#fff;margin-top:1.5rem; }
    .ctr-history .ctr-match-heading { background:#172e45;color:#fff;padding:1.1rem clamp(1rem,2vw,1.5rem); }
    .ctr-history .ctr-match-heading h3.h5 { color:#fff;font-size:1.15rem;margin:0 0 .35rem; }
    .ctr-history .ctr-match-heading p { margin:0;color:#e0ebf5;font-size:.8125rem;line-height:1.6; }
    .ctr-history .ctr-match-body { padding:clamp(1rem,2vw,1.5rem); }
    .ctr-history .ctr-match-body .text-muted { color:#52677b !important; }
    .ctr-history .ctr-match-scroll-cue { display:none; }
    .ctr-history .ctr-match-body > p { max-width:75ch;line-height:1.6; }
    .ctr-history .ctr-match-empty-note { padding:.8rem 1rem;background:#f1f5fa;border-radius:10px;color:#233d58;font-size:.875rem; }
    .ctr-history .ctr-match-list { display:grid;gap:.65rem;margin:1rem 0; }
    .ctr-history .ctr-match-row { display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.5fr) auto;gap:.6rem 1.25rem;align-items:center;padding:1rem;border:1px solid #e0e8f1;border-radius:10px;background:#fbfcfe;overflow-wrap:anywhere; }
    .ctr-history .ctr-match-row h4 { font-size:.9rem;color:#172e45;margin:0 0 .25rem;font-weight:600; }
    .ctr-history .ctr-match-row p { margin:0;color:#52677b;font-size:.8125rem;line-height:1.5; }
    .ctr-history .ctr-match-row button { min-height:44px;white-space:normal;font-size:.8125rem; }
    @media(max-width:767.98px) { .ctr-history .ctr-match-row { grid-template-columns:1fr;gap:.5rem; }.ctr-history .ctr-match-row button { justify-self:start; } }
    @media(max-width:650px) { .ctr-history .ctr-match-scroll-cue { display:block; } }
    @media(min-width:768px) {
      .ctr-history .ctr-overview-snapshot .ct-rating-snapshot:has([data-rating-mini-trend]) {
        display:grid;grid-template-columns:minmax(0,1fr) minmax(0,2fr);gap:.5rem 1.5rem;align-items:start;
      }
      .ctr-history .ctr-overview-snapshot .ct-rating-snapshot > :is(h3,p) { grid-column:1 / -1; }
      .ctr-history .ctr-overview-snapshot .ct-rating-snapshot-trend { margin:0; }
    }
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
    .ctr-history .ctr-history-chart { container-type:inline-size; }
    .ctr-history .ctr-chart-hint { display:none; }
    @container (width < calc(660px + 1.5rem + 2px)) { .ctr-history .ctr-chart-hint { display:block; } }
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
      @include('frontend.my-tennis.rating-snapshot', ['compactUnavailable' => true])
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
        @include('frontend.my-tennis.rating-snapshot', ['expandedTrend' => true, 'compactUnavailable' => true])
      </div>
    @endif
    @include('frontend.my-tennis.rating-top-ten')
    @unless($ratingTopTen['visible'] ?? false)<p>Rating comparisons are not available for this account.</p>@endunless
    <section class="ctr-match-panel" aria-labelledby="ctr-match-progress-heading" data-match-progress>
      <div class="ctr-match-heading">
        <h3 class="h5" id="ctr-match-progress-heading">Reconstructed CTR before/after match</h3>
        <p><strong>Reconstructed using scheduled match order.</strong> CTR estimates, not archived ratings or verified match completion times.</p>
      </div>
      <div class="ctr-match-body">
      @if(!isset($matchProgress) || $matchProgress['reason'] !== null)
        <p class="small text-muted mb-0" role="status">{{ $matchProgress['reason'] ?? 'Before/after reconstruction is awaiting a saved update.' }}</p>
      @else
        <p class="small text-muted">Latest five eligible results · reconstruction updated through {{ $matchProgress['as_of'] }}. Each pair uses eligible results scheduled strictly earlier, then adds that match. Other matches at the same time and later results are excluded from both estimates.</p>
        <p class="small text-muted">Change means after minus before. These are not fixed points awarded for a win or loss, and changes must not be assumed to sum to a monthly movement.</p>
        @php
          $matchProgressRows = array_reverse($matchProgress['rows']);
          $hasMatchProgressValues = collect($matchProgressRows)->contains(fn ($row) => $row['delta'] !== null);
        @endphp
        @if(count($matchProgressRows))
          <figure class="mb-3" data-match-progress-chart>
            @if($hasMatchProgressValues)
            <p class="ctr-match-scroll-cue small text-muted">Swipe or scroll across to see every match. Use “How calculated” for exact values and evidence.</p>
            <div class="ctr-chart-scroll" tabindex="0" aria-label="Before and after match chart; scroll horizontally on a narrow screen">
              <svg viewBox="0 0 620 420" width="100%" style="min-width:600px;max-width:60rem;display:block" role="group" aria-describedby="ctr-match-grid-description" aria-label="Reconstructed using scheduled match order: CTR before and after the latest five eligible matches, oldest to newest, on a fixed 0 to 100 scale">
                <title>Reconstructed using scheduled match order</title>
                <desc id="ctr-match-grid-description">@foreach($matchProgressRows as $progress){{ $progress['date'] }} vs {{ $progress['opponent'] }}: {{ $progress['won'] ? 'Won' : 'Lost' }} {{ $progress['score'] }}. @if($progress['delta'] !== null)Before {{ number_format($progress['before_score'], 1) }}, after {{ number_format($progress['after_score'], 1) }}, change {{ sprintf('%+.1f', $progress['delta']) }} CTR. @else Reconstruction unavailable: {{ $progress['reason'] }}. @endif @endforeach</desc>
                <defs><marker id="ctr-progress-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="5" markerHeight="5" orient="auto-start-reverse"><path d="M 0 0 L 10 5 L 0 10 z" fill="context-stroke" /></marker></defs>
                @foreach([100 => 20, 50 => 110, 0 => 200] as $tick => $tickY)
                  <line x1="40" y1="{{ $tickY }}" x2="600" y2="{{ $tickY }}" stroke="#dbe4ef" /><text x="32" y="{{ $tickY + 4 }}" text-anchor="end" font-size="12" fill="#52677b">{{ $tick }}</text>
                @endforeach
                @foreach($matchProgressRows as $chartIndex => $progress)
                  @php
                    $beforeX = 60 + $chartIndex * 110;
                    $afterX = $beforeX + 60;
                  @endphp
                  <g data-match-explanation-target="ctr-match-explanation-{{ $chartIndex }}" style="cursor:pointer">
                  <rect x="{{ $beforeX - 20 }}" y="0" width="100" height="410" fill="transparent" />
                  @if($progress['delta'] !== null)
                    @php
                      $beforeY = 200 - 1.8 * $progress['before_score'];
                      $afterY = 200 - 1.8 * $progress['after_score'];
                      $slopeColor = $progress['delta'] > 0 ? '#087f5b' : ($progress['delta'] < 0 ? '#c2410c' : '#004177');
                      $changeBackground = $progress['delta'] > 0 ? '#e7f5ed' : ($progress['delta'] < 0 ? '#fff0e5' : '#eef4fb');
                    @endphp
                    <g role="img" data-match-progress-segment data-before="{{ $progress['before_score'] }}" data-after="{{ $progress['after_score'] }}" aria-label="{{ $progress['date'] }} vs {{ $progress['opponent'] }}, {{ $progress['won'] ? 'won' : 'lost' }} {{ $progress['score'] }}: reconstructed before {{ number_format($progress['before_score'], 1) }}, after {{ number_format($progress['after_score'], 1) }}, change {{ sprintf('%+.1f', $progress['delta']) }}">
                      <title>{{ $progress['opponent'] }} · reconstructed scheduled-order pair</title>
                      <line x1="{{ $beforeX }}" y1="{{ $beforeY }}" x2="{{ $afterX }}" y2="{{ $afterY }}" stroke="{{ $slopeColor }}" stroke-width="4" marker-end="url(#ctr-progress-arrow)" />
                      <circle cx="{{ $beforeX }}" cy="{{ $beforeY }}" r="4" fill="{{ $slopeColor }}" /><circle cx="{{ $afterX }}" cy="{{ $afterY }}" r="4" fill="{{ $slopeColor }}" />
                      <text x="{{ $beforeX }}" y="{{ max(12, $beforeY - 10) }}" text-anchor="middle" font-size="12" fill="#172e45">{{ number_format($progress['before_score'], 1) }}</text>
                      <text x="{{ $afterX }}" y="{{ max(12, $afterY - 10) }}" text-anchor="middle" font-size="12" fill="#172e45">{{ number_format($progress['after_score'], 1) }}</text>
                      <rect x="{{ $beforeX - 9 }}" y="248" width="78" height="24" rx="10" fill="{{ $changeBackground }}" aria-hidden="true" />
                      <text x="{{ $beforeX + 30 }}" y="265" text-anchor="middle" font-size="15" font-weight="700" fill="{{ $slopeColor }}"><title>{{ sprintf('%+.1f CTR', $progress['delta']) }}{{ (float) $progress['delta'] === 0.0 ? ' · No measurable change at this precision.' : '' }}</title>{{ $progress['delta'] > 0 ? '↑' : ($progress['delta'] < 0 ? '↓' : '→') }} {{ sprintf('%+.1f', $progress['delta']) }}</text>
                    </g>
                  @else
                    <text x="{{ $beforeX + 30 }}" y="110" text-anchor="middle" font-size="11" fill="#52677b"><title>Reconstruction unavailable: {{ $progress['reason'] }}</title>Unavailable</text>
                  @endif
                  <text x="{{ $beforeX }}" y="222" text-anchor="middle" font-size="11" fill="#172e45">Before</text><text x="{{ $afterX }}" y="222" text-anchor="middle" font-size="11" fill="#172e45">After</text>
                  <text x="{{ $beforeX + 30 }}" y="242" text-anchor="middle" font-size="12" fill="#172e45">{{ \Carbon\CarbonImmutable::parse($progress['date'])->format('d M') }}</text>
                  <foreignObject x="{{ $beforeX - 20 }}" y="282" width="100" height="130">
                    <div xmlns="http://www.w3.org/1999/xhtml" style="font:12px/16px sans-serif;color:#172e45;text-align:center;overflow-wrap:anywhere">
                      <div data-match-chart-opponent title="{{ $progress['opponent'] }}" style="font-weight:600;max-height:48px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical">{{ $progress['opponent'] }}</div>
                      <div data-match-chart-result style="margin-top:4px">{{ $progress['won'] ? 'Won' : 'Lost' }} · {{ $progress['score'] }}</div>
                      <button type="button" class="btn btn-link p-0 mt-1" style="font-size:12px" data-match-explanation-target="ctr-match-explanation-{{ $chartIndex }}" aria-controls="ctr-match-explanation-{{ $chartIndex }}" aria-expanded="false" aria-label="How calculated: {{ $progress['date'] }} vs {{ $progress['opponent'] }}">How calculated</button>
                    </div>
                  </foreignObject>
                  </g>
                @endforeach
              </svg>
            </div>
            <figcaption class="small text-muted mt-1">Oldest to newest · fixed 0–100 CTR scale. Each arrow is one reconstructed before/after pair. Gaps between matches are not connected; changes between cutoffs are not attributed to a match.</figcaption>
            @else
              <p class="ctr-match-empty-note" role="status">Your match results are saved. Before/after CTR is unavailable for these matches; the reason for each is shown below.</p>
              <div class="ctr-match-list" data-match-progress-unavailable>
                @foreach($matchProgressRows as $chartIndex => $progress)
                  <article class="ctr-match-row">
                    <div><h4>{{ $progress['opponent'] }}</h4><p>{{ \Carbon\CarbonImmutable::parse($progress['date'])->format('j M Y') }} · {{ $progress['won'] ? 'Won' : 'Lost' }} {{ $progress['score'] }}</p></div>
                    <p>{{ $progress['reason'] ?? 'A comparable before/after pair is unavailable.' }}</p>
                    <button type="button" class="btn btn-outline-primary" data-match-explanation-target="ctr-match-explanation-{{ $chartIndex }}" aria-controls="ctr-match-explanation-{{ $chartIndex }}" aria-expanded="false" aria-label="How calculated: {{ $progress['date'] }} vs {{ $progress['opponent'] }}">View explanation</button>
                  </article>
                @endforeach
              </div>
            @endif
            @foreach($matchProgressRows as $chartIndex => $progress)
              <section id="ctr-match-explanation-{{ $chartIndex }}" data-match-explanation hidden tabindex="-1" class="border rounded p-3 mt-3" aria-label="Match calculation explanation">
                <h6>{{ $progress['date'] }} · {{ $progress['opponent'] }} · {{ $progress['won'] ? 'Won' : 'Lost' }} {{ $progress['score'] }}</h6>
                <button type="button" class="btn btn-sm btn-outline-secondary mb-2" data-match-explanation-close>Close explanation</button>
                @if($progress['delta'] !== null && isset($progress['explanation']))
                  @php
                    $counts = $progress['explanation'];
                  @endphp
                  <p>Reconstructed using scheduled match order at {{ $progress['time'] }} SAST. These are retrospective estimates, not archived ratings or verified completion times.</p>
                  <dl class="row small mb-2">
                    <dt class="col-7">Eligible network matches</dt><dd class="col-5">{{ $counts['network_before'] }} before → {{ $counts['network_after'] }} after</dd>
                    <dt class="col-7">Your eligible matches</dt><dd class="col-5">{{ $counts['own_before'] }} before → {{ $counts['own_after'] }} after</dd>
                    <dt class="col-7">Your distinct opponents</dt><dd class="col-5">{{ $counts['opponents_before'] }} before → {{ $counts['opponents_after'] }} after</dd>
                  </dl>
                  <p>Before: fit the canonical CTR model to eligible earlier evidence. After: add this match and refit on the same {{ $progress['date'] }} rating date. Other matches at the same time and later matches are excluded from both fits. Both fits must converge on the same comparison scale.</p>
                  <p>The model uses score margins, opponent strength, recency and per-event evidence weights. The refit recalculates event budgets; this is not a fixed points-per-match formula, and changes are not additive.</p>
                  <p class="mb-0"><strong>{{ number_format($progress['after_score'], 1) }} after − {{ number_format($progress['before_score'], 1) }} before = {{ sprintf('%+.1f', $progress['delta']) }} CTR</strong></p>
                  <div class="table-responsive mt-3">
                    <table class="table table-sm small" style="min-width:740px">
                      <caption>Your match evidence used in this comparison. Opponent CTR values are reconstructed on this comparison's before/after cutoff, not at each listed result's date or today. Availability also follows current public comparison rules. Unavailable means permission or a stable comparable pair could not be established. Wider network matches are summarized by counts above. Scroll horizontally on a narrow screen.</caption>
                      <thead><tr><th scope="col">Date</th><th scope="col">Opponent</th><th scope="col">Your result</th><th scope="col">Opponent CTR before → after</th><th scope="col">Before inclusion</th><th scope="col">After inclusion</th></tr></thead>
                      <tbody>
                        @foreach($progress['used_matches'] ?? [] as $used)
                          <tr class="{{ !$used['before'] ? 'table-info' : '' }}">
                            <td class="text-nowrap">{{ $used['date'] }}</td><td style="min-width:150px;overflow-wrap:anywhere">{{ $used['opponent'] }}</td><td class="text-nowrap">{{ $used['won'] ? 'Won' : 'Lost' }} {{ $used['score'] }}{{ !$used['before'] ? ' · This match' : '' }}</td>
                            <td class="text-nowrap">@if(isset($used['opponent_before'], $used['opponent_after'])){{ number_format($used['opponent_before'], 1) }} → {{ number_format($used['opponent_after'], 1) }}@else Unavailable @endif</td>
                            <td class="text-nowrap">{{ $used['before'] ? 'Included' : 'Not included' }}</td><td class="text-nowrap">{{ $used['after'] ? 'Included' : 'Not included' }}</td>
                          </tr>
                        @endforeach
                      </tbody>
                    </table>
                  </div>
                  @if(($progress['hidden_matches'] ?? 0) > 0)<p class="small text-muted">{{ $progress['hidden_matches'] }} own match details are not shown because metadata is unavailable, access is restricted, or the display limit was reached. The evidence counts above include them.</p>@endif
                @else
                  <p class="mb-0">{{ $progress['reason'] ?? 'Saved calculation details are awaiting the next nightly update.' }}</p>
                @endif
              </section>
            @endforeach
          </figure>
        @else
          <p class="small text-muted">No eligible included matches with available details.</p>
        @endif
        @if($matchProgress['restricted'])<p class="small text-muted mt-2">Some match details are restricted and are not shown here.</p>@endif
        <p class="small text-muted mt-2 mb-0">A shared player scheduled in two matches at the same time, uncertain match dates or a changed comparison scale can make a pair unavailable. Current verified identity, publication and rating rules are used retrospectively.</p>
      @endif
      </div>
    </section>
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
      $query = ['month' => $ratingHistory['month'], 'scope' => $ratingHistory['scope'], 'scale' => request()->query('scale', $ratingHistory['scale']), 'rating_section' => 'history'] + $ratingBrowseFilters;
      if ($canBrowseRatings) {
          $query['rating_page'] = (int) request()->query('rating_page', 1);
      }
      $ratingUrl = fn ($changes = []) => route('my.tennis.rating', ['player' => $ratingPlayer->id] + array_replace($query, $changes));
    @endphp
    <p class="small text-muted mt-3 mb-3">Saved through {{ $ratingHistory['as_of'] }}.@if($ratingHistory['stale']) Update pending.@endif</p>
    @if(!$points)<p role="status" class="alert alert-info">No comparable shared rating is available for this player. Suitable recent singles results must connect the player to the reference group.</p>@endif
    @if(!empty($point['historical_connection']))<p role="status" class="alert alert-warning"><strong>Low confidence — historical connection.</strong> The selected estimate needs an older match link dated {{ $point['connection_reference_date'] ?? 'unknown' }} ({{ $point['connection_age_days'] ?? '?' }} days old), even if this player has recent results.</p>@endif
    @if(($point['connection_status'] ?? '') === 'disconnected')<p role="status" class="alert alert-warning"><strong>Provisional rating · low confidence.</strong> This estimate is based on {{ ($point['evidence_basis'] ?? null) === 'finish_only' ? 'published final placings only' : 'this player’s connected evidence group' }}. It is not yet calibrated against other comparison groups; the same number in another group does not establish equal ability.</p>@endif
    <div class="ctr-heading mb-2"><h3 class="h5 mb-0">{{ ucfirst($ratingHistory['cohort']) }} progress</h3><span class="small text-muted">CTR out of 100</span></div>
    <div class="ctr-history-chart">
    <p class="ctr-chart-hint small text-muted mb-2">Scroll the chart sideways to see all months.</p>
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
      <input type="hidden" name="scale" value="{{ $query['scale'] }}">
      @if($ratingFragment)<noscript>@endif
      <button class="btn btn-primary" type="submit">Show selection</button>
      @if($ratingFragment)</noscript>@endif
    </form>
    <p class="small text-muted mt-3" data-rating-progress-explanation>Your CTR is recalculated from eligible results, including score margins, opponents’ playing strength and how recent the evidence is. There is no fixed number of CTR points for a win or loss. Compare monthly changes within the same comparison group.</p>
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
      <p>Ratings update nightly at 03:02 SAST. Opponents’ results and the age of your matches affect your estimate. Completed matches can count during an event. Finishing positions, where eligible, are considered after the event ends.</p>
      <p>Each point refits the model using eligible completed matches dated on or before that date, including matches from ongoing events. The shared model uses up to two years of singles results with a six-month weight half-life. Comparisons needing links older than one year are marked Low confidence — historical connection. Only Green/Yellow singles enter CTR; Red and Orange remain in their own rating pools, without cross-pool fallback. Disconnected groups receive provisional within-group ratings with low confidence; scores are not calibrated against other groups. Recorded publication and player identity are checked as they exist now, so this is a retrospective reconstruction rather than archived historical ratings. This shared rating uses played singles results only; finishing orders and category baselines do not add points.</p>
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
