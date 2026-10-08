<style>#regionsAccordion .accordion-header > .accordion-button{flex-wrap:wrap;gap:.35rem;min-width:0}#regionsAccordion .region-name,#regionsAccordion .region-short-name{min-width:0;max-width:100%;overflow-wrap:anywhere}#regionsAccordion .accordion-header > .accordion-button::after{flex-shrink:0}</style>
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

        @if(session('team_rename_success'))
          <div class="alert alert-success" role="status">{{ session('team_rename_success') }}</div>
        @endif
        @if($errors->teamRename->any())
          <div class="alert alert-danger" role="alert">{{ $errors->teamRename->first('name') }} Open Edit Team Name to correct it.</div>
        @endif

        <div class="accordion" id="regionsAccordion">

          @if ($event->regions->isEmpty())
            <div class="alert alert-primary noRegions text-center">
              <i class="ti ti-info-circle me-1"></i>
              No regions added to this event yet.
            </div>
          @else

          @foreach ($event->regions as $region)
            @php
              $regionTeamCount = $region->teams->count();
              $regionUnpublishedCount = $region->teams->where('published', false)->count();
              $renameTeamId = $errors->teamRename->any() ? old('rename_team_id') : session('renamed_team_id');
              $renameRegionOpen = $renameTeamId && $region->teams->contains('id', (int) $renameTeamId);
            @endphp

            {{-- 🔹 REGION WRAPPER (AJAX TARGET) --}}
            <div class="accordion-item mb-2 border rounded"
                 data-region-row
                 data-region-id="{{ $region->id }}"
                 data-pivot-id="{{ $region->pivot->id }}">

              <h2 class="accordion-header" id="heading-{{ $region->id }}">
                <button class="accordion-button {{ $renameRegionOpen ? '' : 'collapsed' }} fw-semibold"
                        type="button"
                        data-bs-toggle="collapse"
                        data-bs-target="#collapse-{{ $region->id }}"
                        aria-expanded="{{ $renameRegionOpen ? 'true' : 'false' }}">
                  <span class="badge bg-label-secondary me-2">#{{ $region->id }}</span>
                  <span class="region-name">{{ $region->region_name }}</span><span class="region-short-name ms-2 text-muted">({{ \App\Support\RegionAbbreviation::label($region) }})</span>
                  <span class="ms-2 text-muted small">
                    ({{ $region->teams->count() }} Teams)
                  </span>
                </button>
              </h2>

              <div id="collapse-{{ $region->id }}"
                   class="accordion-collapse collapse {{ $renameRegionOpen ? 'show' : '' }}"
                   data-bs-parent="#regionsAccordion">

                <div class="accordion-body pt-2">

                  {{-- 🔹 REGION ACTIONS --}}
                  <div class="d-flex flex-wrap align-items-center mb-3 gap-2">
                    <a href="javascript:void(0)"
                       class="btn btn-sm btn-primary addTeam"
                       data-regionid="{{ $region->id }}"
                       data-bs-toggle="modal"
                       data-bs-target="#addTeamModal">
                      <i class="ti ti-plus me-1"></i> Add Team
                    </a>
                    <a href="javascript:void(0)"
                       class="btn btn-sm btn-outline-primary import-region-teams-btn"
                       data-region-name="{{ $region->region_name }}"
                       data-team-prefix="{{ $region->short_name ?: $region->region_name }}"
                       data-import-url="{{ route('backend.region.teams.import.no.profile', [$event, $region]) }}"
                       data-bs-toggle="modal"
                       data-bs-target="#import-region-teams-modal">
                      <i class="ti ti-file-spreadsheet me-1"></i> Import Teams
                    </a>
                    <div class="dropdown">
                      <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Region tools</button>
                      <div class="dropdown-menu dropdown-menu-end">
                    <button type="button"
                            class="dropdown-item renameRegionEvent"
                            data-id="{{ $region->pivot->id }}"
                            data-name="{{ $region->region_name }}"
                            data-short-name="{{ $region->short_name }}"
                            data-event-count="{{ $region->events()->count() }}">
                      <i class="ti ti-edit me-1"></i> Edit Region
                    </button>
                    <a href="javascript:void(0)"
                       class="dropdown-item text-danger removeRegionEvent"
                       data-id="{{ $region->pivot->id }}">
                      <i class="ti ti-trash me-1"></i> Remove Region
                    </a>
                      </div>
                    </div>
                  </div>
                  <section class="border rounded p-3 mb-3" aria-label="Team publication for {{ $region->region_name }}">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                      <div><h3 class="h6 mb-1">Team publication</h3><p class="small text-muted mb-0">Review the teams before publishing. Draws, schedules and results use separate publication controls.</p></div>
                    <button type="button"
                            class="btn btn-sm btn-success publishRegionTeams"
                            data-url="{{ route('backend.region.teams.publish', [$event, $region]) }}"
                            data-team-count="{{ $regionTeamCount }}"
                            data-unpublished-count="{{ $regionUnpublishedCount }}"
                            @disabled($regionTeamCount === 0 || $regionUnpublishedCount === 0)>
                      <i class="ti ti-eye me-1"></i>
                      {{ $regionTeamCount > 0 && $regionUnpublishedCount === 0 ? 'All Teams Published' : 'Publish All Teams' }}
                    </button>
                    </div>
                  </section>

                  {{-- 🔹 TEAMS CONTAINER --}}
                  <div class="teams-container">
                  @if($region->teams->isEmpty())
                    <div class="alert alert-light border text-center py-2 no-teams-alert">
                      No teams in this region yet.
                    </div>
                  @else

                    <div class="list-group">

                      @foreach ($region->teams as $team)

                        {{-- 🔹 TEAM ROW (AJAX TARGET) --}}
                        <div class="list-group-item d-flex flex-wrap gap-2 justify-content-between align-items-start py-3 px-3 border-0 border-bottom"
                             data-team-row
                             data-team-id="{{ $team->id }}">

                          <div class="flex-grow-1" style="min-width:0; overflow-wrap:anywhere">
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
                            @can('team.update', $team)
                              <details class="mt-2" @if($errors->teamRename->any() && (int) old('rename_team_id') === (int) $team->id) open @endif>
                                <summary class="btn btn-xs btn-outline-secondary">Edit Team Name</summary>
                                <form method="POST" action="{{ route('backend.team.name.update', [$event, $team]) }}" class="mt-2">
                                  @csrf
                                  @method('PATCH')
                                  <input type="hidden" name="rename_team_id" value="{{ $team->id }}">
                                  <label class="form-label" for="team-name-{{ $team->id }}">Team name</label>
                                  <input class="form-control" id="team-name-{{ $team->id }}" name="name" maxlength="255" required value="{{ (int) old('rename_team_id') === (int) $team->id ? old('name', $team->name) : $team->name }}">
                                  <button type="submit" class="btn btn-sm btn-primary mt-2">Save Team Name</button>
                                </form>
                              </details>
                            @endcan
                          </div>

                          <div class="text-end" style="min-width:180px">

                            {{-- ✅ PUBLISH / UNPUBLISH --}}
                            <button type="button"
                               class="publishTeam btn btn-xs w-100 mb-2
                               {{ $team->published ? 'btn-warning' : 'btn-success' }}"
                               data-id="{{ $team->id }}"
                               data-url="{{ route('publish.team', $team) }}"
                               data-state="{{ (int)$team->published }}">

                              <i class="ti {{ $team->published ? 'ti-eye-off' : 'ti-eye' }} me-1"></i>
                              {{ $team->published ? 'Unpublish Team' : 'Publish Team' }}
                            </button>


                            {{-- ✅ NOPROFILE TOGGLE --}}
                            <a href="javascript:void(0)"
                               class="toggleNoProfile btn btn-xs w-100 mb-2
                               {{ $team->noProfile ? 'btn-danger' : 'btn-info' }}"
                               data-url="{{ route('backend.teams.toggle-noprofile', $team->id) }}"
                               data-state="{{ (int)$team->noProfile }}">

                              <i class="ti {{ $team->noProfile ? 'ti-user-off' : 'ti-user' }} me-1"></i>
                              {{ $team->noProfile ? 'Disable NoProfile' : 'Enable NoProfile' }}
                            </a>

                            {{-- ✅ IMPORT NO-PROFILE TEAM (only shown if team is no-profile) --}}
                     
                            @if ($team->noProfile == 1)
                              <button type="button"
                                      class="import-noprofile-btn btn btn-xs btn-outline-success w-100 mb-2"
                                      data-region-id="{{ $region->id }}"
                                      data-team-id="{{ $team->id }}"
                                      data-team-name="{{ $team->name }}"
                                      data-import-url="{{ route('backend.team.import.no.profile', [$event, $team]) }}"
                                      data-template-url="{{ route('team.import.no.profile.template', [$event, $team]) }}"
                                      data-bs-toggle="modal"
                                      data-bs-target="#import-noprofile-modal">
                                <i class="ti ti-file-import me-1"></i> Import Roster
                              </button>
                            @endif

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
                  </div>{{-- /.teams-container --}}

                </div>
              </div>
            </div>

          @endforeach

          @endif

        </div>{{-- /#regionsAccordion --}}

    </div>
  </div>
</div>
