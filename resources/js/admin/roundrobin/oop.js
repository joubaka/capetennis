/** Match operations and persisted display order, independent of bracket match numbers. */
(function ($, root) {
  'use strict';
  let drawId,
    rows = [],
    orderDirty = false,
    busy = false;
  const esc = value =>
    $('<div>')
      .text(value == null ? '' : String(value))
      .html();
  function feederLabel(fx, slot) {
    const list = fx.winner_feeders || [],
      losers = fx.loser_feeders || [],
      index = slot === 'home' ? 0 : 1;
    return list[index] ? 'W' + list[index] : losers[index] ? 'L' + losers[index] : '';
  }
  function matches(f) {
    const query = String($('#rr-ops-search').val() || '').toLowerCase(),
      status = $('#rr-ops-status').val(),
      court = $('#rr-ops-court').val();
    return (
      (!query || [f.home, f.away, f.match_nr, f.id].join(' ').toLowerCase().includes(query)) &&
      (!court || court === 'all' || String(f.court) === court) &&
      (status === 'all' || !status || (status === 'completed' ? !!f.score : !f.score))
    );
  }
  function render() {
    const body = $('#rr-order-table tbody');
    let html = '';
    rows.forEach((f, index) => {
      html +=
        '<tr data-fixture-id="' +
        f.id +
        '"' +
        (!matches(f) ? ' style="display:none"' : '') +
        '><td>' +
        esc(f.match_nr || f.id) +
        '</td><td>' +
        esc(f.home || feederLabel(f, 'home') || 'TBD') +
        '</td><td class="text-center">vs</td><td>' +
        esc(f.away || feederLabel(f, 'away') || 'TBD') +
        '</td><td>' +
        esc(f.round) +
        '</td><td>' +
        esc(f.group_name || f.stage) +
        '</td><td class="d-none d-sm-table-cell">' +
        esc(f.time) +
        '<small class="d-block">' +
        esc(f.venue_name) +
        (f.court ? ' · Court ' + esc(f.court) : '') +
        '</small></td><td>' +
        esc(f.score) +
        '</td><td><div class="d-flex gap-1">';
      if (root.RR_CAN_SCHEDULE)
        html +=
          '<button type="button" class="btn btn-sm btn-outline-secondary rr-order-up" aria-label="Move match ' +
          esc(f.match_nr || f.id) +
          ' earlier"' +
          (!index ? ' disabled' : '') +
          '>↑</button><button type="button" class="btn btn-sm btn-outline-secondary rr-order-down" aria-label="Move match ' +
          esc(f.match_nr || f.id) +
          ' later"' +
          (index === rows.length - 1 ? ' disabled' : '') +
          '>↓</button>';
      html +=
        '<button type="button" class="btn btn-sm btn-primary rr-open-score-modal" data-fixture-id="' +
        f.id +
        '" data-home="' +
        esc(f.home) +
        '" data-away="' +
        esc(f.away) +
        '" aria-label="Score match ' +
        esc(f.match_nr || f.id) +
        '"' +
        (!root.RR_CAN_SCORE ? ' disabled' : '') +
        '>Score</button></div></td></tr>';
    });
    body.html(
      html ||
        '<tr><td colspan="9" class="text-muted text-center p-4">Generate fixtures from Players &amp; Groups to begin.</td></tr>'
    );
    $('#rr-save-order-btn')
      .prop('disabled', !orderDirty || busy || !root.RR_CAN_SCHEDULE)
      .text(busy ? 'Saving…' : orderDirty ? 'Save changed order' : 'Order saved');
  }
  function sync() {
    if (orderDirty) return;
    rows = (AdminState.getOop() || []).slice();
    const court = $('#rr-ops-court').val();
    $('#rr-ops-court')
      .html(
        '<option value="all">All courts</option>' +
          Array.from(new Set(rows.map(f => f.court).filter(Boolean)))
            .map(c => '<option value="' + esc(c) + '">Court ' + esc(c) + '</option>')
            .join('')
      )
      .val(court || 'all');
    render();
  }
  async function save() {
    if (busy || !orderDirty) return;
    busy = true;
    render();
    try {
      await AdminApi.request({
        url: AdminRoutes.drawUrl(drawId, '/save-order'),
        method: 'POST',
        json: true,
        retries: 0,
        data: { order: rows.map(f => f.id) }
      });
      orderDirty = false;
      await root.RRWorkspace.refreshHub();
      AdminToast.success('Order saved');
    } catch (error) {
      AdminToast.error(error.message);
    } finally {
      busy = false;
      render();
    }
  }
  function init(id) {
    drawId = id;
    sync();
    $('#rr-save-order-btn').on('click', save);
    $('#rr-ops-search').on('input', render);
    $('#rr-ops-status,#rr-ops-court').on('change', render);
    $(document).on('click', '.rr-order-up,.rr-order-down', function () {
      if (busy) return;
      const id = Number($(this).closest('tr').data('fixture-id')),
        index = rows.findIndex(f => Number(f.id) === id),
        next = index + ($(this).hasClass('rr-order-up') ? -1 : 1);
      if (next < 0 || next >= rows.length) return;
      [rows[index], rows[next]] = [rows[next], rows[index]];
      orderDirty = true;
      render();
    });
    AdminState.on('rr:oop:updated', sync);
    root.addEventListener('beforeunload', function (event) {
      if (orderDirty) {
        event.preventDefault();
        event.returnValue = '';
      }
    });
    root.renderOrderOfPlay = sync;
  }
  root.RROOP = { init: init, render: sync, feederLabel: feederLabel };
  root.STAGE_LABELS = {
    RR: 'Round Robin',
    MAIN: 'Main Draw',
    PLATE: 'Plate',
    CONS: 'Consolation',
    BOWL: 'Bowl',
    SHIELD: 'Shield',
    SPOON: 'Spoon'
  };
})(jQuery, window);
