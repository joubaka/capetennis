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

    groups.forEach(function (group) {
      var gid      = group.id;
      var gFix     = (fixtures && fixtures[gid]) ? fixtures[gid] : [];

      var players = (group.registrations || []).map(function (r) {
        return { id: r.id, name: r.display_name || 'N/A', seed: r.pivot ? (r.pivot.seed || 9999) : 9999 };
      }).sort(function (a, b) { return a.seed - b.seed; });

      var html = '<h6 class="fw-bold mt-3 mb-2">Box ' + group.name + '</h6>' +
        '<div class="rr-matrix-scroll mb-4"><table class="table table-bordered table-sm rr-matrix-table">' +
        '<thead><tr><th class="bg-light"></th>' +
        players.map(function (p) { return '<th class="text-center">' + p.name + '</th>'; }).join('') +
        '</tr></thead><tbody>';

      players.forEach(function (rowP) {
        html += '<tr><th class="bg-light small">' + rowP.name + '</th>';

        players.forEach(function (colP) {
          if (rowP.id === colP.id) { html += '<td class="bg-diagonal"></td>'; return; }

          var fx = gFix.find(function (f) {
            return (f.r1_id === rowP.id && f.r2_id === colP.id) ||
                   (f.r1_id === colP.id && f.r2_id === rowP.id);
          });

          if (!fx) { html += '<td class="text-center text-muted">–</td>'; return; }

          var scoreHtml = formatScoreCell(fx, rowP.id);
          var content   = scoreHtml || (fx.time ? _formatTime(fx.time) : '–');

          html += '<td class="text-center rr-score-cell"' +
            ' data-fixture-id="' + fx.id + '"' +
            ' data-home="' + rowP.name + '"' +
            ' data-away="' + colP.name + '">' +
            content + '</td>';
        });

        html += '</tr>';
      });

      html += '</tbody></table></div>';
      $wrapper.append(html);
    });
  }

  // ─── Live cell update (no full re-render) ─────────────────────────
  function updateCell(fx) {
    var groups = AdminState.getGroups();
    groups.forEach(function (group) {
      group.registrations.forEach(function (rowReg) {
        group.registrations.forEach(function (colReg) {
          if (rowReg.id === colReg.id) return;
          if (!((fx.r1_id == rowReg.id && fx.r2_id == colReg.id) ||
                (fx.r1_id == colReg.id && fx.r2_id == rowReg.id))) return;

          var $cell = $('.rr-score-cell[data-fixture-id="' + fx.id + '"]');
          if (!$cell.length) return;

          var scoreHtml = formatScoreCell(fx, rowReg.id);
          $cell.html(scoreHtml || '–');
        });
      });
    });
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
