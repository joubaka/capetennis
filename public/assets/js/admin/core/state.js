/**
 * AdminState — Lightweight client-side state layer for RR draw pages.
 *
 * Tracks draw state, fixtures, standings, schedule and lock/publish status.
 * Fires browser CustomEvents so modules can react independently.
 *
 * Events dispatched on document:
 *   rr:fixtures:updated   { detail: { fixtures } }
 *   rr:standings:updated  { detail: { standings } }
 *   rr:oop:updated        { detail: { oop } }
 *   rr:draw:locked        { detail: { locked, published } }
 *   rr:draw:published     { detail: { locked, published } }
 *   rr:groups:updated     { detail: { groups } }
 *   score:saved           { detail: { fixture, mode } }
 *   score:deleted         { detail: { fixtureId } }
 *   schedule:updated      { detail: { schedule } }
 *
 * Usage:
 *   AdminState.setFixtures(data);
 *   AdminState.getFixtures();
 *   AdminState.setLocked(true);
 *   AdminState.on('rr:standings:updated', fn);
 */

(function (root) {
  'use strict';

  var _state = {
    drawId:    null,
    locked:    false,
    published: false,
    engineMode: 'legacy',
    fixtures:  {},
    standings: {},
    oop:       [],
    groups:    [],
    schedule:  []
  };

  // ─── Internal event bus ────────────────────────────────────────────
  function dispatch(name, detail) {
    try {
      document.dispatchEvent(new CustomEvent(name, { detail: detail || {} }));
    } catch (e) {
      // IE11 fallback (unlikely but safe)
      var ev = document.createEvent('CustomEvent');
      ev.initCustomEvent(name, true, false, detail || {});
      document.dispatchEvent(ev);
    }
  }

  function on(name, fn) {
    document.addEventListener(name, fn);
  }

  function off(name, fn) {
    document.removeEventListener(name, fn);
  }

  // ─── Initialise from page globals ─────────────────────────────────
  function init(opts) {
    opts = opts || {};
    _state.drawId     = opts.drawId     || null;
    _state.locked     = !!opts.locked;
    _state.published  = !!opts.published;
    _state.engineMode = opts.engineMode || 'legacy';
    _state.fixtures   = opts.fixtures   || {};
    _state.standings  = opts.standings  || {};
    _state.oop        = opts.oop        || [];
    _state.groups     = opts.groups     || [];
  }

  // ─── Fixtures ─────────────────────────────────────────────────────
  function setFixtures(data) {
    _state.fixtures = data;
    dispatch('rr:fixtures:updated', { fixtures: data });
  }

  function getFixtures() { return _state.fixtures; }

  // ─── Standings ────────────────────────────────────────────────────
  function setStandings(data) {
    _state.standings = data;
    dispatch('rr:standings:updated', { standings: data });
  }

  function getStandings() { return _state.standings; }

  // ─── Order of Play ────────────────────────────────────────────────
  function setOop(data) {
    _state.oop = data;
    dispatch('rr:oop:updated', { oop: data });
  }

  function getOop() { return _state.oop; }

  // ─── Groups ───────────────────────────────────────────────────────
  function setGroups(data) {
    _state.groups = data;
    dispatch('rr:groups:updated', { groups: data });
  }

  function getGroups() { return _state.groups; }

  // ─── Lock / Publish ───────────────────────────────────────────────
  function setLocked(val) {
    _state.locked = !!val;
    dispatch('rr:draw:locked', { locked: _state.locked, published: _state.published });
    _syncDrawStateBadges();
  }

  function setPublished(val) {
    _state.published = !!val;
    dispatch('rr:draw:published', { locked: _state.locked, published: _state.published });
    _syncDrawStateBadges();
  }

  function isLocked()    { return _state.locked; }
  function isPublished() { return _state.published; }

  function _syncDrawStateBadges() {
    // Keep window globals in sync for legacy code during transition
    root.RR_DRAW_LOCKED    = _state.locked;
    root.RR_DRAW_PUBLISHED = _state.published;

    // Update badges
    var $lb = $('#badge-locked');
    if (_state.locked) {
      $lb.removeClass('d-none').addClass('bg-danger').html('<i class="ti ti-lock me-1"></i>Locked');
    } else {
      $lb.addClass('d-none');
    }

    var $pb = $('#badge-published');
    if (_state.published) {
      $pb.removeClass('d-none').addClass('bg-primary').html('<i class="ti ti-eye me-1"></i>Published');
    } else {
      $pb.addClass('d-none');
    }

    // Disable destructive actions when locked or published
    var disable = _state.locked || _state.published;
    $('[data-rr-destructive]').prop('disabled', disable).toggleClass('disabled', disable);
  }

  // ─── Score events ─────────────────────────────────────────────────
  function scoreSaved(fixture, mode) {
    dispatch('score:saved', { fixture: fixture, mode: mode });
  }

  function scoreDeleted(fixtureId) {
    dispatch('score:deleted', { fixtureId: fixtureId });
  }

  // ─── Schedule ─────────────────────────────────────────────────────
  function setSchedule(data) {
    _state.schedule = data;
    dispatch('schedule:updated', { schedule: data });
  }

  function getSchedule() { return _state.schedule; }

  // Fetch authoritative data for tournament-day refreshes.
  function refresh() {
    var hubUrl = root.AdminRoutes && AdminRoutes.get('hub');
    var scheduleUrl = root.AdminRoutes && AdminRoutes.get('scheduleSummary');
    if (!hubUrl) return Promise.reject(new Error('Draw refresh route is unavailable.'));

    var requests = [fetch(hubUrl, { headers: { 'Accept': 'application/json' } }).then(function (r) {
      if (!r.ok) throw new Error('Unable to refresh draw data.');
      return r.json();
    })];
    if (scheduleUrl) requests.push(fetch(scheduleUrl, { headers: { 'Accept': 'application/json' } }).then(function (r) {
      if (!r.ok) throw new Error('Unable to refresh schedule data.');
      return r.json();
    }));

    return Promise.all(requests).then(function (responses) {
      var hub = responses[0] || {};
      if (hub.rrFixtures !== undefined) setFixtures(hub.rrFixtures);
      if (hub.oop !== undefined) setOop(hub.oop);
      if (hub.standings !== undefined) setStandings(hub.standings);
      if (responses[1] && responses[1].schedule !== undefined) setSchedule(responses[1].schedule);
      return responses;
    });
  }

  // ─── Engine mode ──────────────────────────────────────────────────
  function getEngineMode() { return _state.engineMode; }
  function getDrawId()     { return _state.drawId; }

  // ─── Public API ───────────────────────────────────────────────────
  root.AdminState = {
    init:         init,
    on:           on,
    off:          off,

    setFixtures:  setFixtures,
    getFixtures:  getFixtures,

    setStandings: setStandings,
    getStandings: getStandings,

    setOop:       setOop,
    getOop:       getOop,

    setGroups:    setGroups,
    getGroups:    getGroups,

    setLocked:    setLocked,
    setPublished: setPublished,
    isLocked:     isLocked,
    isPublished:  isPublished,

    setSchedule:  setSchedule,
    getSchedule:  getSchedule,
    refresh:      refresh,

    scoreSaved:   scoreSaved,
    scoreDeleted: scoreDeleted,

    getEngineMode: getEngineMode,
    getDrawId:     getDrawId
  };

}(window));
