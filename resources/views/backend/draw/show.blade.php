@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Draw – '.$draw->name)

{{-- ============ VENDOR CSS ============ --}}
@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}">
@endsection

{{-- ============ PAGE CSS ============ --}}
@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-user-view.css')}}">
@endsection


{{-- ============ VENDOR JS ============ --}}
@section('vendor-script')
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/svg.js/3.2.0/svg.min.js"></script>
@endsection


{{-- ============ PAGE JS ============ --}}
@section('page-script')
<script src="{{asset('assets/js/draw-show.js')}}"></script>
<script src="{{asset('assets/js/schedule.js')}}"></script>
@endsection


@section('content')
<input type="hidden" id="drawId" value="{{ $draw->id }}">

<script>
window.scheduleRoutes = {
    data  : "{{ route('backend.draw.schedule.index', $draw->id) }}",
    apply : "{{ route('backend.draw.schedule.apply', $draw->id) }}",
    auto  : "{{ route('backend.draw.schedule.auto', $draw->id) }}",
    clear : "{{ route('backend.draw.schedule.clear', $draw->id) }}"
};
</script>



{{-- ============ PAGE HEADER ============ --}}
<div class="card-header mb-3">
    <h3 class="text-center">{{ $draw->name }}</h3>
    <h6 class="text-center text-muted">{{ $draw->event->name }}</h6>
</div>


{{-- ============ TAB NAVIGATION ============ --}}
<ul class="nav nav-tabs mb-3" id="drawTabs">
  <li class="nav-item">
    <a class="nav-link active" data-bs-toggle="tab" href="#fixturesTab">Fixtures</a>
  </li>

  <li class="nav-item">
    <a class="nav-link" data-bs-toggle="tab" href="#scheduleTab">Schedule</a>
  </li>

@if($draw->drawType->type === 'individual')
  <li class="nav-item">
    <a class="nav-link" data-bs-toggle="tab" href="#bracketTab">Bracket</a>
  </li>
@endif

@if($draw->drawType->is_round_robin)
  <li class="nav-item">
    <a class="nav-link" data-bs-toggle="tab" href="#roundRobinTab">Round Robin</a>
  </li>
@endif
</ul>


{{-- ============ TAB CONTENT ============ --}}
<div class="tab-content">

  {{-- FIXTURES TAB --}}
  <div class="tab-pane fade show active" id="fixturesTab">
      @include('backend.fixture.fixture-table-admin', ['draw' => $draw])
  </div>

  {{-- SCHEDULE TAB --}}
  <div class="tab-pane fade" id="scheduleTab">
      <div class="d-flex justify-content-end mb-2">
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
            Schedule Matches
          </button>
      </div>

      @include('backend.schedule.schedule-table') {{-- your schedule datatable --}}
  </div>

  {{-- BRACKET TAB --}}
  @if($draw->drawType->type === 'individual')
  <div class="tab-pane fade" id="bracketTab">
      <div id="bracketContainer"></div>
  </div>
  @endif

  {{-- ROUND ROBIN TAB --}}
  @if($draw->drawType->is_round_robin)
  <div class="tab-pane fade" id="roundRobinTab">
      <div id="rrMatrixContainer"></div>
  </div>
  @endif

</div>


{{-- ============ SCHEDULING MODAL ============ --}}
@include('backend.schedule._modal')


@endsection
