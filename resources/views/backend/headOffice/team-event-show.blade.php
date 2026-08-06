@extends('layouts/layoutMaster')

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
@endsection


@section('content')

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
  <div class="d-flex flex-column justify-content-center">
    <h4 class="mb-1 mt-3">Fixtures HQ</h4>
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb breadcrumb-style1 mb-0">
        <li class="breadcrumb-item">
          <a href="{{ route('admin.events.overview', $event) }}">Event Dashboard</a>
        </li>
        <li class="breadcrumb-item active">Fixtures HQ</li>
      </ol>
    </nav>
  </div>
  <div class="d-flex align-content-center flex-wrap gap-3 mt-3 mt-md-0">
    <button class="btn btn-primary" id="createNewDrawBtn" data-bs-toggle="modal" data-bs-target="#createDrawModal">
      <i class="ti ti-plus me-1"></i> Create New Draw
    </button>
  </div>
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
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Manage Draws</h5>
        <small class="text-muted">Click a draw to view details</small>
      </div>

      <div class="card-body pt-0">
        <div class="list-group list-group-flush">
          @forelse($event->draws as $draw)
            <div class="list-group-item list-group-item-action d-flex align-items-center py-3">
              <div class="flex-grow-1">
                <div class="d-flex align-items-center mb-1">
                  <h6 class="mb-0 me-2">@include('backend.draw._includes.draw_tab_team')</h6>
                  @if($draw->is_published)
                    <span class="badge badge-dot bg-primary" title="Published"></span>
                  @elseif($draw->is_done)
                    <span class="badge badge-dot bg-success" title="Completed"></span>
                  @else
                    <span class="badge badge-dot bg-warning" title="Draft"></span>
                  @endif
                </div>
                <div class="text-muted small">
                   <span class="me-2"><i class="ti ti-calendar-event ti-xs"></i> {{ $draw->created_at->format('d M, Y') }}</span>
                   @if($draw->is_scheduled) <span class="text-info">| Scheduled</span> @endif
                </div>
              </div>

            
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

@endsection

@section('modals')

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
          <div class="mb-3">
            <label for="drawName" class="form-label fw-bold">Draw Name</label>
            <input type="text" id="drawName" name="drawName" class="form-control"
                   placeholder="e.g. U14 Boys – Round Robin" required>
          </div>

          {{-- Draw Type --}}
          <div class="mb-3">
            <label class="form-label fw-bold">Draw Type</label>
            <div class="d-flex flex-wrap gap-2">
              @foreach($teamDrawTypes as $drawType)
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio"
                         name="draw_type_id"
                         id="drawType{{ $drawType->id }}"
                         value="{{ $drawType->id }}">
                  <label class="form-check-label" for="drawType{{ $drawType->id }}">
                    {{ $drawType->drawTypeName }}
                  </label>
                </div>
              @endforeach
            </div>
          </div>

          @php
            $standardCategories = [];
            $mixedCategoryGroups = [];

            foreach ($categories as $cat) {
              $catName = trim($cat->name);
              $catAge = $catName;
              $catGender = null;

              if (preg_match('/^(.*?)(?:\s*[-–]?\s*)(boys|girls|mixed)$/i', $catName, $matches)) {
                $catAge = trim($matches[1]);
                $catGender = strtolower($matches[2]);
              }

              $cat->parsed_age = $catAge;
              $cat->parsed_gender = $catGender;

              if (in_array($catGender, ['boys', 'girls'], true)) {
                $mixedCategoryGroups[$catAge][$catGender][] = $cat;
              } else {
                $standardCategories[] = $cat;
              }
            }
          @endphp

          {{-- Category --}}
          <div class="mb-3" id="categorySection">
            <label class="form-label fw-bold">Category</label>
            <div class="d-flex flex-wrap gap-2">
              @foreach($standardCategories as $cat)
                <div class="form-check form-check-inline">
                  <input class="form-check-input" type="radio"
                         name="category_choice"
                         id="cat{{ $cat->pivot_id }}"
                         value="{{ $cat->pivot_id }}"
                         data-pivot-id="{{ $cat->pivot_id }}"
                         data-age="{{ $cat->parsed_age }}"
                         data-gender="{{ $cat->parsed_gender }}">
                  <label class="form-check-label" for="cat{{ $cat->pivot_id }}">
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

          {{-- Format selection (v2 only, loaded async) --}}
          @if($teamDrawV2Enabled ?? false)
          <div class="mb-3" id="formatSelectGroup">
            <label for="format_id" class="form-label fw-bold">Tie Format <span class="text-muted fw-normal">(optional – attach later)</span></label>
            <select id="format_id" name="format_id" class="form-select">
              <option value="">— Select format —</option>
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

        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
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

