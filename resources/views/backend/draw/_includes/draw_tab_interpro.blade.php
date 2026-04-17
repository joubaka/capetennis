@php
  $isTeamEvent = optional($draw->event)->eventType == 3;
@endphp

<div class="card mb-3 shadow-sm border draw-card">
  <div class="card-body py-3">
    {{-- Header row --}}
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-2">
      <div class="d-flex align-items-center gap-2">
        <h6 class="mb-0">
          <i class="ti ti-tournament me-1 text-primary"></i>
          {{ $draw->drawName }}
        </h6>
        <span class="badge bg-label-secondary">{{ optional($draw->draw_types)->drawTypeName ?? 'Type' }}</span>
        @if($draw->published)
          <span class="badge bg-success"><i class="ti ti-eye ti-xs me-1"></i>Published</span>
        @else
          <span class="badge bg-label-danger"><i class="ti ti-eye-off ti-xs me-1"></i>Draft</span>
        @endif
      </div>
    </div>

    {{-- Venues display --}}
    <div class="draw-venues mb-3" data-draw-id="{{ $draw->id }}">
      @if($draw->venues && $draw->venues->count() > 0)
        <small class="text-muted me-1"><i class="ti ti-map-pin ti-xs"></i> Venues:</small>
        @foreach($draw->venues as $venue)
          <span class="badge bg-label-primary me-1">
            {{ $venue->name }} <span class="text-muted">({{ $venue->pivot->num_courts }} {{ Str::plural('court', $venue->pivot->num_courts) }})</span>
          </span>
        @endforeach
      @else
        <small class="text-muted"><i class="ti ti-map-pin-off ti-xs me-1"></i>No venues assigned</small>
      @endif
    </div>

    {{-- Action buttons --}}
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-sm btn-warning"
         href="{{ $isTeamEvent
                  ? route('backend.team-fixtures.index', ['draw_id' => $draw->id])
                  : route('backend.draw.roundrobin.show', $draw->id) }}">
        <i class="ti ti-list-details me-1"></i>{{ $isTeamEvent ? 'Team Fixtures' : 'Fixtures' }}
      </a>

      <a class="btn btn-sm btn-info"
         href="{{ route('backend.individual-schedule.page', $draw->id) }}">
        <i class="ti ti-calendar-event me-1"></i>Schedule
      </a>

      <button type="button"
              class="btn btn-sm btn-secondary btn-add-venues"
              data-draw-id="{{ $draw->id }}"
              data-draw-name="{{ $draw->drawName }}"
              data-url="{{ route('backend.draw.venues.store', $draw->id) }}">
        <i class="ti ti-map-pin me-1"></i>Venues
      </button>

      <button type="button"
              class="btn btn-sm toggle-publish {{ $draw->published ? 'btn-success' : 'btn-danger' }}"
              data-url="{{ route('draw.toggle.publish', $draw->id) }}"
              data-status="{{ $draw->published ? 1 : 0 }}">
        <i class="ti ti-{{ $draw->published ? 'eye-off' : 'eye' }} me-1"></i>{{ $draw->published ? 'Unpublish' : 'Publish' }}
      </button>

      <button type="button"
              class="btn btn-sm btn-outline-danger btn-delete-draw ms-auto"
              data-url="{{ route('draws.destroy', $draw->id) }}"
              data-draw-id="{{ $draw->id }}"
              data-draw-name="{{ $draw->drawName }}">
        <i class="ti ti-trash me-1"></i>Delete
      </button>
    </div>
  </div>
</div>

