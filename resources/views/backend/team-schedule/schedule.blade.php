@extends('layouts/layoutMaster')

@section('title', 'Team Schedule – ' . $draw->drawName)

{{-- =========================
     VENDOR STYLES
   ========================= --}}
@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/jquery.dataTables.min.css"/>
  <link rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"
        referrerpolicy="no-referrer" />
@endsection

{{-- =========================
     VENDOR SCRIPTS
   ========================= --}}
@section('vendor-script')
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"
          referrerpolicy="no-referrer"></script>
@endsection

@section('content')
<div class="container-xxl">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="m-0">Team Schedule — {{ $draw->drawName }}</h4>
    <a href="{{ route('event.tab.draws', $event->id) }}" class="btn btn-secondary btn-sm">Back</a>
  </div>

  {{-- Auto Schedule Form --}}
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
          <label class="form-label">Duration (min)</label>
          <input type="number" id="duration" class="form-control" value="90" min="20" step="5">
        </div>
        <div class="col-md-2">
          <label class="form-label">Gap (min)</label>
          <input type="number" id="gap" class="form-control" value="0" min="0" step="5">
        </div>
        <div class="col-md-2">
          <label class="form-label">Round (optional)</label>
          <input type="text" id="round" class="form-control" placeholder="e.g. 1 / 2">
        </div>
        <div class="col-12">
          <label class="form-label">Venues (limit to these)</label>
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

  {{-- Rank → Venue Map --}}
  <div class="card mt-3">
    <div class="card-header">
      <h5 class="mb-0">Rank → Venue Map</h5>
      <small class="text-muted">Assign specific home rank numbers to venues</small>
    </div>
    <div class="card-body">
      <form id="rankVenueForm" class="row g-2 align-items-end">
        <div class="col-md-12">
          <table class="table table-sm table-bordered align-middle">
            <thead class="table-light">
              <tr>
                <th style="width:120px;">Home Rank #</th>
                <th>Venue</th>
                <th style="width:60px;"></th>
              </tr>
            </thead>
            <tbody id="rankVenueRows">
              {{-- Rows will be added dynamically --}}
            </tbody>
          </table>
        </div>
        <div class="col-12">
          <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddRankVenue">
            <i class="ti ti-plus"></i> Add Mapping
          </button>
        </div>
      </form>
    </div>
  </div>

  {{-- Schedule Table --}}
  <div class="card mt-3">
    <div class="card-body">
      <table id="scheduleTable" class="table table-sm table-striped table-bordered align-middle w-100">
        <thead class="table-light">
          <tr class="text-center">
            <th>#</th>
            <th>Round</th>
            <th>Match</th>
            <th>Home vs Away</th>
            <th style="min-width:180px;">Date/Time</th>
            <th>Venue</th>
            <th>Court</th>
            <th>Dur</th>
            <th>Status</th>
            <th style="min-width:120px;">Actions</th>
          </tr>
        </thead>
        <tbody></tbody>
      </table>
    </div>
  </div>
</div>

{{-- =========================
     MAIN SCRIPT
   ========================= --}}
