/**
 * RR Scores module — score modal open/save/delete.
 *
 * Depends on: AdminApi, AdminToast, AdminState, AdminRoutes, AdminLoading
 */

(function ($, root) {
  'use strict';

  // ─── Modal DOM refs (cached once) ────────────────────────────────
  var $form        = $('#rr-score-modal-form');
  var $fixtureId   = $('#rrm-fixture-id');
  var $matchLabel  = $('#rrm-match-label');

  // ─── Lock helper ──────────────────────────────────────────────────
  function _isLocked() {
    if (root.RR_PERMISSIONS) return root.RR_PERMISSIONS.canEditScores === false;
    return root.RR_DRAW_LOCKED === true;
  }
  function _warnLocked() { AdminToast.warning('Draw is locked. Unlock the draw to make changes.'); }

  // ─── Open modal ───────────────────────────────────────────────────
  function open(id, home, away) {
    if (_isLocked()) { _warnLocked(); return; }
    $fixtureId.val(id);
    $matchLabel.html('<b>' + home + '</b> vs <b>' + away + '</b>');

    $('#set1-p1-label, #set2-p1-label, #set3-p1-label').text(home);
    $('#set1-p2-label, #set2-p2-label, #set3-p2-label').text(away);

    // Pre-fill existing score if available
    _prefillScore(id);

    new bootstrap.Modal(document.getElementById('rrScoreModal')).show();
  }

  function _prefillScore(id) {
    // Clear all inputs first
    $('#set1-p1,#set1-p2,#set2-p1,#set2-p2,#set3-p1,#set3-p2').val('');

    var fixtures = AdminState.getFixtures();
    var fx = null;
    for (var gid in fixtures) {
      fx = (fixtures[gid] || []).find(function (f) { return f && f.id == id; });
      if (fx) break;
    }
    if (!fx || !fx.all_sets || !fx.all_sets.length) return;

    fx.all_sets.forEach(function (s, i) {
      var parts = String(s).split('-').map(Number);
      var p1 = parts[0], p2 = parts[1];
      var setNum = i + 1;
      if (setNum > 3) return;
      $('#set' + setNum + '-p1').val(p1);
      $('#set' + setNum + '-p2').val(p2);
    });
  }

  // ─── Read set inputs ──────────────────────────────────────────────
  function _readSets() {
    var sets = [];
    var err  = false;

    [1, 2, 3].forEach(function (n) {
      var v1 = $('#set' + n + '-p1').val().trim();
      var v2 = $('#set' + n + '-p2').val().trim();
      if (v1 === '' && v2 === '') return; // blank set — skip
      if (v1 === '' || v2 === '') {
        AdminToast.error('Please complete both values for Set ' + n + '.');
        err = true;
        return;
      }
      sets.push(v1 + '-' + v2);
    });

    if (err) return null;
    return sets;
  }

  // ─── Save score ───────────────────────────────────────────────────
  function _save(e) {
    e.preventDefault();
    if (_isLocked()) { _warnLocked(); return; }

    var fixtureId = $fixtureId.val();
    if (!fixtureId) { AdminToast.error('No fixture selected.'); return; }

    var sets = _readSets();
    if (sets === null) return; // validation error already shown
    if (!sets.length) { AdminToast.error('Please enter at least one valid set.'); return; }

    var $btn    = $form.find('[type="submit"]');
    var restore = AdminLoading.button($btn, 'Saving…');

    AdminApi.post(AdminRoutes.score(fixtureId), { sets: sets })
      .then(function (res) {
        AdminToast.success('Score saved');
        _handleScoreResponse(res, fixtureId);
        _closeModal();
      })
      .catch(function (err) {
        AdminToast.error(err.message || 'Error saving score');
      })
      .then(function () { restore(); });
  }

  // ─── Delete score ─────────────────────────────────────────────────
  function _delete() {
    if (_isLocked()) { _warnLocked(); return; }
    var fixtureId = $fixtureId.val();
    if (!fixtureId) { AdminToast.warning('No fixture selected.'); return; }

    if (!window.confirm('Delete the score for this match?')) return;

    var $btn    = $('#rrm-delete-score');
    var restore = AdminLoading.button($btn, 'Deleting…');

    AdminApi.delete(AdminRoutes.scoreDelete(fixtureId))
      .then(function (res) {
        AdminToast.success('Score deleted');
        _handleScoreResponse(res, fixtureId);
        AdminState.scoreDeleted(fixtureId);
        _closeModal();
      })
      .catch(function (err) {
        AdminToast.error(err.message || 'Error deleting score');
      })
      .then(function () { restore(); });
  }

  // ─── Handle server response (sync state) ─────────────────────────
  function _handleScoreResponse(res, fixtureId) {
    if (res.rrFixtures)  AdminState.setFixtures(res.rrFixtures);
    if (res.standings)   AdminState.setStandings(res.standings);
    if (res.oop) {
      AdminState.setOop(_normaliseOop(res.oop));
    }

    var mode = res.mode || 'RR';
    AdminState.scoreSaved(res.fixture, mode);
  }

  function _normaliseOop(raw) {
    return (raw || []).map(function (fx) {
      return {
        id:             fx.id,
        stage:          fx.stage   || '',
        round:          fx.round   || fx.round_nr  || '',
        match_nr:       fx.match_nr || '',
        time:           fx.time    || '',
        home:           fx.home    || fx.home_name || fx.name1 || '',
        away:           fx.away    || fx.away_name || fx.name2 || '',
        score:          fx.score   || '',
        winner:         fx.winner_registration || fx.winner || null,
        r1_id:          fx.r1_id,
        r2_id:          fx.r2_id,
        group_id:       fx.group_id    || null,
        group_name:     fx.group_name  || '',
        playoff_type:   fx.playoff_type || null,
        winner_feeders: fx.winner_feeders || [],
        loser_feeders:  fx.loser_feeders  || []
      };
    });
  }

  // ─── Close modal ──────────────────────────────────────────────────
  function _closeModal() {
    var el  = document.getElementById('rrScoreModal');
    var bsm = el ? bootstrap.Modal.getInstance(el) : null;
    if (bsm) bsm.hide();
    $('#set1-p1,#set1-p2,#set2-p1,#set2-p2,#set3-p1,#set3-p2').val('');
    $fixtureId.val('');
    $matchLabel.html('');
  }

  // ─── Bind DOM events ─────────────────────────────────────────────
  function bind() {
    // Matrix cell click
    $(document).on('click', '.rr-score-cell', function () {
      open($(this).data('fixture-id'), $(this).data('home'), $(this).data('away'));
    });

    // OOP button
    $(document).on('click', '.rr-open-score-modal', function (e) {
      e.preventDefault();
      open($(this).data('fixture-id'), $(this).data('home'), $(this).data('away'));
    });

    // Bracket SVG button
    $(document).on('click', '.bracket-score-btn', function () {
      open($(this).data('fixture-id'), $(this).data('home'), $(this).data('away'));
    });

    // Form submit
    $form.on('submit', _save);

    // Delete button
    $(document).on('click', '#rrm-delete-score', _delete);
  }

  // ─── Public API ───────────────────────────────────────────────────
  root.RRScores = { open: open, bind: bind };

  // Auto-bind on DOM ready
  $(function () { bind(); });

}(jQuery, window));
