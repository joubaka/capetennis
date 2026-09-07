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
  var recoverySets = null;
  var recoveryPreview = null;

  // ─── Open modal ───────────────────────────────────────────────────
  function open(id, home, away) {
    if (!root.RR_CAN_SCORE || root.RR_DRAW_LOCKED) { AdminToast.warning('You cannot edit scores in this draw state.'); return; }
    var match = AdminState.getOop().find(function(f) { return f.id == id; });
    if (!match) {
      Object.values(AdminState.getFixtures()).some(function(rows) { match = rows.find(function(f) { return f.id == id; }); return !!match; });
    }
    if (match) { home = match.home || match.name1 || home; away = match.away || match.name2 || away; }
    $fixtureId.val(id);
    $matchLabel.text(home + ' vs ' + away);

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
    if (!fx) fx = AdminState.getOop().find(function(f) { return f.id == id; });
    if (fx && !fx.all_sets) fx = Object.assign({}, fx, {all_sets: String(fx.score || '').split(', ').filter(Boolean)});
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
        if (err.status === 409 && /tournament recovery|generated playoff bracket/i.test(err.message || '')) {
          recoverySets = sets.map(function (set) { return set.split('-').map(Number); });
          $('#rrm-open-recovery').removeClass('d-none');
        }
      })
      .then(function () { restore(); });
  }

  // ─── Delete score ─────────────────────────────────────────────────
  function _delete() {
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
        if (err.status === 409 && /tournament recovery|generated playoff bracket/i.test(err.message || '')) {
          $('#rrm-open-recovery').removeClass('d-none');
        }
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
        court: fx.court || '',
        venue_name: fx.venue_name || '',
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
    $('#rrm-open-recovery').addClass('d-none');
  }

  function _escape(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
  }

  function _openRecovery() {
    var fixtureId = $fixtureId.val();
    var sets = _readSets();
    if (!fixtureId || sets === null || !sets.length) {
      AdminToast.error('Enter the corrected completed result before opening recovery.');
      return;
    }
    recoverySets = sets.map(function (set) { return set.split('-').map(Number); });
    var restore = AdminLoading.button($('#rrm-open-recovery'), 'Checking…');
    AdminApi.postJson(root.RR_RECOVERY.preview, { fixture_id: Number(fixtureId) })
      .then(function (response) {
        recoveryPreview = response;
        var impact = response.impact;
        $('#rr-recovery-impact').html(
          '<p class="mb-2"><strong>' + _escape(impact.effect) + '</strong></p>' +
          '<ul class="mb-0">' +
          '<li>Playoff fixtures reset: ' + impact.playoff_fixtures + '</li>' +
          '<li>Played playoff results reset: ' + impact.scored_playoff_fixtures + '</li>' +
          '<li>Scheduled playoff matches removed for review: ' + impact.scheduled_playoff_fixtures + '</li>' +
          '</ul>' +
          (impact.requires_super_user ? '<p class="text-danger fw-bold mt-2 mb-0">A super-user must apply this recovery because playoff results already exist.</p>' : '')
        );
        $('#rr-recovery-error, #rr-recovery-reason, #rr-recovery-confirmation').val('').text('');
        var scoreModal = bootstrap.Modal.getInstance(document.getElementById('rrScoreModal'));
        if (scoreModal) scoreModal.hide();
        new bootstrap.Modal(document.getElementById('rrRecoveryModal')).show();
      })
      .catch(function (err) { AdminToast.error(err.message || 'Could not preview recovery.'); })
      .then(function () { restore(); });
  }

  function _applyRecovery(event) {
    event.preventDefault();
    if (!recoveryPreview || !recoverySets) return;
    var reason = $('#rr-recovery-reason').val().trim();
    var confirmation = $('#rr-recovery-confirmation').val().trim();
    if (reason.length < 10 || confirmation !== root.RR_RECOVERY.confirmation) {
      $('#rr-recovery-error').text('Provide a detailed reason and type the exact confirmation text.');
      return;
    }
    var restore = AdminLoading.button($('#rr-recovery-apply'), 'Applying recovery…');
    AdminApi.postJson(root.RR_RECOVERY.apply, {
      fixture_id: Number($fixtureId.val()),
      sets: recoverySets,
      reason: reason,
      fingerprint: recoveryPreview.fingerprint,
      confirmation: confirmation
    }).then(function (response) {
      AdminToast.success(response.message);
      window.location.reload();
    }).catch(function (err) {
      $('#rr-recovery-error').text(err.message || 'Recovery failed without changing the draw.');
      restore();
    });
  }

  function _restoreRecovery() {
    var button = $(this);
    var expected = String(button.data('confirmation') || '');
    var reason = window.prompt('Why must this recovery be reversed? Enter at least 10 characters.');
    if (reason === null) return;
    reason = reason.trim();
    if (reason.length < 10) {
      AdminToast.error('A detailed restore reason of at least 10 characters is required.');
      return;
    }
    var confirmation = window.prompt('Type ' + expected + ' exactly to restore the before snapshot.');
    if (confirmation !== expected) {
      AdminToast.error('The restore confirmation did not match. Nothing was changed.');
      return;
    }
    var restore = AdminLoading.button(button, 'Restoring…');
    AdminApi.postJson(button.data('url'), { reason: reason, confirmation: confirmation })
      .then(function (response) {
        AdminToast.success(response.message);
        window.location.reload();
      })
      .catch(function (err) {
        AdminToast.error(err.message || 'The snapshot could not be restored.');
        restore();
      });
  }

  // ─── Bind DOM events ─────────────────────────────────────────────
  var bound = false;
  function bind() {
    if (bound) return;
    bound = true;
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
    $(document).on('click', '#rrm-open-recovery', _openRecovery);
    $(document).on('click', '.rr-restore-recovery', _restoreRecovery);
    $('#rr-recovery-form').on('submit', _applyRecovery);
  }

  // ─── Public API ───────────────────────────────────────────────────
  root.RRScores = { open: open, bind: bind };

  // Auto-bind on DOM ready
  $(function () { bind(); });

}(jQuery, window));
