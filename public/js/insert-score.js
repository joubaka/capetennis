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
/*!*****************************************************!*\
  !*** ./resources/js/pages/frontend/insert-score.js ***!
  \*****************************************************/
(function (window, $) {
  'use strict';

  $(function () {
    // Modal and form elements
    var $scoreModal = $('#editScoreModal');
    var $scoreForm = $('#editScoreForm');
    var $teams = $('#fixtureTeams');

    // Helper: log groups for debugging
    function logGroup(title) {
      var data = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : null;
      console.group(title);
      if (data) console.log(data);
      console.groupEnd();
    }

    // Helper: AJAX error handler
    function ajaxErrorHandler(context, xhr) {
      console.group("\u274C AJAX ERROR \u2192 ".concat(context));
      console.log('Status:', xhr.status);
      console.log('Response Text:', xhr.responseText);
      console.log('Response JSON:', xhr.responseJSON);
      console.groupEnd();
      alert("Error while processing ".concat(context, ". Check console."));
    }

    // Open modal and prefill form
    $('.edit-score-btn').on('click', function () {
      var fixtureId = $(this).data('id');
      var home = $(this).data('home') || 'Home';
      var away = $(this).data('away') || 'Away';
      var actionUrl = $(this).data('action');
      logGroup('🟢 OPEN SCORE MODAL', {
        fixtureId: fixtureId,
        home: home,
        away: away,
        actionUrl: actionUrl
      });
      $teams.text("".concat(home, " vs ").concat(away));
      $scoreForm.data('fixture-id', fixtureId);
      $scoreForm.attr('action', actionUrl);

      // Prefill set scores
      for (var i = 1; i <= 3; i++) {
        var _$$data, _$$data2;
        $("#set".concat(i, "Home")).val((_$$data = $(this).data("set".concat(i, "_home"))) !== null && _$$data !== void 0 ? _$$data : '');
        $("#set".concat(i, "Away")).val((_$$data2 = $(this).data("set".concat(i, "_away"))) !== null && _$$data2 !== void 0 ? _$$data2 : '');
      }
      $scoreModal.modal('show');
    });

    // Submit score via AJAX
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
            alert('Save failed.');
            return;
          }
          updateFixtureRow(fixtureId, data.html, data.winner, data.actionsHtml);
          $scoreModal.modal('hide');
        },
        error: function error(xhr) {
          return ajaxErrorHandler('saving score', xhr);
        }
      });
    });

    // Delete result via AJAX
    $('.delete-result-btn').on('click', function () {
      if (!confirm('Delete the result for this fixture?')) return;
      var fixtureId = $(this).data('id');
      var actionUrl = $(this).data('action');
      $.ajax({
        url: actionUrl,
        type: 'DELETE',
        data: {
          _token: $('meta[name="csrf-token"]').attr('content')
        },
        success: function success(data) {
          if (!data.success) {
            alert('Delete failed.');
            return;
          }
          updateFixtureRow(fixtureId, data.html, data.winner, data.actionsHtml);
        },
        error: function error(xhr) {
          alert('Error deleting result. Check console.');
          console.log(xhr.responseText);
        }
      });
    });

    // After successful insert or delete
    function updateFixtureRow(fixtureId, html, winner, actionsHtml) {
      $("#result-col-".concat(fixtureId)).html(html);
      if (actionsHtml) {
        $("#actions-col-".concat(fixtureId)).html(actionsHtml);
      }

      // Winner/loser classes
      var $row = $("#row-".concat(fixtureId));
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

      // Re-bind delete handler for new button
      $("#actions-col-".concat(fixtureId, " .delete-result-btn")).off('click').on('click', function () {
        if (!confirm('Delete the result for this fixture?')) return;
        var fixtureId = $(this).data('id');
        var actionUrl = $(this).data('action');
        $.ajax({
          url: actionUrl,
          type: 'DELETE',
          data: {
            _token: $('meta[name="csrf-token"]').attr('content')
          },
          success: function success(data) {
            if (!data.success) {
              alert('Delete failed.');
              return;
            }
            updateFixtureRow(fixtureId, data.html, data.winner, data.actionsHtml);
            alert('Result deleted!');
          },
          error: function error(xhr) {
            alert('Error deleting result. Check console.');
            console.log(xhr.responseText);
          }
        });
      });
    }
  });
})(window, jQuery);
/******/ 	return __webpack_exports__;
/******/ })()
;
});