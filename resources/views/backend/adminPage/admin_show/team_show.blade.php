@php
  // Quick counts for badges (TEAM EVENTS)
  $regionCount   = $event->regions->count();
  $teamCount     = $event->regions->sum(fn ($r) => $r->teams->count());
  $categoryCount = $event->eventCategories->count();
  $playerCount   = $event->regions->sum(
    fn ($r) => $r->teams->sum(fn ($t) => $t->teamPlayers->filter(fn ($slot) => (int) $slot->player_id > 0 || $slot->noProfile)->count())
  );
  $reserveCount = ($teamSelectionInvitations ?? collect())->flatten(1)
    ->where('status', \App\Models\TeamSelectionInvitation::RESERVE)->count();
@endphp

<link rel="stylesheet" href="{{ asset('css/team-admin-workspace.css') }}?v={{ filemtime(public_path('css/team-admin-workspace.css')) }}">


<div class="team-admin-workspace" data-backend-wide>
  <div class="nav-tabs-shadow mb-4">

      {{-- Shared Teams workspace navigation. --}}
      @include('backend.event.partials.team-workspace-nav')

      <div class="tab-content p-3">

       {{-- ✅ Regions + Teams merged --}}
{{-- ============================= --}}
{{-- REGIONS TAB --}}
{{-- ============================= --}}
  @include('backend.adminPage.admin_show.tabs.regions') {{-- ✅ use your working version --}}

        {{-- Categories tab --}}
        <div class="tab-pane fade" id="tab-categories" role="tabpanel" aria-labelledby="tab-categories">
          <div class="card">
            <div class="card-header">
              <h5 class="m-0">Event Categories</h5>
              <button class="btn btn-primary btn-sm" id="add-category-button"
                data-bs-toggle="modal" data-bs-target="#add-category-modal">
                <i class="ti ti-plus me-1"></i> Add Category
              </button>
            </div>
            <div class="card-body">
              @if ($event->eventCategories->isEmpty())
                <div class="alert alert-primary noRegions" role="alert">No Categories added to event</div>
              @else
                <ul class="list-group" id="category-list">
                  @foreach ($event->eventCategories as $category)
                    <li class="list-group-item d-flex justify-content-between align-items-center" data-category-id="{{ $category->id }}">
                      <span>{{ $category->category->name }}</span>
                      <div>
                        <span class="text-muted me-2">#{{ $category->id }}</span>
                        <button class="btn btn-sm btn-danger btn-remove-category"
                                data-id="{{ $category->id }}"
                                data-name="{{ $category->category->name }}">
                          <i class="ti ti-trash"></i>
                        </button>
                      </div>
                    </li>
                  @endforeach
                </ul>
              @endif
            </div>
          </div>

          <!-- Add Category Modal -->
          <div class="modal fade" id="add-category-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
              <form id="add-category-form">
                @csrf
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <div class="mb-3">
                      <label for="category-select" class="form-label">Select Category</label>
                      <select id="category-select" name="category_ids[]" class="form-select" multiple required>
                        <option value="">-- Select --</option>
                        @foreach($allCategories as $cat)
                          <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                      </select>
                      <small class="text-muted">Hold Ctrl (Windows) or Cmd (Mac) to select multiple.</small>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Add</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                  </div>
                </div>
              </form>
            </div>
          </div>
        </div>

  {{-- 🧍‍♂️ PLAYERS TAB --}}
  @include('backend.adminPage.admin_show.tabs.players') {{-- ✅ use the working version you built above --}}

  {{-- 🔢 PLAYER ORDER TAB --}}
  @include('backend.adminPage.admin_show.tabs.player-order') {{-- ✅ use your working version --}}



        {{-- Result Ranks (active) --}}
        <div class="tab-pane fade" id="tab-result-rank" role="tabpanel" aria-labelledby="tab-result-rank">
          <div class="row g-3">
            <div class="col-md-3">
              <div class="text-muted small fw-medium mb-2">Categories</div>
              <div class="switches-stacked">
                @foreach($event->eventCategories as $idx => $category)
                  <label class="switch d-block mb-2">
                    <input type="radio"
                           class="switch-input category-radio"
                           name="category-radio"
                           value="{{ $category->id }}"
                           data-name="{{ $category->category->name }}"
                           data-event_id="{{ $event->id }}"
                           {{ $idx === 0 ? 'checked' : '' }}>
                    <span class="switch-toggle-slider">
                      <span class="switch-on"></span>
                      <span class="switch-off"></span>
                    </span>
                    <span class="switch-label">{{ $category->category->name }}</span>
                  </label>
                @endforeach
              </div>
            </div>

            <div class="col-md-9">
              <div class="card" id="rank-table">
                <div class="card-header">
                  <h5 id="category-name" class="m-0"></h5>
                </div>
                <div class="card-body" id="category-table"><!-- AJAX loads here --></div>
              </div>
            </div>
          </div>
        </div>

      </div> {{-- /.tab-content --}}
    </div>
  </div>

