<div class="card m-2">
  <div class="card-body">

    <div class="row">

      {{-- LEFT COLUMN --}}
      <div class="col-12 col-md-3">

        {{-- Draw link --}}
        <a href="{{ route('draw.show', $draw->id) }}"
          class="list-group-item list-group-item-action d-flex align-items-center">

          <div class="w-100">

            <div class="d-flex justify-content-between">

              <div class="user-info">

                <h6 class="mb-1">{{ $draw-> drawName}} (ID: {{ $draw-> id}})</h6>

                <small>{{ $draw-> draw_types -> drawTypeName ?? 'No Type'}}</small>

                <div class="user-status mt-1">
                  <span class="badge badge-dot {{ $draw->locked ? 'bg-danger' : 'bg-success' }}"></span>
                  <small>Draw {{ $draw-> locked ? 'Locked' : 'Unlocked'}}</small>
                </div>
              </div>

              {{-- Delete / Unlock --}}
              @if(Route::currentRouteName() === 'event.draw.index')

                @if(!$draw->locked)
              <button class="btn btn-secondary btn-sm remove-draw-button"
                data-id="{{ $draw->id }}">
                Delete Draw
              </button>

              @else
              <div class="text-end">
                <small class="badge bg-label-warning mb-2 d-block">
                  This will delete ALL fixtures & results!
                </small>

                <button class="btn btn-danger btn-sm unlock-draw-button"
                  data-id="{{ $draw->id }}">
                  Unlock Draw
                </button>
              </div>
              @endif

              @endif

            </div>
          </div>

        </a>

        {{-- Publish Toggles --}}
        <button
          id="toggleDraw{{ $draw->id }}"
          data-id="{{ $draw->id }}"
          class="toggleDraw m-2 btn btn-sm btn-{{ $draw->published ? 'success' : 'danger' }}">
          {{ $draw-> published ? 'Draw is Published' : 'Draw is Not Published'}}
        </button>

        <button
          id="toggleOrderOfPlay{{ $draw->id }}"
          data-id="{{ $draw->id }}"
          class="toggleOrderOfPlay m-2 btn btn-sm btn-{{ $draw->oop_published ? 'success' : 'danger' }}">
          {{ $draw-> oop_published ? 'OOP is Published' : 'OOP is Not Published'}}
        </button>

      </div>

      {{-- MIDDLE COLUMN --}}
      <div class="col-12 col-md-5">
        <button class="btn btn-info btn-sm scheduleDraw"
          data-id="{{ $draw->id }}">
          Schedule Matches
        </button>
      </div>

      {{-- RIGHT COLUMN --}}
      <div class="col-12 col-md-4">

        <button type="button"
          class="btn btn-info btn-sm btn-add-venues mb-3"
          data-draw-id="{{ $draw->id }}"
          data-draw-name="{{ $draw->drawName }}">
          Add Venues
        </button>

        <div class="draw-venues" data-draw-id="{{ $draw->id }}">
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

      </div>

    </div>
  </div>
</div>

{ { --Venue Modal-- } }
@include('backend.draw._modals.addVenueModal')
@once
<script>
    window.venueStoreBase = window.venueStoreBase || "{{ route('backend.draw.venues.store', ['draw' => 'DRAW_ID']) }}";
    window.venueJsonBase = window.venueJsonBase || "{{ route('backend.draw.venues.json', ['draw' => 'DRAW_ID']) }}";
    window.allVenuesUrl = window.allVenuesUrl || "{{ route('venue.list') }}";
</script>
@endonce
