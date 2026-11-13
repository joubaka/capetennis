@unless($draw->isTeamDraw())
@unless(request()->expectsJson())
@include('backend.draw.partials.workflow-assets')
@endunless
@php($workflow = $workflow ?? app(\App\Services\Draw\DrawWorkflow::class)->forDraw($draw))
<section class="draw-journey card card-body mb-3" data-draw-journey data-workflow-context="{{ $workflowContext ?? '' }}" data-default-step="{{ $workflow['generated'] ? 'matches' : 'players' }}" data-workflow-url="{{ route('draw.guide.show', [$draw->event_id, $draw, 'workflow_only' => 1] + $workflow['context']) }}">
  <div class="d-flex flex-wrap justify-content-between gap-2 mb-2"><strong>{{ $workflow['format'] }}</strong><span class="small text-muted">Save privately · review before publishing</span></div>
  <nav class="draw-journey-steps" aria-label="Draw workflow">
    @foreach($workflow['steps'] as $key => $step)
      <a href="{{ $step['url'] }}" data-journey-step="{{ $key }}"><span>{{ $loop->iteration }}. {{ $step['label'] }}</span><small>{{ $step['complete'] ? 'Saved' : ($key === 'results' ? 'During play' : 'To do') }}</small></a>
    @endforeach
  </nav>
  <div class="d-flex flex-wrap align-items-center gap-2 mt-3"><a class="btn btn-primary" data-journey-next href="{{ $workflow['steps'][$workflow['next']]['url'] }}">{{ $workflow['resultsComplete'] && $workflow['next'] === 'results' ? 'Results complete · Review final positions' : 'Next required step: '.strtolower($workflow['steps'][$workflow['next']]['label']) }}</a><a href="{{ route('draw.setup.show', [$draw] + $workflow['context']) }}">Change format</a><a href="{{ $workflow['workspace'] }}#notes">Rules &amp; notes</a><span class="small text-muted">{{ $workflow['matches'] }} matches · {{ $workflow['scheduled'] }} with saved times</span></div>
  @if($draw->isTwoStageRoundRobin() || $draw->settings?->workflow === 'round_robin_playoffs')<p class="small mt-2 mb-0">Later matches become available after confirmed results. Review qualification in Results; opening-stage readiness is not final completion.</p>@endif
  <details class="mt-2"><summary>Edit this draw</summary><div class="mt-2">
    <p class="small mb-2">Open a step to review or edit it. Going back does not reset saved work. Player swaps, changing format, rebuilding matches and moving times are separate operations.</p>
    @foreach($workflow['blockers'] as $blocker)<p class="small mb-1">{{ $blocker }}</p>@endforeach
    <p class="small mb-0">Rebuilding removes generated matches. Saved times and results can prevent reopening starting positions. Review the impact before confirming a reset; notes and permitted schedule edits use their own controls.</p>
  </div></details>
</section>
@endunless
