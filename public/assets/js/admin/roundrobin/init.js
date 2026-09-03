/**
 * RR Admin — main entry point.
 *
 * Boots all RR modules in the correct order, wires AdminState from
 * the Blade-injected globals, and supplies the shared state used by setup and print views.
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
    if (root.RRScores)      RRScores.bind();      // bind() is idempotent
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
    // Setup and print views read these globals.
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
  });

}(jQuery, window));
