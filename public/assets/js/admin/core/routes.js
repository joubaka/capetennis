/**
 * AdminRoutes — Centralized route registry for RR draw pages.
 *
 * Populated from Blade on page load via window.RR_ROUTES.
 * Provides helper methods to avoid string concatenation across modules.
 *
 * Usage:
 *   AdminRoutes.get('hub')               → URL string
 *   AdminRoutes.score(fixtureId)         → POST URL for score save
 *   AdminRoutes.scoreDelete(fixtureId)   → DELETE URL for score
 */

(function (root) {
  'use strict';

  var _routes = {};

  /**
   * Initialise from the Blade-injected window.RR_ROUTES object.
   * Called once on page load by the RR draw page.
   */
  function init(routes) {
    _routes = routes || {};
  }

  /**
   * Get a raw route by key.
   * @param {string} key
   * @returns {string}
   */
  function get(key) {
    return _routes[key] || '';
  }

  /**
   * Score store URL with fixture ID substituted.
   * @param {string|number} fixtureId
   * @returns {string}
   */
  function score(fixtureId) {
    var base = _routes.scoreStore || _routes.legacyScoreStore || '';
    return base.replace('FIXTURE_ID', fixtureId);
  }

  /**
   * Score delete URL.
   * @param {string|number} fixtureId
   * @returns {string}
   */
  function scoreDelete(fixtureId) {
    var base = _routes.scoreDelete || _routes.legacyScoreDelete || '';
    return base.replace('FIXTURE_ID', fixtureId);
  }

  /**
   * App-level URL prefix (window.APP_URL).
   */
  function appUrl() {
    return root.APP_URL || root.location.origin;
  }

  /**
   * Draw-scoped URL helper.
   * @param {string|number} drawId
   * @param {string} path   – e.g. '/settings'
   */
  function drawUrl(drawId, path) {
    return appUrl() + '/backend/draw/' + drawId + (path || '');
  }

  root.AdminRoutes = {
    init:        init,
    get:         get,
    score:       score,
    scoreDelete: scoreDelete,
    appUrl:      appUrl,
    drawUrl:     drawUrl
  };

}(window));
