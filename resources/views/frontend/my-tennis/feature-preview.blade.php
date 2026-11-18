@php
  $featureDescriptions = [
    'record' => ['Your season. Your monthly tennis report.', 'Review matches, wins, events and recent form in your monthly report. Compare your recorded seasons.'],
    'opponents' => ['Opponent Insights', 'Revisit recorded meetings, season records and recent encounters. Past results do not predict the next match.'],
    'pathway' => ['Tennis pathway', 'Explore ball-level progress and tournament options with your coach. Suggestions depend on suitable results and do not guarantee entry.'],
    'events' => ['Past-event ratings', 'Explore retrospective estimates at eligible event completion dates. These use published evidence available at each cutoff; they are not ratings originally published at the time.'],
    'rating' => ['Player rating', 'Follow your monthly CTR history and explore the results behind it. Ratings may be provisional or unavailable.'],
  ];
  $featureQuery = $previewFeature ?? request()->query('feature', 'rating');
  $requestedFeature = is_string($featureQuery) && array_key_exists($featureQuery, $featureDescriptions) ? $featureQuery : 'rating';
  $previewHeading = ($showSample ?? false) ? 'h2' : 'h3';
  $samplePanels = [
      'rating' => ['Orange Ball CTR' => '48.2 / 100', 'Confidence' => 'Medium', 'Last snapshot' => 'October'],
      'record' => ['Month' => 'Example month', 'Recorded matches' => '6', 'Record' => '4 wins · 2 losses', 'Events' => '2'],
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
          <strong>Fictional example · not this player’s results</strong>
          <p class="small mb-0">Sample values only, not a personal assessment.</p>
          <dl>
            @foreach($samplePanels[$featureKey] as $label => $value)
              <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
          </dl>
        </div>
        @if($featureKey === 'rating')
          <p><a href="{{ route('my.tennis.feature-preview', ['player' => $ratingPlayer, 'feature' => 'events']) }}">Preview past-event ratings</a></p>
        @endif
      @endif
      <p class="small">Essential published tournament information remains free.</p>
      <a @class(['btn', 'btn-primary' => $showSample ?? false, 'plus-cta' => !($showSample ?? false)]) href="{{ app(\App\Services\Performance\PlayerRatingAccess::class)->comingSoon() ? route('ctr.index', ['player' => $ratingPlayer->id]) : route('player-plus.player', $ratingPlayer) }}">View Plus for {{ $ratingPlayer->name }} →</a>
    </article>
  @endforeach
</section>
