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
      $body.html('<tr><td colspan="10" class="text-muted text-center">No fixtures…</td></tr>');
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

    var html = '';
    sorted.forEach(function (fx) {
      var p1Cls = '', p2Cls = '';
      if (fx.winner) {
        if (fx.winner == fx.r1_id) { p1Cls = 'bg-success text-white'; p2Cls = 'bg-danger text-white'; }
        else                       { p1Cls = 'bg-danger text-white';  p2Cls = 'bg-success text-white'; }
      }

      var isBracket = fx.stage && fx.stage !== 'RR';

      // Player name cells — show feeder label when player is TBD
      var homeDisplay = (fx.home && fx.home !== 'TBD' && fx.home !== '---')
        ? (fx.home || '---')
        : feederLabel(fx, 'home') || (fx.home || '---');
      var awayDisplay = (fx.away && fx.away !== 'TBD' && fx.away !== '---')
        ? (fx.away || '---')
        : feederLabel(fx, 'away') || (fx.away || '---');

      // Stage/group column
      var stageCell = fx.group_name
        ? 'Box ' + fx.group_name
        : (STAGE_LABELS[fx.stage] || fx.stage || '');
      if (isBracket && fx.playoff_type) {
        stageCell += ' <span class="badge bg-secondary ms-1" style="font-size:10px;">' + fx.playoff_type + '</span>';
      }

      // Feeder summary column (bracket fixtures only)
      var feederCell = '';
      if (isBracket) {
        var wf = fx.winner_feeders || [];
        var lf = fx.loser_feeders  || [];
        var parts = [];
        wf.forEach(function (n) { parts.push('<span style="color:#0d6efd;font-weight:600;">W' + n + '</span>'); });
        lf.forEach(function (n) { parts.push('<span style="color:#e65100;font-weight:600;">L' + n + '</span>'); });
        feederCell = parts.join(' / ');
      }

      html += '<tr data-fixture-id="' + fx.id + '">' +
        '<td>' + fx.id + '</td>' +
        '<td class="' + p1Cls + '">' + homeDisplay + '</td>' +
        '<td class="text-center">vs</td>' +
        '<td class="' + p2Cls + '">' + awayDisplay + '</td>' +
        '<td class="text-center">' + (fx.round || '') + '</td>' +
        '<td class="text-center">' + stageCell + '</td>' +
        '<td class="text-center d-none d-sm-table-cell">' + (fx.time || '') + '</td>' +
        '<td class="text-center fw-bold">' + (fx.score || '') + '</td>' +
        '<td class="text-center">' + feederCell + '</td>' +
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
