<div class="container-xxl py-3" style="max-width:1000px" data-draw-guide data-draw-guide-url="{{ $guideUrl ?? route('draw.guide.show', [$event, $draw]) }}">
  <a href="{{ route('headOffice.show', $event) }}" class="btn btn-link px-0 mb-2">← Back to event</a>
  <h1 class="h3">{{ $draw->drawName }}</h1>
  @if(!empty($scheduleDate))<a class="btn btn-outline-primary mb-3" style="min-height:44px" href="{{ route('backend.event-venue-schedule.calendar', ['event'=>$event->id,'draw_id'=>$draw->id,'date'=>$scheduleDate]) }}">Resume saved day - {{ \Carbon\Carbon::parse($scheduleDate)->format('D j M Y') }}</a>@endif
  @if(($draw->team_draw_selection['competition']['phase'] ?? null) === 'playoff')
    <p class="text-muted">{{ $draw->formatDisplayLabel() }}</p>
  @endif
  <p class="text-muted">{{ $guide['team'] ? 'Set up your team draw.' : 'Choose the category and format, then place your players.' }} Save each step before leaving; you can continue here later.</p>
  @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
  @if($draw->locked)<div class="alert alert-warning">This draw is locked. Review is available; use the existing workspace controls before changing protected setup.</div>@endif
  @foreach($guide['warnings'] as $warning)<div class="alert alert-warning">{{ $warning }}</div>@endforeach
  @if($guide['competition'])
    @php
      $playoffReadiness = app(\App\Services\TeamPlayoffReadinessService::class)->forDraw($draw);
    @endphp
    @include('backend.team-draw.partials.playoff-readiness')
    <section class="card card-body mb-3">
      <h2 class="h5">Competition phases</h2>
      @if($guide['sourceDraw'])
        <p>{{ $draw->isPlacementDraft() ? 'Proposed playoff programme: schedule the position placeholders now. Confirm qualifiers from the round robin to fill these same fixtures without losing times or courts. Scoring and publication remain unavailable until qualification.' : 'The round-robin results determined these playoff pairings.' }}</p>
        <a class="btn btn-outline-primary align-self-start" href="{{ route('draw.guide.show', [$event, $guide['sourceDraw']]) }}">Review round-robin phase</a>
      @elseif($guide['linkedPlayoff'])
        <p>Round-robin and playoff setup have separate schedules and publication reviews.</p>
        <a class="btn btn-primary align-self-start" href="{{ route('draw.guide.show', [$event, $guide['linkedPlayoff']]) }}">Continue playoff programme &amp; schedule</a>
      @else
        <p>Current phase: round robin. Prepare the draft placement fixtures now to plan the programme through Saturday. Confirmed qualifiers fill those same fixtures and retain their times and courts.</p>
        @can('team-draw.createFormat', $event)
          @if($playoffReadiness['draft_playoff'] ?? null)
            <a class="btn btn-primary align-self-start mb-2" href="{{ route('draw.guide.show', [$event, $playoffReadiness['draft_playoff']]) }}">Open draft playoff programme</a>
          @else
            <form method="POST" action="{{ route('team-draw.competition.draft-playoffs', $draw) }}" class="mb-3">@csrf<button class="btn btn-primary" @disabled(!($playoffReadiness['can_prepare'] ?? false))>Prepare draft playoff fixtures</button></form>
          @endif
          <a class="btn btn-outline-primary align-self-start mb-2" href="{{ route('backend.team-draw.standings', $draw) }}">Review qualifiers</a>
          <form method="POST" action="{{ route('team-draw.competition.playoffs', $draw) }}">@csrf<button class="btn btn-outline-primary" @disabled(!($playoffReadiness['can_create'] ?? false))>{{ ($playoffReadiness['draft_playoff'] ?? null) ? 'Confirm qualifiers & fill draft fixtures' : 'Confirm qualifiers & create playoffs' }}</button></form>
        @endcan
      @endif
      @if($draw->team_scoring_rules['undecided'] ?? false)
        <a class="btn btn-outline-primary align-self-start mt-3" href="{{ route('team-draw.competition.scoring', $draw) }}">Confirm scoring before publication</a>
      @endif
    </section>
  @endif
  @php
    $visibleSteps = $guide['team'] ? $guide['steps'] : [
      1 => array_merge($guide['steps'][1], ['label' => 'Category']),
      2 => array_merge($guide['steps'][2], ['label' => 'Format']),
      3 => array_merge($guide['steps'][3], ['label' => 'Players and draw']),
    ];
    $phaseOneReady = !$guide['team'] && $guide['steps'][3]['complete'];
    $trialsReady = $phaseOneReady && $event->isInterprovincialTrials();
    $publicationUrl = !$guide['team'] ? $guide['workspace'].($draw->usesFlexibleMonrad() ? '#matrix' : '?review=publication#matrix') : $guide['steps'][5]['url'];
    $current = $guide['steps'][$guide['next']];
  @endphp
  <ol class="list-unstyled d-flex flex-wrap gap-2 mb-4" aria-label="Draw setup progress">
    @foreach($visibleSteps as $index => $step)
      <li class="border rounded px-3 py-2 {{ $index === $guide['next'] ? 'border-primary' : '' }}" @if($index === $guide['next']) aria-current="step" @endif>
        <span class="small">{{ $guide['team'] ? $index + 1 : $index }}. {{ $step['label'] }}</span>
        <span class="d-block small {{ $step['complete'] ? 'text-success' : 'text-muted' }}">{{ $step['complete'] ? 'Complete' : ($index === $guide['next'] ? 'Continue here' : 'Still to do') }}</span>
      </li>
    @endforeach
  </ol>
  @if($phaseOneReady)
    <section class="card card-body mb-3">
      <h2 class="h4">Draw and players ready</h2>
      <p>Your generated draw is saved. Open it to review the players and matches.</p>
      @if($trialsReady)
        <h3 class="h5" id="trials-schedule-next">Next: programme and schedule</h3>
        <p>{{ $guide['steps'][4]['detail'] }}</p>
        <a class="btn btn-primary align-self-start" href="{{ $guide['scheduleComplete'] ? route('backend.event-venue-schedule.calendar', ['event'=>$event->id, 'draw_id'=>$draw->id, 'date'=>$scheduleDate ?? 'all']) : $guide['steps'][4]['url'] }}">{{ $guide['scheduleComplete'] ? 'Review saved schedule' : 'Set venues and schedule' }}</a>
        <a class="btn btn-outline-secondary align-self-start mt-2" href="{{ $guide['workspace'] }}">Open draw</a>
        <p class="small text-muted mt-3 mb-2">Saving keeps the programme private. Review and publish the draw and its match times separately when ready.</p>
        <a class="align-self-start" href="{{ $publicationUrl }}">Review publication controls</a>
      @else
        <a class="btn btn-primary align-self-start" href="{{ $guide['workspace'] }}">Open draw</a>
      @endif
      <a class="align-self-start mt-3" href="{{ route('headOffice.show', $event) }}">Back to categories</a>
    </section>
  @else
  <section class="card card-body mb-3">
    <p class="small text-primary mb-1">Step {{ $guide['team'] ? $guide['next'] + 1 : $guide['next'] }} of {{ $guide['team'] ? 6 : 3 }}</p>
    <h2 class="h4">{{ $guide['team'] ? $current['label'] : $visibleSteps[$guide['next']]['label'] }}</h2>
    <p>{{ !$guide['team'] && $guide['next'] === 3 ? 'Place your players, save the assignments and generate the matches.' : $current['detail'] }}</p>
    @if($guide['next'] === 1 && !$guide['team'] && !$draw->category_event_id && !$draw->locked && !$draw->published && !$draw->oop_published)
      @if($categories->isEmpty())
        <p>No player categories are configured for this event yet.</p>
        @can('event-category.manage', $event)
          <a class="btn btn-primary align-self-start" href="{{ route('admin.events.categories', $event) }}">Open event categories &amp; entries</a>
        @else
          <p class="mb-0">Ask an event administrator to add the player categories, then return to this saved draw.</p>
        @endcan
      @else
      <form data-draw-guide-form method="POST" action="{{ route('draw.guide.category', [$event, $draw]) }}">@csrf
        <label for="guide-category" class="form-label">Player category</label>
        <select id="guide-category" name="category_event_id" class="form-select mb-3" required><option value="">Choose a category</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->category?->name }}</option>@endforeach</select>
        <button class="btn btn-primary">Save category &amp; continue</button>
      </form>
      @endif
    @elseif($guide['next'] === 3 && $guide['team'] && !$guide['generated'] && !$draw->locked && !$draw->published)
      <p>{{ $guide['participants'] }} teams are selected. Review the teams, format and pair choices before creating the ties.</p>
      <a href="{{ route('draws.manage', $draw) }}" class="btn btn-outline-primary align-self-start mb-3">Review teams &amp; format</a>
      <form data-draw-guide-form method="POST" action="{{ route('draw.guide.generate-team', [$event, $draw]) }}">@csrf
        <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="confirm_generation" value="1" required><span class="form-check-label">I have reviewed the selected teams and want to generate their ties.</span></label>
        <button class="btn btn-primary">Generate team ties &amp; continue</button>
      </form>
    @else
      <a href="{{ $current['url'] }}" class="btn btn-primary align-self-start">{{ $guide['next'] === 5 ? 'Review publication controls' : 'Continue to '.$current['label'] }}</a>
    @endif
    <p class="small text-muted mt-3 mb-0">Saving setup does not publish changes. Review the current draw and schedule publication separately.</p>
  </section>
  @endif
  <details class="card card-body"><summary>Review saved setup{{ $guide['team'] ? ' or open another step' : '' }}</summary><div class="d-grid gap-2 mt-3">@foreach($visibleSteps as $index => $step)<a class="btn btn-outline-secondary text-start" href="{{ $step['url'] }}">{{ $guide['team'] ? $index + 1 : $index }}. {{ $step['label'] }}</a>@endforeach</div></details>
  @if(!$guide['team'] && !$trialsReady)
    <details class="card card-body mt-3"><summary>Later: scheduling and publication</summary>
      <p class="small text-muted mt-2">Prepare match times and publish the draw separately when you are ready.</p>
      <a class="align-self-start" href="{{ $guide['steps'][4]['url'] }}">Open scheduling</a>
      <a class="align-self-start mt-2" href="{{ $publicationUrl }}">Review publication controls</a>
    </details>
  @endif
</div>
