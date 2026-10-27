@php
  $publicationSurface = $publicationSurface ?? 'standalone';
  $publicationFlexible = $publicationDraw->usesFlexibleMonrad();
  $publicationRevision = $publicationFlexible ? $publicationDraw->flexibleMonrad?->revision : null;
@endphp
@can('publish', $publicationDraw)
  <div class="d-flex flex-wrap align-items-center gap-2" data-draw-publication="{{ $publicationDraw->id }}">
    <button type="button" class="btn btn-sm {{ $publicationDraw->published ? 'btn-success' : 'btn-outline-primary' }}"
      aria-pressed="{{ $publicationDraw->published ? 'true' : 'false' }}"
      @if($publicationSurface === 'roundrobin') id="rr-publish-draw" data-url="{{ route('draw.toggle.publish', $publicationDraw) }}"
      @elseif($publicationSurface === 'flexible') data-workspace-publish-draw-proxy
      @else data-workspace-publish-draw="{{ route($publicationFlexible ? 'flexible-monrad.publish' : 'draw.toggle.publish', $publicationDraw) }}"
        data-published="{{ $publicationDraw->published ? '1' : '0' }}"
        @if($publicationFlexible) data-flexible-revision="{{ $publicationRevision ?? 0 }}" @endif
      @endif
      @disabled($publicationFlexible && ($publicationDraw->locked || !$publicationDraw->flexibleMonrad?->graph))>
      {{ $publicationDraw->published ? 'Unpublish draw' : 'Publish draw' }}
    </button>
    <button type="button" class="btn btn-sm {{ $publicationDraw->oop_published ? 'btn-success' : 'btn-outline-primary' }}"
      aria-pressed="{{ $publicationDraw->oop_published ? 'true' : 'false' }}"
      @if($publicationSurface === 'roundrobin') id="rr-publish-schedule" data-url="{{ route('draw.toggle.publish.schedule', $publicationDraw) }}"
      @else data-workspace-publish-schedule="{{ route('draw.toggle.publish.schedule', $publicationDraw) }}" @endif>
      {{ $publicationDraw->oop_published ? 'Unpublish schedule' : 'Publish schedule' }}
    </button>
    <span class="small text-muted" role="status" aria-live="polite" data-publication-status>Draw and schedule publish separately.</span>
  </div>
@endcan
