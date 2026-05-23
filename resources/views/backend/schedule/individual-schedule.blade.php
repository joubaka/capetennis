@extends('layouts/layoutMaster')

@section('title', 'Individual Schedule – ' . $draw->drawName)

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/flatpickr/flatpickr.css') }}">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.11/css/jquery.dataTables.min.css"/>
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}">
  <style>
    .court-block { background: #f8f9fa; border-radius: 8px; padding: 12px; margin-bottom: 12px; }
    .court-block h6 { font-weight: 700; color: #5c67f2; margin-bottom: 8px; }
    .match-row { display: flex; align-items: center; gap: 10px; padding: 6px 10px;
                 background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 6px; }
    .match-time { font-weight: 700; color: #28a745; min-width: 48px; font-size: .85rem; }
    .match-players { flex: 1; font-size: .88rem; }
    .match-stage { font-size: .75rem; }
    .venue-card { background:#fff; border:1px solid #e0e0e0; border-radius:8px; padding:14px 16px; margin-bottom:10px; display:flex; align-items:center; gap:12px; }
    .venue-card .venue-name { font-weight:600; flex:1; }
    .venue-card .courts-badge { background:#e8f0fe; color:#5c67f2; border-radius:20px; padding:2px 12px; font-size:.82rem; font-weight:600; }
    .conflict-item { border-left: 3px solid #dc3545; padding-left: 10px; margin-bottom: 8px; }
    .player-conflict-item { border-left: 3px solid #fd7e14; padding-left: 10px; margin-bottom: 8px; }
    #progress-bar-wrap { transition: all .3s; }
  </style>
@endsection

@section('vendor-script')
  <script src="https://cdn.datatables.net/1.13.11/js/jquery.dataTables.min.js"></script>
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/flatpickr/flatpickr.js') }}"></script>
  <script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

  {{-- Header --}}
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-0"><i class="ti ti-calendar-event me-2 text-primary"></i>Individual Schedule — {{ $draw->drawName }}</h4>
      <small class="text-muted">{{ optional(optional($draw)->event)->name }}</small>
    </div>
    <a href="{{ route('event.tab.draws', $event->id) }}" class="btn btn-label-secondary btn-sm">
      <i class="ti ti-arrow-left me-1"></i> Back
    </a>
  </div>

  {{-- Stats bar --}}
  <div class="row g-3 mb-4" id="stats-bar">
    <div class="col-6 col-md-3">
      <div class="card text-center border-0 shadow-sm">
        <div class="card-body py-3">
          <div class="fs-4 fw-bold text-primary" id="stat-total">—</div>
          <div class="text-muted small">Total Fixtures</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center border-0 shadow-sm">
        <div class="card-body py-3">
          <div class="fs-4 fw-bold text-success" id="stat-scheduled">—</div>
          <div class="text-muted small">Scheduled</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center border-0 shadow-sm">
        <div class="card-body py-3">
          <div class="fs-4 fw-bold text-warning" id="stat-unscheduled">—</div>
          <div class="text-muted small">Unscheduled</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center border-0 shadow-sm">
        <div class="card-body py-3">
          <div class="fs-4 fw-bold text-danger" id="stat-conflicts">—</div>
          <div class="text-muted small">Conflicts</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Progress --}}
  <div id="progress-bar-wrap" class="mb-3 d-none">
    <div class="d-flex justify-content-between mb-1"><small class="text-muted">Scheduling progress</small><small id="progress-label" class="text-muted">0%</small></div>
    <div class="progress" style="height:8px;">
      <div id="progress-bar" class="progress-bar bg-primary progress-bar-striped progress-bar-animated" style="width:0%"></div>
    </div>
  </div>

  {{-- TABS --}}
  <ul class="nav nav-tabs mb-0" id="scheduleTabs">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#tab-schedule">
        <i class="ti ti-calendar me-1"></i> Schedule
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#tab-show" id="tab-show-link">
        <i class="ti ti-layout-rows me-1"></i> Show
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#tab-audit" id="tab-audit-link">
        <i class="ti ti-shield-check me-1"></i> Audit
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#tab-venues" id="tab-venues-link">
        <i class="ti ti-map-pin me-1"></i> Venues
      </a>
    </li>
  </ul>

  <div class="tab-content border border-top-0 rounded-bottom bg-white p-3 shadow-sm">

    {{-- ===================== SCHEDULE TAB ===================== --}}
    <div class="tab-pane fade show active" id="tab-schedule">

      {{-- Auto-schedule panel --}}
      <div class="card mb-3 border-0 bg-light">
        <div class="card-body">
          <form id="autoForm" class="row g-2 align-items-end" onsubmit="return false">

            <div class="col-md-3">
              <label class="form-label fw-semibold">Start</label>
              <input type="text" id="start" class="form-control" placeholder="YYYY-MM-DD HH:mm">
            </div>

            <div class="col-md-3">
              <label class="form-label fw-semibold">End</label>
              <input type="text" id="end" class="form-control" placeholder="YYYY-MM-DD HH:mm">
            </div>

            <div class="col-md-2">
              <label class="form-label fw-semibold">Match Duration (min)</label>
              <input type="number" id="duration" class="form-control" value="60" min="15" step="5">
            </div>

            <div class="col-md-2">
              <label class="form-label fw-semibold">Gap (min)</label>
              <input type="number" id="gap" class="form-control" value="0" min="0" step="5">
            </div>

            <div class="col-md-3">
              <label class="form-label fw-semibold">Schedule Mode</label>
              <select id="schedule_mode" class="form-select">
                <option value="stage_only">Stage Only (RR → MAIN → PLATE → CONS)</option>
                <option value="round_only">Round Only (R1 → R2 → R3)</option>
                <option value="stage_round">Stage + Round Filters</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-semibold">Filter by Stage <small class="text-muted">(optional)</small></label>
              <select id="filter_stage" class="form-select" multiple>
                <option value="RR">RR</option>
                <option value="MAIN">MAIN</option>
                <option value="PLATE">PLATE</option>
                <option value="CONS">CONS</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-semibold">Filter by Round <small class="text-muted">(optional)</small></label>
              <select id="filter_round" class="form-select" multiple>
                @for($i=1;$i<=8;$i++)
                  <option value="{{ $i }}">Round {{ $i }}</option>
                @endfor
              </select>
            </div>

            <div class="col-12">
              <label class="form-label fw-semibold">Use ONLY these venues <small class="text-muted">(optional)</small></label>
              <select id="venues" class="form-select" multiple></select>
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 mt-2">
              <button type="button" id="btnAuto" class="btn btn-primary">
                <i class="ti ti-robot me-1"></i> Auto-Schedule
              </button>
              <button type="button" id="btnReload" class="btn btn-outline-secondary">
                <i class="ti ti-refresh me-1"></i> Reload
              </button>
              <button type="button" id="btn-clear-schedule" class="btn btn-outline-danger">
                <i class="ti ti-trash me-1"></i> Clear All
              </button>
              <button type="button" id="btn-reset-schedule" class="btn btn-outline-warning">
                <i class="ti ti-reload me-1"></i> Reset Auto Schedule
              </button>
            </div>

          </form>
        </div>
      </div>

      {{-- Fixture table --}}
      <div class="table-responsive">
        <table id="scheduleTable" class="table table-sm table-hover table-bordered w-100">
          <thead class="table-dark text-center">
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
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

    {{-- ===================== SHOW TAB ===================== --}}
    <div class="tab-pane fade" id="tab-show">
      <div id="show-loading" class="text-center py-5 text-muted">
        <i class="ti ti-loader ti-spin ti-lg mb-2 d-block"></i> Loading schedule view…
      </div>
      <div id="show-content"></div>
    </div>

    {{-- ===================== AUDIT TAB ===================== --}}
    <div class="tab-pane fade" id="tab-audit">
      <div id="audit-loading" class="text-center py-5 text-muted">
        <i class="ti ti-loader ti-spin ti-lg mb-2 d-block"></i> Running audit…
      </div>
      <div id="audit-content" class="d-none">

        {{-- Stage completion --}}
        <h6 class="fw-bold mb-3"><i class="ti ti-tournament me-1 text-primary"></i> Stage Completion</h6>
        <div id="audit-stages" class="row g-2 mb-4"></div>

        {{-- Venue usage --}}
        <h6 class="fw-bold mb-3"><i class="ti ti-map-pin me-1 text-primary"></i> Venue Usage</h6>
        <div id="audit-venues" class="mb-4"></div>

        {{-- Court conflicts --}}
        <h6 class="fw-bold mb-3"><i class="ti ti-alert-triangle me-1 text-danger"></i> Court Conflicts <span id="conflict-badge" class="badge bg-danger ms-1 d-none"></span></h6>
        <div id="audit-conflicts" class="mb-4"></div>

        {{-- Player double-booking --}}
        <h6 class="fw-bold mb-3"><i class="ti ti-user-x me-1 text-warning"></i> Player Double-Booked <span id="pconflict-badge" class="badge bg-warning text-dark ms-1 d-none"></span></h6>
        <div id="audit-player-conflicts" class="mb-4"></div>

        {{-- Player load --}}
        <h6 class="fw-bold mb-3"><i class="ti ti-users me-1 text-info"></i> Player Match Load</h6>
        <div id="audit-player-load" class="mb-4"></div>

        {{-- Unscheduled fixtures --}}
        <h6 class="fw-bold mb-3"><i class="ti ti-clock-off me-1 text-secondary"></i> Unscheduled Fixtures <span id="unsched-badge" class="badge bg-secondary ms-1 d-none"></span></h6>
        <div id="audit-unscheduled"></div>

      </div>
    </div>

    {{-- ===================== VENUES TAB ===================== --}}
    <div class="tab-pane fade" id="tab-venues">

      {{-- Current venues --}}
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0"><i class="ti ti-map-pin me-1 text-primary"></i> Assigned Venues</h6>
        <button class="btn btn-sm btn-primary" id="btn-add-venue-row">
          <i class="ti ti-plus me-1"></i> Add Venue
        </button>
      </div>

      <div id="venues-list" class="mb-4">
        <div class="text-muted text-center py-3" id="venues-empty">Loading…</div>
      </div>

      {{-- Add / edit form (hidden until needed) --}}
      <div id="venue-form-wrap" class="card border-0 bg-light p-3 d-none">
        <h6 class="fw-bold mb-3" id="venue-form-title">Add Venue</h6>
        <div class="row g-2 align-items-end">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Venue</label>
            <select id="vf-venue-id" class="form-select"></select>
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold">Courts</label>
            <input type="number" id="vf-num-courts" class="form-control" value="1" min="1" max="50">
          </div>
          <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-success w-100" id="btn-venue-save">
              <i class="ti ti-device-floppy me-1"></i> Save
            </button>
            <button class="btn btn-label-secondary" id="btn-venue-cancel">Cancel</button>
          </div>
        </div>
      </div>

    </div>

  </div>
</div>
@endsection

@section('page-script')
<script>
$(function () {
  'use strict';

  const csrf   = $('meta[name="csrf-token"]').attr('content');
  const fpOpts = { enableTime: true, dateFormat: "Y-m-d H:i", time_24hr: true };
  let VENUES   = [];

  // -------------------------------------------------------
  // Helpers
  // -------------------------------------------------------
  function safeFlatpickr(el) {
    if (!el._flatpickr) flatpickr(el, fpOpts);
  }

  function safeSelect2($el, opts = {}) {
    if ($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
    $el.select2({ width: '100%', ...opts });
  }

  function venueOptionsHtml(selected) {
    let opts = '<option value="">— venue —</option>';
    VENUES.forEach(v => {
      opts += `<option value="${v.id}" ${Number(selected) === Number(v.id) ? 'selected' : ''}>${v.name} (${v.num_courts})</option>`;
    });
    return opts;
  }

  function stageBadge(stage) {
    const map = { RR: 'bg-info', MAIN: 'bg-primary', PLATE: 'bg-warning', CONS: 'bg-secondary' };
    return `<span class="badge ${map[stage] || 'bg-dark'}">${stage || '—'}</span>`;
  }

  function rowToRender(fx) {
    return {
      id: fx.id,
      round: fx.round ?? '',
      match: fx.match_nr ?? '',
      stage: stageBadge(fx.stage),
      p1: fx.p1,
      p2: fx.p2,
      datetime_html: `<input type="text" class="form-control form-control-sm dtp" data-id="${fx.id}" value="${fx.scheduled_at || ''}">`,
      venue_html:    `<select class="form-select form-select-sm venue-select" data-id="${fx.id}">${venueOptionsHtml(fx.venue_id)}</select>`,
      court_html:    `<input type="text" class="form-control form-control-sm court-input" data-id="${fx.id}" value="${fx.court_label || ''}" placeholder="e.g. 1">`,
      status_html:   fx.scheduled_at
        ? '<span class="badge bg-success"><i class="ti ti-check me-1"></i>Scheduled</span>'
        : '<span class="badge bg-secondary">Pending</span>',
      actions_html:  `<button class="btn btn-sm btn-primary btn-save" data-id="${fx.id}"><i class="ti ti-device-floppy me-1"></i>Save</button>`,
    };
  }

  // -------------------------------------------------------
  // DataTable
  // -------------------------------------------------------
  const table = $('#scheduleTable').DataTable({
    ordering: false,
    paging: true,
    pageLength: 25,
    searching: true,
    columns: [
      { data: 'id',            className: 'text-center' },
      { data: 'round',         className: 'text-center' },
      { data: 'match',         className: 'text-center' },
      { data: 'stage',         className: 'text-center' },
      { data: 'p1' },
      { data: 'p2' },
      { data: 'datetime_html', orderable: false },
      { data: 'venue_html',    orderable: false },
      { data: 'court_html',    orderable: false },
      { data: 'status_html',   className: 'text-center', orderable: false },
      { data: 'actions_html',  className: 'text-center', orderable: false },
    ],
    drawCallback: function () {
      $('#scheduleTable .dtp').each(function () { safeFlatpickr(this); });
      $('#scheduleTable .venue-select').each(function () { safeSelect2($(this)); });
    }
  });

  // -------------------------------------------------------
  // Update stats bar
  // -------------------------------------------------------
  function updateStats(fixtures) {
    const total       = fixtures.length;
    const scheduled   = fixtures.filter(f => f.scheduled_at).length;
    const unscheduled = total - scheduled;
    $('#stat-total').text(total);
    $('#stat-scheduled').text(scheduled);
    $('#stat-unscheduled').text(unscheduled);
    const pct = total ? Math.round(scheduled / total * 100) : 0;
    $('#progress-bar-wrap').removeClass('d-none');
    $('#progress-bar').css('width', pct + '%');
    $('#progress-label').text(pct + '%');
  }

  // -------------------------------------------------------
  // Load schedule data
  // -------------------------------------------------------
  function loadData() {
    $.get(`{{ route('backend.individual-schedule.data', $draw->id) }}`)
      .done(res => {
        VENUES = res.venues || [];

        $('#venues').empty();
        VENUES.forEach(v => $('#venues').append(new Option(`${v.name} (${v.num_courts})`, v.id)));
        safeSelect2($('#venues'), { placeholder: 'Select venues' });
        safeSelect2($('#filter_stage'), { placeholder: 'Filter stages' });
        safeSelect2($('#filter_round'), { placeholder: 'Filter rounds' });

        const rows = (res.fixtures || []).map(rowToRender);
        table.clear().rows.add(rows).draw();
        updateStats(res.fixtures || []);
      });
  }

  // -------------------------------------------------------
  // Save fixture
  // -------------------------------------------------------
  $('#scheduleTable').on('click', '.btn-save', function () {
    const id    = $(this).data('id');
    const dt    = $(`.dtp[data-id="${id}"]`).val();
    const venue = $(`.venue-select[data-id="${id}"]`).val();
    const court = $(`.court-input[data-id="${id}"]`).val();

    $.post(`{{ route('backend.individual-schedule.save', $draw->id) }}`, {
      _token: csrf, fixture_id: id,
      scheduled_at: dt || null,
      venue_id: venue || null,
      court_label: court || null
    })
    .done(() => { toastr.success('Saved'); loadData(); })
    .fail(() => toastr.error('Save failed'));
  });

  // -------------------------------------------------------
  // Build payload
  // -------------------------------------------------------
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

  // -------------------------------------------------------
  $('#autoForm').on('submit', function(e){ e.preventDefault(); });

  // Auto-schedule
  // -------------------------------------------------------
  $('#btnAuto').on('click', function () {
    if (!$('#start').val()) {
      toastr.warning('Please enter a Start date/time before scheduling.', 'Start time required');
      $('#start').focus();
      return;
    }
    $(this).prop('disabled', true).html('<i class="ti ti-loader ti-spin me-1"></i> Scheduling…');
    $.post(`{{ route('backend.individual-schedule.auto', $draw->id) }}`, buildPayload())
      .done(res => { toastr.success(`Auto-scheduled ${res.count || 0} matches`); loadData(); loadAudit(); })
      .fail((xhr) => xhrError(xhr, 'Auto schedule failed'))
      .always(() => $(this).prop('disabled', false).html('<i class="ti ti-robot me-1"></i> Auto-Schedule'));
  });

  function xhrError(xhr, fallback) {
    let msg = fallback;
    try {
      const json = JSON.parse(xhr.responseText);
      msg = json.message || json.error || fallback;
    } catch(e) {
      if (xhr.responseText) msg = fallback + ': ' + $('<div>').html(xhr.responseText).find('title,h1').first().text().trim();
    }
    toastr.error(msg, 'Error ' + xhr.status, { timeOut: 8000 });
    console.error(fallback, xhr.status, xhr.responseText);
  }

  $('#btn-clear-schedule').on('click', function () {
    Swal.fire({ title: 'Clear all?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Yes, clear' })
      .then(r => {
        if (!r.isConfirmed) return;
        $.post(`{{ route('backend.individual-schedule.clear', $draw->id) }}`, { _token: csrf })
          .done(res => { toastr.success(res.message || 'Schedule cleared'); loadData(); loadAudit(); })
          .fail(xhr => xhrError(xhr, 'Clear failed'));
      });
  });

  $('#btn-reset-schedule').on('click', function () {
    if (!$('#start').val()) {
      toastr.warning('Please enter a Start date/time before resetting.', 'Start time required');
      $('#start').focus();
      return;
    }
    Swal.fire({ title: 'Reset & re-schedule?', icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, reset' })
      .then(r => {
        if (!r.isConfirmed) return;
        $.post(`{{ route('backend.individual-schedule.reset', $draw->id) }}`, buildPayload())
          .done(() => { toastr.success('Auto schedule complete'); loadData(); loadAudit(); })
          .fail(xhr => xhrError(xhr, 'Reset failed'));
      });
  });

  $('#btnReload').on('click', loadData);

  // -------------------------------------------------------
  // AUDIT TAB
  // -------------------------------------------------------
  function loadAudit() {
    // Reset all previous audit content and badges before reloading
    $('#audit-loading').removeClass('d-none');
    $('#audit-content').addClass('d-none');
    $('#audit-stages').empty();
    $('#audit-venues').empty();
    $('#audit-conflicts').empty();
    $('#audit-player-conflicts').empty();
    $('#audit-player-load').empty();
    $('#audit-unscheduled').empty();
    $('#conflict-badge').text('').addClass('d-none');
    $('#pconflict-badge').text('').addClass('d-none');
    $('#unsched-badge').text('').addClass('d-none');

    $.get(`{{ route('backend.individual-schedule.audit', $draw->id) }}`)
      .done(res => {
        const totalConflicts = res.conflicts.length + res.player_conflicts.length;
        $('#stat-conflicts').text(totalConflicts);

        // Stage completion
        const stageColors = { RR: 'info', MAIN: 'primary', PLATE: 'warning', CONS: 'secondary' };
        let stagesHtml = '';
        Object.entries(res.stages || {}).forEach(([stage, data]) => {
          const pct = data.total ? Math.round(data.scheduled / data.total * 100) : 0;
          const color = stageColors[stage] || 'dark';
          stagesHtml += `
            <div class="col-6 col-md-3">
              <div class="card border-0 shadow-sm text-center">
                <div class="card-body py-2">
                  <span class="badge bg-${color} mb-1">${stage}</span>
                  <div class="fs-5 fw-bold">${data.scheduled}/${data.total}</div>
                  <div class="progress mt-1" style="height:6px;">
                    <div class="progress-bar bg-${color}" style="width:${pct}%"></div>
                  </div>
                  <small class="text-muted">${pct}%</small>
                </div>
              </div>
            </div>`;
        });
        $('#audit-stages').html(stagesHtml || '<p class="text-muted">No stage data.</p>');

        // Venue usage
        let venueHtml = '';
        if (res.venues && res.venues.length) {
          res.venues.forEach(v => {
            const pct = res.scheduled ? Math.round(v.matches / res.scheduled * 100) : 0;
            venueHtml += `
              <div class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                  <span class="fw-semibold">${v.name}</span>
                  <span class="text-muted small">${v.matches} matches &nbsp;|&nbsp; ${v.num_courts} courts</span>
                </div>
                <div class="progress" style="height:10px;">
                  <div class="progress-bar bg-primary" style="width:${pct}%"></div>
                </div>
              </div>`;
          });
        } else {
          venueHtml = '<p class="text-muted">No venues assigned.</p>';
        }
        $('#audit-venues').html(venueHtml);

        // Court conflicts
        let conflictHtml = '';
        if (res.conflicts.length) {
          $('#conflict-badge').text(res.conflicts.length).removeClass('d-none');
          res.conflicts.forEach(c => {
            conflictHtml += `
              <div class="conflict-item">
                <strong>&#9888; Court Overlap</strong> — Court <strong>${c.court}</strong><br>
                Fixture <strong>#${c.fixture_a}</strong> @ ${c.time_a} &nbsp;&#8596;&nbsp;
                Fixture <strong>#${c.fixture_b}</strong> @ ${c.time_b}
              </div>`;
          });
        } else {
          conflictHtml = '<div class="alert alert-success py-2 mb-0"><i class="ti ti-check me-1"></i> No court conflicts found.</div>';
        }
        $('#audit-conflicts').html(conflictHtml);

        // Player double-bookings
        let pcHtml = '';
        if (res.player_conflicts.length) {
          $('#pconflict-badge').text(res.player_conflicts.length).removeClass('d-none');
          res.player_conflicts.forEach(c => {
            pcHtml += `
              <div class="player-conflict-item">
                <strong>&#128310; ${c.player}</strong> double-booked<br>
                Fixture <strong>#${c.fixture_a}</strong> @ ${c.time_a} &nbsp;&#8596;&nbsp;
                Fixture <strong>#${c.fixture_b}</strong> @ ${c.time_b}
              </div>`;
          });
        } else {
          pcHtml = '<div class="alert alert-success py-2 mb-0"><i class="ti ti-check me-1"></i> No player double-bookings found.</div>';
        }
        $('#audit-player-conflicts').html(pcHtml);

        // Player load table
        let loadHtml = '';
        if (res.player_load && res.player_load.length) {
          loadHtml = `<div class="table-responsive">
            <table class="table table-sm table-bordered">
              <thead class="table-light"><tr><th>Player</th><th class="text-center">Fixtures</th><th class="text-center">Scheduled</th><th class="text-center">Pending</th></tr></thead>
              <tbody>`;
          res.player_load.forEach(p => {
            const pending = p.total - p.scheduled;
            loadHtml += `<tr>
              <td>${p.name}</td>
              <td class="text-center">${p.total}</td>
              <td class="text-center"><span class="badge bg-success">${p.scheduled}</span></td>
              <td class="text-center">${pending > 0 ? '<span class="badge bg-warning text-dark">' + pending + '</span>' : '<span class="badge bg-success">0</span>'}</td>
            </tr>`;
          });
          loadHtml += `</tbody></table></div>`;
        } else {
          loadHtml = '<p class="text-muted">No player data.</p>';
        }
        $('#audit-player-load').html(loadHtml);

        // Unscheduled fixtures
        let unschedHtml = '';
        if (res.unscheduled_fixtures && res.unscheduled_fixtures.length) {
          $('#unsched-badge').text(res.unscheduled_fixtures.length).removeClass('d-none');
          unschedHtml = `<div class="table-responsive">
            <table class="table table-sm table-bordered">
              <thead class="table-light"><tr><th>#</th><th>Stage</th><th>Round</th><th>Match</th><th>Player 1</th><th>Player 2</th></tr></thead>
              <tbody>`;
          res.unscheduled_fixtures.forEach(f => {
            unschedHtml += `<tr>
              <td>${f.id}</td>
              <td>${f.stage || '—'}</td>
              <td>${f.round}</td>
              <td>${f.match}</td>
              <td>${f.p1}</td>
              <td>${f.p2}</td>
            </tr>`;
          });
          unschedHtml += `</tbody></table></div>`;
        } else {
          unschedHtml = '<div class="alert alert-success py-2 mb-0"><i class="ti ti-check me-1"></i> All fixtures are scheduled.</div>';
        }
        $('#audit-unscheduled').html(unschedHtml);

        $('#audit-loading').addClass('d-none');
        $('#audit-content').removeClass('d-none');
      })
      .fail(() => toastr.error('Audit failed to load.'));
  }

  // -------------------------------------------------------
  // SHOW TAB
  // -------------------------------------------------------
  function loadShow() {
    $('#show-loading').removeClass('d-none');
    $('#show-content').empty().addClass('d-none');

    $.get(`{{ route('backend.individual-schedule.show-data', $draw->id) }}`)
      .done(res => {
        const grouped = res.grouped || {};
        const dates = Object.keys(grouped).sort();

        if (!dates.length) {
          $('#show-content').html('<p class="text-muted text-center py-4">No matches scheduled yet.</p>');
          $('#show-loading').addClass('d-none');
          return;
        }

        let html = '';
        dates.forEach(date => {
          html += `<h5 class="mt-3 mb-2 text-primary fw-bold"><i class="ti ti-calendar me-1"></i>${date}</h5>`;
          const courts = grouped[date];
          Object.keys(courts).sort().forEach(court => {
            html += `<div class="court-block"><h6><i class="ti ti-wall me-1"></i> Court: ${court}</h6>`;
            courts[court].forEach(m => {
              html += `
                <div class="match-row">
                  <span class="match-time">${m.time}</span>
                  <span class="badge ${stageBadge(m.stage).match(/bg-\w+/)?.[0] || 'bg-dark'} match-stage">${m.stage} R${m.round}</span>
                  <span class="match-players"><strong>${m.p1}</strong> <span class="text-muted">vs</span> <strong>${m.p2}</strong></span>
                  <span class="text-muted small">#${m.id}</span>
                </div>`;
            });
            html += `</div>`;
          });
        });

        $('#show-content').html(html).removeClass('d-none');
        $('#show-loading').addClass('d-none');
      });
  }

  // -------------------------------------------------------
  // -------------------------------------------------------
  // VENUES TAB
  // -------------------------------------------------------
  const venueStoreUrl = '{{ route('backend.draw.venues.store', $draw->id) }}';
  const venueJsonUrl  = '{{ route('backend.draw.venues.json', $draw->id) }}';
  const venueEditUrl  = '{{ route('backend.draw.venues.edit', $draw->id) }}';
  let allVenues = [];   // full venue list from server
  let drawVenues = [];  // currently assigned venues
  let editingVenueId = null; // null = adding new

  function renderVenueList() {
    if (!drawVenues.length) {
      $('#venues-list').html('<div class="alert alert-warning py-2 mb-0"><i class="ti ti-alert-triangle me-1"></i> No venues assigned. Add one above to enable scheduling.</div>');
      return;
    }
    let html = '';
    drawVenues.forEach(v => {
      html += `
        <div class="venue-card">
          <i class="ti ti-map-pin text-primary fs-5"></i>
          <span class="venue-name">${v.name}</span>
          <span class="courts-badge"><i class="ti ti-wall me-1"></i>${v.num_courts} court${v.num_courts > 1 ? 's' : ''}</span>
          <button class="btn btn-sm btn-outline-primary btn-edit-venue" data-id="${v.id}" data-courts="${v.num_courts}">
            <i class="ti ti-pencil"></i> Edit Courts
          </button>
          <button class="btn btn-sm btn-outline-danger btn-remove-venue" data-id="${v.id}" data-name="${v.name}">
            <i class="ti ti-trash"></i>
          </button>
        </div>`;
    });
    $('#venues-list').html(html);
  }

  function loadVenues() {
    $.get(venueEditUrl).done(res => {
      allVenues  = res.allVenues || [];
      drawVenues = (res.venues || []).map(v => ({
        id:         v.id,
        name:       allVenues.find(a => a.id == v.id)?.name || 'Venue ' + v.id,
        num_courts: v.num_courts,
      }));
      renderVenueList();
    });
  }

  function saveVenues() {
    const payload = { _token: csrf, venue_id: [], num_courts: [] };
    // Merge current drawVenues with the edit
    let merged = [...drawVenues];
    if (editingVenueId) {
      // editing existing
      merged = merged.map(v => v.id == editingVenueId
        ? { ...v, num_courts: parseInt($('#vf-num-courts').val()) || 1 }
        : v);
    } else {
      // adding new
      const newId     = $('#vf-venue-id').val();
      const newCourts = parseInt($('#vf-num-courts').val()) || 1;
      if (!newId) { toastr.warning('Please select a venue.'); return; }
      if (merged.find(v => v.id == newId)) { toastr.warning('This venue is already assigned.'); return; }
      const newName = allVenues.find(a => a.id == newId)?.name || 'Venue';
      merged.push({ id: newId, name: newName, num_courts: newCourts });
    }
    merged.forEach(v => { payload.venue_id.push(v.id); payload.num_courts.push(v.num_courts); });
    $.post(venueStoreUrl, payload)
      .done(res => {
        toastr.success(res.message || 'Venues saved.');
        drawVenues = (res.venues || []).map(v => ({
          id:         v.id,
          name:       v.name,
          num_courts: v.pivot?.num_courts ?? v.num_courts,
        }));
        renderVenueList();
        hideVenueForm();
        // Refresh VENUES in scheduler too
        VENUES = drawVenues.map(v => ({ id: v.id, name: v.name, num_courts: v.num_courts }));
        $('#venues').empty();
        VENUES.forEach(v => $('#venues').append(new Option(`${v.name} (${v.num_courts})`, v.id)));
        safeSelect2($('#venues'), { placeholder: 'Select venues' });
      })
      .fail(() => toastr.error('Failed to save venues.'));
  }

  function removeVenue(venueId, venueName) {
    Swal.fire({
      title: `Remove ${venueName}?`,
      text: 'This will also unschedule any matches assigned to this venue.',
      icon: 'warning', showCancelButton: true,
      confirmButtonColor: '#d33', confirmButtonText: 'Yes, remove'
    }).then(r => {
      if (!r.isConfirmed) return;
      const payload = { _token: csrf, venue_id: [], num_courts: [] };
      drawVenues.filter(v => v.id != venueId).forEach(v => {
        payload.venue_id.push(v.id);
        payload.num_courts.push(v.num_courts);
      });
      $.post(venueStoreUrl, payload)
        .done(res => {
          toastr.success('Venue removed.');
          drawVenues = drawVenues.filter(v => v.id != venueId);
          renderVenueList();
          VENUES = drawVenues.map(v => ({ id: v.id, name: v.name, num_courts: v.num_courts }));
          $('#venues').empty();
          VENUES.forEach(v => $('#venues').append(new Option(`${v.name} (${v.num_courts})`, v.id)));
          safeSelect2($('#venues'), { placeholder: 'Select venues' });
        })
        .fail(() => toastr.error('Failed to remove venue.'));
    });
  }

  function showVenueForm(editId = null) {
    editingVenueId = editId;
    const $sel = $('#vf-venue-id');

    // Always re-populate options from allVenues
    if ($sel.hasClass('select2-hidden-accessible')) $sel.select2('destroy');
    $sel.empty();
    allVenues.forEach(v => $sel.append(new Option(v.name, v.id)));

    if (editId) {
      const v = drawVenues.find(x => x.id == editId);
      $('#venue-form-title').text('Edit Courts — ' + (v?.name || ''));
      $sel.prop('disabled', true).val(editId);
      $('#vf-num-courts').val(v?.num_courts || 1);
    } else {
      $('#venue-form-title').text('Add Venue');
      $sel.prop('disabled', false).val('');
      $('#vf-num-courts').val(1);
    }

    $('#venue-form-wrap').removeClass('d-none');
    $sel.select2({ width: '100%', placeholder: 'Select a venue', allowClear: true, dropdownParent: $('#venue-form-wrap') });
    $('html, body').animate({ scrollTop: $('#venue-form-wrap').offset().top - 80 }, 300);
  }

  function hideVenueForm() {
    editingVenueId = null;
    $('#venue-form-wrap').addClass('d-none');
    $('#vf-venue-id').prop('disabled', false);
  }

  $('#btn-add-venue-row').on('click', () => showVenueForm(null));
  $('#btn-venue-cancel').on('click', hideVenueForm);
  $('#btn-venue-save').on('click', saveVenues);

  $(document).on('click', '.btn-edit-venue', function () {
    showVenueForm($(this).data('id'));
  });

  $(document).on('click', '.btn-remove-venue', function () {
    removeVenue($(this).data('id'), $(this).data('name'));
  });

  $('#tab-show-link').on('click', loadShow);
  $('#tab-audit-link').on('click', loadAudit);
  $('#tab-venues-link').on('click', loadVenues);

  // -------------------------------------------------------
  // Init
  // -------------------------------------------------------
  flatpickr('#start', fpOpts);
  flatpickr('#end', fpOpts);
  loadData();
});
</script>
@endsection

