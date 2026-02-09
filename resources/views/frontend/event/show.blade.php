@extends('layouts/layoutMaster')

@section('title', 'Event Details')

{{-- ================= VENDOR CSS ================= --}}
@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/datatables-checkboxes-jquery/datatables.checkboxes.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/katex.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/spinkit/spinkit.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/animate-css/animate.css') }}">
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection

{{-- ================= PAGE CSS ================= --}}
@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-profile.css') }}">
@endsection

{{-- ================= VENDOR JS ================= --}}
@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/pickr/pickr.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/quill/katex.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

{{-- ================= PAGE JS ================= --}}
@section('page-script')
<script src="{{ asset('assets/js/pages-profile.js') }}"></script>
<script src="{{ asset('assets/js/forms-editors.js') }}"></script>
<script src="{{ asset('assets/js/select2-search-addon.js') }}"></script>
<script src="{{ asset('assets/js/event-show-front-end-final.js') }}"></script>

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

{{-- ================= HEADER ================= --}}
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
            class="rounded user-profile-img"
            alt="{{ $event->name }} logo">
        </div>

        <div class="flex-grow-1 mt-3 mt-sm-5">
          <div class="mx-4">
            <h4>{{ $event->name }}</h4>

            <ul class="list-inline d-flex gap-2 flex-wrap">
              @if($event->isIndividual())
                <li class="badge bg-label-success">
                  <i class="ti ti-users"></i>
                  Total Entries: {{ $event->registrations->count() }}
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

{{-- ================= NAVBAR ================= --}}
<div class="row">
  <div class="col-12">
    <ul class="nav nav-pills flex-column flex-sm-row mb-4 align-items-center">

@if($signUp === 'open' && $event->isIndividual())
  <a class="btn btn-success btn-sm m-2" href="{{ route('register.register',$event->id) }}">
    <i class="ti ti-user-check"></i> Sign Up
  </a>
@endif
     
{{-- ================= USER REGISTRATIONS ================= --}}
@foreach($userRegistrations as $registration)

  @php
    $canWithdraw = auth()->check()
      ? $registration->canWithdraw(auth()->user())
      : ['ok' => false];
  @endphp

{{-- Withdraw --}}
@if($canWithdraw['ok'])
  <form method="POST"
        action="{{ route('registrations.withdraw', $registration) }}"
        class="d-inline">
    @csrf
    <button class="btn btn-warning btn-sm m-1">
      <i class="ti ti-x"></i>
      Withdraw {{ $registration->display_name }}
    </button>
  </form>
@endif
{{-- Choose refund method --}}
@if(
  $registration->status === 'withdrawn' &&
  is_null($registration->refund_method) &&
  ($canWithdraw['refund_allowed'] ?? false)
)
  <a href="{{ route('registrations.refund.choose', $registration) }}"
     class="btn btn-info btn-sm m-1">
    <i class="ti ti-cash"></i>
    Choose refund for {{ $registration->display_name }}
  </a>
@endif


  {{-- Process wallet refund --}}
  @if(
    $registration->refund_status === 'pending' &&
    $registration->refund_method === 'wallet'
  )
    <form method="POST"
          action="{{ route('registrations.refund.process', $registration) }}"
          class="d-inline">
      @csrf
      <button class="btn btn-success btn-sm m-1">
        <i class="ti ti-wallet"></i>
        Refund {{ $registration->display_name }} to Wallet
      </button>
    </form>
  @endif

@endforeach

{{-- ================= ADMIN ================= --}}
@if(auth()->check() && (
  auth()->user()->hasRole('super-user') ||
  $event->admins->contains(auth()->id())
))
  <a class="btn btn-secondary m-2" href="{{ route('admin.events.overview',$event) }}">
    <i class="ti ti-shield ti-xs"></i> Administrator
  </a>
@endif

    </ul>
  </div>
</div>

{{-- ================= EVENT CONTENT ================= --}}
@switch($event->eventType)
  @case(5)  @include('frontend.event.eventTypes.cavaliers_trials') @break
  @case(6)  @include('frontend.event.eventTypes.individual') @break
  @case(9)  @include('frontend.event.eventTypes.parentChildDoubles') @break
  @case(3)
  @case(7)  @include('frontend.event.eventTypes.team') @break
  @case(13) @include('frontend.event.eventTypes.interpro') @break
@endswitch

{{-- ================= TOASTS ================= --}}
@if(session('success'))
<script>
document.addEventListener('DOMContentLoaded', () => {
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'success',
    title: @json(session('success')),
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true
  });
});
</script>
@endif

@if($errors->any())
<script>
document.addEventListener('DOMContentLoaded', () => {
  Swal.fire({
    toast: true,
    position: 'top-end',
    icon: 'error',
    title: @json($errors->first()),
    showConfirmButton: false,
    timer: 5000,
    timerProgressBar: true
  });
});
</script>
@endif

@endsection
