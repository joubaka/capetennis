@extends('layouts/layoutMaster')

@section('title', 'Cavaliers Trials Schedule – ' . $draw->drawName)

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/jquery.dataTables.min.css"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"/>
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

  <div class="d-flex justify-content-between mb-3">
    <h4>Cavaliers Trials Schedule — {{ $draw->drawName }}</h4>
    <a href="{{ route('event.tab.draws', $event->id) }}" class="btn btn-secondary btn-sm">Back</a>
  </div>

  {{-- ==========================
       AUTO-SCHEDULE PANEL
     ========================== --}}
  <div class="card mb-3">
    <div class="card-body">
      <form id="trialsAutoForm" class="row g-2 align-items-end">

        <div class="col-md-3">
          <label class="form-label">Start</label>
          <input type="text" id="start" class="form-control" placeholder="YYYY-MM-DD HH:mm">
        </div>

        <div class="col-md-3">
          <label class="form-label">Match Duration (min)</label>
          <input type="number" id="duration" class="form-control" value="60" min="10" step="5">
        </div>

        <div class="col-md-2">
          <label class="form-label">Gap (min)</label>
          <input type="number" id="gap" class="form-control" value="0" min="0" step="5">
        </div>

        {{-- NEW: AUTO VENUE --}}
        <div class="col-md-3">
          <label class="form-label">Venue</label>
          <select id="auto_venue" class="form-select"></select>
        </div>

      

        <div class="col-md-4">
          <label class="form-label">Schedule Order</label>
          <select id="schedule_mode" class="form-select">
            <option value="bracket_round_match" selected>Bracket → Round → Match</option>
            <option value="bracket_only">Bracket Only</option>
            <option value="round_only">Round Only</option>
            <option value="match_only">Match Number Only</option>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Filter Bracket (optional)</label>
          <select id="filter_bracket" class="form-select" multiple></select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Filter Round (optional)</label>
          <select id="filter_round" class="form-select" multiple>
            <option value="1">Round 1</option>
            <option value="2">Round 2</option>
            <option value="3">Round 3</option>
            <option value="4">Round 4</option>
          </select>
        </div>

        <div class="col-12 mt-3 d-flex gap-2">
          <button type="button" id="btnAutoSchedule" class="btn btn-primary">Auto-Schedule</button>
           <button type="button" id="btnResetTrials" 
        data-id="{{ $draw->id }}"
        data-route="{{ route('backend.trials.reset', $draw->id) }}"
        class="btn btn-danger">
    Reset Schedule
</button>

          <button type="button" id="btnReload" class="btn btn-secondary">Reload</button>
        </div>

      </form>
    </div>
  </div>

  {{-- ==========================
       FIXTURE TABLE
     ========================== --}}
  <div class="card">
    <div class="card-body">

      <table id="trialsTable" class="table table-sm table-striped table-bordered w-100">
        <thead class="table-light text-center">
          <tr>
            <th>#</th>
            <th>Bracket</th>
            <th>Round</th>
            <th>Match</th>
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
@endsection

