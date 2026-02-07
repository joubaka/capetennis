



@php
  use Illuminate\Support\Str;
@endphp

<div class="container-fluid mt-3">
  @foreach ($regions as $region)
  @php
    $checked = $event->regions->contains('id', $region->id);
  @endphp

  <div class="form-check">
    <input class="form-check-input event-region-checkbox"
           type="checkbox"
           value="{{ $region->id }}"
           {{ $checked ? 'checked' : '' }}>
    <label class="form-check-label">
      {{ $region->region_name }}
    </label>
  </div>
@endforeach

<button id="saveEventRegions" class="btn btn-sm btn-outline-primary mt-3">
  Save Regions
</button>

  {{-- ===============================
      HEADER
  =============================== --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-semibold mb-0">Team Assignment</h4>

    <div class="d-flex align-items-center gap-2">

      {{-- CATEGORY FILTER --}}
      <select id="categoryFilter" class="form-select form-select-sm" style="width:auto;">
        <option value="all">All Categories</option>
        @foreach ($event->categoryEvents as $categoryEvent)
          <option value="{{ $categoryEvent->id }}">
            {{ $categoryEvent->category->name }}
          </option>
        @endforeach
      </select>

      {{-- ADD TEAM --}}
      <button id="addTeamBtn" class="btn btn-sm btn-primary">
        <i class="ti ti-plus"></i> Add Team
      </button>

      {{-- SAVE --}}
      <button id="saveTeams" class="btn btn-sm btn-success">
        <i class="ti ti-device-floppy"></i> Save Teams
      </button>

    </div>
  </div>

  <div class="alert alert-info py-2 small mb-3">
    Drag players into teams (category restricted). Drag inside a team to change playing order.
  </div>

  <div class="row">

    {{-- ===============================
        LEFT: PLAYER POOLS
    =============================== --}}
    <div class="col-lg-3">

      <div class="accordion" id="categoryAccordion">

        @foreach ($event->categoryEvents as $categoryEvent)
          @php
            $catId   = $categoryEvent->id;
            $catName = $categoryEvent->category->name;
          @endphp

          <div class="accordion-item category-block"
               data-category-event-id="{{ $catId }}">

            <h2 class="accordion-header">
              <button class="accordion-button py-2" type="button"
                data-bs-toggle="collapse"
                data-bs-target="#collapse-cat-{{ $catId }}">
                {{ $catName }}
              </button>
            </h2>

            <div id="collapse-cat-{{ $catId }}" class="accordion-collapse collapse show">
              <div class="accordion-body p-2">

                <ul class="list-group sortable-player-pool"
                    data-category-event-id="{{ $catId }}"
                    style="min-height:150px;">

                  @foreach ($categoryEvent->registrations as $registration)
                    @php
                      $player = $registration->players->first();
                    @endphp

                    @if ($player)
                      <li class="list-group-item d-flex justify-content-between align-items-center"
                          data-player-id="{{ $player->id }}"
                          data-category-event-id="{{ $catId }}">

                        <span>{{ $player->name }} {{ $player->surname }}</span>
                        <small class="text-muted">{{ $catName }}</small>
                      </li>
                    @endif
                  @endforeach

                </ul>

              </div>
            </div>
          </div>
        @endforeach

      </div>
    </div>

    {{-- ===============================
        RIGHT: TEAMS BY REGION
    =============================== --}}
    <div class="col-lg-9">

      @foreach ($event->regions as $region)

        <h5 class="fw-semibold mt-2 mb-2">
          {{ $region->region_name }}
        </h5>

        <div class="row g-3 mb-4">

          @foreach (
            \App\Models\Team::where('region_id', $region->id)
              ->whereIn('category_event_id', $event->categoryEvents->pluck('id'))
              ->orderBy('name')
              ->get()
            as $team
          )

            <div class="col-md-4 team-wrapper"
                 data-region-id="{{ $team->region_id }}"
                 data-category-event-id="{{ $team->category_event_id }}">

              <div class="card shadow-sm border border-primary team-card"
                   data-team-id="{{ $team->id }}">

                {{-- HEADER --}}
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                  <span class="fw-bold">{{ $team->name }}</span>
                  <button class="btn btn-sm btn-outline-light remove-team">×</button>
                </div>

                {{-- PLAYERS --}}
                <ul class="list-group list-group-flush sortable-team"
                    data-category-event-id="{{ $team->category_event_id }}"
                    style="min-height:200px;">

                  @foreach ($team->team_players as $tp)
                    <li class="list-group-item d-flex justify-content-between align-items-center"
                        data-player-id="{{ $tp->player_id }}">
                      <span>{{ $tp->player->name }} {{ $tp->player->surname }}</span>
                      <small class="text-muted">#{{ $tp->rank }}</small>
                    </li>
                  @endforeach

                </ul>

              </div>
            </div>

          @endforeach

        </div>

      @endforeach

    </div>
  </div>
</div>

{{-- ===============================
    CREATE TEAM MODAL
=============================== --}}
<div class="modal fade" id="createTeamModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">

      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Create New Team</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        <div class="mb-3">
          <label class="form-label">Team Name</label>
          <input type="text" id="teamName" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Category</label>
          <select id="teamCategory" class="form-select" required>
            @foreach ($event->categoryEvents as $categoryEvent)
              <option value="{{ $categoryEvent->id }}">
                {{ $categoryEvent->category->name }}
              </option>
            @endforeach
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Region</label>
          <select id="teamRegion" class="form-select" required>
            @foreach ($event->regions as $region)
              <option value="{{ $region->id }}">
                {{ $region->region_name }}
              </option>
            @endforeach
          </select>
        </div>

      </div>

      <div class="modal-footer">
        <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="createTeamConfirm">Create Team</button>
      </div>

    </div>
  </div>
</div>
