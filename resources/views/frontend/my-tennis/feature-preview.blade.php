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
@endphp
<section class="plus-feature-explanations" aria-label="Explore Player Plus features" data-plus-feature-explanations>
  @foreach($featureDescriptions as $featureKey => [$featureTitle, $featureDescription])
    <article id="plus-feature-{{ $featureKey }}" class="plus-feature-explanation" tabindex="-1" data-plus-feature-panel="{{ $featureKey }}" @if($requestedFeature !== $featureKey) hidden @endif>
      <span class="small fw-semibold">Player Plus preview · {{ $ratingPlayer->full_name }}</span>
      <h3>@if(in_array($featureKey, ['rating', 'events'], true))<x-ctr-mark class="me-2" />@endif{{ $featureTitle }}</h3><p>{{ $featureDescription }}</p>
      <p class="small">This explanation contains no private ratings or results. Essential published tournament information remains free.</p>
      <a class="btn plus-cta" href="{{ route('player-plus.player', $ratingPlayer) }}">View membership and availability for {{ $ratingPlayer->name }} →</a>
    </article>
  @endforeach
</section>
