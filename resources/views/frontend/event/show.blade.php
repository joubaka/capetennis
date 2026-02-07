@extends('layouts/layoutMaster')

@section('title', 'Event Details')

{{-- Vendor CSS --}}
@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/katex.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/spinkit/spinkit.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
@endsection

{{-- Page CSS --}}
@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-profile.css') }}" />
@endsection

{{-- Vendor JS --}}
@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/pickr/pickr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/quill/katex.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

{{-- Page JS --}}
@section('page-script')
<script src="{{ asset('assets/js/pages-profile.js') }}"></script>
<script src="{{ asset('assets/js/forms-editors.js') }}"></script>
<script src="{{ asset('assets/js/select2-search-addon.js') }}"></script>
<script src="{{ asset('assets/js/event-show-front-end-ver5.js') }}"></script>



<script>
  window.auth = {
    loggedIn: @json(auth()->check()),
    loginUrl: @json(route('login'))
  };
</script>
@endsection

@section('content')

<input type="hidden" id="event_id" value="{{ $event->id }}">
<script>
  window.APP_URL = @json(url('/'));
  window.EVENT_ID = @json($event->id);
</script>

<!-- Header -->
<div class="row">
  <div class="col-12">
    <div class="card mb-4">
      <div class="user-profile-header-banner">
        <img src="{{ asset('assets/img/pages/profile-banner.png') }}" class="rounded-top">
      </div>

      <div class="user-profile-header d-flex flex-column flex-sm-row mb-4">
        <div class="flex-shrink-0 mt-n2 mx-sm-0 mx-auto">
          <img
            src="{{ $event->logo ? asset('assets/img/logos/'.$event->logo) : asset('assets/img/misc/placeholder-logo.png') }}"
            alt="{{ $event->name }} logo"
            class="rounded user-profile-img">
        </div>

        <div class="flex-grow-1 mt-3 mt-sm-5">
          <div class="d-flex justify-content-between mx-4 flex-column gap-2">
            <div>
              <h4>{{ $event->name }}</h4>

              <ul class="list-inline d-flex gap-2 flex-wrap">

                @can('super-user')
                <li class="list-inline-item">
                  <i class="ti ti-color-swatch"></i>
                  {{ $event->eventTypeModel?->name ?? 'Unknown Type' }}
                  — id: {{ $event->eventTypeModel?->id ?? '-' }}
                </li>
                @endcan

                @if($event->isIndividual())
                  <li class="badge bg-label-success">
                    <i class="ti ti-users"></i>
                    Total Entries: {{ $event->registrations->count() }}
                  </li>
                @elseif($event->isTeam())
                  <li class="badge bg-label-success">
                    <i class="ti ti-users"></i>
                    Total Entries: {{ $teamRegs->count() > 400 ? '' : $teamRegs->count() }}
                  </li>
                @endif

                <li class="list-inline-item">
                  <i class="ti ti-map-pin"></i> {{ $event->venues }}
                </li>

              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!--/ Header -->

<!-- Navbar -->
<div class="row">
  <div class="col-12">
    <ul class="nav nav-pills flex-column flex-sm-row mb-4">
@if($signUp === 'open')
  @if($event->isIndividual())
    <a class="btn btn-success btn-sm m-2" href="{{ route('register.register',$event->id) }}">
      <i class="ti ti-user-check"></i> Sign Up
    </a>
  @elseif($event->isTeam())
    <span class="badge bg-label-danger m-2">
      Register at the bottom of the page
    </span>
  @endif
@endif
     

@if(auth()->check() && (
    auth()->user()->hasRole('super-user') ||
    $event->admins->contains(auth()->id())
))
<li class="nav-item">
  <a class="btn btn-warning m-2" href="{{ route('admin.events.overview',$event) }}">
    <i class="ti ti-shield ti-xs me-1"></i> Administrator
  </a>
</li>
@endif
@if(auth()->check() && (
    auth()->user()->hasRole('super-user') ||
    $event->admins->contains(auth()->id())
))

@endif





    </ul>
  </div>
</div>
<!--/ Navbar -->

<!-- Event Content -->
@switch($event->eventType)

  @case(5)
    @include('frontend.event.eventTypes.cavaliers_trials')
    @break

  @case(6)
    @include('frontend.event.eventTypes.individual')
    @break

  @case(9)
    @include('frontend.event.eventTypes.parentChildDoubles')
    @break

  @case(3)
  @case(7)
    @include('frontend.event.eventTypes.team')
    @break

  @case(13)
    @include('frontend.event.eventTypes.interpro')
    @break

  @default
    <p>No event type view found.</p>

@endswitch
<!--/ Event Content -->

@include('_partials._modals.modal-add-announcement')
@include('_partials._modals.modal-edit-event')
@include('_partials._modals.modal-add-player')
@include('_partials._modals.modal-add-upload-file')
<script>
  window.App = {
    user: @json(Auth::user()),
    eventCategories: @json($eventCats),
    administrators: @json($administrators)
  };
</script>
@endsection