@section('page-script')
<script>
$(document).ready(function () {
  'use strict';
    // RESET TRIALS — using Blade route
  // ===============================
  $(document).on('click', '#btnResetTrials', function () {
    console.log("RESET CLICKED"); // test if click fires

    const url = $(this).data('route');

    $.ajax({
      url: url,
      type: "DELETE",
      data: { _token: $('meta[name="csrf-token"]').attr('content') }
    })
      .done(function () {
        toastr.success("Schedule reset");
        loadData();
      })
      .fail(() => toastr.error("Reset failed"));
  });


  const csrf = $('meta[name="csrf-token"]').attr('content');
  let VENUES = [];

  const fpOpts = {
    enableTime: true,
    dateFormat: "Y-m-d H:i",
    time_24hr: true
  };

  flatpickr('#start', fpOpts);

  function safeFlatpickr(el) {
    if (el._flatpickr) el._flatpickr.destroy();
    flatpickr(el, fpOpts);
  }

  function safeSelect2($el) {
    if ($el.hasClass('select2-hidden-accessible')) {
      $el.select2('destroy');
    }
    $el.select2({ width: '100%' });
  }

  function venueOptions(selected) {
    return VENUES.map(v =>
      `<option value="${v.id}" ${Number(selected) === Number(v.id) ? 'selected' : ''}>
          ${v.name} (${v.num_courts})
       </option>`
    ).join('');
  }

  function rowToRender(fx) {
    return {
      id: fx.id,
      bracket_id: fx.bracket_id,
      round: fx.round,
      match: fx.match_nr,
      p1: fx.p1,
      p2: fx.p2,

      datetime_html:
        `<input type="text" class="form-control form-control-sm dtp"
          data-id="${fx.id}"
          value="${fx.scheduled_at || ''}">`,

      venue_html:
        `<select class="form-select form-select-sm venue-select"
                 data-id="${fx.id}">
            ${venueOptions(fx.venue_id)}
         </select>`,

      court_html:
        `<input type="text" class="form-control form-control-sm court-input"
          data-id="${fx.id}" value="${fx.court_label || ''}">`,

      status_html:
        fx.scheduled
          ? '<span class="badge bg-success">Scheduled</span>'
          : '<span class="badge bg-secondary">Pending</span>',

      actions_html:
        `<button class="btn btn-primary btn-sm btn-save" data-id="${fx.id}">
            Save
         </button>`
    };
  }

  const table = $('#trialsTable').DataTable({
    ordering: false,
    searching: true,
    columns: [
      { data: 'id', className: 'text-center' },
      { data: 'bracket_id', className: 'text-center' },
      { data: 'round', className: 'text-center' },
      { data: 'match', className: 'text-center' },
      { data: 'p1' },
      { data: 'p2' },
      { data: 'datetime_html' },
      { data: 'venue_html' },
      { data: 'court_html' },
      { data: 'status_html', className: 'text-center' },
      { data: 'actions_html', className: 'text-center' },
    ],
    drawCallback: function () {
      $('#trialsTable .dtp').each(function () { safeFlatpickr(this); });
      $('#trialsTable .venue-select').each(function () { safeSelect2($(this)); });
    }
  });

  function refreshAutoCourts() {
    const venueId = $('#auto_venue').val();
    const venue = VENUES.find(v => Number(v.id) === Number(venueId));

    $('#auto_court').empty();
    if (!venue) return;

    for (let i = 1; i <= venue.num_courts; i++) {
      $('#auto_court').append(new Option(`Court ${i}`, i));
    }
  }

  function loadData() {
     $.get(`{{ route('backend.individual-schedule.data', $draw->id) }}`)
      .done(res => {

        VENUES = res.venues;

        // AUTO VENUE dropdown
        $('#auto_venue').empty();
        VENUES.forEach(v => {
          $('#auto_venue').append(
            new Option(`${v.name} (${v.num_courts} courts)`, v.id)
          );
        });
        refreshAutoCourts();

        $('#auto_venue').off('change').on('change', refreshAutoCourts);

        // Bracket filter
        const bracketSet = [...new Set(res.fixtures.map(f => f.bracket_id))];
        $('#filter_bracket').empty();
        bracketSet.forEach(id => {
          $('#filter_bracket').append(new Option("Bracket " + id, id));
        });
        safeSelect2($('#filter_bracket'));

        const rows = res.fixtures.map(rowToRender);
        table.clear().rows.add(rows).draw();
      });
  }

  loadData();

  $('#trialsTable').on('click', '.btn-save', function(){
    const id = $(this).data('id');
    const dt = $(`.dtp[data-id="${id}"]`).val();
    const venue = $(`.venue-select[data-id="${id}"]`).val();
    const court = $(`.court-input[data-id="${id}"]`).val();

    $.post(`{{ route('backend.individual-schedule.save', $draw->id) }}`, {
      _token: csrf,
      fixture_id: id,
      scheduled_at: dt,
      venue_id: venue,
      court_label: court
    })
    .done(() => {
      toastr.success('Saved');
      loadData();
    });
  });
  
  $('#btnReload').on('click', loadData);

  $('#btnAutoSchedule').on('click', function(){
    $.post(`{{ route('backend.trials.auto', $draw->id) }}`, buildPayload())
      .done(res => {
        toastr.success(`Scheduled ${res.count} matches`);
        loadData();
      })
      .fail(() => toastr.error('Auto scheduling failed'));
  });

 function buildPayload() {
    const payload = {
        _token: csrf,
        start: $('#start').val(),
        duration: $('#duration').val(),
        gap: $('#gap').val(),
        schedule_mode: $('#schedule_mode').val(),
        brackets: $('#filter_bracket').val() || [],
        rounds: $('#filter_round').val() || [],
        venue_id: $('#auto_venue').val(),
        court: $('#auto_court').val()
    };

    console.log("AUTO SCHEDULE PAYLOAD:", payload);

    return payload;
}

});
</script>
@endsection
