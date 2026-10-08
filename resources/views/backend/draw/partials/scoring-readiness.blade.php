@if($draw->isTeamDraw())
  @php($scoring = app(\App\Services\TeamDrawScoringPublicationService::class)->state($draw))
  <span class="event-scoring-status badge bg-label-{{ $scoring['ready'] ? 'success' : 'warning' }}" data-scoring-status data-draw-id="{{ $draw->id }}">{{ $scoring['ready'] ? 'Scoring ready' : 'Scoring not ready' }}</span>
  @unless($statusOnly ?? false)
    @can('publish', $draw)
      <button type="button" class="btn btn-sm btn-outline-primary" style="min-height:44px" data-enable-scoring data-draw-id="{{ $draw->id }}" data-url="{{ route('draw.enable-scoring', $draw) }}" data-status-url="{{ route('draw.scoring-readiness', $draw) }}" @if(!$draw->published || $scoring['ready']) hidden @endif @disabled($draw->locked) @if($draw->locked) title="Unlock this draw before enabling scoring." @endif>Enable scoring</button>
    @endcan
    <span class="small text-muted" data-scoring-feedback data-draw-id="{{ $draw->id }}" role="status" aria-live="polite"></span>
    @once
      <style>[data-enable-scoring][hidden] { display: none !important; }</style>
      <script src="{{ asset('js/team-draw-scoring-readiness.js') }}?v={{ filemtime(public_path('js/team-draw-scoring-readiness.js')) }}" defer></script>
    @endonce
  @endunless
@endif
