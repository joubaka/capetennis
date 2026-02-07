{{-- List item --}}
<div class="list-group-item d-flex align-items-center">
  <div class="w-100">
    <div class="d-flex justify-content-between align-items-start gap-2">
      <div class="user-info">
        <h6 class="mb-2">
          {{ $draw->drawName }}
          <span class="text-muted">— {{ optional($draw->draw_types)->drawTypeName ?? 'Type' }}</span>
        </h6>

        @php
          $isTeamEvent = optional($draw->event)->eventType == 3;
        @endphp

        {{-- 📍 Venues display --}}
        <div class="draw-venues mb-2" data-draw-id="{{ $draw->id }}">
          @if($draw->venues && $draw->venues->count() > 0)
            <small class="text-muted">Venues:</small>
            @foreach($draw->venues as $venue)
              <span class="badge bg-label-primary me-1">
                {{ $venue->name }} <span class="text-muted">({{ $venue->pivot->num_courts }})</span>
              </span>
            @endforeach
          @endif
        </div>

        <div class="btn-group btn-group-sm flex-wrap" role="group">

          {{-- Show Fixtures --}}
          <a class="btn btn-warning"
             href="{{ $isTeamEvent
                      ? route('backend.team-fixtures.index', ['draw_id' => $draw->id])
                      : route('backend.draw.roundrobin.show', $draw->id) }}">
            {{ $isTeamEvent ? 'Show Team Fixtures' : 'Show Fixtures' }}
          </a>

          {{-- Publish / Unpublish --}}
          <button type="button"
                  class="btn toggle-publish {{ $draw->published ? 'btn-success' : 'btn-danger' }}"
                  data-url="{{ route('draw.toggle.publish', $draw->id) }}"
                  data-status="{{ $draw->published ? 1 : 0 }}">
            {{ $draw->published ? 'Unpublish Draw' : 'Publish Draw' }}
          </button>

          {{-- Schedule Page --}}
        <a class="btn btn-info"
   href="{{ route('backend.individual-schedule.page', $draw->id) }}">
    Schedule Matches
</a>


          {{-- Add Venues --}}
          <button type="button"
                  class="btn btn-secondary btn-add-venues"
                  data-draw-id="{{ $draw->id }}"
                  data-draw-name="{{ $draw->drawName }}"
                  data-url="{{ route('backend.draw.venues.store', $draw->id) }}">
            Add Venues
          </button>


          {{-- Delete Draw --}}
          <button type="button"
                  class="btn btn-danger btn-delete-draw"
                  data-url="{{ route('draws.destroy', $draw->id) }}"
                  data-draw-id="{{ $draw->id }}"
                  data-draw-name="{{ $draw->drawName }}">
            Delete
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Global Venues Modal --}}
<div class="modal fade" id="venuesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="venuesForm" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Assign Venues</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <div id="venues-container"></div>
          <button type="button" class="btn btn-sm btn-secondary" id="addVenueRow">+ Add Venue</button>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Save</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </form>
  </div>
</div>

