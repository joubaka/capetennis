<section id="player-development-pathway" class="ctr-pathway mt-3" aria-labelledby="ctr-pathway-heading" data-player-pathway>
  <style>
    .ctr-pathway{border:1px solid #d9e8e4;border-radius:14px;padding:1rem;background:#f6faf9;overflow-wrap:anywhere;scroll-margin-top:6rem}
    .ctr-pathway .pathway-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:1.25rem;margin-top:1rem}
    .ctr-pathway .pathway-action{grid-column:1/-1;border-top:1px solid #d9e8e4;padding-top:1rem}
    .ctr-pathway .pathway-map{list-style:none;padding:0;display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:1.1rem;margin:1.25rem 0}
    .ctr-pathway .pathway-map > li{position:relative;border:1px solid #d9e8e4;border-top:4px solid var(--stage-ink);border-radius:14px;padding:1rem .8rem;background:linear-gradient(150deg,#fff,#f0f7f5);min-width:0;box-shadow:0 5px 14px #183c3210}
    .ctr-pathway .pathway-step-number{display:flex;align-items:center;justify-content:center;width:2rem;height:2rem;border-radius:50%;background:var(--stage-ink);color:#fff;font-weight:700;margin-bottom:.75rem}
    .ctr-pathway .pathway-map [aria-current="step"]{background:#e8f5ef;box-shadow:0 6px 20px #17684b20}
    .ctr-pathway .pathway-map > li + li::before{content:'→';position:absolute;left:-1rem;top:1rem;color:#4d675f}
    .ctr-pathway .pathway-map [aria-current="step"]{outline:2px solid #233d50;outline-offset:2px}
    .ctr-pathway .pathway-map small{display:block;margin-top:.35rem}
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
    @media(max-width:991.98px){.ctr-pathway .pathway-grid{grid-template-columns:1fr}.ctr-pathway .pathway-admin form>div{width:100%}.ctr-pathway .pathway-map{grid-template-columns:1fr}.ctr-pathway .pathway-map > li + li::before{content:'↓';left:50%;top:-1.25rem}}
    @media(max-width:575.98px){.ctr-pathway .pathway-stages,.ctr-pathway .pathway-map{grid-template-columns:1fr}.ctr-pathway .pathway-map > li + li::before{content:'↓';left:50%;top:-1.25rem}}
  </style>
  <div class="ctr-heading"><h3 class="h5 mb-0" id="ctr-pathway-heading">Your tennis pathway</h3><span class="small text-muted">Current assessment · independent of the selected history month</span></div>
    <div>
      <h4 class="h6">Current rated level: {{ $pathway['current_stage_label'] }}</h4>
      <div class="pathway-score" data-pathway-top-badge><x-ctr-badge :stage="$pathway['badge']['stage']" :label="$pathway['badge']['stage'] === 'standard' ? 'Wilson B / Wilson A' : null" :score="$pathway['badge']['score']" :local="$pathway['badge']['local']" /></div>
      <p class="small text-muted mt-2 mb-0">@if($pathway['metrics']['date'] ?? null) Latest saved ball-level rating · {{ $pathway['metrics']['date'] }} @else No saved ball-level rating available. @endif</p>
      <p class="small mt-2 mb-0">Your latest saved badge is used automatically as your current development level. No starting-stage confirmation is needed.</p>
      @if($pathway['metrics']['stale'] ?? false)<p class="small text-muted mt-2 mb-0">Saved rating awaiting an update; progress is not assessed.</p>@endif
    </div>
  <ol class="pathway-map" aria-label="Development pathway snapshot" data-pathway-snapshot>
    @foreach(['red' => ['Red ball', '#ed2029', 0], 'orange' => ['Orange ball', '#ed7100', 1]] as $ball => [$label, $ink, $benchmarkIndex])
      <li style="--stage-ink:{{ $ink }}" @if($pathway['current_stage'] === $ball) aria-current="step" @endif>
        <span class="pathway-step-number" aria-hidden="true">{{ $loop->iteration }}</span>
        <strong>{{ $label }}</strong>
        @if($pathway['current_stage'] === $ball)<small class="fw-semibold">You are here · latest badge</small>@endif
        <ul class="pathway-events small ps-3 mt-2 mb-2">
        @foreach(\App\Services\Performance\PlayerPathwayService::EVENT_OPTIONS[$ball] ?? [] as $eventOption)<li>@if(str_contains($eventOption, 'TSA'))<a href="https://tennissa.co.za/" target="_blank" rel="noopener noreferrer">{{ $eventOption }} <span class="visually-hidden">(opens in a new tab)</span></a>@else{{ $eventOption }}@endif</li>@endforeach
        </ul>
          <small>@if($pathway['benchmarks'][$benchmarkIndex]['target']) CTR {{ number_format($pathway['benchmarks'][$benchmarkIndex]['target']['ctr'], 0) }} + {{ $pathway['benchmarks'][$benchmarkIndex]['target']['events'] }} {{ $label }} events @else benchmark awaiting configuration @endif</small>
      </li>
    @endforeach
    @foreach(['Wilson B' => ['#12765a', 3], 'Wilson A' => ['#145c8d', 4], 'TSA tournaments' => ['#6654a3', 5]] as $competition => [$ink, $step])
      <li style="--stage-ink:{{ $ink }}" data-pathway-competition="{{ $competition }}" @if($pathway['suggestedCompetition'] === $competition) aria-current="step" @endif>
        <span class="pathway-step-number" aria-hidden="true">{{ $step }}</span>
        <strong>{{ $competition === 'TSA tournaments' ? 'TSA' : $competition }}</strong>
        @if($pathway['suggestedCompetition'] === $competition)<small class="fw-semibold">You are here · suggested competition</small>@endif
        @if($competition === 'Wilson B')
          <small>After Orange</small><small>@if($pathway['wilsonCutoff'] !== null) CTR {{ number_format($pathway['wilsonCutoff'], 0) }} or below @else Recommendation awaiting configuration @endif</small>
        @elseif($competition === 'Wilson A')
          <small>@if($pathway['wilsonCutoff'] !== null) CTR above {{ number_format($pathway['wilsonCutoff'], 0) }}@if($pathway['tsaCutoff'] !== null) through {{ number_format($pathway['tsaCutoff'], 0) }}@endif @else Recommendation awaiting configuration @endif</small>
        @else
          <small>@if($pathway['tsaCutoff'] !== null) CTR above {{ number_format($pathway['tsaCutoff'], 0) }} @else Recommendation awaiting configuration @endif</small>
          <a class="small d-inline-flex align-items-center" style="min-height:44px" href="https://tennissa.co.za/" target="_blank" rel="noopener noreferrer">TSA events <span class="visually-hidden">(opens in a new tab)</span></a>
        @endif
        <small>Review with your coach; event entry rules apply.</small>
      </li>
    @endforeach
  </ol>
  <p class="small text-muted mb-0">A guide to development, not automatic promotion. Red and Orange progression use their own rating and played-event targets; Wilson divisions are suggestions from current comparable CTR.</p>
  <section class="mt-3" aria-labelledby="pathway-event-targets-heading" data-pathway-event-targets>
    <h4 class="h6" id="pathway-event-targets-heading">Suggested events to play</h4>
    @if($pathway['current_stage'])
      <p class="small mb-2">Options at your current rated level: <strong>{{ $pathway['current_stage_label'] }}</strong></p>
      <ul class="small ps-3 mb-2" data-pathway-current-events>
        @foreach(\App\Services\Performance\PlayerPathwayService::EVENT_OPTIONS[$pathway['current_stage']] as $eventOption)<li>{{ $eventOption }}</li>@endforeach
      </ul>
      <h5 class="h6 mt-3">{{ $pathway['current_stage'] === 'standard' ? 'Which Wilson division to play' : 'What to achieve before moving on' }}</h5>
      <ul class="small ps-3 mb-2" data-pathway-advancement-targets>
        @if($pathway['current_stage'] === 'standard')
          <li>@if($pathway['wilsonCutoff'] !== null) Above CTR {{ number_format($pathway['wilsonCutoff'], 0) }}@if($pathway['tsaCutoff'] !== null) through {{ number_format($pathway['tsaCutoff'], 0) }}@endif: Wilson A suggested. CTR {{ number_format($pathway['wilsonCutoff'], 0) }} or below: Wilson B suggested, after Orange. @else Wilson recommendation awaiting configuration. @endif</li>
          @if($pathway['tsaCutoff'] !== null)<li>Above CTR {{ number_format($pathway['tsaCutoff'], 0) }}: TSA tournaments suggested. Exactly {{ number_format($pathway['tsaCutoff'], 0) }} remains Wilson A. Check each tournament’s entry rules.</li>@endif
          <li>{{ $pathway['assessment_status'] }} · {{ $pathway['reason'] }}</li>
        @else
          <li>@if($pathway['target']) {{ $pathway['ctr_label'] }} {{ number_format($pathway['target']['ctr'], 0) }} and {{ $pathway['target']['events'] }} published {{ $pathway['current_stage_label'] }} events @else Target not configured @endif · {{ $pathway['assessment_status'] }}</li>
        @endif
      </ul>
      <p class="small text-muted mb-2">@if($pathway['current_stage'] === 'standard') Wilson suggestions use current comparable Wilson pathway evidence. The event count is supporting history, not a division-entry requirement. @else Both the comparable CTR target and the played-event minimum must be met at the same ball level using current saved evidence. @endif Review with your coach; promotion is not automatic.</p>
      <p class="small mb-1"><strong>Next pathway target to review</strong></p>
      <ul class="small ps-3 mb-2">@foreach($pathway['options'] as $option)<li>{{ $option }}</li>@endforeach</ul>
      @if(!$pathway['ready'] && $pathway['current_stage'] !== 'standard')<p class="small text-muted mb-2">{{ $pathway['assessment_status'] }}. Review the progress requirements below before considering the next level with your coach.</p>@endif
    @else
      <p class="small mb-2">No rated ball level is available yet. Ask your coach which ball level suits you, then use the event options below.</p>
    @endif
    <p class="small text-muted mb-0">These are event types to discuss with your coach. Check each event’s age, entry and nomination rules; a pathway target does not grant entry or selection. TSA tournament entry follows its own rules.</p>
  </section>
  <div class="pathway-grid">
    <div>
      <h4 class="h6">Current progress assessment</h4>
      @if($pathway['benchmark_division'])<p class="small">Next benchmark to review: <strong>Wilson {{ $pathway['benchmark_division'] }}</strong>. This is development guidance, not an assigned competition division.</p>@endif
      @if($pathway['current_stage'] === 'standard')
        <p>{{ $pathway['assessment_status'] }} · {{ $pathway['reason'] }}</p>
        <p class="small">Wilson pathway CTR: {{ $pathway['score'] !== null ? number_format($pathway['score'], 1) : 'Unavailable' }} · {{ $pathway['ownEvents'] ?? 'Unknown' }} published events in the supporting evidence.</p>
      @else
      <ul class="pathway-list">
        <li>{{ $pathway['ctr_label'] }}: <strong>{{ $pathway['score'] !== null ? number_format($pathway['score'], 1) : 'Unavailable' }}</strong>@if($pathway['target']) / {{ number_format($pathway['target']['ctr'], 0) }} target @endif<br><span class="small {{ $pathway['ratingMet'] ? 'pathway-met' : 'text-muted' }}">{{ !$pathway['assessed'] ? 'Not assessed — see the requirements below' : ($pathway['ratingMet'] ? 'CTR target met' : 'CTR target not yet met') }}</span></li>
        <li>Confirmed {{ $pathway['current_stage_label'] }} events: <strong>{{ $pathway['ownEvents'] ?? 'Unavailable' }}</strong> / {{ $pathway['target']['events'] ?? 3 }} minimum<br><span class="small {{ $pathway['eventsMet'] ? 'pathway-met' : 'text-muted' }}">{{ $pathway['eventsMet'] ? 'Event minimum met; this alone does not complete a stage' : 'Only played events in this ball level count towards the minimum' }}</span></li>
      </ul>
      <p class="small mb-0 {{ $pathway['ready'] ? 'pathway-met' : 'text-muted' }}" role="status"><strong>{{ $pathway['assessment_status'] }}</strong> · {{ $pathway['reason'] }}</p>
      @endif
      @if($pathway['current_stage'] === 'standard')
        <ul class="small ps-3 mt-2 mb-0" data-pathway-wilson-benchmarks>
          @foreach($pathway['wilsonProgress'] as $benchmarkDivision => $progress)
            <li>Wilson {{ $benchmarkDivision }} benchmark: <strong>{{ $progress['status'] }}</strong>@if($progress['target']) · CTR {{ number_format($progress['target']['ctr'], 0) }} and {{ $progress['target']['events'] }} Wilson pathway events @endif</li>
          @endforeach
        </ul>
      @endif
    </div>
    <div class="pathway-action">
      <h4 class="h6">What to do next</h4>
      <ul class="small ps-3 mb-3" data-pathway-next-steps>@foreach($pathway['next_steps'] as $step)<li class="mb-2">{{ $step }}</li>@endforeach</ul>
      <a class="btn btn-sm btn-outline-primary pathway-browse" href="{{ route('home') }}">Browse published events</a>
      <a class="btn btn-sm btn-outline-primary pathway-browse" href="https://tennissa.co.za/" target="_blank" rel="noopener noreferrer">SA Tennis · TSA events <span class="visually-hidden">(opens in a new tab)</span></a>
    </div>
  </div>
  <details class="mt-3" data-pathway-explanation>
    <summary>How the pathway works</summary>
  @if($pathway['stageProgress'])
    <div class="pathway-stages" aria-label="Independent ball-stage progress">
      @foreach($pathway['stageProgress'] as $ball => $progress)
        <div class="pathway-stage" style="--stage-ink:{{ ['red' => '#ed2029', 'orange' => '#ed7100', 'standard' => '#095c60'][$ball] }}" data-pathway-ball="{{ $ball }}">
          <strong>{{ $progress['label'] }}</strong>
          <div class="my-2"><x-ctr-badge size="small" :stage="$ball" :label="$ball === 'standard' ? 'Wilson B / Wilson A' : null" :score="$progress['score'] ?? null" :local="$progress['local'] ?? false" /></div>
          @if($progress['reason'] ?? null)<p class="small text-muted">{{ $progress['reason'] }}</p>@endif
          @if($ball === 'standard')
          <p class="small mb-0">{{ $pathway['suggestedCompetition'] ? $pathway['suggestedCompetition'].' suggested' : 'Current comparable Wilson evidence pending' }} · {{ $progress['events'] ?? 'Unknown' }} published events in the supporting evidence.</p>
          @else
          <span class="small {{ $progress['met'] ? 'pathway-met' : 'text-muted' }}">{{ $progress['status'] }}</span>
          <p class="small text-muted mt-1 mb-0">{{ $progress['events'] ?? 'Unknown' }} confirmed {{ $progress['label'] }} events @if($progress['target']) · minimum {{ $progress['target']['events'] }} · {{ $progress['ctr_label'] }} {{ number_format($progress['target']['ctr'], 0) }} @if($ball === 'standard') (Wilson {{ $pathway['benchmark_division'] ?? 'B' }} benchmark) @endif @else · target not configured @endif</p>
          @endif
        </div>
      @endforeach
    </div>
  @endif
  <p class="small text-muted mt-2 mb-0">Each ball level has an independent opponent-adjusted rating. Red results affect Red only; Orange affects Orange only; all other eligible results contribute to the shared Wilson pathway evidence. Red and Orange progression also require the configured minimum of published Cape Tennis events at their own level. Targets describe the current {{ \App\Services\Performance\AllAgeRatingModel::WINDOW_DAYS }}-day evidence window, not a permanent completion award. A higher rated level or recorded starting stage does not complete earlier stages.</p>
  <p class="small text-muted mt-2">{{ $pathway['events'] !== null ? $pathway['events'].' distinct published Cape Tennis events' : 'Event count unavailable' }} included in the latest rating’s {{ \App\Services\Performance\AllAgeRatingModel::WINDOW_DAYS }}-day window. The highest rated level is shown: Wilson B / Wilson A, then Orange, then Red. Eligible events not marked Red or Orange contribute to the shared Wilson pathway evidence. Wilson B and Wilson A use the same underlying rating and event count, with division suggestions based on CTR.</p>
    <ol class="small ps-3 mt-2 mb-2">
      <li>Play eligible singles at your ball level in published Cape Tennis events. Multiple matches in one event count as one event at that level.</li>
      <li>Wait for the saved rating update. Each ball level has its own CTR; another level’s rating or event count cannot satisfy its benchmarks.</li>
      <li>The programme uses your latest saved badge as your current development level and automatically compares its current comparable CTR and played-event count with Red or Orange benchmarks. @if($pathway['wilsonCutoff'] !== null) After Orange, current comparable Wilson pathway CTR above {{ number_format($pathway['wilsonCutoff'], 0) }}@if($pathway['tsaCutoff'] !== null) through {{ number_format($pathway['tsaCutoff'], 0) }}@endif suggests Wilson A; {{ number_format($pathway['wilsonCutoff'], 0) }} or below suggests Wilson B. @endif @if($pathway['tsaCutoff'] !== null) Above {{ number_format($pathway['tsaCutoff'], 0) }} suggests TSA tournaments. @endif</li>
      <li>When Red or Orange benchmarks are met, review the next playing level with your coach and check the event’s own age, entry and nomination rules. Promotion is not automatic.</li>
    </ol>
    <ul class="small ps-3 mb-2">
      @foreach($pathway['benchmarks'] as $benchmark)
        <li>{{ $benchmark['label'] }}: @if($benchmark['target']) {{ number_format($benchmark['target']['ctr'], 0) }} and {{ $benchmark['target']['events'] }} published {{ $benchmark['ball'] }} events @else benchmark awaiting configuration @endif</li>
      @endforeach
    </ul>
    <p class="small text-muted mb-0">These are Cape Tennis review benchmarks, not tournament entry restrictions. A Wilson division suggestion does not determine whether you may enter a TSA tournament. Your latest saved badge determines the current development level without a manual starting stage or division. Wilson pathway results do not complete Red or Orange.</p>
  </details>
  @if(!($hidePathwayAdmin ?? false) && auth()->user()?->hasRole('super-user') && $pathway['available'])
    <details class="pathway-admin" data-pathway-admin><summary>Admin tools · optional participation records</summary>
      <p class="small text-muted mb-0">These optional historical records do not control the automatic assessment. Progress uses the latest saved badge, CTR and played-event evidence; it cannot be manually awarded here.</p>
      <form method="post" action="{{ route('my.tennis.pathway.stage', $ratingPlayer) }}" data-pathway-form>
        @csrf
        <div><label for="pathway-stage">Recorded starting stage</label><select id="pathway-stage" name="stage" class="form-select" data-pathway-stage><option value="">Not recorded</option>@foreach(\App\Services\Performance\PlayerPathwayService::STAGES as $value => $label)<option value="{{ $value }}" @selected($pathway['stage'] === $value)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="pathway-division">Current Wilson division · Wilson B / Wilson A</label><select id="pathway-division" name="wilson_division" class="form-select" data-pathway-division><option value="">Not recorded</option><option value="B" @selected($pathway['saved_division'] === 'B')>B</option><option value="A" @selected($pathway['saved_division'] === 'A')>A</option></select></div>
        <button class="btn btn-outline-primary" type="submit">Save pathway metadata</button>
      </form>
      <p class="small mt-2 mb-0" role="status" aria-live="polite" data-pathway-status></p>
    </details>
  @endif
</section>
