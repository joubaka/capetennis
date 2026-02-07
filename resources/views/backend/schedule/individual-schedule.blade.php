@extends('layouts/layoutMaster')

@section('title', 'Individual Schedule – ' . $draw->drawName)

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/jquery.dataTables.min.css"/>
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>
@endsection

@section('vendor-script')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
@endsection

@section('content')
<div class="container-xxl">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="m-0">Individual Schedule — {{ $draw->drawName }}</h4>
    <a href="{{ route('event.tab.draws', $event->id) }}" class="btn btn-secondary btn-sm">Back</a>
  </div>

  {{-- ============================
       AUTO-SCHEDULE PANEL
     ============================ --}}
  <div class="card mb-3">
    <div class="card-body">
      <form id="autoForm" class="row g-2 align-items-end">

        <div class="col-md-3">
          <label class="form-label">Start</label>
          <input type="text" id="start" class="form-control" placeholder="YYYY-MM-DD HH:mm">
        </div>

        <div class="col-md-3">
          <label class="form-label">End</label>
          <input type="text" id="end" class="form-control" placeholder="YYYY-MM-DD HH:mm">
        </div>

        <div class="col-md-2">
          <label class="form-label">Match Duration (min)</label>
          <input type="number" id="duration" class="form-control" value="60" min="15" step="5">
        </div>

        <div class="col-md-2">
          <label class="form-label">Gap (min)</label>
          <input type="number" id="gap" class="form-control" value="0" min="0" step="5">
        </div>

        {{-- ============================
             SCHEDULE MODE
           ============================ --}}
        <div class="col-md-3">
          <label class="form-label">Schedule Mode</label>
          <select id="schedule_mode" class="form-select">
            <option value="stage_only">Stage Only (RR → MAIN → PLATE → CONS)</option>
            <option value="round_only">Round Only (R1 → R2 → R3)</option>
            <option value="stage_round">Stage + Round Filters</option>
          </select>
        </div>

        {{-- ============================
             NEW: FILTER BY STAGE
           ============================ --}}
        <div class="col-md-3">
          <label class="form-label">Filter by Stage (optional)</label>
          <select id="filter_stage" class="form-select" multiple>
            <option value="RR">RR</option>
            <option value="MAIN">MAIN</option>
            <option value="PLATE">PLATE</option>
            <option value="CONS">CONS</option>
          </select>
        </div>

        {{-- ============================
             NEW: FILTER BY ROUND
           ============================ --}}
        <div class="col-md-3">
          <label class="form-label">Filter by Round (optional)</label>
          <select id="filter_round" class="form-select" multiple>
            <option value="1">Round 1</option>
            <option value="2">Round 2</option>
            <option value="3">Round 3</option>
            <option value="4">Round 4</option>
            <option value="5">Round 5</option>
          </select>
        </div>

        {{-- ============================
             VENUES
           ============================ --}}
        <div class="col-12">
          <label class="form-label">Use ONLY these venues (optional)</label>
          <select id="venues" class="form-select" multiple></select>
        </div>

        <div class="col-12 d-flex gap-2 mt-2">
          <button type="button" id="btnAuto" class="btn btn-primary">Auto-Schedule</button>
          <button type="button" id="btnReload" class="btn btn-outline-secondary">Reload</button>

          <button type="button" id="btn-clear-schedule" class="btn btn-outline-danger btn-sm">
            <i class="ti ti-trash"></i> Clear All
          </button>

          <button type="button" id="btn-reset-schedule" class="btn btn-outline-warning btn-sm">
            <i class="ti ti-refresh"></i> Reset Auto Schedule
          </button>
        </div>

      </form>
    </div>
  </div>

  {{-- ============================
       FIXTURE TABLE
     ============================ --}}
  <div class="card">
    <div class="card-body">

      <table id="scheduleTable" class="table table-sm table-striped table-bordered w-100">
        <thead class="table-light text-center">
          <tr>
            <th>#</th>
            <th>Round</th>
            <th>Match</th>
            <th>Stage</th>
            <th>Player 1</th>
            <th>Player 2</th>
            <th>Date/Time</th>
            <th>Venue</th>
            <th>Court</th>
            <th>Status</th>
            <th style="width:120px;">Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>

    </div>
  </div>

</div>

{{-- ============================
     MAIN SCRIPT
   ============================ --}}
