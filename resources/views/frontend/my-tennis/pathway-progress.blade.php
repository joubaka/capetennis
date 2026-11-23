@if(!$pendingMove && $pathway['current_stage'] === 'standard' && $pathway['currentCompetition'] && $pathway['competition_bands_available'])
  <section class="pathway-progress-panel" aria-labelledby="pathway-progress-heading">
    <h3 class="h6" id="pathway-progress-heading">Your Green/Yellow rating in perspective</h3>
    <p class="small">Latest saved CTR: <strong>{{ number_format($pathway['score'], 1) }}</strong> · {{ $pathway['presentation']['status'] }}. This is a guidance band, not a percentage of the pathway completed.</p>
    <div class="pathway-band" role="img" aria-label="Saved Green/Yellow CTR {{ number_format($pathway['score'], 1) }}. Wilson B at or below {{ $pathway['wilsonCutoff'] }}, Wilson A above {{ $pathway['wilsonCutoff'] }} through {{ $pathway['tsaCutoff'] }}, TSA above {{ $pathway['tsaCutoff'] }}.">
      <span class="pathway-band-marker" style="left:{{ max(0, min(100, $pathway['score'])) }}%">{{ number_format($pathway['score'], 1) }} ↓</span>
      <div class="pathway-band-track" aria-hidden="true"><span style="width:{{ $pathway['wilsonCutoff'] }}%"></span><span style="width:{{ $pathway['tsaCutoff'] - $pathway['wilsonCutoff'] }}%"></span><span style="width:{{ 100 - $pathway['tsaCutoff'] }}%"></span></div>
      <div class="pathway-band-labels" aria-hidden="true"><span>Wilson B · ≤{{ $pathway['wilsonCutoff'] }}</span><span>Wilson A · >{{ $pathway['wilsonCutoff'] }}–{{ $pathway['tsaCutoff'] }}</span><span>TSA · >{{ $pathway['tsaCutoff'] }}</span></div>
    </div>
  </section>
@elseif(!$pendingMove && in_array($pathway['current_stage'], ['red', 'orange'], true) && $pathway['target'])
  <section class="pathway-progress-panel" aria-labelledby="pathway-progress-heading">
    <h3 class="h6" id="pathway-progress-heading">Your {{ $pathway['current_stage_label'] }} targets</h3>
    <p class="small">{{ $pathway['assessment_status'] }}. Discuss your next step with your coach. These targets use only this ball level’s evidence.</p>
    @if($pathway['assessed'])
      <label for="pathway-rating-target">{{ $pathway['ctr_label'] }}: {{ number_format($pathway['score'], 1) }} / {{ $pathway['target']['ctr'] }}</label>
      <progress class="pathway-meter" id="pathway-rating-target" value="{{ min($pathway['score'], $pathway['target']['ctr']) }}" max="{{ $pathway['target']['ctr'] }}">{{ number_format($pathway['score'], 1) }}</progress>
      <label for="pathway-event-target">Published {{ $pathway['current_stage_label'] }} events: {{ $pathway['ownEvents'] }} / {{ $pathway['target']['events'] }}</label>
      <progress class="pathway-meter" id="pathway-event-target" value="{{ min($pathway['ownEvents'], $pathway['target']['events']) }}" max="{{ $pathway['target']['events'] }}">{{ $pathway['ownEvents'] }}</progress>
    @else
      <p class="small mb-0">Current progress is unavailable until comparable, up-to-date rating and event evidence can be assessed. Saved values are shown separately below.</p>
    @endif
  </section>
@endif
