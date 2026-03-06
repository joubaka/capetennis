(function (window, $) {
  'use strict';

  $(function () {
    // Modal and form elements
    const $scoreModal = $('#editScoreModal');
    const $scoreForm = $('#editScoreForm');
    const $teams = $('#fixtureTeams');

    // Helper: log groups for debugging
    function logGroup(title, data = null) {
      console.group(title);
      if (data) console.log(data);
      console.groupEnd();
    }

    // Helper: AJAX error handler
    function ajaxErrorHandler(context, xhr) {
      console.group(`❌ AJAX ERROR → ${context}`);
      console.log('Status:', xhr.status);
      console.log('Response Text:', xhr.responseText);
      console.log('Response JSON:', xhr.responseJSON);
      console.groupEnd();
      alert(`Error while processing ${context}. Check console.`);
    }

    // Open modal and prefill form
    $('.edit-score-btn').on('click', function () {
      const fixtureId = $(this).data('id');
      const home = $(this).data('home') || 'Home';
      const away = $(this).data('away') || 'Away';
      const actionUrl = $(this).data('action');

      logGroup('🟢 OPEN SCORE MODAL', { fixtureId, home, away, actionUrl });

      $teams.text(`${home} vs ${away}`);
      $scoreForm.data('fixture-id', fixtureId);
      $scoreForm.attr('action', actionUrl);

      // Prefill set scores
      for (let i = 1; i <= 3; i++) {
        $(`#set${i}Home`).val($(this).data(`set${i}_home`) ?? '');
        $(`#set${i}Away`).val($(this).data(`set${i}_away`) ?? '');
      }

      $scoreModal.modal('show');
    });

    // Submit score via AJAX
    $scoreForm.on('submit', function (e) {
      e.preventDefault();

      const action = $scoreForm.attr('action');
      const fixtureId = $scoreForm.data('fixture-id');
      const payload = $scoreForm.serialize();

      logGroup('🚀 SUBMIT SCORE', { fixtureId, action, payload });

      $.ajax({
        url: action,
        type: 'POST',
        data: payload,
        success: function (data) {
          logGroup('✅ SCORE RESPONSE', data);

          if (!data.success) {
            alert('Save failed.');
            return;
          }

          updateFixtureRow(fixtureId, data.html, data.winner, data.actionsHtml);
          $scoreModal.modal('hide');
        },
        error: (xhr) => ajaxErrorHandler('saving score', xhr)
      });

    });

    // Delete result via AJAX
    $('.delete-result-btn').on('click', function () {
      if (!confirm('Delete the result for this fixture?')) return;
      const fixtureId = $(this).data('id');
      const actionUrl = $(this).data('action');
      $.ajax({
        url: actionUrl,
        type: 'DELETE',
        data: { _token: $('meta[name="csrf-token"]').attr('content') },
        success: function (data) {
          if (!data.success) {
            alert('Delete failed.');
            return;
          }

          updateFixtureRow(fixtureId, data.html, data.winner, data.actionsHtml);
        
        },
        error: function (xhr) {
          alert('Error deleting result. Check console.');
          console.log(xhr.responseText);
        }
      });
    });

    // After successful insert or delete
    function updateFixtureRow(fixtureId, html, winner, actionsHtml) {
      $(`#result-col-${fixtureId}`).html(html);
      if (actionsHtml) {
        $(`#actions-col-${fixtureId}`).html(actionsHtml);
      }

      // Winner/loser classes
      const $row = $(`#row-${fixtureId}`);
      const $home = $row.find('.home-cell');
      const $away = $row.find('.away-cell');
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
      $(`#actions-col-${fixtureId} .delete-result-btn`).off('click').on('click', function () {
        if (!confirm('Delete the result for this fixture?')) return;
        const fixtureId = $(this).data('id');
        const actionUrl = $(this).data('action');
        $.ajax({
          url: actionUrl,
          type: 'DELETE',
          data: { _token: $('meta[name="csrf-token"]').attr('content') },
          success: function (data) {
            if (!data.success) {
              alert('Delete failed.');
              return;
            }
            updateFixtureRow(fixtureId, data.html, data.winner, data.actionsHtml);
            alert('Result deleted!');
          },
          error: function (xhr) {
            alert('Error deleting result. Check console.');
            console.log(xhr.responseText);
          }
        });
      });
    }
  });

})(window, jQuery);
