@extends('layouts.backend')

@section('title', 'Admin - Event Page')

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection

@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-user-view.css')}}" />
<style>
  .event-draw-list { display: grid; gap: 1rem; }
  .event-draw-card { min-width: 0; padding: 1rem; border: 1px solid var(--bs-border-color, #dbdade); border-radius: .75rem; }
  .event-draw-card .list-group-item { padding: 0; border: 0; background: transparent; }
  .event-draw-card .user-info { width: 100%; min-width: 0; }
  .event-draw-card h6 { overflow-wrap: anywhere; }
  .event-draw-card .btn-group { display: flex; flex-wrap: wrap; gap: .5rem; width: 100%; }
  .event-draw-card .btn-group > .btn-group { width: 100%; margin: 0; }
  .event-draw-card .btn-group > .btn { flex: 0 1 auto; min-height: 44px; margin: 0; border-radius: .375rem !important; white-space: normal; }
  .event-draw-meta { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; margin-top: .75rem; }
  .event-draw-links { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .75rem; }
  .event-draw-links .btn { min-height: 44px; white-space: normal; }
  @media (max-width: 575.98px) {
    .event-draw-header { flex-direction: column; align-items: flex-start !important; gap: .25rem; }
    .event-draw-body { padding-inline: 1rem; }
    .event-draw-card { padding: .875rem; }
    .event-draw-card .btn-group > .btn { flex: 1 1 calc(50% - .5rem); padding-inline: .5rem; }
    .event-draw-card .user-info > .btn-group > .btn:first-child { flex-basis: 100%; }
    .event-draw-links .btn { flex: 1 1 0; }
  }
</style>
@endsection

@section('vendor-script')
<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sortablejs/sortable.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@endsection

@section('page-script')
<script>
  window.HeadOffice = {
    venues: @json($allVenues),
    previewUrl: "{{ route('headoffice.previewTeamDraw', $event) }}",
    createUrl: "{{ route('headoffice.createSingleDraw.team', $event) }}",
    individualCreateUrl: "{{ route('headoffice.createSingleDraw', $event) }}",
    individualDrawTypeId: @json(($individualDrawTypes->firstWhere('drawTypeName', 'Singles') ?? $individualDrawTypes->first())?->id),
    backendDrawVenuesStoreTemplate: @json(route('backend.draw.venues.store', ['draw' => '__ID__'])),
    backendDrawVenuesJsonTemplate: @json(route('backend.draw.venues.json', ['draw' => '__ID__'])),
    // v2 endpoints
    teamDrawV2Enabled: @json($teamDrawV2Enabled ?? false),
    formatsUrl: @json(route('team-draw.formats.index', $event)),
    generateTiesUrlTemplate: @json(route('team-draw.generate-ties', ['draw' => '__DRAW_ID__'])),
    generateRubbersUrlTemplate: @json(route('team-draw.generate-rubbers', ['draw' => '__DRAW_ID__'])),
    attachFormatUrlTemplate: @json(route('team-draw.attach-format', ['draw' => '__DRAW_ID__'])),
  };

  $(function () {
    @if(session('success')) toastr.success(@json(session('success')), 'Success'); @endif
    @if(session('error')) toastr.error(@json(session('error')), 'Error'); @endif
    @if(session('warning')) toastr.warning(@json(session('warning')), 'Warning'); @endif
    @if(session('info')) toastr.info(@json(session('info')), 'Info'); @endif
  });
</script>

<script src="{{ asset(mix('js/headOffice.js')) }}"></script>
<script src="{{ asset('js/team-draw-mode.js') }}?v={{ filemtime(public_path('js/team-draw-mode.js')) }}"></script>
@endsection


@section('content')

@include('backend.event.partials.header', [
  'eventWorkspaceActive' => 'draws',
  'eventWorkspaceIcon' => 'ti-tournament',
  'eventWorkspaceSubtitle' => 'Team and individual draws, fixtures and venues',
])
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 no-print">
  <div><h2 class="h4 mb-1">Tournament draws</h2><p class="text-muted mb-0">Create team ties or individual singles draws, allocate venues and manage fixtures.</p></div>
  @can('team-draw.createFormat', $event)
  <a class="btn btn-outline-primary" href="{{ route('backend.team-rules.edit', $event) }}">Event scoring rules</a>
  @endcan
  <button class="btn btn-primary" id="createNewDrawBtn" data-bs-toggle="modal" data-bs-target="#createDrawModal">
      <i class="ti ti-plus me-1"></i> Create New Draw
  </button>
</div>

<div class="row mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-primary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-primary"><i class="ti ti-tournament ti-md"></i></span>
          </div>
          <h4 class="ms-1 mb-0">{{ $event->draws->count() }}</h4>
        </div>
        <p class="mb-1">Total Draws</p>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-info h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-info"><i class="ti ti-map-pin ti-md"></i></span>
          </div>
          <h4 class="ms-1 mb-0">{{ $scheduledVenues->count() }}</h4>
        </div>
        <p class="mb-1">Active Venues</p>
      </div>
    </div>
  </div>
</div>

<div class="row">

  <div class="col-xl-7 col-lg-6">
    <div class="card mb-4">
      <div class="card-header event-draw-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Manage Draws</h5>
        <small class="text-muted">Open fixtures or manage a draw below</small>
      </div>

      <div class="card-body event-draw-body pt-0">
        <div class="event-draw-list">
          @forelse($event->draws as $draw)
            <div class="event-draw-card">
                @include('backend.draw._includes.draw_tab_team')
                <div class="event-draw-meta text-muted small">
                  @if($draw->is_published)
                    <span class="badge bg-label-primary">Published</span>
                  @elseif($draw->is_done)
                    <span class="badge bg-label-success">Completed</span>
                  @else
                    <span class="badge bg-label-warning">Draft</span>
                  @endif
                   <span class="me-2"><i class="ti ti-calendar-event ti-xs"></i> {{ $draw->created_at->format('d M, Y') }}</span>
                   @if($draw->is_scheduled) <span class="text-info">Scheduled</span> @endif
                </div>
              @if($draw->isTeamDraw())
              @can('team-fixture.view', $draw)
              <div class="event-draw-links">
              @if($draw->team_format_snapshot !== null)
              <a class="btn btn-sm btn-outline-primary me-2" href="{{ route('backend.team-draw.operations', $draw) }}">Team ties</a>
              @endif
              <a class="btn btn-sm btn-outline-primary" href="{{ route('backend.team-draw.standings', $draw) }}">Standings</a>
              </div>
              @endcan
              @endif
            </div>
          @empty
            <div class="text-center py-5">
              <i class="ti ti-folders ti-lg text-muted mb-2"></i>
              <p class="text-muted">No draws created for this event yet.</p>
            </div>
          @endforelse
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-5 col-lg-6">
    <div class="card mb-4">
      <div class="card-header">
        <h5 class="mb-0">Venue Fixture Lists</h5>
      </div>
      <div class="card-body">
        <div class="list-group">
          @forelse($scheduledVenues as $venue)
            <a href="{{ route('headoffice.venue.fixtures', [$event->id, $venue->id]) }}"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-3 mb-2 border rounded">
              <div class="d-flex align-items-center">
                <div class="avatar avatar-sm me-3">
                  <span class="avatar-initial rounded bg-label-secondary"><i class="ti ti-building-community"></i></span>
                </div>
                <div>
                  <div class="fw-bold text-heading">{{ $venue->name }}</div>
                  <small class="text-muted">{{ $venue->location ?? 'Main Complex' }}</small>
                </div>
              </div>
              <div class="text-end">
                <span class="badge bg-label-info rounded-pill">
                  @php
                    $total = $venue->scheduled_fixtures_count ?? 0;
                    $finished = $venue->finished_fixtures_count ?? 0;
                  @endphp
                  {{ $finished }}/{{ $total }} finished
                </span>
                <div class="mt-1"><i class="ti ti-chevron-right text-muted ti-xs"></i></div>
              </div>
            </a>
          @empty
            <div class="alert alert-outline-secondary d-flex align-items-center" role="alert">
              <span class="alert-icon text-secondary me-2">
                <i class="ti ti-info-circle ti-xs"></i>
              </span>
              No venues have been assigned fixtures yet.
            </div>
          @endforelse
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Modal: Create New Draw (Team Event) -->
<div class="modal fade" id="createDrawModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <form id="createDrawForm">
        @csrf

        <div class="modal-header">
          <h5 class="modal-title">Create New Draw</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          {{-- Draw Name --}}
          <div class="mb-3" id="singleDrawNameGroup">
            <label for="drawName" class="form-label fw-bold">Draw Name</label>
            <input type="text" id="drawName" name="drawName" class="form-control"
                   placeholder="Choose a category and draw type below" maxlength="255">
            <div class="form-text">The name fills in automatically from your choices. You can edit it.</div>
          </div>
          <div id="bulkDrawNameHelp" class="form-text mb-3 d-none">Each selected draw is named automatically by category and draw type.</div>

          <fieldset class="mb-3">
            <legend class="form-label fw-bold mb-2">Competition</legend>
            <div class="row g-2">
              <div class="col-sm-6">
                <label class="form-check border rounded p-3 m-0 h-100" for="drawModeIndividual">
                  <input class="form-check-input" type="radio" name="draw_mode" id="drawModeIndividual" value="individual">
                  <span class="form-check-label ms-1">
                    <span class="fw-semibold d-block">Individual singles</span>
                    <span class="text-muted small">One player competes directly against another.</span>
                  </span>
                </label>
              </div>
              <div class="col-sm-6">
                <label class="form-check border rounded p-3 m-0 h-100" for="drawModeTeam">
                  <input class="form-check-input" type="radio" name="draw_mode" id="drawModeTeam" value="team">
                  <span class="form-check-label ms-1">
                    <span class="fw-semibold d-block">Team tie</span>
                    <span class="text-muted small">Teams compete through singles, reverse singles, doubles and mixed doubles, as defined by the event format.</span>
                  </span>
                </label>
              </div>
            </div>
            <div class="form-text">Choose individual singles for a normal player draw.</div>
          </fieldset>

          {{-- Draw Type --}}
          <div class="mb-3 d-none" id="teamDrawTypeSection">
            <label class="form-label fw-bold">Team Draw Type</label>
            <div class="d-flex flex-wrap gap-2">
              @foreach($teamDrawTypes as $drawType)
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio"
                         name="draw_type_id"
                         id="drawType{{ $drawType->id }}"
                         value="{{ $drawType->id }}" data-code="{{ app(\App\Services\TeamDrawSelectionService::class)->code($drawType) }}">
                  <label class="form-check-label" for="drawType{{ $drawType->id }}">
                    {{ $drawType->drawTypeName }}
                  </label>
                </div>
              @endforeach
            </div>
          </div>

          @php
            $standardCategories = $categories;
            $teamCategories = \App\Support\TeamDrawCategoryGroups::make($categories);
            $mixedCategoryGroups = [];
            foreach ($teamCategories as $cat) {
              if (in_array($cat->parsed_gender, ['boys', 'girls'], true)) {
                $mixedCategoryGroups[$cat->parsed_age][$cat->parsed_gender][] = $cat;
              }
            }
          @endphp

          <div class="mb-3 d-none" id="bulkTeamGroup">
            <label class="form-check"><input class="form-check-input" type="checkbox" id="bulkTeamDraws"><span class="form-check-label">Create multiple draws</span></label>
            <div id="bulkTeamChoices" class="d-none border rounded p-3 mt-2">
              <div class="d-flex justify-content-between gap-2"><strong>Draw types to create</strong><button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllDrawTypes">Select all types</button></div>
              <div class="d-grid gap-2 mt-2">
                @foreach($teamDrawTypes as $drawType)
                  @php
                    $typeCode = app(\App\Services\TeamDrawSelectionService::class)->code($drawType);
                  @endphp
                  @if($typeCode)
                  <label class="form-check"><input class="form-check-input" type="checkbox" name="bulk_draw_types[]" value="{{ $drawType->id }}" data-code="{{ $typeCode }}" data-name="{{ $drawType->name }}"><span class="form-check-label">{{ $drawType->name }}</span></label>
                  @endif
                @endforeach
              </div>
              <div class="d-flex justify-content-between gap-2 mt-3"><strong>Categories to include</strong><button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllDrawCategories">Select all categories</button></div>
              <div class="d-grid gap-2 mt-2">
                @foreach($teamCategories as $cat)
                @php
                  $bulkCategoryKey = app(\App\Services\TeamDrawSideResolver::class)->categoryKey($cat->name);
                @endphp
                <label class="form-check"><input class="form-check-input" type="checkbox" name="bulk_categories[]" value="{{ $cat->pivot_id }}" data-pivot-ids="{{ json_encode($cat->pivot_ids) }}" data-name="{{ $cat->name }}" data-age="{{ $bulkCategoryKey['group'] }}" data-gender="{{ $bulkCategoryKey['gender'] }}"><span class="form-check-label">{{ $cat->name }}</span></label>
                @endforeach
              </div>
              <div class="form-text">Each chosen category gets the selected singles, reverse singles and doubles draws. Mixed combines boys and girls of the same age and division within each region. Preview flags missing partners; deselect those categories or types before creating.</div>
            </div>
          </div>

          <div class="mb-3 d-none" id="manualCategoryToggleGroup">
            <label class="form-check">
              <input class="form-check-input" type="checkbox" id="manualTeamCategories">
              <span class="form-check-label">Choose categories manually</span>
            </label>
            <div class="form-text">Select the categories whose teams should compete in this draw.</div>
          </div>
          <div class="mb-3 d-none" id="manualCategoryChoices">
            <label class="form-label fw-bold">Categories to combine</label>
            <div class="d-grid gap-2">
              @foreach($standardCategories as $cat)
                @php
                  $group = collect($teamCategories)->first(fn ($group) => in_array((int) $cat->pivot_id, $group->pivot_ids, true));
                @endphp
                <label class="form-check m-0">
                  <input class="form-check-input" type="checkbox" name="manual_category_ids[]"
                         value="{{ $cat->pivot_id }}" data-gender="{{ $group?->parsed_gender }}" data-name="{{ $group?->name ?? $cat->name }}" data-age="{{ $group?->parsed_age }}" disabled>
                  <span class="form-check-label">{{ $cat->name }} <span class="text-muted small">({{ $cat->teams_count }} teams · category {{ $cat->pivot_id }})</span></span>
                </label>
              @endforeach
            </div>
            <div class="form-text">For mixed doubles, select both boys and girls categories.</div>
          </div>

          {{-- Category --}}
          <div class="mb-3 d-none" id="categorySection">
            <label class="form-label fw-bold">Category</label>
            <div class="d-flex flex-wrap gap-2" id="individualCategoryChoices">
              @foreach($standardCategories as $cat)
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio"
                         name="category_choice"
                         id="cat{{ $cat->pivot_id }}"
                         value="{{ $cat->pivot_id }}"
                         data-pivot-id="{{ $cat->pivot_id }}"
                         data-age="{{ $cat->name }}"
                         data-gender="">
                  <label class="form-check-label" for="cat{{ $cat->pivot_id }}">
                    {{ $cat->name }}
                  </label>
                </div>
              @endforeach
            </div>
            <div class="gap-2" id="teamCategoryChoices" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));">
              @foreach($teamCategories as $cat)
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio"
                         name="category_choice"
                         id="teamcat{{ $cat->pivot_id }}"
                         value="{{ $cat->pivot_id }}"
                         data-pivot-id="{{ $cat->pivot_id }}"
                         data-pivot-ids="{{ json_encode($cat->pivot_ids) }}"
                         data-age="{{ $cat->name }}"
                         data-gender="">
                  <label class="form-check-label" for="teamcat{{ $cat->pivot_id }}">
                    {{ $cat->name }}
                  </label>
                </div>
              @endforeach
            </div>
          </div>

          <div class="mb-3 d-none" id="type3Categories">
            <label class="form-label fw-bold">Mixed Doubles Pairing</label>
            <div class="alert alert-info py-2 px-3">
              Choose one boys category and one girls category for the same age group.
            </div>

            @if(empty($mixedCategoryGroups))
              <div class="alert alert-warning mb-0">
                No boys/girls category pairs are available for this event.
              </div>
            @else
              @foreach($mixedCategoryGroups as $age => $genders)
                <div class="card mb-3 border">
                  <div class="card-header py-2">
                    <strong>{{ $age }}</strong>
                  </div>
                  <div class="card-body">
                    <div class="row g-3">
                      <div class="col-md-6">
                        <h6 class="mb-2">Boys</h6>
                        <div class="d-grid gap-2">
                          @forelse(($genders['boys'] ?? []) as $cat)
                            <label class="form-check form-check-inline border rounded p-2 m-0 w-100">
                              <input class="form-check-input me-2" type="radio"
                                     name="category_choice_boys"
                                     value="{{ $cat->pivot_id }}"
                                     data-pivot-id="{{ $cat->pivot_id }}"
                                     data-pivot-ids="{{ json_encode($cat->pivot_ids) }}"
                                     data-age="{{ $cat->parsed_age }}"
                                     data-gender="{{ $cat->parsed_gender }}">
                              <span class="form-check-label">{{ $cat->name }}</span>
                            </label>
                          @empty
                            <div class="text-muted small">No boys category for this age group.</div>
                          @endforelse
                        </div>
                      </div>
                      <div class="col-md-6">
                        <h6 class="mb-2">Girls</h6>
                        <div class="d-grid gap-2">
                          @forelse(($genders['girls'] ?? []) as $cat)
                            <label class="form-check form-check-inline border rounded p-2 m-0 w-100">
                              <input class="form-check-input me-2" type="radio"
                                     name="category_choice_girls"
                                     value="{{ $cat->pivot_id }}"
                                     data-pivot-id="{{ $cat->pivot_id }}"
                                     data-pivot-ids="{{ json_encode($cat->pivot_ids) }}"
                                     data-age="{{ $cat->parsed_age }}"
                                     data-gender="{{ $cat->parsed_gender }}">
                              <span class="form-check-label">{{ $cat->name }}</span>
                            </label>
                          @empty
                            <div class="text-muted small">No girls category for this age group.</div>
                          @endforelse
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              @endforeach
            @endif
          </div>

          <div class="mb-3 d-none" id="mixedPlaceholder">
            <div class="alert alert-secondary mb-0">
              Select a mixed draw type to choose boys and girls categories.
            </div>
          </div>

          {{-- Existing event formats are selectable without changing rollout settings. --}}
          @if(($availableFormats ?? collect())->isNotEmpty())
          <div class="mb-3 d-none" id="formatSelectGroup">
            <label for="format_id" class="form-label fw-bold">Tie Format <span class="text-muted fw-normal">(optional – use event default)</span></label>
            <select id="format_id" name="format_id" class="form-select">
              <option value="">— Use event default —</option>
              @foreach($availableFormats ?? [] as $fmt)
                <option value="{{ $fmt->id }}">{{ $fmt->name }}</option>
              @endforeach
            </select>
            <div class="form-text">
              Defines the rubber sequence (singles, doubles, mixed, etc.) for each tie.
            </div>
          </div>
          @endif

        </div>

        <div id="teamDrawPreview" class="px-4 pb-3 d-none" aria-live="polite"></div>

        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" id="previewTeamDrawButton" class="btn btn-outline-primary d-none">Preview team ties</button>
          <button type="submit" class="btn btn-primary">Create Draw</button>
        </div>

      </form>
    </div>
  </div>
</div>

<!-- Single Venues Modal (centralized to avoid duplicates / flicker) -->
<div class="modal fade" id="venuesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <form id="venuesForm" method="POST">
      @csrf
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Assign Venues</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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

<script>
  // Expose venues to legacy scripts that expect ALL_VENUES
  window.ALL_VENUES = window.HeadOffice?.venues || @json($allVenues ?? []);

  // Remove any other legacy venuesModal instances that might still be present
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#venuesModal').forEach(function (el, idx) {
      // Keep the first one, remove extras
      if (idx > 0) el.remove();
    });
  });

</script>

@endsection