<!-- Import all no-profile teams for one region -->
<div class="modal fade" id="import-region-teams-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-1">Import no-profile teams</h5>
          <div class="small text-muted">Region: <span id="bulk-import-region-name"></span></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2 mb-3">
          <strong>Preview first.</strong> Choose the workbook and check the detected teams. Nothing is created until you confirm the selected complete teams.
        </div>
        <form id="bulk-team-import-form" enctype="multipart/form-data">
          @csrf
          <input type="hidden" id="bulk-import-confirmed" name="confirmed" value="0">

          <div class="row g-3">
            <div class="col-lg-6">
              <label for="bulk-import-file" class="form-label">Team workbook</label>
              <input type="file" class="form-control" id="bulk-import-file" name="file" accept=".xlsx,.xls,.csv" required>
              <div class="form-text">
                Excel or CSV. Supports side-by-side headings such as Boys U10, Girls U10, Seuns o10 and Dogters 010, or columns for Category, Rank, Name and Surname.
              </div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <label for="bulk-import-prefix" class="form-label">Team names start with</label>
              <input type="text" class="form-control" id="bulk-import-prefix" name="team_prefix" maxlength="100" required>
              <div class="form-text">Example: ZFM creates “ZFM Boys U10”.</div>
            </div>
            <div class="col-sm-6 col-lg-3">
              <label for="bulk-import-expected" class="form-label">Players per team</label>
              <input type="number" class="form-control" id="bulk-import-expected" name="expected_players" min="1" max="50" value="8" required>
              <div class="form-text">Incomplete teams are shown but cannot be selected.</div>
            </div>
            <div class="col-lg-6 d-none" id="bulk-import-sheet-wrap">
              <label for="bulk-import-sheet" class="form-label">Worksheet</label>
              <select class="form-select" id="bulk-import-sheet" name="sheet_name">
                <option value="">Auto-detect the best worksheet</option>
              </select>
            </div>
            <div class="col-12">
              <div class="form-check border rounded p-3 ps-5 bg-light">
                <input class="form-check-input" type="checkbox" value="1" id="bulk-import-fill-missing" name="fill_missing_players">
                <label class="form-check-label fw-medium" for="bulk-import-fill-missing">
                  Import incomplete teams with placeholder players
                </label>
                <div class="form-text mt-1">
                  Missing ranks become clearly labelled slots such as “Player 8 — To be confirmed”. Partial names, duplicate ranks and invalid ranks still block import.
                </div>
              </div>
            </div>
          </div>

          <div id="bulk-import-status" class="alert alert-primary d-none mt-3 mb-0">
            <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
            Reading workbook…
          </div>
          <div id="bulk-import-errors" class="alert alert-danger d-none mt-3 mb-0"></div>

          <div id="bulk-import-preview" class="d-none mt-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
              <div>
                <h6 class="mb-0">Review teams before importing</h6>
                <div id="bulk-import-summary" class="small text-muted"></div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary" id="bulk-import-select-complete">Select all teams ready to import</button>
            </div>
            <div class="table-responsive border rounded">
              <table class="table table-sm align-middle mb-0">
                <thead>
                  <tr>
                    <th class="text-center">Import</th>
                    <th>Detected category</th>
                    <th>Team</th>
                    <th class="text-center">Players</th>
                    <th>Action</th>
                    <th>Validation</th>
                  </tr>
                </thead>
                <tbody id="bulk-import-preview-body"></tbody>
              </table>
            </div>
            <div class="alert alert-warning py-2 small mt-3 mb-0">
              Importing creates unpublished no-profile teams and roster slots. Existing linked profiles are preserved; conflicting changes are blocked.
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="bulk-import-cancel">Cancel</button>
        <button type="button" class="btn btn-primary" id="bulk-import-submit" disabled>Preview teams</button>
      </div>
    </div>
  </div>
