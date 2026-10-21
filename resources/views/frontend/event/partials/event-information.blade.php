<div class="card event-section-card mb-4">
  <div class="card-body event-card-padding p-4 p-xl-5">
    <div class="event-section-heading mb-4">
      <span class="event-section-icon" aria-hidden="true">
        <svg class="event-section-icon-svg" viewBox="0 0 24 24" focusable="false">
          <circle cx="12" cy="12" r="9" />
          <path d="M12 10v6m0-9v.01" />
        </svg>
      </span>
      <div>
        <h4 class="mb-1">Event information</h4>
        <p class="text-muted mb-0">Please review these details before entering the tournament.</p>
      </div>
    </div>

    @if(filled(strip_tags($event->information ?? '')))
      <div class="event-information-content">
        {!! $event->information !!}
      </div>
    @else
      <div class="alert alert-secondary mb-0" role="status">
        The organiser has not added further event information yet.
      </div>
    @endif

    @php($publishedDrawCount = ($eventDraws ?? collect())->where('published', true)->count())
    @if($publishedDrawCount > 0)
      <div class="mt-3 pt-3 border-top">
        <a href="#event-draws-match-times" class="btn btn-outline-primary" data-event-draw-jump style="min-height:44px;white-space:normal">
          <i class="ti ti-tournament me-1" aria-hidden="true"></i>
          {{ $publishedDrawCount === 1 ? 'View published draw' : 'View published draws' }}
          <i class="ti ti-arrow-down ms-1" aria-hidden="true"></i>
        </a>
      </div>
    @endif
  </div>
</div>

@once
  <style>
    [data-event-draw-section] { scroll-margin-top: 8rem; }
    [data-event-draw-section]:focus { outline: 3px solid #173f7a; outline-offset: 3px; }
  </style>
  <script>
    document.addEventListener('click', function (event) {
      const link = event.target.closest('[data-event-draw-jump]');
      if (!link) return;
      const target = Array.from(document.querySelectorAll('[data-event-draw-section]'))
        .find(section => section.getClientRects().length > 0);
      if (!target) return;
      event.preventDefault();
      target.focus({ preventScroll: true });
      target.scrollIntoView({ block: 'start', behavior: 'instant' });
      history.replaceState(null, '', '#' + target.id);
    });
  </script>
@endonce
