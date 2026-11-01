@php
    $pathwayPoints = array_filter($ratingHistory['points'] ?? [], fn ($point) => is_numeric($point['score'] ?? null) && is_finite((float) $point['score']));
    $pathwayLatest = $pathwayPoints ? end($pathwayPoints) : null;
@endphp
<section class="ctr-pathway mt-3" aria-labelledby="ctr-pathway-heading" data-player-pathway>
  <style>
    .ctr-pathway{border:1px solid #d9e8e4;border-radius:14px;padding:1rem;background:#f6faf9;overflow-wrap:anywhere}
    .ctr-pathway .pathway-grid{display:grid;grid-template-columns:minmax(12rem,1fr) minmax(0,1.4fr) minmax(0,1.3fr);gap:1.25rem;margin-top:1rem}
    .ctr-pathway .pathway-score{display:flex;align-items:center;gap:.8rem;flex-wrap:wrap}
    .ctr-pathway .pathway-list{padding-left:1.2rem;margin:0}.ctr-pathway .pathway-list li{margin-bottom:.65rem}
    .ctr-pathway .pathway-met{font-weight:700;color:#17684b}
    .ctr-pathway .pathway-stages{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.6rem;margin-top:1rem}
    .ctr-pathway .pathway-stage{padding:.75rem;border:1px solid #d9e8e4;border-top:3px solid var(--stage-ink);border-radius:8px;background:white}
    .ctr-pathway .pathway-admin{border-top:1px solid #d9e8e4;padding-top:.75rem;margin-top:1rem}
    .ctr-pathway summary{cursor:pointer;min-height:44px;align-content:center}
    .ctr-pathway .pathway-admin form{display:flex;flex-wrap:wrap;gap:.75rem;align-items:end;margin-top:.75rem}
    .ctr-pathway .pathway-admin label{font-size:.8rem}.ctr-pathway .pathway-admin select,.ctr-pathway button,.ctr-pathway .pathway-browse{min-height:44px}
    .ctr-pathway [data-pathway-status]{white-space:pre-line}
    @media(max-width:991.98px){.ctr-pathway .pathway-grid{grid-template-columns:1fr}.ctr-pathway .pathway-admin form>div{width:100%}}
    @media(max-width:575.98px){.ctr-pathway .pathway-stages{grid-template-columns:1fr}}
  </style>
  <div class="ctr-heading"><h3 class="h5 mb-0" id="ctr-pathway-heading">Your tennis pathway</h3><span class="small text-muted">Current assessment · independent of the selected history month</span></div>
  @unless($pathway['available'])<p class="small text-muted mt-2 mb-0" role="status">Pathway stage recording is awaiting setup.</p>@endunless
  <div class="pathway-grid">
    <div>
      <h4 class="h6">Current rated level: {{ $pathway['current_stage_label'] }}</h4>
      <div class="pathway-score" data-pathway-top-badge><x-ctr-badge :stage="$pathway['badge']['stage']" :score="$pathway['badge']['score']" :local="$pathway['badge']['local']" /></div>
      <p class="small text-muted mt-2 mb-0">@if($pathway['metrics']['date'] ?? null) Latest saved ball-level rating · {{ $pathway['metrics']['date'] }} @else No saved ball-level rating available. @endif The highest rated ball level is shown: Green/Yellow, then Orange, then Red. Ratings at different ball levels are independent; this does not complete a stage.</p>
      <p class="small mt-2 mb-0">Recorded starting stage: <strong>{{ $pathway['stage'] ? $pathway['stage_label'] : 'Not recorded' }}</strong></p>
      @if($pathway['current_stage'] === 'standard')<p class="small mt-2 mb-0">Current Wilson division: <strong>{{ $pathway['division'] ?? 'Not recorded' }}</strong></p>@endif
      @if($pathway['metrics']['stale'] ?? false)<p class="small text-muted mt-2 mb-0">Saved rating awaiting an update; progress is not assessed.</p>@endif
    </div>
    <div>
      <h4 class="h6">Current progress assessment</h4>
      <ul class="pathway-list">
        <li>{{ $pathway['current_stage_label'] }} CTR: <strong>{{ $pathway['score'] !== null ? number_format($pathway['score'], 1) : 'Unavailable' }}</strong>@if($pathway['target']) / {{ number_format($pathway['target']['ctr'], 0) }} target @endif<br><span class="small {{ $pathway['ratingMet'] ? 'pathway-met' : 'text-muted' }}">{{ !$pathway['assessed'] ? 'Not assessed — see the requirements below' : ($pathway['ratingMet'] ? 'CTR target met' : 'CTR target not yet met') }}</span></li>
        <li>Confirmed {{ $pathway['current_stage_label'] }} events: <strong>{{ $pathway['ownEvents'] ?? 'Unavailable' }}</strong> / {{ $pathway['target']['events'] ?? 3 }} minimum<br><span class="small {{ $pathway['eventsMet'] ? 'pathway-met' : 'text-muted' }}">{{ $pathway['eventsMet'] ? 'Event minimum met; this alone does not complete a stage' : 'Only played events in this ball level count towards the minimum' }}</span></li>
      </ul>
      <p class="small text-muted mb-2">{{ $pathway['events'] !== null ? $pathway['events'].' distinct published Cape Tennis events' : 'Event count unavailable' }} included in the latest rating’s {{ \App\Services\Performance\AllAgeRatingModel::WINDOW_DAYS }}-day window. Other ball stages do not complete this stage. Events not marked Red or Orange count as Green/Yellow.</p>
      <p class="small mb-0 {{ $pathway['ready'] ? 'pathway-met' : 'text-muted' }}" role="status"><strong>{{ $pathway['assessment_status'] }}</strong> · {{ $pathway['reason'] }}</p>
      @if(!$pathway['stage'] || ($pathway['current_stage'] === 'standard' && !$pathway['division']))<p class="small text-muted mt-2 mb-0">@if(auth()->user()?->hasRole('super-user'))Use Admin tools below to record the starting stage and, where applicable, Wilson division.@else Contact Cape Tennis to record or correct the starting stage or Wilson division.@endif</p>@endif
    </div>
    <div>
      <h4 class="h6">Competition pathway guidance</h4>
      <p class="mb-2"><strong>{{ $pathway['competition'] }}</strong></p>
      <p class="small mb-1"><strong>{{ $pathway['ready'] ? 'Next pathway options to consider:' : 'Possible pathway options; assessment pending or targets not yet met:' }}</strong></p>
      <ul class="small ps-3 mb-2">@foreach($pathway['options'] as $option)<li>{{ $option }}</li>@endforeach</ul>
      <p class="small text-muted mb-2">General guidance based on the current rated ball level, not a tournament eligibility check. Each event has its own age, entry and nomination rules. Trials remain subject to those rules. These are internal Cape Tennis benchmarks, not official TSA qualification. Targets do not grant entry or selection.</p>
      <a class="btn btn-sm btn-outline-primary pathway-browse" href="{{ route('home') }}">Browse published events</a>
    </div>
  </div>
  @if($pathway['stageProgress'])
    <div class="pathway-stages" aria-label="Independent ball-stage progress">
      @foreach($pathway['stageProgress'] as $ball => $progress)
        <div class="pathway-stage" style="--stage-ink:{{ ['red' => '#ed2029', 'orange' => '#ed7100', 'standard' => '#095c60'][$ball] }}" data-pathway-ball="{{ $ball }}">
          <strong>{{ $progress['label'] }}</strong>
          <div class="my-2"><x-ctr-badge size="small" :stage="$ball" :score="$progress['score'] ?? null" :local="$progress['local'] ?? false" /></div>
          @if($progress['reason'] ?? null)<p class="small text-muted">{{ $progress['reason'] }}</p>@endif
          <span class="small {{ $progress['met'] ? 'pathway-met' : 'text-muted' }}">{{ $progress['status'] }}</span>
          <p class="small text-muted mt-1 mb-0">{{ $progress['events'] ?? 'Unknown' }} confirmed events @if($progress['target']) · minimum {{ $progress['target']['events'] }} · CTR {{ number_format($progress['target']['ctr'], 0) }} @else · {{ $ball === 'standard' && !$pathway['division'] ? 'A/B division not recorded' : 'target not configured' }} @endif</p>
        </div>
      @endforeach
    </div>
  @endif
  <p class="small text-muted mt-2 mb-0">Each ball level has an independent opponent-adjusted rating. Red results affect Red only; Orange affects Orange only; all other results affect Green/Yellow. Progress also requires at least three published Cape Tennis events in that level. Targets describe the current {{ \App\Services\Performance\AllAgeRatingModel::WINDOW_DAYS }}-day evidence window, not a permanent completion award. A higher rated level or recorded starting stage does not complete earlier stages.</p>
  @if(auth()->user()?->hasRole('super-user') && $pathway['available'])
    <details class="pathway-admin" data-pathway-admin><summary>Admin tools · starting stage and current division</summary>
      <p class="small text-muted mb-0">The starting stage and current Green/Yellow Wilson division are separate participation metadata. Recording a division does not change the starting stage or rated level. Progress is calculated automatically from CTR and played-event evidence; it cannot be manually awarded here.</p>
      <form method="post" action="{{ route('my.tennis.pathway.stage', $ratingPlayer) }}" data-pathway-form>
        @csrf
        <div><label for="pathway-stage">Recorded starting stage</label><select id="pathway-stage" name="stage" class="form-select" data-pathway-stage><option value="">Not recorded</option>@foreach(\App\Services\Performance\PlayerPathwayService::STAGES as $value => $label)<option value="{{ $value }}" @selected($pathway['stage'] === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="pathway-division">Current Wilson division · Green/Yellow ball</label><select id="pathway-division" name="wilson_division" class="form-select" data-pathway-division><option value="">Not recorded</option><option value="B" @selected($pathway['saved_division'] === 'B')>B</option><option value="A" @selected($pathway['saved_division'] === 'A')>A</option></select></div>
        <button class="btn btn-outline-primary" type="submit">Save pathway metadata</button>
      </form>
      <p class="small mt-2 mb-0" role="status" aria-live="polite" data-pathway-status></p>
    </details>
  @endif
</section>
