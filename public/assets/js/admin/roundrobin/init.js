/**
 * RR Admin — main entry point.
 *
 * Boots all RR modules in the correct order, wires AdminState from
 * the Blade-injected globals, and preserves backward compatibility
 * with the legacy draw-roundrobin1.js during the transition period.
 *
 * Load order (guaranteed by Blade page-script section):
 *   1. core/api.js
 *   2. core/toast.js
 *   3. core/modal.js
 *   4. core/loading.js
 *   5. core/confirm.js
 *   6. core/routes.js
 *   7. core/state.js
 *   8. roundrobin/matrix.js
 *   9. roundrobin/scores.js
 *  10. roundrobin/standings.js
 *  11. roundrobin/oop.js
 *  12. roundrobin/groups.js
 *  13. roundrobin/schedule.js
 *  14. roundrobin/brackets.js
 *  15. roundrobin/state-badges.js
 *  16. roundrobin/init.js   ← this file
 */

(function ($, root) {
  'use strict';

  $(function () {

    // ── 1. Bail if not on the RR page ────────────────────────────
    if (!$('#round-robin-app').length) return;

    var drawId = $('#round-robin-app').data('draw-id');

    // ── 2. Boot AdminRoutes from Blade-injected globals ──────────
    if (root.AdminRoutes && root.RR_ROUTES) {
      AdminRoutes.init(root.RR_ROUTES);
    }

    // ── 3. Boot AdminState from Blade-injected globals ───────────
    if (root.AdminState) {
      AdminState.init({
        drawId:    drawId,
        locked:    !!root.RR_DRAW_LOCKED,
        published: !!root.RR_DRAW_PUBLISHED,
        engineMode: root.RR_ENGINE_MODE || 'legacy',
        fixtures:  root.RR_FIXTURES  || {},
        standings: root.RR_STANDINGS || {},
        oop:       root.RR_OOP       || [],
        groups:    root.RR_GROUPS    || []
      });
    }

    // ── 4. Init each module ───────────────────────────────────────
    if (root.RRMatrix)      RRMatrix.init();
    if (root.RRScores)      RRScores.bind();      // already auto-bound; no-op if called twice
    if (root.RRStandings)   { /* event-driven, no explicit init */ }
    if (root.RROOP)         RROOP.init(drawId);
    if (root.RRGroups)      RRGroups.init(drawId);
    if (root.RRSchedule)    RRSchedule.init(drawId);
    if (root.RRBrackets)    RRBrackets.init(drawId);
    if (root.RRStateBadges) RRStateBadges.init(drawId);

    // ── 5. Initial renders for the default active tab (Matrix) ───
    if (root.RRMatrix) {
      RRMatrix.render();
      root.__RR_MATRIX_RENDERED = true;
    }
    if (root.RROOP)       RROOP.render();
    if (root.RRStandings) RRStandings.render();

    // ── 6. Keep window globals in sync (legacy shims) ────────────
    // These are read by draw-roundrobin1.js during the transition.
    AdminState.on('rr:fixtures:updated', function (e) {
      root.RR_FIXTURES = e.detail.fixtures;
    });
    AdminState.on('rr:standings:updated', function (e) {
      root.RR_STANDINGS = e.detail.standings;
    });
    AdminState.on('rr:oop:updated', function (e) {
      root.RR_OOP = e.detail.oop;
    });
    AdminState.on('rr:groups:updated', function (e) {
      root.RR_GROUPS = e.detail.groups;
    });

    console.log('[RR Admin] Modular boot complete — draw', drawId);

    // ── 7. Load and render the draw status bar ───────────────────
    _loadStatusBar(drawId);

    // Refresh status bar whenever a score is saved/deleted
    $(document).on('rr:score:saved rr:score:deleted rr:groups:saved rr:rr:regenerated', function () {
      _loadStatusBar(drawId);
    });
  });

  function _loadStatusBar(drawId) {
    var url = (window.RR_ROUTES && window.RR_ROUTES.drawStatus)
      ? window.RR_ROUTES.drawStatus
      : (AdminRoutes.appUrl() + '/backend/draw/' + drawId + '/status');

    AdminApi.get(url).then(function (s) {
      _applyStatusBadge('#dss-groups',    s.groups_configured,  'Groups');
      _applyStatusBadge('#dss-fixtures',  s.fixtures_generated, 'Fixtures');
      $('#dss-rr').text('RR ' + s.rr_complete_pct + '%')
        .removeClass('bg-secondary bg-success bg-warning bg-danger')
        .addClass(s.rr_complete ? 'bg-success' : (s.rr_played > 0 ? 'bg-warning' : 'bg-secondary'));
      _applyStatusBadge('#dss-standings', s.standings_ready,    'Standings');
      _applyStatusBadge('#dss-brackets',  s.brackets_generated, 'Brackets');

      // Lock / publish
      $('#dss-lock').text(s.locked ? 'Locked' : 'Unlocked')
        .removeClass('bg-secondary bg-warning bg-danger')
        .addClass(s.locked ? 'bg-danger' : 'bg-secondary');
      if (s.published) { $('#dss-publish').show().removeClass('bg-secondary').addClass('bg-success'); }
      else             { $('#dss-publish').hide(); }

      // Warnings
      if (s.warnings && s.warnings.length) {
        var html = s.warnings.map(function (w) {
          return '<div class="small text-warning"><i class="ti ti-alert-triangle me-1"></i>' + w + '</div>';
        }).join('');
        $('#dss-warnings').html(html).show();
      } else {
        $('#dss-warnings').hide();
      }

      $('#draw-status-bar').show();
    }).catch(function () { /* silently skip if not available */ });
  }

  function _applyStatusBadge(sel, ok, label) {
    $(sel).text(label)
      .removeClass('bg-secondary bg-success bg-danger')
      .addClass(ok ? 'bg-success' : 'bg-secondary');
  }

}(jQuery, window));
