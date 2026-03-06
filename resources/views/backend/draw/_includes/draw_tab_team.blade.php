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
          $typeName    = optional($draw->draw_types)->drawTypeName ?? 'Type';
          $fixtureCount = $draw->fixtures_count ?? $draw->fixtures()->count();
          $isLocked    = (bool) $draw->locked;
          $isPublished = (bool) $draw->published;

          // #5 — Draw type color mapping
          $typeColor = match(strtolower($typeName)) {
            'round robin'       => 'info',
            'knockout'          => 'danger',
            'mixed'             => 'warning',
            default             => 'secondary',
          };
        @endphp

        {{-- 📍 Venues display here --}}
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
          {{-- Show Fixtures / Team Fixtures --}}
          <a class="btn btn-warning"
             href="{{ $isTeamEvent
                      ? route('backend.team-fixtures.index', ['draw_id' => $draw->id])
                      : route('draw.show', $draw->id) }}">
            {{ $isTeamEvent ? 'Show Team Fixtures' : 'Show Fixtures' }}
          </a>

          <a class="btn btn-sm btn-info"
             href="{{ route('backend.team-schedule.page', $draw->id) }}">
            <i class="ti ti-calendar me-1"></i>
            Schedule
          </a>

        {{-- Inline action buttons (Publish/Assign/Recreate/Delete) --}}
        <div class="btn-group btn-group-sm flex-wrap" role="group">
          {{-- Publish / Unpublish --}}
          <button type="button"
                  class="btn btn-sm btn-outline-secondary toggle-publish"
                  data-url="{{ route('draw.toggle.publish', $draw->id) }}"
                  data-status="{{ $isPublished ? 1 : 0 }}">
            <i class="ti ti-{{ $isPublished ? 'eye-off' : 'eye' }} me-1"></i>
            {{ $isPublished ? 'Unpublish' : 'Publish' }}
          </button>

          {{-- Assign Venues --}}
          <button type="button"
                  class="btn btn-sm btn-outline-info btn-add-venues"
                  data-draw-id="{{ $draw->id }}"
                  data-draw-name="{{ $draw->drawName }}"
                  data-url="{{ route('backend.draw.venues.store', $draw->id) }}">
            <i class="ti ti-map-pin me-1"></i>
            Assign Venues to draw
          </button>

          {{-- Recreate Fixtures (disabled if locked) --}}
          <button type="button"
                  class="btn btn-sm btn-outline-secondary btn-recreate-fixtures {{ $isLocked ? 'disabled' : '' }}"
                  data-url="{{ route('headoffice.recreateFixturesForDraw', $draw->id) }}"
                  data-draw-id="{{ $draw->id }}"
                  data-draw-name="{{ $draw->drawName }}"
                  {{ $isLocked ? 'disabled' : '' }}>
            <i class="ti ti-refresh me-1"></i>
            Recreate
            @if($isLocked)
              <small class="text-muted ms-1">(locked)</small>
            @endif
          </button>

          {{-- Delete Draw (disabled if locked) --}}
          <button type="button"
                  class="btn btn-sm btn-outline-danger btn-delete-draw {{ $isLocked ? 'disabled' : '' }}"
                  data-url="{{ route('draws.destroy', $draw->id) }}"
                  data-draw-id="{{ $draw->id }}"
                  data-draw-name="{{ $draw->drawName }}"
                  {{ $isLocked ? 'disabled' : '' }}>
            <i class="ti ti-trash me-1"></i>
            Delete
            @if($isLocked)
              <small class="text-muted ms-1">(locked)</small>
            @endif
          </button>
        </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Venues modal removed from repeated include to avoid duplicate IDs ---}}










