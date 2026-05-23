@extends('layouts/layoutMaster')

@section('title', 'Event Not Available')

@section('page-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/css/pages/page-misc.css') }}">
@endsection

@section('content')
<div class="container-xxl container-p-y">
  <div class="misc-wrapper text-center py-5">

    <div class="mb-4">
      <i class="ti ti-calendar-off" style="font-size: 5rem; color: #a0aec0;"></i>
    </div>

    <h2 class="mb-2">Event Not Available</h2>

    <p class="mb-1 text-muted">
      @if(isset($event) && $event->name)
        <strong>{{ $event->name }}</strong> is not publicly available yet.
      @else
        This event is not publicly available yet.
      @endif
    </p>

    <p class="mb-4 text-muted">
      It may still be in preparation. Please check back later or contact the organiser.
    </p>

    <a href="{{ url('/') }}" class="btn btn-primary">
      <i class="ti ti-home me-1"></i> Back to Home
    </a>

  </div>
</div>
@endsection
