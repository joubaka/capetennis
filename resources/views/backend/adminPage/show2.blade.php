@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Admin - Event Page')

{{-- Vendor CSS --}}
@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/katex.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}" />
@endsection

{{-- Page CSS --}}
@section('page-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-user-view.css') }}" />
@endsection

{{-- Vendor JS --}}
@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/quill/katex.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/sortablejs/sortable.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

{{-- Page JS --}}
@section('page-script')
  <script src="{{ asset('assets/js/admin-showver3.js') }}"></script>
  <script src="{{ asset('assets/js/draw.js') }}"></script>
  <script src="{{ asset('assets/js/app-email.js') }}"></script>
  <script src="{{ asset('assets/js/ui-toasts.js') }}"></script>
  <script src="{{ asset('assets/js/extended-ui-drag-and-drop.js') }}"></script>
  {{-- Note: menu.js is typically loaded in the base layout. Remove if duplicated there. --}}
  {{-- <script src="{{ asset('assets/vendor/js/menu.js') }}"></script> --}}
@endsection

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('content')

  @switch($event->eventType)
    @case(3) {{-- Team event --}}
      @include('backend.adminPage.admin_show.team_show')
      @break

    @case(5) {{-- Cavaliers trials --}}
      @include('backend.adminPage.admin_show.cavaliers_trials_show')
      @break

    @case(6) {{-- Overberg trials (individual) --}}
      @include('backend.adminPage.admin_show.individual_show')
      @break

    @case(10) {{-- Admin tournament --}}
      @include('backend.adminPage.admin_show.admin_tournament_show')
      @break

    @case(11) {{-- Overberg Trials Admin --}}
      @include('backend.adminPage.admin_show.overbergTrialsAdmin')
      @break

    @default
      {{-- Fallback to team_show (or a neutral "unknown type" partial) --}}
      @include('backend.adminPage.admin_show.team_show')
  @endswitch

  {{-- Modals --}}
  @include('_partials._modals.modal-add-team')
  @include('_partials._modals.modal-add-region')
  @include('_partials._modals.modal-add-send-email')
  @include('_partials._modals.modal-add-registration')
  @include('_partials._modals.modal-player-in-team')
  @include('_partials._modals.modal-edit-team-category')
  @include('_partials._modals.add-category-modal')

@endsection

<script>
  // Prefer roles/permissions over hard-coded user IDs
  @php
    $isAdmin = method_exists(auth()->user(), 'hasRole')
      ? auth()->user()->hasRole('admin') || auth()->user()->hasRole('super-admin')
      : (bool) (auth()->user()->is_admin ?? false);
  @endphp
  const isAdmin = @json($isAdmin);
  console.log('isAdmin:', isAdmin);
</script>
