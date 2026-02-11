       {{-- ✅ Regions + Teams merged --}}
{{-- ============================= --}}
{{-- REGIONS TAB --}}
{{-- ============================= --}}
<div class="tab-pane fade" id="tab-regions" role="tabpanel" aria-labelledby="tab-regions">
  <div class="card">

    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="m-0">
        <i class="ti ti-home me-1"></i> Regions & Teams in Event
      </h5>

      <button type="button"
              class="btn btn-primary btn-sm"
              data-bs-toggle="modal"
              data-bs-target="#modalToggle">
        <i class="ti ti-plus me-1"></i> Add Region
      </button>
    </div>

    <div class="card-body">

      @if ($event->regions->isEmpty())
        <div class="alert alert-primary noRegions text-center">
          <i class="ti ti-info-circle me-1"></i>
          No regions added to this event yet.
        </div>
      @else

        <div class="accordion" id="regionsAccordion">

          @foreach ($event->regions as $region)

            {{-- 🔹 REGION WRAPPER (AJAX TARGET) --}}
            <div class="accordion-item mb-2 border rounded"
                 data-region-row
                 data-region-id="{{ $region->id }}"
                 data-pivot-id="{{ $region->pivot->id }}">

              <h2 class="accordion-header" id="heading-{{ $region->id }}">
                <button class="accordion-button collapsed fw-semibold"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#collapse-{{ $region->id }}"
                        aria-expanded="false">
                  <span class="badge bg-label-secondary me-2">#{{ $region->id }}</span>
                  {{ $region->region_name }}
                  <span class="ms-2 text-muted small">
                    ({{ $region->teams->count() }} Teams)
                  </span>
                </button>
              </h2>

              <div id="collapse-{{ $region->id }}"
                   class="accordion-collapse collapse"
                   data-bs-parent="#regionsAccordion">

                <div class="accordion-body pt-2">

                  {{-- 🔹 REGION ACTIONS --}}
                  <div class="d-flex justify-content-end mb-2 gap-2">
                    <a href="javascript:void(0)"
                       class="text-danger removeRegionEvent"
                       data-id="{{ $region->pivot->id }}">
                      <i class="ti ti-trash me-1"></i> Remove Region
                    </a>

                    <a href="javascript:void(0)"
                       class="btn btn-sm btn-primary addTeam"
                       data-regionid="{{ $region->id }}"
                       data-bs-toggle="modal"
                       data-bs-target="#addTeamModal">
                      <i class="ti ti-plus me-1"></i> Add Team
                    </a>
                  </div>

                  {{-- 🔹 TEAMS --}}
                  @if($region->teams->isEmpty())
                    <div class="alert alert-light border text-center py-2">
                      No teams in this region yet.
                    </div>
                  @else

                    <div class="list-group">

                      @foreach ($region->teams as $team)

                        {{-- 🔹 TEAM ROW (AJAX TARGET) --}}
                        <div class="list-group-item d-flex justify-content-between align-items-start py-3 px-3 border-0 border-bottom"
                             data-team-row
                             data-team-id="{{ $team->id }}">

                          <div>
                            <div class="fw-medium">{{ $team->name }}</div>

                            <small class="text-muted d-block mb-1 category-{{ $team->id }}">
                              Category:
                              <span class="fw-semibold text-primary">
                                {{ $team->category?->category?->name ?? 'None' }}
                              </span>
                            </small>

                            <button class="btn btn-xs bg-label-info edit-team-category"
                                    data-team='@json($team->only(["id","name"]))'
                                    data-bs-toggle="modal"
                                    data-bs-target="#edit-team-category-modal">
                              <i class="ti ti-edit me-25"></i> Edit Category
                            </button>
                          </div>

                          <div class="text-end" style="min-width:180px">

                            {{-- ✅ PUBLISH / UNPUBLISH --}}
                            <a href="javascript:void(0)"
                               class="publishTeam btn btn-xs w-100 mb-2
                               {{ $team->published ? 'btn-warning' : 'btn-success' }}"
                               data-id="{{ $team->id }}"
                               data-state="{{ (int)$team->published }}">

                              <i class="ti {{ $team->published ? 'ti-eye-off' : 'ti-eye' }} me-1"></i>
                              {{ $team->published ? 'Unpublish Team' : 'Publish Team' }}
                            </a>

                            {{-- ✅ NOPROFILE TOGGLE --}}
                            <a href="javascript:void(0)"
                               class="toggleNoProfile btn btn-xs w-100 mb-2
                               {{ $team->noProfile ? 'btn-danger' : 'btn-info' }}"
                               data-url="{{ route('backend.teams.toggle-noprofile', $team->id) }}"
                               data-state="{{ (int)$team->noProfile }}">

                              <i class="ti {{ $team->noProfile ? 'ti-user-off' : 'ti-user' }} me-1"></i>
                              {{ $team->noProfile ? 'Disable NoProfile' : 'Enable NoProfile' }}
                            </a>

                            {{-- DELETE TEAM (future AJAX-ready) --}}
                            <a href="javascript:void(0)"
                               class="text-danger small removeTeam"
                               data-id="{{ $team->id }}">
                              <i class="ti ti-trash me-25"></i> Delete
                            </a>

                          </div>
                        </div>

                      @endforeach
                    </div>
                  @endif

                </div>
              </div>
            </div>

          @endforeach
        </div>
      @endif

    </div>
  </div>
</div>
