@php
  $publicDrawWorkflow = $draw->settings?->workflow;
  $publicDrawFormat = \App\Http\Controllers\Backend\DrawSetupController::OPTIONS[$publicDrawWorkflow][0]
    ?? ($draw->usesFlexibleMonrad() ? 'Custom Monrad' : 'Format not specified');
  $publicDrawVenues = $draw->venues->pluck('name')->filter()->unique()->values();
  $publicDrawIsPreview = ! (bool) $draw->published;
  $publicDrawEventDate = optional($draw->event?->start_date)?->format('D d M Y');
@endphp

<header class="ct-public-draw-header">
  <a class="ct-public-draw-back" href="{{ route('events.show', $draw->event_id) }}">
    <span aria-hidden="true">&larr;</span> Back to tournament
  </a>

  @if($publicDrawIsPreview)
    <div class="ct-public-draw-preview" role="status">
      <strong>Draft preview</strong>
      <span>This draw is visible to authorised staff only and has not been released publicly.</span>
    </div>
  @endif

  <div class="ct-public-draw-heading">
    <div>
      <p class="ct-public-draw-eyebrow">Cape Tennis tournament draw</p>
      <h1>{{ $draw->drawName }}</h1>
      <p class="ct-public-draw-event">{{ $draw->event?->name }}</p>
    </div>
    <div class="ct-public-draw-actions">
      @if(!empty($publicDrawPrintButtonId))
        <button id="{{ $publicDrawPrintButtonId }}" type="button" class="ct-public-draw-action">Print</button>
      @endif
      @can('view', $draw)
        <a class="ct-public-draw-action ct-public-draw-manage" href="{{ route('backend.draw.roundrobin.show', $draw) }}">Manage this draw</a>
      @endcan
    </div>
  </div>

  <div class="ct-public-draw-meta" aria-label="Draw details">
    <span>{{ $publicDrawFormat }}</span>
    @if($publicDrawEventDate)<span>{{ $publicDrawEventDate }}</span>@endif
    @if($publicDrawVenues->isNotEmpty())<span>{{ $publicDrawVenues->join(', ') }}</span>@endif
    <span class="{{ $draw->published ? 'is-ready' : 'is-pending' }}">{{ $draw->published ? 'Draw published' : 'Draw not published' }}</span>
    <span class="{{ $draw->oop_published ? 'is-ready' : 'is-pending' }}">{{ $draw->oop_published ? 'Match times published' : 'Match times to follow' }}</span>
  </div>
</header>
