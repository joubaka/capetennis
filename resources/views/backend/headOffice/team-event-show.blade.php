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
    <button class="btn btn-primary" id="createNewDrawBtn">
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

