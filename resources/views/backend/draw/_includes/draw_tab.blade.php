<div class="card m-2">
  <div class="card-body">

    <div class="row">

      {{-- LEFT COLUMN --}}
      <div class="col-12 col-md-3">

        <a href="{{ route('draw.show', $draw->id) }}"
           class="list-group-item list-group-item-action d-flex align-items-center">

          <div class="w-100">

            <div class="d-flex justify-content-between">

              <div class="user-info">

                <h6 class="mb-1">{{ $draw->drawName }} (ID: {{ $draw->id }})</h6>

                <small>
                  {{ $draw->draw_types->drawTypeName ?? 'No Type' }}
                </small>

                <div class="user-status mt-1">
                  <span class="badge badge-dot {{ $draw->locked ? 'bg-danger' : 'bg-success' }}"></span>
                  <small>Draw {{ $draw->locked ? 'Locked' : 'Unlocked' }}</small>
                </div>
              </div>

              {{-- Delete / Unlock button logic --}}
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

        {{-- Publish toggles --}}
        <button
          id="toggleDraw{{ $draw->id }}"
          data-id="{{ $draw->id }}"
          class="toggleDraw m-2 btn btn-sm btn-{{ $draw->published ? 'success' : 'danger' }}">
          {{ $draw->published ? 'Draw is Published' : 'Draw is Not Published' }}
        </button>

        <button
          id="toggleOrderOfPlay{{ $draw->id }}"
          data-id="{{ $draw->id }}"
          class="toggleOrderOfPlay m-2 btn btn-sm btn-{{ $draw->oop_published ? 'success' : 'danger' }}">
          {{ $draw->oop_published ? 'OOP is Published' : 'OOP is Not Published' }}
        </button>

      </div>


      {{-- MIDDLE COLUMN --}}
      <div class="col-12 col-md-5">

        {{-- Day selector --}}
        <div class="mb-4">
          <label class="form-label">Day to Schedule</label>

          <select
            class="form-select"
            id="dayScheduleSelect{{ $draw->id }}"
            name="dayScheduleSelect">

            <option value="" selected>-</option>

            @foreach($playingDays ?? [] as $index => $day)
              <option value="{{ $index+1 }}">
                {{ $day }} — Day {{ $index+1 }}
              </option>
            @endforeach

          </select>
        </div>

        {{-- Time selectors --}}
        <div class="mb-4">
          <div class="row">

            <div class="col-6">
              <label class="form-label">Start Time</label>
              <input type="text"
                     class="form-control flatPickerTime"
                     placeholder="HH:MM"
                     id="flatpickr-time-start{{ $draw->id }}">
            </div>

            <div class="col-6">
              <label class="form-label">Last Match Time</label>
              <input type="text"
                     class="form-control flatPickerTime"
                     placeholder="HH:MM"
                     id="flatpickr-time-end{{ $draw->id }}">
            </div>

          </div>
        </div>

        <button class="btn btn-info btn-sm scheduleDraw"
                data-id="{{ $draw->id }}">
          Schedule Matches
        </button>

      </div>


      {{-- RIGHT COLUMN --}}
      <div class="col-12 col-md-4">

        <button type="button"
                class="btn btn-info btn-sm addVenues mb-3"
                data-id="{{ $draw->id }}"
                data-bs-toggle="modal"
                data-bs-target="#basicModal">
          Add Venues
        </button>

        @foreach($draw->venues as $venue)
          <p class="mb-1">

            {{ $venue->name }} — {{ $venue->pivot->num_courts }} courts

            <span class="btn btn-sm btn-danger deleteVenue ms-2"
                  data-id="{{ $draw->id }}"
                  data-venue="{{ $venue->id }}">
              Delete
            </span>

          </p>
        @endforeach

      </div>

    </div>
  </div>
</div>

{{-- Venue Modal --}}
@include('backend.draw._modals.addVenueModal')
