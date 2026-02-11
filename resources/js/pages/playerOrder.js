/*
 * Admin — Player Order (Sortable) JS
 */

(function ($, window, document) {
  'use strict';

  console.log('↕️ playerOrder.js loaded');

  const APP_URL = window.APP_URL || window.location.origin;

  function initPlayerOrder() {
    if (typeof Sortable === 'undefined') return;

    $('tbody.sortablePlayers').each(function () {
      const $tbody = $(this);
      if ($tbody.data('init')) return;

      const teamId = $tbody.data('team-id');
      if (!teamId) return;

      console.log('↕️ Init order', teamId);
      $tbody.data('init', true);

      new Sortable(this, {
        animation: 150,
        handle: '.drag-handle',
        draggable: 'tr.drag-item',
        onEnd() {
          const order = $tbody.find('tr.drag-item').map(function (i) {
            return {
              id: $(this).data('playerteamid'),
              type: $(this).data('type'),
              position: i + 1
            };
          }).get();

          console.log('↕️ Save order', order);

          $.post(`${APP_URL}/backend/team/orderPlayerList`, {
            team_id: teamId,
            order
          })
            .done(() => toastr.success('Order saved'))
            .fail(() => toastr.error('Failed to save order'));
        }
      });
    });
  }

  initPlayerOrder();
  document.addEventListener('shown.bs.tab', initPlayerOrder);

})(jQuery, window, document);
