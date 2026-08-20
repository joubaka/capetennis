/**
 * RR Order of Play module — render and save the OOP table.
 *
 * Listens for rr:oop:updated events from AdminState.
 * Exposes renderOrderOfPlay() for tab activation.
 *
 * Depends on: AdminApi, AdminToast, AdminState, AdminRoutes, AdminLoading
 */

(function ($, root) {
  'use strict';

  var DRAW_ID = null;

  // Stage labels shared with print module
  var STAGE_LABELS = {
    RR:     'Round Robin',
    MAIN:   'Main Draw',
    PLATE:  'Plate',
    CONS:   'Consolation',
    BOWL:   'Bowl',
    SHIELD: 'Shield',
    SPOON:  'Spoon'
  };

  // ─── Feeder label helper (also used by print module) ─────────────
  function feederLabel(fx, slot) {
    if (fx.stage === 'RR') return '';
    var wf  = fx.winner_feeders || [];
    var lf  = fx.loser_feeders  || [];
    var idx = (slot === 'home') ? 0 : 1;
    var playerName = (slot === 'home') ? fx.home : fx.away;

    if (playerName && playerName !== 'TBD' && playerName !== '---') return '';

    if (wf.length >= 2)        return '<small style="color:#0d6efd;">W' + wf[idx] + '</small>';
    if (wf.length === 1 && lf.length >= 1) {
      return idx === 0
        ? '<small style="color:#0d6efd;">W' + wf[0] + '</small>'
        : '<small style="color:#e65100;">L' + lf[0] + '</small>';
    }
    if (lf.length >= 2) return '<small style="color:#e65100;">L' + lf[idx] + '</small>';
    if (lf.length === 1 && idx === 0) return '<small style="color:#e65100;">L' + lf[0] + '</small>';
    return '';
  }

  // ─── Render table ─────────────────────────────────────────────────
  function render() {
    var oop   = AdminState.getOop();
    var $body = $('#rr-order-table tbody');
    if (!$body.length) return;

    if (!oop || !oop.length) {
      $body.html('<tr><td colspan="9" class="text-muted text-center">No fixtures…</td></tr>');
      return;
    }

    // Sort: RR by round→group→match_nr, then playoff fixtures
    var rrFx = oop.filter(function (f) { return f.stage === 'RR' || !f.stage; })
      .slice().sort(function (a, b) {
        if (a.round    !== b.round)    return (a.round    || 0) - (b.round    || 0);
        if (a.group_id !== b.group_id) return (a.group_id || 0) - (b.group_id || 0);
        return (a.match_nr || 0) - (b.match_nr || 0);
      });
    var otherFx = oop.filter(function (f) { return f.stage && f.stage !== 'RR'; });
    var sorted  = rrFx.concat(otherFx);

    var search = String($('#rr-ops-search').val() || '').toLowerCase().trim();
    var status = $('#rr-ops-status').val() || 'all';
    var court = $('#rr-ops-court').val() || 'all';
    var courts = {};
    sorted.forEach(function (fx) { if (fx.court || fx.venue_name) courts[fx.court || 'Unassigned'] = true; });
    var $court = $('#rr-ops-court');
    if ($court.length) {
      var current = $court.val();
      $court.find('option:not(:first)').remove();
      Object.keys(courts).sort().forEach(function (value) { $court.append($('<option>', { value: value, text: value })); });
      $court.val(current && courts[current] ? current : 'all');
      court = $court.val() || 'all';
    }

    var html = '';
    sorted.forEach(function (fx) {
      var isCompleted = !!fx.score;
      var haystack = [fx.id, fx.home, fx.away, fx.group_name, fx.court].join(' ').toLowerCase();
      if (search && haystack.indexOf(search) === -1) return;
      if (status === 'completed' && !isCompleted) return;
      if (status === 'upcoming' && isCompleted) return;
      if (court !== 'all' && (fx.court || 'Unassigned') !== court) return;
      var p1Cls = '', p2Cls = '';
      if (fx.winner) {
        if (fx.winner == fx.r1_id) { p1Cls = 'bg-success text-white'; p2Cls = 'bg-danger text-white'; }
        else                       { p1Cls = 'bg-danger text-white';  p2Cls = 'bg-success text-white'; }
      }

      html += '<tr data-fixture-id="' + fx.id + '">' +
        '<td>' + fx.id + '</td>' +
        '<td class="' + p1Cls + '">' + (fx.home || '---') + '</td>' +
        '<td class="text-center">vs</td>' +
        '<td class="' + p2Cls + '">' + (fx.away || '---') + '</td>' +
        '<td class="text-center">' + (fx.round || '') + '</td>' +
        '<td class="text-center">' + (fx.group_name ? 'Box ' + fx.group_name : (fx.stage || '')) + '</td>' +
        '<td class="text-center d-none d-sm-table-cell">' + (fx.time || '') + '</td>' +
        '<td class="text-center fw-bold">' + (fx.score || '<span class="badge bg-label-warning">Upcoming</span>') + '</td>' +
        '<td class="text-center">' +
          '<button class="btn btn-sm btn-primary rr-open-score-modal"' +
          ' data-fixture-id="' + fx.id + '"' +
          ' data-home="' + (fx.home || '') + '"' +
          ' data-away="' + (fx.away || '') + '">' +
          '<i class="ti ti-ball-tennis"></i></button>' +
        '</td>' +
        '</tr>';
    });

    $body.html(html);
  }

  // ─── Save order ───────────────────────────────────────────────────
  function saveOrder() {
    var $btn    = $('#rr-save-order-btn');
    var restore = AdminLoading.button($btn, 'Saving…');

    var order = [];
    $('#rr-order-table tbody tr').each(function () {
      order.push($(this).data('fixture-id'));
    });

    AdminApi.post(AdminRoutes.drawUrl(DRAW_ID, '/save-order'), { order: order })
      .then(function () { AdminToast.success('Order saved'); })
      .catch(function () { AdminToast.error('Failed to save order'); })
      .then(function () { restore(); });
  }

  // ─── Bind ─────────────────────────────────────────────────────────
  function bind() {
    $(document).on('click', '#rr-save-order-btn', saveOrder);
    $(document).on('input change', '#rr-ops-search, #rr-ops-status, #rr-ops-court', render);
    $(document).on('click', '#rr-refresh-ops-btn', function () {
      var $btn = $(this);
      $btn.prop('disabled', true);
      var request = window.AdminState && AdminState.refresh ? AdminState.refresh() : Promise.reject(new Error('Refresh unavailable.'));
      request.then(function () { AdminToast.success('Live operations refreshed'); })
        .catch(function (err) { AdminToast.error(err.message || 'Refresh failed'); })
        .then(function () { $btn.prop('disabled', false); render(); });
    });

    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
      if ($(e.target).attr('id') === 'oop-tab') { render(); }
    });

    AdminState.on('rr:oop:updated', function () { render(); });
  }

  function init(drawId) {
    DRAW_ID = drawId;
    bind();
    // Expose for tab activation and legacy shims
    root.renderOrderOfPlay = render;
  }

  root.RROOP          = { init: init, render: render, feederLabel: feederLabel };
  root.STAGE_LABELS   = STAGE_LABELS;

}(jQuery, window));