<script>
$(function () {
  'use strict';

  const csrf = $('meta[name="csrf-token"]').attr('content');
  const fpOpts = { enableTime: true, dateFormat: "Y-m-d H:i", time_24hr: true };
  let VENUES = [];

  function safeFlatpickr(el) {
    if (!el._flatpickr) flatpickr(el, fpOpts);
  }

  function safeSelect2($el, opts={}) {
    if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
    $el.select2({ width: '100%', ...opts });
  }

  function venueOptionsHtml(selected) {
    return VENUES.map(v =>
      `<option value="${v.id}" ${Number(selected)===Number(v.id)?'selected':''}>${v.name} (${v.num_courts})</option>`
    ).join('');
  }

  function rowToRender(fx) {
    return {
      id: fx.id,
      round: fx.round ?? '',
      match: fx.match_nr ?? '',
      stage: fx.stage ?? '',
      p1: fx.p1,
      p2: fx.p2,

      datetime_html:
        `<input type="text" class="form-control form-control-sm dtp"
          data-id="${fx.id}"
          value="${fx.scheduled_at || ''}">`,

      venue_html:
        `<select class="form-select form-select-sm venue-select"
                 data-id="${fx.id}">
                 ${venueOptionsHtml(fx.venue_id)}
         </select>`,

      court_html:
        `<input type="text" class="form-control form-control-sm court-input"
          data-id="${fx.id}"
          value="${fx.court_label || ''}">`,

      status_html:
        fx.scheduled_at
          ? '<span class="badge bg-success">Scheduled</span>'
          : '<span class="badge bg-secondary">Pending</span>',

      actions_html:
        `<button class="btn btn-sm btn-primary btn-save" data-id="${fx.id}">Save</button>`
    };
  }

  // ---------------------------------------------
  // DATATABLE
  // ---------------------------------------------
  const table = $('#scheduleTable').DataTable({
    ordering: false,
    paging: true,
    searching: true,
    columns: [
      { data: 'id', className:'text-center' },
      { data: 'round', className:'text-center' },
      { data: 'match', className:'text-center' },
      { data: 'stage', className:'text-center' },
      { data: 'p1' },
      { data: 'p2' },
      { data: 'datetime_html' },
      { data: 'venue_html' },
      { data: 'court_html' },
      { data: 'status_html', className:'text-center' },
      { data: 'actions_html', className:'text-center' },
    ],
    drawCallback: function () {
      $('#scheduleTable .dtp').each(function(){ safeFlatpickr(this); });
      $('#scheduleTable .venue-select').each(function(){ safeSelect2($(this)); });
    }
  });

  // ---------------------------------------------
  // LOAD DATA
  // ---------------------------------------------
  function loadData() {
    $.get(`{{ route('backend.individual-schedule.data', $draw->id) }}`)
      .done(res => {
        VENUES = res.venues || [];

        safeSelect2($('#venues').empty(), { placeholder: 'Select venues' });
        VENUES.forEach(v =>
          $('#venues').append(new Option(`${v.name} (${v.num_courts})`, v.id))
        );

        safeSelect2($('#filter_stage'), { placeholder: 'Filter stages' });
        safeSelect2($('#filter_round'), { placeholder: 'Filter rounds' });

        const rows = (res.fixtures || []).map(rowToRender);
        table.clear().rows.add(rows).draw();
      });
  }

  // ---------------------------------------------
  // SAVE MATCH
  // ---------------------------------------------
  $('#scheduleTable').on('click', '.btn-save', function(){
    const id    = $(this).data('id');
    const dt    = $(`.dtp[data-id="${id}"]`).val();
    const venue = $(`.venue-select[data-id="${id}"]`).val();
    const court = $(`.court-input[data-id="${id}"]`).val();

    $.post(`{{ route('backend.individual-schedule.save', $draw->id) }}`, {
      _token: csrf,
      fixture_id: id,
      scheduled_at: dt || null,
      venue_id: venue || null,
      court_label: court || null
    })
    .done(() => { toastr.success('Saved'); loadData(); })
    .fail(() => toastr.error('Save failed'));
  });

  // ---------------------------------------------
  // BUILD PAYLOAD
  // ---------------------------------------------
  function buildPayload() {
    return {
      _token: csrf,
      start: $('#start').val(),
      end: $('#end').val(),
      duration: $('#duration').val(),
      gap: $('#gap').val(),
      venues: $('#venues').val() || [],
      schedule_mode: $('#schedule_mode').val(),
      stages: $('#filter_stage').val() || [],
      rounds: $('#filter_round').val() || []
    };
  }

  // ---------------------------------------------
  // AUTO-SCHEDULE
  // ---------------------------------------------
  $('#btnAuto').on('click', function () {
    $.post(`{{ route('backend.individual-schedule.auto', $draw->id) }}`, buildPayload())
      .done(res => {
        toastr.success(`Auto-scheduled ${res.count || 0} matches`);
        loadData();
      })
      .fail(() => toastr.error('Auto schedule failed'));
  });

  // CLEAR
  $('#btn-clear-schedule').on('click', function () {
    if (!confirm('Clear ALL schedules for this draw?')) return;

    $.post(`{{ route('backend.individual-schedule.clear', $draw->id) }}`, {
      _token: csrf
    })
    .done(res => { toastr.success(res.message); loadData(); })
    .fail(() => toastr.error('Failed'));
  });

  // RESET
  $('#btn-reset-schedule').on('click', function () {
    if (!confirm('Reset all and auto-schedule again?')) return;
    $.post(`{{ route('backend.individual-schedule.reset', $draw->id) }}`, buildPayload())
      .done(() => { toastr.success('Auto schedule complete'); loadData(); })
      .fail(() => toastr.error('Failed'));
  });

  $('#btnReload').on('click', loadData);

  // INIT
  flatpickr('#start', fpOpts);
  flatpickr('#end', fpOpts);
  loadData();

});
</script>
@endsection
