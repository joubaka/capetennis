/**
 * RR Standings module — render standings tables from AdminState.
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

  function _headToHead(a, b, fixtures) {
    var match = (fixtures || []).find(function (f) {
      return (f.r1_id === a.reg_id && f.r2_id === b.reg_id) ||
             (f.r1_id === b.reg_id && f.r2_id === a.reg_id);
    });
    if (!match || !match.winner) return 0;
    return match.winner === a.reg_id ? 1 : -1;
  }

  function _resolveGroup(grp, fixtures) {
    if (grp.length <= 1) return grp;

    if (grp.length === 2) {
      var hh = _headToHead(grp[0], grp[1], fixtures);
      if (hh !== 0) {
        grp[0].tiebreak = grp[0].tiebreak || 'H2H';
        grp[1].tiebreak = grp[1].tiebreak || 'H2H';
        return hh === 1 ? grp : [grp[1], grp[0]];
      }
      var dSets = _sp(grp[1]) - _sp(grp[0]);
      if (Math.abs(dSets) > 0.0001) {
        grp[0].tiebreak = grp[0].tiebreak || 'Sets %';
        grp[1].tiebreak = grp[1].tiebreak || 'Sets %';
        return dSets > 0 ? [grp[1], grp[0]] : grp;
      }
      var dGames = _gp(grp[1]) - _gp(grp[0]);
      if (Math.abs(dGames) > 0.0001) {
        grp[0].tiebreak = grp[0].tiebreak || 'Games %';
        grp[1].tiebreak = grp[1].tiebreak || 'Games %';
        return dGames > 0 ? [grp[1], grp[0]] : grp;
      }
      grp[0].tiebreak = grp[0].tiebreak || '=';
      grp[1].tiebreak = grp[1].tiebreak || '=';
      return grp;
    }

    grp.sort(function (a, b) {
      var dS = _sp(b) - _sp(a);
      if (Math.abs(dS) > 0.0001) return dS;
      return _gp(b) - _gp(a);
    });

    var resolved = [];
    var i = 0;
    while (i < grp.length) {
      var j = i + 1;
      while (j < grp.length &&
             Math.abs(_sp(grp[j]) - _sp(grp[i])) <= 0.0001 &&
             Math.abs(_gp(grp[j]) - _gp(grp[i])) <= 0.0001) {
        j++;
      }
      var subGroup = grp.slice(i, j);
      if (subGroup.length === grp.length) {
        resolved.push.apply(resolved, subGroup);
      } else {
        resolved.push.apply(resolved, _resolveGroup(subGroup, fixtures));
      }
      i = j;
    }
    return resolved;
  }

  function render() {
    $wrapper = $wrapper || $('#rr-standings-wrapper');
    if (!$wrapper.length) return;

    var groups   = AdminState.getGroups();
    var standings = AdminState.getStandings();
    var fixtures  = AdminState.getFixtures();

    if (!groups || !groups.length) {
      $wrapper.html('<div class="text-muted text-center py-4">No groups configured yet.</div>');
      return;
    }

    $wrapper.html('');

    groups.forEach(function (group) {
      var gid = group.id;
      if (!standings[gid]) return;

      var gFixtures = fixtures[gid] || [];
      var rows = Object.values(standings[gid]);

      // Sort by wins then resolve ties
      rows.sort(function (a, b) { return b.wins - a.wins; });

      var final = [];
      var i = 0;
      while (i < rows.length) {
        var j = i + 1;
        while (j < rows.length && rows[j].wins === rows[i].wins) j++;
        final.push.apply(final, _resolveGroup(rows.slice(i, j), gFixtures));
        i = j;
      }
      rows = final;

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
