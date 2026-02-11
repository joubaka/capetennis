$(function () {
  'use strict';

  const csrf = $('meta[name="csrf-token"]').attr('content');
 

let VENUES = [];
const fpOpts = { enableTime: true, dateFormat: "Y-m-d H:i", time_24hr: true };

function safeFlatpickr(el) {
  if (!el._flatpickr) flatpickr(el, fpOpts);
}
function safeSelect2($el, opts = {}) {
  if (!$el.hasClass('select2-hidden-accessible')) $el.select2({ width: '100%', ...opts });
}
function initRowEditors($row) {
  $row.find('.dtp').each(function () { safeFlatpickr(this); });
  $row.find('.venue-select').each(function () { safeSelect2($(this)); });
}
function reloadScheduleTable() {
  if ($.fn.DataTable.isDataTable('#scheduleTable')) {
    $('#scheduleTable').DataTable().ajax.reload(null, false);
  } else {
    loadData(); // fallback
  }
}

function venueOptionsHtml(selectedId) {
  const opts = ['<option value="">—</option>'];
  VENUES.forEach(v => {
    const label = `${v.name} (x${v.num_courts})`;
    const sel = (selectedId && +selectedId === +v.id) ? 'selected' : '';
    opts.push(`<option value="${v.id}" ${sel}>${label}</option>`);
  });
  return opts.join('');
}

function rowToRender(r) {
  const teams = `${r.p1} <span class="text-muted">vs</span> ${r.p2}`;
  const dtVal = r.scheduled_at ? r.scheduled_at : '';

  const venueSel = `<select class="form-select form-select-sm venue-select" data-id="${r.id}">${venueOptionsHtml(r.venue_id)}</select>`;
  const courtInp = `<input class="form-control form-control-sm court-input" data-id="${r.id}" value="${r.court_label ?? ''}" maxlength="50">`;
  const durInp = `<input type="number" min="20" max="480" step="5" class="form-control form-control-sm dur-input text-center" data-id="${r.id}" value="${r.duration_min ?? ''}" placeholder="min">`;

  const status = r.clash_flag
    ? '<span class="badge bg-danger">Clash</span>'
    : (r.scheduled_at ? '<span class="badge bg-success">Scheduled</span>' : '<span class="badge bg-secondary">Unscheduled</span>');

  const saveBtn = `<button class="btn btn-sm btn-primary btn-save" data-id="${r.id}">Save</button>`;

  return {
    id: r.id,
    round: r.round ?? '',
    match: r.match ?? '',
    teams,
    datetime_html: `<input type="text" class="form-control form-control-sm dtp" data-id="${r.id}" value="${dtVal}" placeholder="YYYY-MM-DD HH:mm">`,
    venue_html: venueSel,
    court_html: courtInp,
    duration_html: durInp,
    status_html: status,
    actions_html: saveBtn
  };
}

const table = $('#scheduleTable').DataTable({
  processing: false,
  serverSide: false,
  paging: true,
  searching: true,
  ordering: true,
  autoWidth: false,
  columns: [
    { data: 'id', className: 'text-center', width: '60px' },
    { data: 'round', className: 'text-center' },
    { data: 'match', className: 'text-center' },
    { data: 'teams' },
    { data: 'datetime_html', orderable: false },
    { data: 'venue_html', orderable: false },
    { data: 'court_html', orderable: false },
    { data: 'duration_html', orderable: false, className: 'text-center', width: '70px' },
    { data: 'status_html', orderable: false, className: 'text-center' },
    { data: 'actions_html', orderable: false, className: 'text-center' }
  ],
  drawCallback: function () {
    $('#scheduleTable tbody tr').each(function () { initRowEditors($(this)); });
  }
});

function loadData() {
  $('#btnReload').prop('disabled', true);

  const drawId = $('#drawId').val();
  const url = window.teamScheduleUrls[drawId].data;

  $.get(url)

    .done(function (res) {
      VENUES = res.venues || [];
      const $venues = $('#venues').empty();
      VENUES.forEach(v => $venues.append(new Option(`${v.name} (x${v.num_courts})`, v.id, false, false)));
      safeSelect2($venues, { placeholder: 'Select venues (optional)' });

      const rows = (res.fixtures || []).map(rowToRender);
      table.clear().rows.add(rows).draw();
    })
    .fail(function () {
      toastr.error('Failed to load schedule data');
    })
    .always(function () { $('#btnReload').prop('disabled', false); });
}

// Save per row
$('#scheduleTable').on('click', '.btn-save', function () {
  const id = $(this).data('id');
  const dt = $(`.dtp[data-id="${id}"]`).val();
  const venue = $(`.venue-select[data-id="${id}"]`).val();
  const court = $(`.court-input[data-id="${id}"]`).val();
  const dur = $(`.dur-input[data-id="${id}"]`).val();

  const $btn = $(this).prop('disabled', true).text('Saving…');

  $.post(`{{ route('backend.team-schedule.save', $draw->id) }}`, {
    _token: csrf,
    fixture_id: id,
    scheduled_at: dt || null,
    venue_id: venue || null,
    court_label: court || null,
    duration_min: dur || null
  })
    .done(function () {
      toastr.success('Saved');
      loadData();
    })
    .fail(function () {
      toastr.error('Save failed');
    })
    .always(function () { $btn.prop('disabled', false).text('Save'); });
});

// Auto-schedule
$('#btnAuto').on('click', function () {
  const $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Auto-Scheduling…');
  const payload = {
    _token: csrf,
    start: $('#start').val(),
    end: $('#end').val(),
    duration: $('#duration').val(),
    gap: $('#gap').val(),
    round: $('#round').val(),
    venues: $('#venues').val() ?? []
  };

  $.post(`{{ route('backend.team-schedule.auto', $draw->id) }}`, payload)
    .done(function (res) {
      const n = (res.assigned && res.assigned.length) ? res.assigned.length : 0;
      toastr.success(`Auto-scheduled ${n} matches`);
      loadData();
    })
    .fail(function () {
      toastr.error('Auto-schedule failed');
    })
    .always(function () { $btn.prop('disabled', false).text('Auto-Schedule'); });
});

// Reload
$('#btnReload').on('click', loadData);

// Clear All
$('#btn-clear-schedule').on('click', function () {
  console.log('🟢 Clear button clicked');
  if (!confirm('Are you sure you want to clear ALL scheduled fixtures for this draw?')) return;
  const $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Clearing…');

  $.post(`{{ route('backend.draw.schedule.clear', $draw->id) }}`, { _token: csrf })
    .done(function (res) {
      toastr.success(res.message || 'Schedules cleared');
      loadData();
    })
    .fail(function () {
      toastr.error('Failed to clear schedules');
    })
    .always(function () { $btn.prop('disabled', false).html('<i class="ti ti-trash"></i> Clear All'); });
});

// Reset Auto Schedule
$('#btn-reset-schedule').on('click', function () {
  console.log('🟢 reset button clicked');
  if (!confirm('This will clear everything and re-auto-schedule. Continue?')) return;
  const $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Rescheduling…');

  $.post(`{{ route('backend.draw.schedule.reset', $draw->id) }}`, {
    _token: csrf,
    start: $('#start').val(),
    end: $('#end').val(),
    duration: $('#duration').val(),
    gap: $('#gap').val(),
    round: $('#round').val(),
    venues: $('#venues').val() ?? []
  })
    .done(function (res) {
      toastr.success('Auto schedule completed');
      loadData();
    })
    .fail(function () {
      toastr.error('Failed to reset schedule');
    })
    .always(function () { $btn.prop('disabled', false).html('<i class="ti ti-refresh"></i> Reset Auto Schedule'); });
});

// Init date pickers
flatpickr('#start', fpOpts);
flatpickr('#end', fpOpts);

// Initial load
loadData();
});
