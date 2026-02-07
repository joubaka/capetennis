@php
  $configData = Helper::appClasses();
@endphp

@extends('layouts/layoutMaster')

@section('title', 'Home')

{{-- Vendor Styles --}}
@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/typography.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/katex.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/quill/editor.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/spinkit/spinkit.css') }}" />
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}" />
@endsection

{{-- Vendor Scripts --}}
@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/quill/katex.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/quill/quill.js') }}"></script>
@endsection

{{-- Page Scripts --}}
@section('page-script')
  <script src="{{ asset('assets/js/forms-selects.js') }}"></script>
  <script src="{{ asset('assets/js/select2-search-addon.js') }}"></script>
@endsection

@section('content')
<div class="row">

  {{-- LEFT COLUMN --}}
  <div class="col-md-8">
    <div class="form-group">

      <div class="row align-items-center">
        {{-- PERIOD SWITCHES --}}
        <div class="col-md-6 p-4">
          <div class="switches time_period">
            <label class="switch switch-square">
              <input type="radio" class="switch-input" name="period" value="upcoming" checked>
              <span class="switch-toggle-slider"></span>
              <span class="switch-label">Upcoming Events</span>
            </label>

            <label class="switch switch-square">
              <input type="radio" class="switch-input" name="period" value="past">
              <span class="switch-toggle-slider"></span>
              <span class="switch-label">Past Events</span>
            </label>

            <label class="switch switch-square">
              <input type="radio" class="switch-input" name="period" value="all">
              <span class="switch-toggle-slider"></span>
              <span class="switch-label">All Events</span>
            </label>
          </div>
        </div>

        {{-- SEARCH --}}
        <div class="col-md-6 p-4">
          <input
            type="text"
            id="eventSearch"
            class="form-control"
            placeholder="Search events by name..."
          />
        </div>
      </div>

   

      {{-- EVENTS CONTAINER --}}
      <div id="test">
        <div class="spinner-border d-none" role="status" id="spinner1">
          <span class="visually-hidden">Loading...</span>
        </div>
      </div>

    </div>
  </div>

  {{-- SERIES RANKINGS --}}
  <div class="col-md-4">
    <h2 class="fw-semibold">Series Rankings</h2>
    <div class="list-group mt-3">
      @foreach ($series as $value)
        @if ($value->leaderboard_published)
          <a href="{{ route('rankings.results', $value->id) }}"
             class="list-group-item list-group-item-action d-flex align-items-center p-3">
            <div class="badge bg-primary me-3">
              <i class="ti ti-clipboard ti-xl"></i>
            </div>
            <h6 class="mb-0">{{ $value->name }}</h6>
          </a>
        @endif
      @endforeach
    </div>
  </div>

</div>

@include('templates.homeEventTemplate')
@include('_partials._modals.modal-add-event')

<script>
'use strict';

$(function () {

  const getEvents = APP_URL + '/home/get_events';
  const showEvent = APP_URL + '/events/';
  const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };

  let searchTimer = null;

  function renderEvent(event) {
    if (!event || !event.start_date) return;

    const startDate = new Date(event.start_date);
    const endDate   = event.end_date ? new Date(event.end_date) : null;

    const deadlineDate = new Date(startDate);
    if (event.deadline !== null) {
      deadlineDate.setDate(startDate.getDate() - parseInt(event.deadline, 10));
    }

    const img = event.logo
      ? `<img src="${APP_URL}/assets/img/logos/${event.logo}"
              height="120" width="120"
              style="margin:5px;border-radius:15px" />`
      : '';

    const card = $('#eventInfo').clone().removeClass('d-none');

    card.find('.eventName')
      .text(event.name)
      .attr('href', showEvent + event.id)
      .addClass('text-white');

    card.find('.start_date').text(startDate.toLocaleDateString('en-US', dateOptions));
    card.find('.end_date').text(endDate ? endDate.toLocaleDateString('en-US', dateOptions) : '—');
    card.find('.deadline').text(event.deadline !== null ? deadlineDate.toLocaleDateString('en-US', dateOptions) : '—');
    card.find('.logo').html(img);
    card.find('.buttons').html(
      `<a href="${showEvent + event.id}" class="btn btn-label-success">More Information</a>`
    );

    $('#test').append(card);
  }

  function loadEvents() {
    const period = $('.time_period input:checked').val();
    const search = $('#eventSearch').val();

    $('#test').empty();
    $('#spinner1').removeClass('d-none');

    $.ajax({
      url: getEvents,
      data: { period, search },
      success: function (data) {
        $('#spinner1').addClass('d-none');
        if (Array.isArray(data)) {
          data.forEach(renderEvent);
        }
      },
      error: function () {
        $('#spinner1').addClass('d-none');
        alert('Error loading events');
      }
    });
  }

  /* PERIOD CHANGE */
  $('.time_period').on('change', loadEvents);

  /* SEARCH (DEBOUNCED) */
  $('#eventSearch').on('keyup', function () {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadEvents, 300);
  });

  /* INITIAL LOAD */
  loadEvents();

});
</script>
@endsection
