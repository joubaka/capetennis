<div class="row">
  <div class="col-xl-8 col-lg-7 col-md-7">
    @include('frontend.event.partials.event-information')
    @include('frontend.event.partials.event-announcements')
  </div>

  <div class="col-xl-4 col-lg-5 col-md-5">
    @include('frontend.event.partials.event-about')

    @include('frontend.event.partials.event-documents', [
      'documentsReadOnly' => true,
      'documentCardClass' => 'event-section-card',
      'documentTitle' => 'Event documents',
      'documentDescription' => 'Downloads supplied by the organiser.',
      'documentEmptyMessage' => 'No event documents are available yet.',
      'documentNewWindow' => false,
    ])
    @if($event->results_published == 1)
      <div class="card event-section-card mb-4">
        <div class="card-header"><small class="text-uppercase">Results</small></div>
        <div class="card-body">
          <a href="{{ route('events.results', $event->id) }}" class="btn bg-label-success btn-sm">
            <i class="ti ti-trophy me-1"></i> View Results
          </a>
        </div>
      </div>
    @endif
  </div>
</div>
