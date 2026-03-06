$(function () {
  'use strict';

  // =====================================================
  // GLOBAL STATE
  // =====================================================
  window.VENUES = [];
  window.rankVenueMap = {};

  const CONFIG = window.scheduleConfig;
  const ROUTES = CONFIG.routes;
  const csrf = $('meta[name="csrf-token"]').attr('content');
  const fpOpts = { enableTime: true, dateFormat: "Y-m-d H:i", time_24hr: true };
  const DEBUG = true;

  function log(...args) { if (DEBUG) console.log('[SCHEDULE]', ...args); }

  // =====================================================
  // HELPERS
  // =====================================================
  function safeFlatpickr(el) {
    if (!el._flatpickr) {
      flatpickr(el, fpOpts);
      log('Flatpickr init', el);
    }
  }

  function safeSelect2($el, opts = {}) {
    if ($el.hasClass('select2-hidden-accessible')) {
      $el.select2('destroy');
    }
    $el.select2({ width: '100%', ...opts });
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

  function venueOptionsHtml(selectedId) {
    return '<option value="">-- Select --</option>' +
      VENUES.map(v =>
        `<option value="${v.id}" ${+selectedId === +v.id ? 'selected' : ''}>
                ${v.name} (x${v.num_courts})
            </option>`
      ).join('');
  }

  function rowToRender(r) {
    return {
      id: r.id,
      round: r.round ?? '',
      tie: r.tie ?? '',
      rank: r.home_rank ?? '',
      teams: `${r.p1} <span class="text-muted">vs</span> ${r.p2}`,
      datetime_html:
        `<input type="text" class="form-control form-control-sm dtp"
                data-id="${r.id}"
                data-val="${r.scheduled_at || ''}"
                value="${r.scheduled_at || ''}"
                placeholder="YYYY-MM-DD HH:mm">`,
      venue_html:
        `<select class="form-select form-select-sm venue-select"
                data-id="${r.id}">
                ${venueOptionsHtml(r.venue_id)}
            </select>`,
      court_html:
        `<input class="form-control form-control-sm court-input"
                data-id="${r.id}"
                value="${r.court_label ?? ''}"
                maxlength="50">`,
      duration_html:
        `<input type="number" min="20" max="480" step="5"
                class="form-control form-control-sm dur-input text-center"
                data-id="${r.id}"
                value="${r.duration_min ?? ''}"
                placeholder="min">`,
      status_html: r.clash_flag
        ? '<span class="badge bg-danger">Clash</span>'
        : (r.scheduled_at
          ? '<span class="badge bg-success">Scheduled</span>'
          : '<span class="badge bg-secondary">Unscheduled</span>'),
      actions_html:
        `<button class="btn btn-sm btn-primary btn-save"
                data-id="${r.id}">Save</button>`
    };
  }

  // =====================================================
  // DATATABLE
  // =====================================================
  const table = $('#scheduleTable').DataTable({
    paging: true,
    searching: true,
    ordering: false,
    pageLength: 25,
    autoWidth: false,
    columns: [
      { data: 'id', className: 'text-center', width: '50px' },
      { data: 'round', className: 'text-center', width: '60px' },
      { data: 'tie', className: 'text-center', width: '50px' },
      { data: 'rank', className: 'text-center', width: '50px' },
      { data: 'teams' },
      { data: 'datetime_html' },
      { data: 'venue_html' },
      { data: 'court_html', width: '80px' },
      { data: 'duration_html', className: 'text-center', width: '70px' },
      { data: 'status_html', className: 'text-center' },
      { data: 'actions_html', className: 'text-center' }
    ],
    drawCallback: function () {
      $('#scheduleTable tbody tr').each(function () {
        initRowEditors($(this));
      });
    }
  });

  // =====================================================
  // LOAD DATA
  // =====================================================
  function loadData() {
    $.get(ROUTES.data)
      .done(function (res) {
        VENUES = res.venues || [];

        // Populate the venues filter dropdown
        const $venueSelect = $('#venues');
        $venueSelect.empty();
        VENUES.forEach(v => {
          $venueSelect.append(`<option value="${v.id}">${v.name} (x${v.num_courts})</option>`);
        });
        safeSelect2($venueSelect, { placeholder: 'All venues (leave empty)' });

        const rows = (res.fixtures || []).map(rowToRender);
        table.clear().rows.add(rows).draw();

        log('Loaded fixtures:', rows.length);
      })
      .fail(function (xhr) {
        console.error('[SCHEDULE] Load failed', xhr);
        toastr.error('Failed to load schedule data');
      });
  }

  // =====================================================
  // SAVE FIXTURE
  // =====================================================
  $('#scheduleTable').on('click', '.btn-save', function () {

    const id = $(this).data('id');

    $.post(ROUTES.save, {
      _token: csrf,
      fixture_id: id,
      scheduled_at: $(`.dtp[data-id="${id}"]`).val() || null,
      venue_id: $(`.venue-select[data-id="${id}"]`).val() || null,
      court_label: $(`.court-input[data-id="${id}"]`).val() || null,
      duration_min: $(`.dur-input[data-id="${id}"]`).val() || null
    })
      .done(function () {
        toastr.success('Saved');
        loadData();
      })
      .fail(function (xhr) {
        console.error('[SCHEDULE] Save failed', xhr);
        toastr.error('Save failed');
      });
  });

  // =====================================================
  // RANK → VENUE MAP RENDER
  // =====================================================
  function renderRankVenueRows() {

    const $tbody = $('#rankVenueRows').empty();

    Object.entries(rankVenueMap).forEach(([rank, config]) => {

      const venueId = config?.venue_id ?? '';
      const duration = config?.duration ?? '';

      $tbody.append(`
            <tr data-rank="${rank}">
                <td>
                    <input type="number"
                        class="form-control form-control-sm rank-input"
                        value="${rank}" min="1">
                </td>
                <td>
                    <select class="form-select form-select-sm venue-select-row">
                        ${VENUES.map(v =>
        `<option value="${v.id}"
                                ${v.id == venueId ? 'selected' : ''}>
                                ${v.name}
                            </option>`
      ).join('')}
                    </select>
                </td>
                <td>
                    <input type="number"
                        class="form-control form-control-sm dur-override"
                        value="${duration}"
                        min="20" step="5">
                </td>
                <td>
                    <button type="button"
                        class="btn btn-sm btn-outline-danger btnRemoveRankVenue">
                        ✕
                    </button>
                </td>
            </tr>
        `);
    });

    $('#rankVenueRows .venue-select-row').each(function () {
      safeSelect2($(this));
    });
  }

  // =====================================================
  // BUILD PAYLOAD
  // =====================================================
  function buildPayload() {

    const simpleMap = {};
    const durationMap = {};

    Object.entries(rankVenueMap).forEach(([rank, config]) => {
      simpleMap[rank] = config.venue_id;
      if (config.duration) {
        durationMap[rank] = config.duration;
      }
    });

    return {
      _token: csrf,
      start: $('#start').val(),
      end: $('#end').val(),
      duration: $('#duration').val(),
      gap: $('#gap').val(),
      round: $('#round').val() || null,
      venues: $('#venues').val() ?? [],
      rank_venue_map: simpleMap,
      rank_duration_map: durationMap
    };
  }

  // =====================================================
  // AUTO SCHEDULE
  // =====================================================
  $('#btnAuto').on('click', function () {

    const payload = buildPayload();

    if (!payload.start || !payload.end) {
      toastr.error('Please set start and end times');
      return;
    }

    $.post(ROUTES.auto, payload)
      .done(res => {
        toastr.success(`Auto-scheduled ${res.count ?? 0} matches`);
        loadData();
      })
      .fail(xhr => {
        console.error('[SCHEDULE] Auto failed', xhr);
        toastr.error(xhr.responseJSON?.message || 'Auto failed');
      });
  });

  // =====================================================
  // CLEAR / RESET
  // =====================================================
  $('#btn-clear-schedule').on('click', function () {
    if (!confirm('Clear ALL scheduled fixtures?')) return;

    $.post(ROUTES.clear, { _token: csrf })
      .done(res => {
        toastr.success(res.message || 'Schedules cleared');
        loadData();
      })
      .fail(() => toastr.error('Failed to clear'));
  });

  $('#btn-reset-schedule').on('click', function () {
    if (!confirm('Reset and auto-schedule again?')) return;

    $.post(ROUTES.reset, buildPayload())
      .done(() => {
        toastr.success('Reset complete');
        loadData();
      })
      .fail(() => toastr.error('Reset failed'));
  });

  // =====================================================
  // RANK → VENUE MAP ACTIONS
  // =====================================================
  $('#btnAddRankVenue').on('click', function () {
    // Get selected venues from the filter dropdown
    const selectedVenueIds = $('#venues').val() || [];
    const selectedVenues = selectedVenueIds.length > 0
      ? VENUES.filter(v => selectedVenueIds.includes(String(v.id)))
      : VENUES;

    if (selectedVenues.length === 0) {
      toastr.warning('No venues selected or available');
      return;
    }

    // Get unique ranks from current fixtures
    const ranks = [];
    table.rows().every(function () {
      const rank = this.data().rank;
      if (rank && !ranks.includes(rank)) ranks.push(rank);
    });
    ranks.sort((a, b) => a - b);

    if (ranks.length === 0) {
      toastr.warning('No ranks found in fixtures');
      return;
    }

    // Clear existing map
    rankVenueMap = {};

    if (selectedVenues.length === 1) {
      // Single venue: assign all ranks to it
      ranks.forEach(rank => {
        rankVenueMap[rank] = { venue_id: selectedVenues[0].id, duration: '' };
      });
    } else if (selectedVenues.length === 2) {
      // Two venues: split ranks in half
      const midpoint = Math.ceil(ranks.length / 2);
      ranks.forEach((rank, index) => {
        const venueIndex = index < midpoint ? 0 : 1;
        rankVenueMap[rank] = { venue_id: selectedVenues[venueIndex].id, duration: '' };
      });
    } else {
      // More than 2 venues: distribute round-robin
      ranks.forEach((rank, index) => {
        const venueIndex = index % selectedVenues.length;
        rankVenueMap[rank] = { venue_id: selectedVenues[venueIndex].id, duration: '' };
      });
    }

    renderRankVenueRows();
    toastr.success(`Mapped ${ranks.length} rank(s) to ${selectedVenues.length} venue(s)`);
  });

  $('#btnAutoMapRanks').on('click', function () {
    // Same logic as Add Mapping for auto-map
    $('#btnAddRankVenue').trigger('click');
  });

  // Remove single mapping row
  $('#rankVenueRows').on('click', '.btnRemoveRankVenue', function () {
    const rank = $(this).closest('tr').data('rank');
    delete rankVenueMap[rank];
    renderRankVenueRows();
  });

  // Update map when row values change
  $('#rankVenueRows').on('change', '.rank-input, .venue-select-row, .dur-override', function () {
    const $row = $(this).closest('tr');
    const oldRank = $row.data('rank');
    const newRank = $row.find('.rank-input').val();
    const venueId = $row.find('.venue-select-row').val();
    const duration = $row.find('.dur-override').val();

    if (oldRank !== newRank) {
      delete rankVenueMap[oldRank];
    }

    rankVenueMap[newRank] = { venue_id: venueId, duration: duration || '' };
    $row.data('rank', newRank);
  });

  // =====================================================
  // INIT
  // =====================================================
  const today = new Date();
  const pad = n => n.toString().padStart(2, '0');
  const dateStr = `${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`;
  const startStr = `${dateStr} 08:00`;
  const endStr = `${dateStr} 18:00`;

  // Set input values directly
  $('#start').val(startStr);
  $('#end').val(endStr);

  // Now initialize flatpickr
  flatpickr('#start', fpOpts);
  flatpickr('#end', fpOpts);
  $('#btnReload').on('click', loadData);

  loadData();

});
