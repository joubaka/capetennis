@php($pendingMove = isset($pathway['move']) && $pathway['move']['score'] === null ? $pathway['move'] : null)
<section id="player-development-pathway" class="ctr-pathway mt-3" aria-labelledby="ctr-pathway-heading" data-player-pathway>
  <link rel="stylesheet" href="{{ asset('assets/css/player-pathway.css') }}?v={{ filemtime(public_path('assets/css/player-pathway.css')) }}">
  <header class="pathway-hero"><span class="pathway-eyebrow">Cape Tennis · Your game, your opportunities</span><h2 id="ctr-pathway-heading">Your Tennis Pathway</h2><p>Find your place. Explore your next challenge. Discover opportunities to play.</p><p class="h4 text-white mb-2">{{ $pathway['presentation']['stage'] }}</p><p class="small">{{ $pathway['presentation']['status'] }}@if($pathway['metrics']['date'] ?? null) · Saved {{ $pathway['metrics']['date'] }} @endif</p>@if($pathway['presentation']['event_stage'])<a class="btn" href="#pathway-upcoming-events">Explore matching events <span aria-hidden="true">→</span></a>@else<a class="btn" href="#pathway-level-map">Explore tournament levels <span aria-hidden="true">→</span></a>@endif</header>
  @include('frontend.my-tennis.pathway-journey')
  <div class="pathway-summary">
    <div class="pathway-next-step">
      <h3 class="h6 mb-1">Your tournament stage</h3>
      <p class="h5 mb-2" data-pathway-applicable-stage>{{ $pathway['presentation']['stage'] }}</p>
      <p class="small fw-semibold mb-2" role="status" data-pathway-stage-state="{{ $pathway['presentation']['state'] }}">{{ $pathway['presentation']['status'] }}</p>
      <h4 class="h6 mb-1">Why this stage?</h4>
      <p class="small text-muted mb-2">{{ $pathway['presentation']['why'] }}</p>
      @if(!$pendingMove && in_array($pathway['current_stage'], ['red', 'orange'], true) && $pathway['target'])
        <p class="small mb-2" data-pathway-target-progress>
          {{ $pathway['ctr_label'] }}: <strong>{{ number_format($pathway['score'], 1) }} / {{ number_format($pathway['target']['ctr'], 1) }}</strong> ·
          Published {{ $pathway['current_stage_label'] }} events: <strong>{{ $pathway['ownEvents'] ?? 'Unknown' }} / {{ $pathway['target']['events'] }}</strong>.
          {{ $pathway['assessment_status'] }}.
        </p>
      @endif
      <h4 class="h6 mb-1">Suggested next step</h4>
      @if($pendingMove)
        <p class="small mb-3">Play eligible published singles at {{ \App\Services\Performance\BallRatingModel::LABELS[$pendingMove['to']] }} level to establish the next CTR.</p>
      @else
        <ul class="small ps-3 mb-3">
          @foreach($pathway['next_steps'] as $step)<li>{{ $step }}</li>@endforeach
        </ul>
      @endif
      @if($pathway['presentation']['event_stage'])
        <a class="btn btn-sm btn-primary pathway-browse" href="#pathway-upcoming-events">View matching events</a>
      @endif
      <a class="btn btn-sm btn-outline-primary pathway-browse" href="{{ route('home') }}">Browse all events</a>
    </div>
    <div class="pathway-current-level">
      @if($pendingMove)
        <h3 class="h6">Latest rated ball level</h3>
        @if($pathway['badge']['stage'])
          <div class="pathway-score" data-pathway-top-badge><x-ctr-badge :stage="$pathway['badge']['stage']" :score="$pathway['badge']['score']" :local="$pathway['badge']['local']" /></div>
        @else
          <p class="small mb-2">No saved rating from an earlier ball level.</p>
        @endif
        <p class="small mb-0">{{ \App\Services\Performance\BallRatingModel::LABELS[$pendingMove['to']] }} · CTR pending for the recorded move.</p>
      @else
      <h3 class="h6">Ball level: {{ $pathway['current_stage'] === 'standard' ? 'Green/yellow ball' : $pathway['current_stage_label'] }}</h3>
      <div class="pathway-score" data-pathway-top-badge><x-ctr-badge :stage="$pathway['badge']['stage']" :label="$pathway['badge']['stage'] === 'standard' ? 'Green/yellow ball' : null" :score="$pathway['badge']['score']" :local="$pathway['badge']['local']" /></div>
      <p class="small text-muted mt-2 mb-0">@if($pathway['metrics']['date'] ?? null) Latest saved ball-level rating · {{ $pathway['metrics']['date'] }} @else No saved ball-level rating available. @endif</p>
      @if($pathway['metrics']['stale'] ?? false)<p class="small text-muted mt-2 mb-0">@if($pathway['assessment_available'] ?? false) Assessment uses the latest saved rating shown above while the nightly update is due. @else Saved rating awaiting an update; progress is not assessed. @endif</p>@endif
      @endif
    </div>
  </div>
  @include('frontend.my-tennis.pathway-progress')
  @include('frontend.my-tennis.pathway-move')
  @include('frontend.my-tennis.pathway-events')
  <details class="pathway-evidence-details mt-3"><summary>How your pathway is assessed · saved evidence</summary>
  <section class="mt-3" aria-labelledby="pathway-ball-evidence-heading">
    <h3 class="h6 mb-1" id="pathway-ball-evidence-heading">Your saved evidence by ball level</h3>
    <p class="small text-muted mb-0">Counts cover rating-eligible published singles in the {{ \App\Services\Performance\AllAgeRatingModel::WINDOW_DAYS }} days ending {{ $pathway['metrics']['date'] ?? 'on the saved rating date' }}.</p>
    <div class="pathway-stages">
      @foreach(['red' => '#ed2029', 'orange' => '#ed7100', 'standard' => '#12765a'] as $ball => $ink)
        @php($progress = $pathway['stageProgress'][$ball] ?? [])
        @php($stagePending = ($pendingMove['to'] ?? null) === $ball)
        <div class="pathway-stage" style="--stage-ink:{{ $ink }}" data-pathway-ball-evidence="{{ $ball }}">
          <h4 class="h6 mb-2">{{ \App\Services\Performance\BallRatingModel::LABELS[$ball] }}</h4>
          <p class="small mb-1">Saved CTR <strong>{{ $stagePending ? 'Pending' : (isset($progress['score']) ? number_format($progress['score'], 1) : 'Unavailable') }}</strong>@if(!$stagePending && ($progress['local'] ?? false)) · Within-group only @endif</p>
          <p class="small mb-1">Played singles <strong>{{ $progress['matches'] ?? 'Unavailable' }}</strong></p>
          <p class="small mb-0">Confidence <strong>{{ !$stagePending && isset($progress['score']) ? ($progress['confidence_label'] ?? 'Unavailable') : 'Unavailable' }}</strong></p>
        </div>
      @endforeach
    </div>
  </section>
  </details>
</section>
