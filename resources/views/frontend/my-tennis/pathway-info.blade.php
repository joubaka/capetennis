@php
  $ball = in_array($infoStage, ['red', 'orange'], true) ? $infoStage : 'standard';
  $label = ['red' => 'Red ball', 'orange' => 'Orange ball', 'wilson-b' => 'Wilson B', 'wilson-a' => 'Wilson A', 'tsa' => 'TSA tournaments'][$infoStage];
  $progress = $pathway['stageProgress'][$ball] ?? [];
@endphp
<div data-pathway-stage-details="{{ $infoStage }}">
  <h5 class="h6">{{ $label }} · {{ $ratingPlayer->full_name }}</h5>
  @if($ball !== 'standard')
    <p>Enjoy singles events at {{ $label }} level and build your experience. Each ball level has its own CTR, so your progress here comes from results at this level.</p>
    <h6 class="small fw-bold">Events to explore</h6>
    <ul class="ps-3">@foreach(\App\Services\Performance\PlayerPathwayService::EVENT_OPTIONS[$ball] as $option)<li>@if(str_contains($option, 'TSA'))<a href="https://www.satennis.co.za/" target="_blank" rel="noopener noreferrer">{{ $option }} <span class="visually-hidden">(opens in a new tab)</span></a>@else{{ $option }}@endif</li>@endforeach</ul>
    @php($target = $progress['target'] ?? ($pathway['benchmarks'][$ball === 'red' ? 0 : 1]['target'] ?? null))
    <p><strong>Ready to explore the next level?</strong> @if($target) {{ number_format($target['ctr'], 0) }} CTR and {{ $target['events'] }} published {{ $label }} events. @else Benchmark awaiting configuration. @endif Chat with your coach when both targets are met using your current rating and event history at this level. Several matches at the same event count as one event.</p>
    <p><strong>Your progress so far:</strong> {{ isset($progress['score']) ? number_format($progress['score'], 1).' CTR' : 'Rating unavailable' }} · {{ $progress['events'] ?? 'Unknown' }} published events. {{ $progress['status'] ?? 'Not assessed' }}</p>
    @if($progress['reason'] ?? null)<p>{{ $progress['reason'] }}</p>@endif
    <p>These targets look at your latest {{ \App\Services\Performance\AllAgeRatingModel::WINDOW_DAYS }} days of results. Progress can change as that window moves, and playing at a higher level does not complete earlier stages.</p>
  @elseif($infoStage === 'tsa')
    <p>Thinking about TSA tournaments? Chat with your coach about which events would suit you, then check their age, entry and nomination requirements.</p>
    <p>@if($pathway['tsaCutoff'] !== null) A current comparable Green/Yellow CTR above {{ number_format($pathway['tsaCutoff'], 0) }} points to TSA tournaments as an option to explore. A CTR of exactly {{ number_format($pathway['tsaCutoff'], 0) }} still points to Wilson A. @else TSA recommendation awaiting configuration. @endif Your coach can help you choose a suitable event.</p>
    <p>This CTR suggestion is a guide, not a TSA entry minimum. TSA-sanctioned open tournaments welcome different playing strengths and levels, subject to each event’s rules. Ranking points help shape the competitive pathway, seeding and invitations.</p>
    <h6 class="small fw-bold">Junior tournament guide</h6>
    <p class="small text-muted">Formats vary by event. Check the current TSA calendar and the event’s SportyHQ listing for formats, age groups, dates, sponsors, ranking points and entry requirements.</p>
    <dl>
      <dt>Rainbow Kidz</dt>
      <dd>Under-10 introduction using red, orange and green balls on smaller courts. Usually 1–2 days, with no ranking points.</dd>
      <dt>Seasonal tournaments</dt>
      <dd>Summer, Autumn, Winter and Spring events, from U12 to Open. An option for a first TSA tournament.</dd>
      <dt>Mini Series</dt>
      <dd>Series 1–6, from U12 to Open, typically over 3 days. Platinum, Gold, Silver and Bronze groups are arranged by ranking; points depend on finishing position.</dd>
      <dt>Grand Prix</dt>
      <dd>U12 to Open, usually 4–5 days, mainly in a single Platinum draw.</dd>
      <dt>Masters</dt>
      <dd>Invitation events, with qualification through the Mini Series race rankings.</dd>
      <dt>Super 8</dt>
      <dd>Usually 5–6 days, aimed at experienced players, mainly in Platinum draws with higher ranking points.</dd>
      <dt>Junior Nationals</dt>
      <dd>The national junior championship, competing for a national junior title.</dd>
    </dl>
    <h6 class="small fw-bold">Planning your time</h6>
    <p>Allow roughly 2–6 days, depending on the event and draw: around 2–3 days for a 16-player draw, 4–5 days for a 32-player draw, and 6 days for longer events with 32-, 64- or 128-player draws. Confirm the actual schedule before making travel plans.</p>
    <a href="https://www.satennis.co.za/" target="_blank" rel="noopener noreferrer">SA Tennis · TSA events <span class="visually-hidden">(opens in a new tab)</span></a>
  @else
    <p>Wilson A and B use the same Green/Yellow rating and event history. Your current comparable CTR helps suggest a division, while your past events add context. There is no event-count target for choosing between them.</p>
    @if($infoStage === 'wilson-b')<p>After Orange, a @if($pathway['wilsonCutoff'] !== null) CTR {{ number_format($pathway['wilsonCutoff'], 0) }} or below points to Wilson B as an option to explore with your coach. @else recommendation awaiting configuration. @endif</p>
    @else<p>@if($pathway['wilsonCutoff'] !== null) CTR above {{ number_format($pathway['wilsonCutoff'], 0) }}@if($pathway['tsaCutoff'] !== null) through {{ number_format($pathway['tsaCutoff'], 0) }}@endif points to Wilson A as an option to explore with your coach. @else Recommendation awaiting configuration. @endif</p>@endif
    <h6 class="small fw-bold">Events to explore</h6>
    <ul class="ps-3">@foreach(\App\Services\Performance\PlayerPathwayService::EVENT_OPTIONS['standard'] as $option)<li>{{ $option }}</li>@endforeach</ul>
  @endif
  @if($ball === 'standard')<p><strong>Your Green/Yellow progress:</strong> {{ isset($progress['score']) ? number_format($progress['score'], 1).' CTR' : 'Rating unavailable' }} · {{ $progress['events'] ?? 'Unknown' }} published events. {{ $progress['reason'] ?? '' }}</p>@endif
  @if($pathway['current_stage'] === $ball)
    <p><strong>{{ $pathway['assessment_status'] }}</strong> · {{ $pathway['reason'] }}</p>
    <h6 class="small fw-bold">What’s next?</h6><ul class="ps-3">@foreach($pathway['next_steps'] as $step)<li>{{ $step }}</li>@endforeach</ul>
  @endif
  <p class="text-muted mb-0">This is a guide to your next step. Each event or team still has its own entry and selection process.</p>
</div>