<script>
$(function () {
  'use strict';

  // Global state
  window.VENUES = [];
  window.rankVenueMap = {};

  const csrf = $('meta[name="csrf-token"]').attr('content');
  const drawId = {{ $draw->id }};
  const fpOpts = { enableTime: true, dateFormat: "Y-m-d H:i", time_24hr: true };
  const DEBUG = true;

  function log(...args) { if (DEBUG) console.log('[SCHEDULE]', ...args); }

  // Helpers -------------------------------
  function safeFlatpickr(el) {
    if (!el._flatpickr) {
      flatpickr(el, fpOpts);
      log('Flatpickr init', el);
    }
  }
  function safeSelect2($el, opts = {}) {
    if ($el.hasClass('select2-hidden-accessible')) {
      $el.select2('destroy');
      log('Select2 destroyed', $el.attr('id') || $el.data('id'));
    }
    $el.select2({ width: '100%', ...opts });
    log('Select2 init', $el.attr('id') || $el.data('id'));
  }
  function initRowEditors($row) {
    $row.find('.dtp').each(function () {
      safeFlatpickr(this);
      const val = $(this).data('val');
      if (val && this._flatpickr) this._flatpickr.setDate(val, true);
    });
    $row.find('.venue-select').each(function () {
      safeSelect2($(this));
    });
  }

  // Rendering -------------------------------
  function venueOptionsHtml(selectedId) {
    return VENUES.map(v =>
      `<option value="${v.id}" ${+selectedId === +v.id ? 'selected' : ''}>${v.name} (x${v.num_courts})</option>`
    ).join('');
  }
  function rowToRender(r) {
  
    return {
      id: r.id,
      round: r.round ?? '',
      match: r.match ?? '',
      teams: `${r.p1} <span class="text-muted">vs</span> ${r.p2}`,
      datetime_html: `<input type="text" class="form-control form-control-sm dtp" data-id="${r.id}" data-val="${r.scheduled_at || ''}" value="${r.scheduled_at || ''}" placeholder="YYYY-MM-DD HH:mm">`,
      venue_html: `<select class="form-select form-select-sm venue-select" data-id="${r.id}">${venueOptionsHtml(r.venue_id)}</select>`,
      court_html: `<input class="form-control form-control-sm court-input" data-id="${r.id}" value="${r.court_label ?? ''}" maxlength="50">`,
      duration_html: `<input type="number" min="20" max="480" step="5" class="form-control form-control-sm dur-input text-center" data-id="${r.id}" value="${r.duration_min ?? ''}" placeholder="min">`,
      status_html: r.clash_flag
        ? '<span class="badge bg-danger">Clash</span>'
        : (r.scheduled_at ? '<span class="badge bg-success">Scheduled</span>' : '<span class="badge bg-secondary">Unscheduled</span>'),
      actions_html: `<button class="btn btn-sm btn-primary btn-save" data-id="${r.id}">Save</button>`
    };
  }

  // DataTable -------------------------------
  const table = $('#scheduleTable').DataTable({
    processing: false, serverSide: false,
    paging: true, searching: true, ordering: false,
    autoWidth: false,
    columns: [
      { data: 'id', className: 'text-center', width: '60px' },
      { data: 'round', className: 'text-center' },
      { data: 'match', className: 'text-center' },
      { data: 'teams' },
      { data: 'datetime_html', orderable: false },
      { data: 'venue_html', orderable: false },
      { data: 'court_html', orderable: false },
      { data: 'duration_html', orderable: false, className:'text-center', width:'70px' },
      { data: 'status_html', orderable: false, className:'text-center' },
      { data: 'actions_html', orderable: false, className:'text-center' }
    ],
    drawCallback: function () {
      $('#scheduleTable tbody tr').each(function(){ initRowEditors($(this)); });
    }
  });

  // Load data -------------------------------
  function loadData() {
    $.get(`{{ route('backend.team-schedule.data', $draw->id) }}`)
      .done(function (res) {
        VENUES = res.venues || [];
        const $venues = $('#venues').empty();
        VENUES.forEach(v => {
          $venues.append(new Option(`${v.name} (x${v.num_courts})`, v.id, false, false));
        });
        safeSelect2($venues, { placeholder: 'Select venues (optional)' });

        const rows = (res.fixtures || []).map(rowToRender);
        table.clear().rows.add(rows).draw();
      });
  }

  // Save button -------------------------------
  $('#scheduleTable').on('click', '.btn-save', function(){
    const id    = $(this).data('id');
    const dt    = $(`.dtp[data-id="${id}"]`).val();
    const venue = $(`.venue-select[data-id="${id}"]`).val();
    const court = $(`.court-input[data-id="${id}"]`).val();
    const dur   = $(`.dur-input[data-id="${id}"]`).val();

    $.post(`{{ route('backend.team-schedule.save', $draw->id) }}`, {
      _token: csrf, fixture_id: id,
      scheduled_at: dt || null, venue_id: venue || null,
      court_label: court || null, duration_min: dur || null
    })
    .done(function(){
      toastr.success('Saved');
      loadData();
    })
    .fail(function(){ toastr.error('Save failed'); });
  });

  // Rank → Venue Map -------------------------------
  function renderRankVenueRows() {
    const $tbody = $('#rankVenueRows').empty();
    Object.entries(rankVenueMap).forEach(([rank, venueId]) => {
      const venueName = VENUES.find(v => v.id == venueId)?.name || `Venue ${venueId}`;
      $tbody.append(`
        <tr data-rank="${rank}">
          <td><input type="number" class="form-control form-control-sm rank-input" value="${rank}" min="1"></td>
          <td>
            <select class="form-select form-select-sm venue-select-row">
              ${VENUES.map(v =>
                `<option value="${v.id}" ${v.id == venueId ? 'selected' : ''}>${v.name}</option>`
              ).join('')}
            </select>
          </td>
          <td><button type="button" class="btn btn-sm btn-outline-danger btnRemoveRankVenue"><i class="ti ti-trash"></i></button></td>
        </tr>
      `);
    });
    $('#rankVenueRows .venue-select-row').each(function(){ safeSelect2($(this)); });
  }

  $('#btnAddRankVenue').on('click', function () {
    const nextRank = Object.keys(rankVenueMap).length + 1;
    rankVenueMap[nextRank] = VENUES[0]?.id || null;
    renderRankVenueRows();
  });

  $('#rankVenueRows').on('change', '.rank-input, .venue-select-row', function () {
    const $row = $(this).closest('tr');
    const rank = $row.find('.rank-input').val();
    const venue = $row.find('.venue-select-row').val();
    if (rank && venue) {
      delete rankVenueMap[$row.data('rank')];
      rankVenueMap[rank] = venue;
      $row.attr('data-rank', rank);
    }
  });

  $('#rankVenueRows').on('click', '.btnRemoveRankVenue', function () {
    const rank = $(this).closest('tr').data('rank');
    delete rankVenueMap[rank];
    renderRankVenueRows();
  });

  // Build payload -------------------------------
 function buildPayload() {
  // Parse comma-separated rounds into array of ints
  const roundRaw = $('#round').val();
  const rounds = roundRaw
    ? roundRaw.split(',').map(r => r.trim()).filter(r => r.length > 0)
    : [];

  return {
    _token: csrf,
    start: $('#start').val(),
    end: $('#end').val(),
    duration: $('#duration').val(),
    gap: $('#gap').val(),
    round: rounds,                 // 👈 send as array, not single value
    venues: $('#venues').val() ?? [],
    rank_venue_map: rankVenueMap
  };
}


 $('#btnAuto').on('click', function () {
  const payload = buildPayload();

  $.post(`{{ route('backend.team-schedule.auto', $draw->id) }}`, payload)
    .done(res => {
      // 🔹 Friendly summary
      const roundsText = (payload.round && payload.round.length)
        ? `round${payload.round.length > 1 ? 's' : ''} ${payload.round.join(', ')}`
        : 'all rounds';

      toastr.success(`Auto-scheduled ${res.count ?? 0} matches for ${roundsText}`);

      // 🔹 Optional info logs
      if (res.rounds_processed?.length) {
        console.info('[SCHEDULE] Processed rounds:', res.rounds_processed);
      }

      if (res.clashes?.length) {
        toastr.warning(`${res.clashes.length} clashes detected`);
        console.warn('[SCHEDULE] Clashes:', res.clashes);
      }

      if (res.skipped?.length) {
        toastr.info(`${res.skipped.length} matches skipped`);
        console.info('[SCHEDULE] Skipped:', res.skipped);
      }

      loadData();
    })
    .fail(xhr => {
      console.error('[SCHEDULE] Auto-schedule failed', xhr);
      toastr.error('Auto-schedule failed');
    });
});


  // Clear / Reset -------------------------------
  $('#btn-clear-schedule').on('click', function () {
    if (!confirm('Clear ALL scheduled fixtures for this draw?')) return;
    $.post(`{{ route('backend.draw.schedule.clear', $draw->id) }}`, { _token: csrf })
      .done(res => { toastr.success(res.message || 'Schedules cleared'); loadData(); })
      .fail(() => toastr.error('Failed to clear schedules'));
  });

  $('#btn-reset-schedule').on('click', function () {
    if (!confirm('This will clear everything and re-auto-schedule. Continue?')) return;
    const payload = buildPayload();
    $.post(`{{ route('backend.draw.schedule.reset', $draw->id) }}`, payload)
      .done(res => { toastr.success('Auto schedule completed'); loadData(); })
      .fail(() => toastr.error('Failed to reset schedule'));
  });

  // Init -------------------------------
  flatpickr('#start', fpOpts);
  flatpickr('#end', fpOpts);
  $('#btnReload').on('click', loadData);
  loadData();
});
</script>
@endsection
