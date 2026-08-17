(function webpackUniversalModuleDefinition(root, factory) {
	if(typeof exports === 'object' && typeof module === 'object')
		module.exports = factory();
	else if(typeof define === 'function' && define.amd)
		define([], factory);
	else {
		var a = factory();
		for(var i in a) (typeof exports === 'object' ? exports : root)[i] = a[i];
	}
})(self, function() {
return /******/ (function() { // webpackBootstrap
var __webpack_exports__ = {};
/*!**************************************************!*\
  !*** ./resources/js/pages/draw-fixtures-show.js ***!
  \**************************************************/
(function (window, $) {
  'use strict';

  console.log('🎬 Draw Fixtures Show JS booting...');
  $(function () {
    console.group('🚀 Team Fixtures JS Init');
    console.log('DOM Ready');
    console.groupEnd();
    var $scoreModal = $('#editScoreModal');
    var $scoreForm = $('#editScoreForm');
    var $teams = $('#fixtureTeams');
    var $playersModal = $('#editPlayersModal');
    var $playersForm = $('#editPlayersForm');
    var $playersTeams = $('#playersFixtureTeams');

    // ---------------------------
    // HELPERS
    // ---------------------------

    function logGroup(title) {
      var data = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;
      console.group(title);
      if (data) console.log(data);
      console.groupEnd();
    }
    function ajaxErrorHandler(context, xhr) {
      console.group("\u274C AJAX ERROR \u2192 ".concat(context));
      console.log('Status:', xhr.status);
      console.log('Response Text:', xhr.responseText);
      console.log('Response JSON:', xhr.responseJSON);
      console.groupEnd();
      alert("Error while processing ".concat(context, ". Check console."));
    }
    function updateWinnerClasses($row, winner) {
      console.group('🎨 Updating Winner Classes');
      console.log('Winner:', winner);
      var $home = $row.find('.home-cell');
      var $away = $row.find('.away-cell');
      $home.removeClass('winner-home loser-home draw-cell');
      $away.removeClass('winner-home loser-home draw-cell');
      if (winner === 'home') {
        $home.addClass('winner-home');
        $away.addClass('loser-home');
      } else if (winner === 'away') {
        $home.addClass('loser-home');
        $away.addClass('winner-home');
      } else if (winner === 'draw') {
        $home.addClass('draw-cell');
        $away.addClass('draw-cell');
      }
      console.groupEnd();
    }

    // ============================================================
    // SCORE EDIT
    // ============================================================

    $('.edit-score-btn').on('click', function () {
      var fixtureId = $(this).data('id');
      var home = $(this).data('home') || 'Home';
      var away = $(this).data('away') || 'Away';
      logGroup('🟢 OPEN SCORE MODAL', {
        fixtureId: fixtureId,
        home: home,
        away: away
      });
      $teams.text("".concat(home, " vs ").concat(away));
      var actionUrlFromAttr = $(this).data('action') || null;
      var actionUrl = actionUrlFromAttr || "{{ route('backend.team-fixtures.update', ':id') }}".replace(':id', fixtureId);
      console.log('Setting form action:', actionUrl);
      $scoreForm.data('fixture-id', fixtureId);
      $scoreForm.attr('action', actionUrl);

      // Prefill sets: prefer data-* on the clicked button (they are now rendered server-side)
      for (var i = 1; i <= 3; i++) {
        var _$$data, _$$data2;
        var homeVal = (_$$data = $(this).data("set".concat(i, "_home"))) !== null && _$$data !== void 0 ? _$$data : '';
        var awayVal = (_$$data2 = $(this).data("set".concat(i, "_away"))) !== null && _$$data2 !== void 0 ? _$$data2 : '';
        console.log("Set ".concat(i, ":"), homeVal, awayVal);
        $("#set".concat(i, "Home")).val(homeVal);
        $("#set".concat(i, "Away")).val(awayVal);
      }
      $scoreModal.modal('show');
    });
    $scoreForm.on('submit', function (e) {
      e.preventDefault();
      var action = $scoreForm.attr('action');
      var fixtureId = $scoreForm.data('fixture-id');
      var payload = $scoreForm.serialize();
      logGroup('🚀 SUBMIT SCORE', {
        fixtureId: fixtureId,
        action: action,
        payload: payload
      });
      $.ajax({
        url: action,
        type: 'POST',
        data: payload,
        success: function success(data) {
          logGroup('✅ SCORE RESPONSE', data);
          if (!data.success) {
            console.warn('Server returned success=false');
            alert('Save failed.');
            return;
          }
          var $row = $("#row-".concat(fixtureId));
          if (!$row.length) {
            console.warn('Row not found for fixture:', fixtureId);
            return;
          }
          $("#result-col-".concat(fixtureId)).html(data.html);
          updateWinnerClasses($row, data.winner);
          console.log('Updating edit button data attributes');
          var $editBtn = $("#edit-btn-".concat(fixtureId));
          for (var i = 1; i <= 3; i++) {
            $editBtn.data("set".concat(i, "_home"), data.scores["set".concat(i, "_home")] || '');
            $editBtn.data("set".concat(i, "_away"), data.scores["set".concat(i, "_away")] || '');
          }
          $scoreModal.modal('hide');
          console.log("\uD83C\uDF89 Score updated for fixture ".concat(fixtureId));
        },
        error: function error(xhr) {
          return ajaxErrorHandler('saving score', xhr);
        }
      });
    });

    // ============================================================
    // DELETE RESULT
    // ============================================================

    $('.delete-result-btn').on('click', function () {
      if (!confirm('Delete the result for this fixture?')) return;
      var fixtureId = $(this).data('id');
      var url = "{{ route('backend.team-fixtures.destroyResult', ':id') }}".replace(':id', fixtureId);
      logGroup('🗑 DELETE RESULT', {
        fixtureId: fixtureId,
        url: url
      });
      $.ajax({
        url: url,
        type: 'POST',
        data: {
          _method: 'DELETE',
          _token: '{{ csrf_token() }}'
        },
        success: function success(data) {
          logGroup('✅ DELETE RESPONSE', data);
          if (!data.success) {
            console.warn('Delete returned success=false');
            alert('Delete failed.');
            return;
          }
          $("#result-col-".concat(fixtureId)).html(data.html);
          $("#row-".concat(fixtureId)).find('.home-cell, .away-cell').removeClass('winner-home loser-home draw-cell');
          console.log('Result deleted successfully');
        },
        error: function error(xhr) {
          return ajaxErrorHandler('deleting result', xhr);
        }
      });
    });

    // ============================================================
    // PLAYERS EDIT
    // ============================================================

    $('.edit-players-btn').on('click', function () {
      var fixtureId = $(this).data('id');
      var home = $(this).data('home') || 'Home';
      var away = $(this).data('away') || 'Away';
      logGroup('🟢 OPEN PLAYERS MODAL', {
        fixtureId: fixtureId,
        home: home,
        away: away
      });
      $playersTeams.text("".concat(home, " vs ").concat(away));
      var actionUrl = "{{ route('backend.team-fixtures.updatePlayers', ':id') }}".replace(':id', fixtureId);
      console.log('Setting players form action:', actionUrl);
      $playersForm.data('fixture-id', fixtureId);
      $playersForm.attr('action', actionUrl);
      $playersModal.modal('show');
      $playersModal.one('shown.bs.modal', function () {
        console.log('📢 Players modal fully shown');
        $('#homePlayers, #awayPlayers').each(function () {
          console.log('Initializing Select2:', this.id);
          $(this).select2({
            dropdownParent: $playersModal,
            width: '100%',
            placeholder: 'Select players',
            allowClear: true
          });
        });
        var jsonUrl = "{{ url('backend/team-fixtures') }}/" + fixtureId + "/json";
        console.log('Fetching players JSON:', jsonUrl);
        $.ajax({
          url: jsonUrl,
          type: "GET",
          dataType: "json",
          success: function success(fixture) {
            logGroup('📡 PLAYERS JSON RESPONSE', fixture);
            if (!fixture) {
              console.warn('Empty fixture JSON');
              return;
            }
            $('#homePlayers').val(fixture.team1_ids || []).trigger('change');
            $('#awayPlayers').val(fixture.team2_ids || []).trigger('change');
            if (!(fixture.team1_ids || []).length) console.warn('No home players returned');
            if (!(fixture.team2_ids || []).length) console.warn('No away players returned');
            console.log('Players applied to Select2');
          },
          error: function error(xhr) {
            return ajaxErrorHandler('loading players', xhr);
          }
        });
      });
    });
    $playersForm.on('submit', function (e) {
      e.preventDefault();
      var action = $playersForm.attr('action');
      var fixtureId = $playersForm.data('fixture-id');
      var payload = $playersForm.serialize();
      logGroup('🚀 SUBMIT PLAYERS', {
        fixtureId: fixtureId,
        action: action,
        payload: payload
      });
      $.ajax({
        url: action,
        type: 'POST',
        data: payload,
        success: function success(data) {
          logGroup('✅ PLAYERS SAVE RESPONSE', data);
          if (!data.success) {
            console.warn('Save players returned success=false');
            alert('Save failed.');
            return;
          }
          var $row = $("#row-".concat(fixtureId));
          if ($row.length) {
            $row.find('.home-cell').html(data.homeHtml);
            $row.find('.away-cell').html(data.awayHtml);
          } else {
            console.warn('Row not found for updating players');
          }
          $playersModal.modal('hide');
          console.log("\uD83C\uDF89 Players updated for fixture ".concat(fixtureId));
        },
        error: function error(xhr) {
          return ajaxErrorHandler('saving players', xhr);
        }
      });
    });
    console.log('🎬 Draw Fixtures Show JS fully loaded');
  });
})(window, jQuery);
/******/ 	return __webpack_exports__;
/******/ })()
;
});