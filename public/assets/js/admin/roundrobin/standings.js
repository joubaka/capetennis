/**
 * RR Standings module — render standings tables from AdminState.
 *
 * Standings are pre-sorted by the canonical StandingsService on the server.
 * This module renders them in the order received; it does NOT re-sort.
 *
 * Listens for rr:standings:updated and rr:fixtures:updated events.
 * Can also be called manually: RRStandings.render()
 *
 * Depends on: AdminState
 */

(function ($, root) {
  'use strict';

  var $wrapper = null;

  function _sp(r) {
    var t = r.sets_won + r.sets_lost;
    return t > 0 ? r.sets_won / t : 0;
  }

  function _gp(r) {
    var t = (r.games_won || 0) + (r.games_lost || 0);
    return t > 0 ? (r.games_won || 0) / t : 0;
  }

  function render() {
    $wrapper = $wrapper || $('#rr-standings-wrapper');
    if (!$wrapper.length) return;

    var groups   = AdminState.getGroups();
    var standings = AdminState.getStandings();

    if (!groups || !groups.length) {
      $wrapper.html('<div class="text-muted text-center py-4">No groups configured yet.</div>');
      return;
    }

    $wrapper.html('');

    groups.forEach(function (group) {
      var gid = group.id;
      if (!standings[gid]) return;

      // Use server-provided order — already sorted by canonical StandingsService
      var rows = Object.values(standings[gid]);

      var html = [
        '<h6 class="fw-bold mt-4">Box ' + group.name + '</h6>',
        '<div class="table-responsive mb-2">',
        '<table class="table table-sm table-striped">',
        '<thead><tr>',
        '<th>#</th><th>Player</th>',
        '<th class="text-center">W</th>',
        '<th class="text-center">L</th>',
        '<th class="text-center">Sets %</th>',
        '<th class="text-center">Games %</th>',
        '<th class="text-center">TB</th>',
        '</tr></thead><tbody>'
      ].join('');

      rows.forEach(function (r, idx) {
        var totalSets  = r.sets_won + r.sets_lost;
        var setsPct    = totalSets > 0 ? ((r.sets_won / totalSets) * 100).toFixed(0) : '-';
        var totalGames = (r.games_won || 0) + (r.games_lost || 0);
        var gamesPct   = totalGames > 0 ? (((r.games_won || 0) / totalGames) * 100).toFixed(0) : '-';
        var tb         = r.tiebreak || '';
        var tbBadge    = tb ? '<span class="badge bg-warning text-dark" style="font-size:10px;">' + tb + '</span>' : '';

        var rowCls = idx === 0
          ? 'table-success fw-bold'
          : idx === rows.length - 1 ? 'table-danger' : 'table-light';

        html += '<tr class="' + rowCls + '">' +
          '<td>' + (idx + 1) + '</td>' +
          '<td>' + r.player + '</td>' +
          '<td class="text-center">' + r.wins + '</td>' +
          '<td class="text-center">' + r.losses + '</td>' +
          '<td class="text-center">' + setsPct + '%</td>' +
          '<td class="text-center">' + gamesPct + '%</td>' +
          '<td class="text-center">' + tbBadge + '</td>' +
          '</tr>';
      });

      html += '</tbody></table></div>';
      $wrapper.append(html);
    });
  }

  // React to state events
  AdminState.on('rr:standings:updated', function () { render(); });
  AdminState.on('rr:fixtures:updated',  function () { render(); });

  // Expose to tab activation handler
  root.renderStandings = render;
  root.RRStandings = { render: render };

  // Render on standings tab activation
  $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
    if ($(e.target).attr('id') === 'standings-tab') { render(); }
  });

}(jQuery, window));
