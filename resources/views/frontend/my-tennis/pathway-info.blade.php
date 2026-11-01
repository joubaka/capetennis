@php
  $ball = in_array($infoStage, ['red', 'orange'], true) ? $infoStage : 'standard';
  $label = ['red' => 'Red ball', 'orange' => 'Orange ball', 'wilson-b' => 'Wilson B', 'wilson-a' => 'Wilson A', 'tsa' => 'TSA tournaments'][$infoStage];
  $progress = $pathway['stageProgress'][$ball] ?? [];
@endphp
<div data-pathway-stage-details="{{ $infoStage }}">
  <h5 class="h6">{{ $label }} · {{ $ratingPlayer->full_name }}</h5>
  @if($ball !== 'standard')
    <p>Play eligible published {{ $label }} singles. Each ball level has its own opponent-adjusted CTR; results at another level do not complete this stage.</p>
    <h6 class="small fw-bold">Events to discuss with your coach</h6>
    <ul class="ps-3">@foreach(\App\Services\Performance\PlayerPathwayService::EVENT_OPTIONS[$ball] as $option)<li>@if(str_contains($option, 'TSA'))<a href="https://www.satennis.co.za/" target="_blank" rel="noopener noreferrer">{{ $option }} <span class="visually-hidden">(opens in a new tab)</span></a>@else{{ $option }}@endif</li>@endforeach</ul>
    @php($target = $progress['target'] ?? ($pathway['benchmarks'][$ball === 'red' ? 0 : 1]['target'] ?? null))
    <p><strong>Before reviewing the next level:</strong> @if($target) {{ number_format($target['ctr'], 0) }} CTR and {{ $target['events'] }} published {{ $label }} events. @else Benchmark awaiting configuration. @endif Both requirements use current comparable evidence at this ball level. Multiple matches in one event count as one event.</p>
    <p><strong>Your saved progress:</strong> {{ isset($progress['score']) ? number_format($progress['score'], 1).' CTR' : 'Rating unavailable' }} · {{ $progress['events'] ?? 'Unknown' }} published events. {{ $progress['status'] ?? 'Not assessed' }}</p>
    @if($progress['reason'] ?? null)<p>{{ $progress['reason'] }}</p>@endif
    <p>Targets describe the current {{ \App\Services\Performance\AllAgeRatingModel::WINDOW_DAYS }}-day evidence window, not a permanent completion award. A higher rated level does not complete earlier stages.</p>
  @elseif($infoStage === 'tsa')
    <p>TSA tournaments follow their own entry rules. Discuss tournament choice with your coach and check the event’s age, entry and nomination requirements.</p>
    <p>@if($pathway['tsaCutoff'] !== null) Current comparable Green/Yellow CTR above {{ number_format($pathway['tsaCutoff'], 0) }} suggests reviewing TSA tournaments. Exactly {{ number_format($pathway['tsaCutoff'], 0) }} remains in the Wilson A suggestion. @else TSA recommendation awaiting configuration. @endif This is guidance, not an entry restriction or automatic selection.</p>
    <a href="https://www.satennis.co.za/" target="_blank" rel="noopener noreferrer">SA Tennis · TSA events <span class="visually-hidden">(opens in a new tab)</span></a>
  @else
    <p>Wilson B and Wilson A share the Green/Yellow ball rating and supporting event count. The suggested division uses current comparable CTR; event count is supporting history, not a division-entry requirement.</p>
    @if($infoStage === 'wilson-b')<p>After Orange, @if($pathway['wilsonCutoff'] !== null) CTR {{ number_format($pathway['wilsonCutoff'], 0) }} or below suggests Wilson B. @else recommendation awaiting configuration. @endif</p>
    @else<p>@if($pathway['wilsonCutoff'] !== null) CTR above {{ number_format($pathway['wilsonCutoff'], 0) }}@if($pathway['tsaCutoff'] !== null) through {{ number_format($pathway['tsaCutoff'], 0) }}@endif suggests Wilson A. @else Recommendation awaiting configuration. @endif</p>@endif
    <h6 class="small fw-bold">Events to discuss with your coach</h6>
    <ul class="ps-3">@foreach(\App\Services\Performance\PlayerPathwayService::EVENT_OPTIONS['standard'] as $option)<li>{{ $option }}</li>@endforeach</ul>
  @endif
  @if($ball === 'standard')<p><strong>Your saved Green/Yellow evidence:</strong> {{ isset($progress['score']) ? number_format($progress['score'], 1).' CTR' : 'Rating unavailable' }} · {{ $progress['events'] ?? 'Unknown' }} published events. {{ $progress['reason'] ?? '' }}</p>@endif
  @if($pathway['current_stage'] === $ball)
    <p><strong>{{ $pathway['assessment_status'] }}</strong> · {{ $pathway['reason'] }}</p>
    <h6 class="small fw-bold">What to do next</h6><ul class="ps-3">@foreach($pathway['next_steps'] as $step)<li>{{ $step }}</li>@endforeach</ul>
  @endif
  <p class="text-muted mb-0">Review with your coach. Promotion is not automatic; a rating or pathway suggestion does not grant event entry or team selection.</p>
</div>
