/**
 * RR Matrix module — render the round-robin grid and update cells on score.
 *
 * Listens for rr:fixtures:updated and score:saved events from AdminState.
 * Exposes RRMatrix.render() for tab activation / manual refresh.
 *
 * Depends on: AdminState
 */

(function ($, root) {
  'use strict';

  // ─── Fixture normalizer (shared util) ────────────────────────────
  function normalizeFixture(f) {
    if (!f) return f;
    f.id    = parseInt(f.id    || 0, 10);
    f.r1_id = parseInt(f.r1_id || 0, 10);
    f.r2_id = parseInt(f.r2_id || 0, 10);
    f.time  = f.time || null;

    if ((f.home_score == null || f.away_score == null) && f.score) {
      var parts = String(f.score).trim().split(' ');
      var last  = parts[parts.length - 1];
      if (last && last.includes('-')) {
        var ns = last.split('-').map(Number);
        if (!isNaN(ns[0]) && !isNaN(ns[1])) { f.home_score = ns[0]; f.away_score = ns[1]; }
      }
    }

    if (!f.all_sets || !Array.isArray(f.all_sets)) {
      f.all_sets = f.score
        ? String(f.score).trim().split(' ').filter(function (s) { return s.includes('-'); })
        : [];
    }

    return f;
  }

  function normalizeAll(fixtures) {
    var out = {};
    for (var gid in fixtures) {
      out[gid] = (fixtures[gid] || []).map(normalizeFixture);
    }
    return out;
  }

  // ─── Score cell formatter ─────────────────────────────────────────
  function formatScoreCell(fx, rowPlayerId) {
    if (!fx || !fx.all_sets || !fx.all_sets.length) return '';

    var display = fx.all_sets.map(function (setStr) {
      var p = setStr.split('-').map(Number);
      return fx.r2_id === rowPlayerId ? (p[1] + '-' + p[0]) : (p[0] + '-' + p[1]);
    });

    var last = display[display.length - 1];
    var ab   = last.split('-').map(Number);
    var cls  = ab[0] > ab[1] ? 'rr-win' : ab[1] > ab[0] ? 'rr-loss' : '';

    return '<span class="' + cls + '">' + display.join(', ') + '</span>';
  }

  function _formatTime(dtString) {
    if (!dtString) return '';
    try {
      var dt = new Date(dtString.replace(' ', 'T'));
      return dt.toLocaleString('en-GB', { weekday: 'short', hour: '2-digit', minute: '2-digit' }).replace(',', '');
    } catch (e) { return dtString; }
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char];
    });
  }

  function findPlayer(players, id, fallback) {
    return players.find(function (player) { return player.id === parseInt(id || 0, 10); }) || {
      id: parseInt(id || 0, 10),
      name: fallback || 'Player'
    };
  }

  function scoreButton(fx, rowPlayer, columnPlayer, mobile) {
    var scoreHtml = formatScoreCell(fx, rowPlayer.id);
    var resultText = (fx.all_sets || []).join(', ');
    var content;
    var actionLabel;

    if (scoreHtml) {
      content = mobile
        ? '<span class="rr-mobile-status">Result: ' + scoreHtml + '</span>'
        : '<span class="rr-cell-score">' + scoreHtml + '</span><span class="rr-cell-hint">Update result</span>';
      actionLabel = 'Update score for ' + rowPlayer.name + ' versus ' + columnPlayer.name + '. Current result ' + resultText;
    } else if (fx.time) {
      var time = escapeHtml(_formatTime(fx.time));
      content = mobile
        ? '<span class="rr-mobile-status">Scheduled ' + time + ' · Enter result</span>'
        : '<span class="rr-cell-time">' + time + '</span><span class="rr-cell-hint">Enter result</span>';
      actionLabel = 'Enter score for ' + rowPlayer.name + ' versus ' + columnPlayer.name + ', scheduled ' + _formatTime(fx.time);
    } else {
      content = mobile
        ? '<span class="rr-mobile-status">Enter score</span>'
        : '<span class="rr-cell-action">Enter score</span>';
      actionLabel = 'Enter score for ' + rowPlayer.name + ' versus ' + columnPlayer.name;
    }

    var classes = mobile ? 'rr-score-cell rr-mobile-match' : 'rr-score-cell rr-score-action';
    var players = mobile
      ? '<span class="rr-mobile-players"><span>' + escapeHtml(rowPlayer.name) + '</span><span class="rr-mobile-versus">vs</span><span>' + escapeHtml(columnPlayer.name) + '</span></span>'
      : '';

    return '<button type="button" class="' + classes + '"' +
      ' data-fixture-id="' + fx.id + '"' +
      ' data-home="' + escapeHtml(rowPlayer.name) + '"' +
      ' data-away="' + escapeHtml(columnPlayer.name) + '"' +
      ' aria-label="' + escapeHtml(actionLabel) + '">' + players + content + '</button>';
  }

  // ─── Render full matrix ───────────────────────────────────────────
  function render() {
    var fixtures = AdminState.getFixtures();
    var groups   = AdminState.getGroups();

    var $wrapper = $('#rr-matrix-wrapper');
    if (!$wrapper.length) return;

    $wrapper.empty().addClass('rr-matrix-scroll');

    if (!groups || !groups.length) {
      $wrapper.html('<div class="text-muted text-center py-4">No groups configured yet.</div>');
      return;
    }

    // Normalize once
    fixtures = normalizeAll(fixtures);
    AdminState.setFixtures(fixtures);

    var totalFixtures = 0;
    var completedFixtures = 0;

    groups.forEach(function (group) {
      var gid      = group.id;
      var gFix     = (fixtures && fixtures[gid]) ? fixtures[gid] : [];

      var players = (group.registrations || []).map(function (r) {
        return { id: r.id, name: r.display_name || 'N/A', seed: r.pivot ? (r.pivot.seed || 9999) : 9999 };
      }).sort(function (a, b) { return a.seed - b.seed; });

      var completed = gFix.filter(function (fx) { return fx.all_sets && fx.all_sets.length; }).length;
      totalFixtures += gFix.length;
      completedFixtures += completed;

      var html = '<section class="rr-group-section" aria-labelledby="rr-group-' + gid + '">' +
        '<div class="rr-group-heading"><h6 id="rr-group-' + gid + '" class="fw-bold">Box ' + escapeHtml(group.name) + '</h6>' +
        '<span class="rr-group-meta">' + players.length + ' players · ' + gFix.length + ' matches · ' + completed + ' completed</span></div>' +
        '<div class="rr-matrix-scroll rr-matrix-table-shell"><table class="table table-bordered table-sm rr-matrix-table" aria-describedby="rr-matrix-help">' +
        '<caption class="visually-hidden">Round robin results for Box ' + escapeHtml(group.name) + '</caption>' +
        '<thead><tr><th scope="col" aria-label="Player"></th>' +
        players.map(function (p) { return '<th scope="col" class="text-center" title="' + escapeHtml(p.name) + '">' + escapeHtml(p.name) + '</th>'; }).join('') +
        '</tr></thead><tbody>';

      players.forEach(function (rowP) {
        html += '<tr><th scope="row" class="small" title="' + escapeHtml(rowP.name) + '">' + escapeHtml(rowP.name) + '</th>';

        players.forEach(function (colP) {
          if (rowP.id === colP.id) { html += '<td class="bg-diagonal" aria-label="Same player; no match"></td>'; return; }

          var fx = gFix.find(function (f) {
            return (f.r1_id === rowP.id && f.r2_id === colP.id) ||
                   (f.r1_id === colP.id && f.r2_id === rowP.id);
          });

          if (!fx) { html += '<td class="text-center text-muted" aria-label="Match not generated">Not generated</td>'; return; }

          html += '<td class="rr-match-cell">' + scoreButton(fx, rowP, colP, false) + '</td>';
        });

        html += '</tr>';
      });

      html += '</tbody></table></div><div class="rr-mobile-match-list">';
      gFix.forEach(function (fx) {
        var p1 = findPlayer(players, fx.r1_id, fx.name1);
        var p2 = findPlayer(players, fx.r2_id, fx.name2);
        html += scoreButton(fx, p1, p2, true);
      });
      if (!gFix.length) html += '<p class="text-muted small mb-0">No matches have been generated for this box.</p>';
      html += '</div></section>';
      $wrapper.append(html);
    });

    $('#rr-matrix-status').text(completedFixtures + ' of ' + totalFixtures + ' matches completed');
  }

  // ─── Live cell update (no full re-render) ─────────────────────────
  function updateCell(fx) {
    render();
  }

  // ─── Bind ─────────────────────────────────────────────────────────
  function bind() {
    // Re-render matrix on tab activation (once)
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
      if ($(e.target).attr('id') === 'matrix-tab') {
        if (!root.__RR_MATRIX_RENDERED) {
          render();
          root.__RR_MATRIX_RENDERED = true;
        }
      }
    });

    // Full re-render after any fixture update
    AdminState.on('rr:fixtures:updated', function () {
      render();
    });

    // Lightweight cell update after score save
    AdminState.on('score:saved', function (e) {
      if (e.detail && e.detail.mode === 'RR' && e.detail.fixture) {
        // Full re-render is simplest and avoids stale cell state
        render();
        $('#rr-matrix-status').prepend('Result saved. ');
      }
    });
  }

  function init() {
    bind();
    // Expose for legacy compatibility and tab event
    root.renderMatrixFallback = render;
    root.RR_INIT = function () { render(); root.__RR_MATRIX_RENDERED = true; };
  }

  root.RRMatrix = { init: init, render: render, updateCell: updateCell, formatScoreCell: formatScoreCell, normalizeFixture: normalizeFixture };

}(jQuery, window));
