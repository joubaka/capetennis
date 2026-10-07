@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts.backend')

@section('title', 'Admin - Event Page')

{{-- Vendor CSS --}}
@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
  @if((int) $event->eventType !== 3)
    <link rel="stylesheet" href="{{ asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css') }}">
  @endif
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/katex.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
@endsection

@section('page-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-user-view.css') }}" />
  @if((int) $event->eventType === 3)
    <link rel="stylesheet" href="{{ asset('css/team-workspace.css') }}?v={{ filemtime(public_path('css/team-workspace.css')) }}">
  @endif
@endsection

{{-- Vendor JS --}}
@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/moment/moment.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/cleavejs/cleave.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/cleavejs/cleave-phone.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
  @if((int) $event->eventType !== 3)
    <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js') }}"></script>
  @endif
  <script src="{{ asset('assets/vendor/libs/quill/katex.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/sortablejs/sortable.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection



@section('content')
@if((int) $event->eventType !== 3)
<div class="mb-3"><a class="btn btn-outline-primary" href="{{ route('backend.event-mail-log.index',$event) }}">Email log</a></div>
@endif
  @include('backend.event.partials.header')



  @switch($event->eventType)
    @case(3)  {{-- Team event --}}
      @include('backend.adminPage.admin_show.team-workspace')
      @break

    @case(5)  {{-- Cavaliers trials --}}
      @include('backend.adminPage.admin_show.cavaliers_trials_show')
      @break

    @case(6)  {{-- Overberg trials (individual) --}}
      @include('backend.adminPage.admin_show.individual_show')
      @break

    @case(10) {{-- Admin tournament --}}
      @include('backend.adminPage.admin_show.admin_tournament_show')
      @break

    @case(11) {{-- Overberg trials admin --}}
      @include('backend.adminPage.admin_show.overbergTrialsAdmin')
      @break
  @case(12) {{-- Overberg trials admin --}}
      @include('backend.adminPage.admin_show.schools')
      @break
 @case(13) {{-- Overberg trials admin --}}

      @include('backend.adminPage.admin_show.interpro-dash')
      @break
    @default
      <div class="alert alert-warning">Unknown event type: {{ $event->eventType }}</div>
  @endswitch

  @include('_partials._modals.modal-add-team')
  @include('_partials._modals.modal-add-region')
  @include('_partials._modals.modal-add-send-email')
  @if((int) $event->eventType !== 3)
    @include('_partials._modals.modal-add-registration')
    @include('_partials._modals.modal-player-in-team')
    @include('_partials._modals.modal-edit-team-category')
  @endif
  @if((int) $event->eventType !== 3)
    @include('_partials._modals.add-category-modal')
  @endif

@endsection
@section('page-script')

{{-- Cache-bust JS --}}
<script>window.APP_URL = @json(url('/'));</script>
<script src="{{ asset(mix('js/regions.js')) }}"></script>
<script src="{{ asset(mix('js/categories.js')) }}"></script>
<script src="{{ asset('js/players.js') }}?v={{ filemtime(public_path('js/players.js')) }}"></script>
<script src="{{ asset(mix('js/playerOrder.js')) }}"></script>
@if((int) $event->eventType === 3)
<script src="{{ asset('js/team-workspace.js') }}?v={{ filemtime(public_path('js/team-workspace.js')) }}"></script>
@endif
<script src="{{ asset('assets/js/draw.js') }}?v={{ filemtime(public_path('assets/js/draw.js')) }}"></script>
<script src="{{ asset('assets/js/app-email.js') }}?v={{ filemtime(public_path('assets/js/app-email.js')) }}"></script>
<script src="{{ asset('assets/js/ui-toasts.js') }}?v={{ filemtime(public_path('assets/js/ui-toasts.js')) }}"></script>
<script src="{{ asset('assets/js/extended-ui-drag-and-drop.js') }}?v={{ filemtime(public_path('assets/js/extended-ui-drag-and-drop.js')) }}"></script>
<script src="{{ asset('assets/vendor/js/menu.js') }}?v={{ filemtime(public_path('assets/vendor/js/menu.js')) }}"></script>

@can('admin')
  <script>window.isAdmin = true;</script>
@else
  <script>window.isAdmin = false;</script>
@endcan
<script>
window.routes = {
    changePayStatus: "{{ route('team.change.pay.status') }}",
    replacePlayer: "{{ route('backend.team.replace.player') }}",
  replaceForm: "{{ route('backend.team.player.replace.form') }}",
 addRegionToEvent: "{{ route('eventRegion.store') }}"
};

  window.routes = window.routes || {};
  window.routes.addRegionToEvent = "{{ route('eventRegion.store') }}";
</script>



@endsection

