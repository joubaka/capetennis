@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Admin - Main Page')

{{-- Vendor CSS --}}
@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
@endsection

{{-- Page-specific CSS --}}
@section('page-style')
@endsection

{{-- Vendor JS --}}
@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
@endsection

{{-- Page-specific JS --}}
@section('page-script')
    <script src="{{ asset('assets/js/admin-main.js') }}"></script>
@endsection

<meta name="csrf-token" content="{{ csrf_token() }}">

@section('content')
<div class="card">
  <div class="card-header event-header">
    <h3 class="text-center">{{ $event->name }}</h3>
  </div>

  <div class="card-body px-4">
    {{-- Tab Navigation --}}
    <ul class="nav nav-tabs mb-3" role="tablist">
      <li class="nav-item">
        <a class="nav-link active ajax-tab" data-url="{{ route('event.tab.draws', $event->id) }}" href="#drawsTab" data-bs-toggle="tab" role="tab">Draws</a>
      </li>
      {{-- <li class="nav-item">
        <a class="nav-link  ajax-tab" data-url="{{ route('event.tab.entries', $event->id) }}" href="#entriesTab" data-bs-toggle="tab" role="tab">Entries</a>
      </li>

      <li class="nav-item">
        <a class="nav-link ajax-tab" data-url="{{ route('event.tab.results', $event->id) }}" href="#resultsTab" data-bs-toggle="tab" role="tab">Results</a>
      </li>
      <li class="nav-item">
        <a class="nav-link ajax-tab" data-url="{{ route('event.tab.settings', $event->id) }}" href="#settingsTab" data-bs-toggle="tab" role="tab">Settings</a>
      </li> --}}
    </ul>

    <div class="tab-content">
      <div class="tab-pane fade" id="entriesTab" role="tabpanel"></div>
      <div class="tab-pane fade show active" id="drawsTab" role="tabpanel"></div>
      <div class="tab-pane fade" id="resultsTab" role="tabpanel"></div>
      <div class="tab-pane fade" id="settingsTab" role="tabpanel"></div>
    </div>



  </div>
</div>



@endsection


