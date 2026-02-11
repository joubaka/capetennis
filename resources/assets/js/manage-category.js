$(function () {
  const csrfToken = $('meta[name="csrf-token"]').attr('content');
  const APP_URL = $('meta[name="app-url"]').attr('content');

  // Enable drag
  $(document).on('mouseenter', '.draggable-player', function () {
    if (!$(this).data('ui-draggable')) {
      $(this).draggable({
        helper: 'clone',
        revert: 'invalid',
        start: function () {
          $(this).addClass('dragging');
        },
        stop: function () {
          $(this).removeClass('dragging');
        }
      });
    }
  });

  $('.dropzone').droppable({
    accept: '.draggable-player',
    tolerance: 'pointer',
    hoverClass: 'border-primary',
    drop: function (event, ui) {
      const $thisZone = $(this);
      const drawId = $thisZone.data('draw-id') || 0; // target
      const playerId = $(ui.draggable).data('player-id');
      const sourceDrawId = $(ui.draggable).data('draw-id') || 0;

      // Clone fresh from master
      const $playerCard = $(`#master-player-list .draggable-player-template[data-player-id="${playerId}"]`)
        .clone()
        .removeClass('draggable-player-template')
        .addClass('draggable-player')
        .attr('data-draw-id', drawId);

      // Append player visually (safe)
      if ($thisZone.hasClass('dropzone') && !$thisZone.hasClass('card-body')) {
        $thisZone.append($playerCard);
      } else if ($thisZone.hasClass('card-body')) {
        $thisZone.append($playerCard);
      } else {
        console.warn('⚠️ Unexpected drop target');
        $thisZone.append($playerCard);
      }

      // Remove the dragged card only if it’s a different drop zone
      if (sourceDrawId !== drawId) {
        $(ui.draggable).remove();
      }

      // Make the new card draggable
      $playerCard.draggable({
        helper: 'clone',
        revert: 'invalid',
        start: function () {
          $(this).addClass('dragging');
        },
        stop: function () {
          $(this).removeClass('dragging');
        }
      });

      // ✅ Sync removal if moved out of previous draw
      if (sourceDrawId && sourceDrawId !== drawId) {
        $.post(`${APP_URL}/backend/draw/${sourceDrawId}/remove-player`, {
          registration_id: playerId,
          _token: csrfToken
        }).done(() => {
          console.log(`🗑️ Removed from draw ${sourceDrawId}`);
        }).fail(() => {
          console.error(`❌ Failed to remove from draw ${sourceDrawId}`);
        });
      }

      // ✅ Sync addition to new draw
      if (drawId > 0 && sourceDrawId !== drawId) {
        $.post(`${APP_URL}/backend/draw/${drawId}/add-player`, {
          registration_id: playerId,
          _token: csrfToken
        }).done(() => {
          console.log(`✅ Added to draw ${drawId}`);
        }).fail(() => {
          console.error(`❌ Failed to add to draw ${drawId}`);
        });
      }
    }

  });
  $(function () {
    $('#draw-settings-form input, #draw-settings-form select').on('input change', function () {
      $('#preview-name').text($('#drawName').val() || 'Draw Name Preview');
      $('#preview-type').text($('#drawType option:selected').text());
      $('#preview-rounds').text($('#numRounds').val() || '-');
    });

    // Optional: Prevent actual submit for now
    $('#draw-settings-form').on('submit', function (e) {
      e.preventDefault();
      alert('Settings saved (simulation)');
    });
  });

});
