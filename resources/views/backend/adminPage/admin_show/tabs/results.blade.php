<div class="mb-3 gap-3">
  <div>
    <span id="publishResults" data-event_id="{{ $event->id }}"
      class="mb-2 align-middle btn btn-{{ $event->results_published ? 'danger' : 'success' }} btn-sm">
      {{ $event->results_published ? 'Unpublish Results' : 'Publish Results' }}
    </span>

    @if ($event->series)
      @if ($event->series->rankType->type == 'position' || $event->series->rankType->type == 'overberg')
        @include('backend.adminPage._includes.position_type')
      @else
        @include('backend.adminPage._includes.participation_type')
      @endif
    @elseif($event->eventType != 7)
      @include('backend.adminPage._includes.position_type')
    @endif
  </div>
</div>
