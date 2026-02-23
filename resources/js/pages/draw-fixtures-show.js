(function (window, $) {
  'use strict';

  console.log('🎬 Draw Fixtures Show JS booting...');

  $(function () {

    console.group('🚀 Team Fixtures JS Init');
    console.log('DOM Ready');
    console.groupEnd();

    const $scoreModal = $('#editScoreModal');
    const $scoreForm = $('#editScoreForm');
    const $teams = $('#fixtureTeams');

    const $playersModal = $('#editPlayersModal');
    const $playersForm = $('#editPlayersForm');
    const $playersTeams = $('#playersFixtureTeams');

    // ---------------------------
    // HELPERS
    // ---------------------------

    function logGroup(title, data = null) {
      console.group(title);
      if (data) console.log(data);
      console.groupEnd();
    }

    function ajaxErrorHandler(context, xhr) {
      console.group(`❌ AJAX ERROR → ${context}`);
      console.log('Status:', xhr.status);
      console.log('Response Text:', xhr.responseText);
      console.log('Response JSON:', xhr.responseJSON);
      console.groupEnd();
      alert(`Error while processing ${context}. Check console.`);
    }

    function updateWinnerClasses($row, winner) {
      console.group('🎨 Updating Winner Classes');
      console.log('Winner:', winner);

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

      console.groupEnd();
    }

    // ============================================================
    // SCORE EDIT
    // ============================================================

    $('.edit-score-btn').on('click', function () {

      const fixtureId = $(this).data('id');
      const home = $(this).data('home') || 'Home';
      const away = $(this).data('away') || 'Away';

      logGroup('🟢 OPEN SCORE MODAL', {
        fixtureId,
        home,
        away
      });

      $teams.text(`${home} vs ${away}`);

      const actionUrlFromAttr = $(this).data('action') || null;
      const actionUrl = actionUrlFromAttr ||
        ("{{ route('backend.team-fixtures.update', ':id') }}".replace(':id', fixtureId));

      console.log('Setting form action:', actionUrl);

      $scoreForm.data('fixture-id', fixtureId);
      $scoreForm.attr('action', actionUrl);

      // Prefill sets: prefer data-* on the clicked button (they are now rendered server-side)
      for (let i = 1; i <= 3; i++) {
        const homeVal = $(this).data(`set${i}_home`) ?? '';
        const awayVal = $(this).data(`set${i}_away`) ?? '';
        console.log(`Set ${i}:`, homeVal, awayVal);
        $(`#set${i}Home`).val(homeVal);
        $(`#set${i}Away`).val(awayVal);
      }

      $scoreModal.modal('show');
    });

    $scoreForm.on('submit', function (e) {
      e.preventDefault();

      const action = $scoreForm.attr('action');
      const fixtureId = $scoreForm.data('fixture-id');
      const payload = $scoreForm.serialize();

      logGroup('🚀 SUBMIT SCORE', {
        fixtureId,
        action,
        payload
      });

      $.ajax({
        url: action,
        type: 'POST',
        data: payload,
        success: function (data) {

          logGroup('✅ SCORE RESPONSE', data);

          if (!data.success) {
            console.warn('Server returned success=false');
            alert('Save failed.');
            return;
          }

          const $row = $(`#row-${fixtureId}`);

          if (!$row.length) {
            console.warn('Row not found for fixture:', fixtureId);
            return;
          }

          $(`#result-col-${fixtureId}`).html(data.html);
          updateWinnerClasses($row, data.winner);

          console.log('Updating edit button data attributes');

          const $editBtn = $(`#edit-btn-${fixtureId}`);
          for (let i = 1; i <= 3; i++) {
            $editBtn.data(`set${i}_home`, data.scores[`set${i}_home`] || '');
            $editBtn.data(`set${i}_away`, data.scores[`set${i}_away`] || '');
          }

          $scoreModal.modal('hide');
          console.log(`🎉 Score updated for fixture ${fixtureId}`);
        },
        error: (xhr) => ajaxErrorHandler('saving score', xhr)
      });
    });

    // ============================================================
    // DELETE RESULT
    // ============================================================

    $('.delete-result-btn').on('click', function () {

      if (!confirm('Delete the result for this fixture?')) return;

      const fixtureId = $(this).data('id');
      const url = "{{ route('backend.team-fixtures.destroyResult', ':id') }}"
        .replace(':id', fixtureId);

      logGroup('🗑 DELETE RESULT', { fixtureId, url });

      $.ajax({
        url: url,
        type: 'POST',
        data: { _method: 'DELETE', _token: '{{ csrf_token() }}' },
        success: function (data) {

          logGroup('✅ DELETE RESPONSE', data);

          if (!data.success) {
            console.warn('Delete returned success=false');
            alert('Delete failed.');
            return;
          }

          $(`#result-col-${fixtureId}`).html(data.html);
          $(`#row-${fixtureId}`)
            .find('.home-cell, .away-cell')
            .removeClass('winner-home loser-home draw-cell');

          console.log('Result deleted successfully');
        },
        error: (xhr) => ajaxErrorHandler('deleting result', xhr)
      });
    });

    // ============================================================
    // PLAYERS EDIT
    // ============================================================

    $('.edit-players-btn').on('click', function () {

      const fixtureId = $(this).data('id');
      const home = $(this).data('home') || 'Home';
      const away = $(this).data('away') || 'Away';

      logGroup('🟢 OPEN PLAYERS MODAL', {
        fixtureId,
        home,
        away
      });

      $playersTeams.text(`${home} vs ${away}`);

      const actionUrl = "{{ route('backend.team-fixtures.updatePlayers', ':id') }}"
        .replace(':id', fixtureId);

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

        const jsonUrl = "{{ url('backend/team-fixtures') }}/" + fixtureId + "/json";
        console.log('Fetching players JSON:', jsonUrl);

        $.ajax({
          url: jsonUrl,
          type: "GET",
          dataType: "json",
          success: function (fixture) {

            logGroup('📡 PLAYERS JSON RESPONSE', fixture);

            if (!fixture) {
              console.warn('Empty fixture JSON');
              return;
            }

            $('#homePlayers')
              .val(fixture.team1_ids || [])
              .trigger('change');

            $('#awayPlayers')
              .val(fixture.team2_ids || [])
              .trigger('change');

            if (!(fixture.team1_ids || []).length)
              console.warn('No home players returned');

            if (!(fixture.team2_ids || []).length)
              console.warn('No away players returned');

            console.log('Players applied to Select2');
          },
          error: (xhr) => ajaxErrorHandler('loading players', xhr)
        });
      });
    });

    $playersForm.on('submit', function (e) {

      e.preventDefault();

      const action = $playersForm.attr('action');
      const fixtureId = $playersForm.data('fixture-id');
      const payload = $playersForm.serialize();

      logGroup('🚀 SUBMIT PLAYERS', {
        fixtureId,
        action,
        payload
      });

      $.ajax({
        url: action,
        type: 'POST',
        data: payload,
        success: function (data) {

          logGroup('✅ PLAYERS SAVE RESPONSE', data);

          if (!data.success) {
            console.warn('Save players returned success=false');
            alert('Save failed.');
            return;
          }

          const $row = $(`#row-${fixtureId}`);

          if ($row.length) {
            $row.find('.home-cell').html(data.homeHtml);
            $row.find('.away-cell').html(data.awayHtml);
          } else {
            console.warn('Row not found for updating players');
          }

          $playersModal.modal('hide');
          console.log(`🎉 Players updated for fixture ${fixtureId}`);
        },
        error: (xhr) => ajaxErrorHandler('saving players', xhr)
      });
    });

    console.log('🎬 Draw Fixtures Show JS fully loaded');

  });

})(window, jQuery);