</div>

<!-- Import No-Profile Team Modal -->
<div class="modal fade" id="import-noprofile-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Import No-Profile Team: <span id="import-team-name"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="import-noprofile-form" enctype="multipart/form-data">
          @csrf
          <input type="hidden" id="import-team-id" name="team_id">
          <input type="hidden" id="import-region-id" name="region_id">
          <input type="hidden" id="import-confirmed" name="confirmed" value="0">

          <!-- Spinner & status (hidden initially) -->
          <div id="import-status" class="d-flex align-items-center mb-3" style="display:none;">
            <div id="import-spinner" class="spinner-border text-primary me-3" role="status" aria-hidden="true" style="width:1.4rem;height:1.4rem;"></div>
            <div>
              <div class="small">Importing… <strong id="import-timer">00:00</strong></div>
              <div id="import-message" class="small text-muted">Uploading file and processing rows.</div>
            </div>
          </div>

          <div class="mb-3">
            <label for="import-file" class="form-label">Select Excel File</label>
            <input type="file" class="form-control" id="import-file" name="file" accept=".xlsx,.xls,.csv" required>
            <small class="text-muted d-block mt-2">
              One team per file. Team ID and payment status are deliberately not imported.
            </small>
            <a id="import-template-link" class="btn btn-sm btn-outline-secondary mt-2" href="#">
              <i class="ti ti-download me-1"></i>Download this team’s template
            </a>
          </div>

          <div class="card bg-light">
            <div class="card-body">
              <h6 class="card-title">File Format Example:</h6>
              <table class="table table-sm table-borderless">
                <thead>
                  <tr class="text-muted">
                    <th>rank</th>
                    <th>name</th>
                    <th>surname</th>
                    <th>dateOfBirth</th>
                  </tr>
                </thead>
                <tbody class="text-muted small">
                  <tr>
                    <td>1</td>
                    <td>John</td>
                    <td>Doe</td>
                    <td>2013-05-20</td>
                  </tr>
                  <tr>
                    <td>2</td>
                    <td>Jane</td>
                    <td>Smith</td>
                    <td></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div id="import-preview" class="d-none mt-3">
            <h6>Review before importing</h6>
            <div class="table-responsive">
              <table class="table table-sm align-middle">
                <thead><tr><th>Rank</th><th>Player</th><th>DOB</th><th>Existing matches</th></tr></thead>
                <tbody id="import-preview-body"></tbody>
              </table>
            </div>
            <div class="alert alert-warning py-2 small mb-0">Confirm only after checking the names, ranks and possible existing-profile matches.</div>
          </div>

          <div id="import-errors" class="alert alert-danger d-none mt-3 mb-0"></div>

        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="import-cancel-btn">Cancel</button>
        <button type="button" class="btn btn-primary" id="import-submit-btn">Preview roster</button>
      </div>
    </div>
  </div>
</div>

{{-- 🟠 Edit No-Profile Modal --}}
<div class="modal fade" id="editNoProfileModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="editNoProfileForm">@csrf
        <div class="modal-header">
          <h5 class="modal-title">Edit Dummy Player</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="noProfileId">
          <div class="mb-3">
            <label class="form-label">First Name</label>
            <input type="text" class="form-control" name="name" id="noProfileName" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Surname</label>
            <input type="text" class="form-control" name="surname" id="noProfileSurname" required>
          </div>
        </div>
        <div class="modal-footer"><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
  </div>
</div>

<script>
  window.deleteCategoryUrl = "{{ url('backend/event/category') }}";
  window.eventAttachCategoryUrl = "{{ route('admin.categories.attach', $event->id) }}";
  window.importNoProfileUrl = null;

  // Handle import noprofile button click
 
</script>









