@php
  $featureDescriptions = [
    'record' => ['Playing Record', 'Bring eligible saved match results together by calendar year. Compare seasons, revisit recorded milestones and explore wins and losses. This is an available-record view, not a complete career archive.'],
    'opponents' => ['Opponent Insights', 'Explore recorded meetings with familiar opponents, per-season outcomes and recent encounters. Sparse results and estimated dates stay labelled; outcomes do not predict the next match.'],
    'pathway' => ['Tennis pathway', 'Explore ball-level benchmarks, recorded event experience and development options to discuss with your coach. A membership does not guarantee a rating or a completed pathway stage.'],
    'events' => ['Past-event ratings', 'Explore retrospective estimates at eligible event completion dates. These use published evidence available at each cutoff; they are not ratings originally published at the time.'],
    'rating' => ['Player rating', 'Explore saved monthly CTR estimates, confidence and the eligible evidence behind them. Missing or non-comparable evidence remains clearly identified.'],
  ];
  $featureQuery = $previewFeature ?? request()->query('feature', 'rating');
  $requestedFeature = is_string($featureQuery) && array_key_exists($featureQuery, $featureDescriptions) ? $featureQuery : 'rating';
  $previewHeading = ($showSample ?? false) ? 'h2' : 'h3';
  $samplePanels = [
      'rating' => ['Orange Ball CTR' => '48.2 / 100', 'Confidence' => 'Medium', 'Last snapshot' => 'October'],
      'record' => ['Season' => '2026', 'Recorded wins' => '12', 'Recorded losses' => '7'],
      'opponents' => ['Opponent' => 'Sample player', 'Recorded meetings' => '3', 'Match record' => '2 wins · 1 loss'],
      'pathway' => ['Current ball level' => 'Orange Ball', 'Event type to explore' => 'Junior development events'],
      'events' => ['Event' => 'Sample junior tournament', 'Estimated CTR' => '46.8 / 100'],
  ];
@endphp
<section class="plus-feature-explanations" aria-label="Explore Player Plus features" data-plus-feature-explanations>
  @foreach($featureDescriptions as $featureKey => [$featureTitle, $featureDescription])
    <article id="plus-feature-{{ $featureKey }}" class="plus-feature-explanation" tabindex="-1" data-plus-feature-panel="{{ $featureKey }}" @if($requestedFeature !== $featureKey) hidden @endif>
      <span class="small fw-semibold">Player Plus preview · {{ $ratingPlayer->full_name }}</span>
      <{{ $previewHeading }}>@if(in_array($featureKey, ['rating', 'events'], true))<x-ctr-mark class="me-2" />@endif{{ $featureTitle }}</{{ $previewHeading }}>
      <p>{{ $featureDescription }}</p>
      @if(($showSample ?? false) && $requestedFeature === $featureKey)
        <div class="plus-sample" data-plus-sample>
          <strong>Blurred example</strong>
          <p class="small mb-0">These sample values show what this tab looks like with Player Plus.</p>
          <dl>
            @foreach($samplePanels[$featureKey] as $label => $value)
              <div><dt>{{ $label }}</dt><dd aria-hidden="true">{{ $value }}</dd><dd class="visually-hidden">Sample value hidden</dd></div>
            @endforeach
          </dl>
        </div>
        @if($featureKey === 'rating')
          <p><a href="{{ route('my.tennis.feature-preview', ['player' => $ratingPlayer, 'feature' => 'events']) }}">Preview past-event ratings</a></p>
        @endif
      @endif
      <p class="small">Essential published tournament information remains free.</p>
      <a @class(['btn', 'btn-primary' => $showSample ?? false, 'plus-cta' => !($showSample ?? false)]) href="{{ route('player-plus.player', $ratingPlayer) }}">View membership and availability for {{ $ratingPlayer->name }} →</a>
    </article>
  @endforeach
</section>
