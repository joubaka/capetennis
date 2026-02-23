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
  // Pass server-side data to the compiled script
  window.HeadOffice = {
    venues: @json($venues),
    previewUrl: "{{ route('headoffice.previewTeamDraw', $event) }}",
    createUrl: "{{ route('headoffice.createSingleDraw.team', $event) }}",
    backendDrawVenuesStoreTemplate: @json(route('backend.draw.venues.store', ['draw' => '__ID__'])),
    backendDrawVenuesJsonTemplate: @json(route('backend.draw.venues.json', ['draw' => '__ID__'])),
  };

  // Show toastr for session flash messages on page load
  $(function () {
    @if(session('success'))
      toastr.success(@json(session('success')), 'Success');
    @endif

    @if(session('error'))
      toastr.error(@json(session('error')), 'Error');
    @endif

    @if(session('warning'))
      toastr.warning(@json(session('warning')), 'Warning');
    @endif

    @if(session('info'))
      toastr.info(@json(session('info')), 'Info');
    @endif
  });
</script>

<script src="{{ asset(mix('js/headOffice.js')) }}"></script>
@endsection

@section('content')

{{-- #6 — Breadcrumb navigation --}}
<nav aria-label="breadcrumb" class="mb-3">
  <ol class="breadcrumb">
    <li class="breadcrumb-item">
      <a href="{{ route('admin.events.overview', $event) }}">
        <i class="ti ti-arrow-left me-1"></i>Event Dashboard
      </a>
    </li>
    <li class="breadcrumb-item active" aria-current="page">Fixtures HQ</li>
  </ol>
</nav>

<!-- Event Header -->
<div class="card mb-4">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h3 class="mb-0">Fixtures HQ: <span class="text-primary">{{ $event->name }}</span></h3>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDrawModal">
        <i class="ti ti-plus me-1"></i> Create Draw
      </button>

      {{-- Replace Player button (opens replace form scoped to current event) --}}
      <a href="{{ route('backend.team-fixtures.replacePlayerForm', ['event' => $event->id]) }}"
         class="btn btn-outline-warning"
         title="Replace player in remaining fixtures for this event">
        <i class="ti ti-user-x me-1"></i> Replace Player
      </a>

      <button id="generate-fixtures-btn"
              data-url="{{ route('headoffice.createFixtures', $event->id) }}"
              class="btn btn-success">
        <i class="ti ti-bolt me-1"></i> Generate All Fixtures
      </button>
    </div>
  </div>
</div>

{{-- #3 — Full-width layout (sidebar removed) --}}
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <h5 class="mb-0">
      <i class="ti ti-tournament me-1"></i>
      Event Draws
      <span class="badge bg-label-primary ms-1">{{ $event->draws->count() }}</span>
    </h5>
  </div>

  <!-- Loading Overlay -->
  <div id="loading-overlay"
       class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 justify-content-center align-items-center d-none"
       style="z-index: 2000;">
    <div class="spinner-border text-light" role="status" style="width: 4rem; height: 4rem;">
      <span class="visually-hidden">Generating fixtures...</span>
    </div>
    <span class="ms-3 text-white fw-bold fs-5">Generating fixtures... Please wait...</span>
  </div>

  <div class="card-body">
    <div class="list-group">
      @forelse($event->draws as $draw)
        @include('backend.draw._includes.draw_tab_team')
      @empty
        <div class="alert alert-warning mb-0">
          <i class="ti ti-info-circle me-1"></i>
          No draws available yet. Click <strong>Create Draw</strong> to get started.
        </div>
      @endforelse
    </div>
  </div>
</div>

<!-- Modal: Create New Draw -->
<div class="modal fade" id="createDrawModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <form id="createDrawForm">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Create New Draw</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <!-- Step 1: Draw Type -->
          <div class="mb-4">
            <h6 class="fw-bold mb-3">Choose Draw Type</h6>
            <div class="d-flex flex-column gap-2">
              <small class="text-muted fw-bold">Team Formats</small>
              @foreach($teamDrawTypes as $type)
                <label class="switch switch-info">
                  <input type="radio" name="draw_type_id" class="switch-input"
                         value="{{ $type->id }}" data-mixed="{{ $type->is_mixed ? '1' : '0' }}">
                  <span class="switch-toggle-slider"></span>
                  <span class="switch-label">{{ $type->drawTypeName }}</span>
                </label>
              @endforeach
              <small class="text-muted fw-bold mt-2">Individual Formats</small>
              @foreach($individualDrawTypes as $type)
                <label class="switch switch-info">
                  <input type="radio" name="draw_type_id" class="switch-input"
                         value="{{ $type->id }}" data-mixed="{{ $type->is_mixed ? '1' : '0' }}">
                  <span class="switch-toggle-slider"></span>
                  <span class="switch-label">{{ $type->drawTypeName }}</span>
                </label>
              @endforeach
            </div>
          </div>

          <!-- Step 2a: Categories (NOT mixed) -->
          <div id="categorySection" class="mb-4 d-none">
            <h6 class="fw-bold mb-3">Choose Category</h6>
            <div class="d-flex flex-column gap-2">
              @foreach($categories as $cat)
                <label class="switch switch-primary">
                  <input type="radio"
                         name="category_choice"
                         class="switch-input"
                         data-pivot-id="{{ $cat->pivot_id }}"     {{-- category_events.id --}}
                         data-category-id="{{ $cat->category_id }}"> {{-- categories.id (optional debug) --}}
                  <span class="switch-toggle-slider"></span>
                  <span class="switch-label">{{ $cat->name }}</span>
                </label>
              @endforeach
            </div>
          </div>

          <!-- Step 2b: Placeholder for Mixed -->
          <div id="mixedPlaceholder" class="mb-4 d-none">
            <div class="alert alert-info">
              Mixed draw option will be available soon.
            </div>
          </div>

          @php
            $ageGroups = collect($categories)
                          ->map(function($cat) {
                            return preg_replace('/\s+(Boys|Girls)$/i', '', $cat->name);
                          })
                          ->unique()
                          ->values();
          @endphp

          <!-- Step 2c: Special Categories for Type 3 -->
          <div id="type3Categories" class="mb-4 d-none">
            <h6 class="fw-bold mb-3">Choose Age Group</h6>
            <div class="d-flex flex-column gap-2">
              @foreach($ageGroups as $age)
                @php
                  $boys  = $categories->first(fn($c) => $c->name === $age . ' Boys');
                  $girls = $categories->first(fn($c) => $c->name === $age . ' Girls');
                @endphp
                @if($boys && $girls)
                  <label class="switch switch-primary">
                    <input type="radio"
                           name="category_choice"
                           class="switch-input"
                           data-age="{{ $age }}"
                           data-ids='[{{ $boys->pivot_id }},{{ $girls->pivot_id }}]'> {{-- pivot ids --}}
                    <span class="switch-toggle-slider"></span>
                    <span class="switch-label">{{ $age }}</span>
                  </label>
                @endif
              @endforeach
            </div>
          </div>

          <!-- Step 3: Auto-generated Name -->
          <div class="mb-3">
            <label for="drawName" class="form-label fw-bold">Draw Name (Auto)</label>
            <input type="text" id="drawName" name="drawName" class="form-control" readonly>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal: Assign Venues (rendered ONCE) -->
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

@endsection
