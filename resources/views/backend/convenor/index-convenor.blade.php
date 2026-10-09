@php
$configData = Helper::appClasses();
@endphp

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
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/typography.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/katex.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
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
<script src="{{asset('assets/vendor/libs/quill/katex.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sortablejs/sortable.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@endsection

@section('page-script')

<script src="{{asset('assets/js/draw-show.js')}}"></script>
<script src="{{asset('assets/js/head-office.js')}}"></script>
<script src="{{asset('assets/js/my-functions.js')}}"></script>
@endsection



@section('content')
@isset($event)
  @include('backend.event.partials.header', [
    'eventWorkspaceActive' => 'directors',
    'eventWorkspaceIcon' => 'ti-users',
    'eventWorkspaceSubtitle' => 'Event directors and operational access',
  ])
@endisset
<div class="director-admin">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h4 class="mb-1">Event directors</h4><p class="text-muted mb-0">{{ ($convenors ?? collect())->count() }} assignments. Access depends on the saved dates and each account's permissions.</p></div>
    @isset($event) @can('event.settings.manage', $event)
      <a href="{{ route('admin.events.settings', $event) }}#event-directors" class="btn btn-primary">Add or edit directors</a>
    @endcan @endisset
  </div>
  <div class="card"><div class="card-body">
    <h5>Assigned directors and access dates</h5>
    <div class="row g-3">
    @forelse(($convenors ?? collect()) as $convenor)
      <div class="col-md-6"><div class="border rounded p-3 h-100">
        <div class="d-flex flex-wrap justify-content-between gap-2"><strong>{{ $convenor->user?->name ?? 'User unavailable' }}</strong><span class="badge {{ $convenor->isActive() ? 'bg-label-success' : 'bg-label-warning' }}">{{ $convenor->isActive() ? 'Within access dates' : ($convenor->starts_at?->isFuture() ? 'Starts later' : 'Expired') }}</span></div>
        <dl class="mt-3 mb-0"><dt>Starts</dt><dd>{{ $convenor->starts_at?->format('d M Y H:i') ?? 'No start limit' }}</dd><dt>Expires</dt><dd>{{ $convenor->expires_at?->format('d M Y H:i') ?? 'No expiry saved' }}</dd><dt>Assignment</dt><dd class="mb-0">{{ $convenor->isHoof() ? 'Lead director' : ($convenor->isHulp() ? 'Supporting director' : 'Event director') }}</dd></dl>
      </div></div>
    @empty
      <div class="col-12"><x-backend.empty-state title="No event directors assigned" description="Use event settings to select directors and their access dates." icon="ti-users" /></div>
    @endforelse
    </div>
  </div></div>
  <details class="card mt-3"><summary class="card-header">Responsibilities and access</summary><div class="card-body"><p class="mb-0">Directors support event operations. Select staff and adjust their access dates in event settings. This list shows recorded assignments; it does not grant new account permissions or change financial allocations.</p></div></details>
</div>
<style>.director-admin :is(.btn,summary) { min-height:44px !important; } .director-admin summary { cursor:pointer; align-content:center; } .director-admin :is(strong,dd) { overflow-wrap:anywhere; } .director-admin :focus-visible { outline:3px solid #117a72; outline-offset:2px; }</style>

@endsection
