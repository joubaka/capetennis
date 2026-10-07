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

      let savedRows = [];
      let savedRanks = [];
      let saving = false;
      function restoreRows() {
        savedRows.forEach((row, index) => {
          $tbody.append(row);
          $(row).find('td').eq(1).find('.badge').first().text(savedRanks[index]);
        });
      }

      const sortable = new Sortable(this, {
        animation: 150,
        handle: '.drag-handle',
        draggable: 'tr.drag-item',
        onMove() { return !saving; },
        onStart() {
          savedRows = $tbody.find('tr.drag-item').toArray();
          savedRanks = savedRows.map(row => $(row).find('td').eq(1).find('.badge').first().text());
        },
        onEnd() {
          if (saving) { restoreRows(); return; }
          saving = true;
          $tbody.data('order-busy', true);
          $tbody.closest('.roster-team-body').find('[data-order-status]').text('Saving order…');
          sortable.option('disabled', true);
          const debugRows = [];
          const mismatches = [];

          $tbody.find('tr.drag-item').each(function (i) {
            const $row = $(this);
            const position = i + 1;

            const id = $row.data('playerteamid');
            const teamPlayerId = $row.data('teamplayerid');
            const noProfileId = $row.data('noprofileid');
            const type = $row.data('type');

            const $cells = $row.find('td');
            const profileName = $cells.eq(2).text().trim();
            const hasNoProfileCol = $cells.length > 6;
            const noProfileName = hasNoProfileCol ? $cells.eq(3).text().trim() : null;

            const $rankBadge = $cells.eq(1).find('.badge').first();
            const badgeBefore = $rankBadge.text().trim();
            $rankBadge.text(position);

            const profileOk = type === 'profile' ? id === teamPlayerId : true;
            const noProfileOk = type === 'noprofile' ? id === noProfileId : true;

            if (!profileOk || !noProfileOk) {
              mismatches.push({
                position,
                id,
                teamPlayerId,
                noProfileId,
                type
              });
            }

            debugRows.push({
              position,
              id,
              teamPlayerId,
              noProfileId,
              type,
              profileName,
              noProfileName,
              badgeBefore
            });
          });

          const order = debugRows.map(row => ({
            id: row.id,
            team_player_id: row.teamPlayerId,
            no_profile_id: row.noProfileId,
            type: row.type,
            position: row.position
          }));

          console.log('↕️ Drag debug rows', debugRows);
          if (mismatches.length) {
            console.warn('↕️ Drag ID mismatches', mismatches);
          }

          console.log('↕️ Save order', order);

          $.post(`${APP_URL}/backend/team/orderPlayerList`, {
            team_id: teamId,
            order
          })
            .done(res => {
              console.log('↕️ Save order response', res);

              const responsePlayers = res?.players || [];
              const responseMap = new Map(
                responsePlayers.map(p => [`${p.type}:${Number(p.id)}`, Number(p.rank)])
              );

              const compare = order.map(o => {
                const entityId = o.type === 'noprofile'
                  ? Number(o.no_profile_id || o.id)
                  : Number(o.team_player_id || o.id);

                const key = `${o.type}:${entityId}`;

                return {
                  key,
                  clientRank: Number(o.position),
                  serverRank: responseMap.get(key)
                };
              });

              const rankMismatches = compare.filter(c => c.serverRank && c.serverRank !== c.clientRank);

              console.log('↕️ Drag compare (client vs server)', compare);
              if (rankMismatches.length) {
                console.warn('↕️ Rank mismatches', rankMismatches);
              }

              // Update Players tab to reflect new order
              const $playersTab = $('#tab-players');
              order.forEach(item => {
                const entityId = item.type === 'noprofile'
                  ? (item.no_profile_id || item.id)
                  : (item.team_player_id || item.id);

                const $playersRow = $playersTab.find(`tr[data-playerteamid="${entityId}"]`);
                if ($playersRow.length) {
                  const $rankBadge = $playersRow.find('td:first .badge');
                  if ($rankBadge.length) {
                    $rankBadge.text(item.position);
                  }
                }
              });

              // Re-sort rows in each affected tbody
              $playersTab.find('tbody').each(function () {
                const $tbody = $(this);
                const $rows = $tbody.find('tr[data-playerteamid]');
                if ($rows.length < 2) return;

                // Check if any row in this tbody was part of the reorder
                const orderIds = new Set(order.map(o =>
                  String(o.type === 'noprofile' ? (o.no_profile_id || o.id) : (o.team_player_id || o.id))
                ));
                const hasMatch = $rows.toArray().some(r => orderIds.has(String($(r).data('playerteamid'))));
                if (!hasMatch) return;

                // Sort rows by their rank badge number
                const sorted = $rows.toArray().sort((a, b) => {
                  const rankA = parseInt($(a).find('td:first .badge').text()) || 0;
                  const rankB = parseInt($(b).find('td:first .badge').text()) || 0;
                  return rankA - rankB;
                });

                sorted.forEach(row => $tbody.append(row));
                console.log('↕️ Re-sorted Players tab tbody');
              });

              toastr.success('Order saved');
              $tbody.closest('.roster-team-body').find('[data-order-status]').text('Order saved');
              document.dispatchEvent(new CustomEvent('roster:changed', { detail: { teamId } }));
            })
            .fail(xhr => {
              restoreRows();
              $tbody.closest('.roster-team-body').find('[data-order-status]').text('The previous order has been restored.');
              toastr.error(xhr.responseJSON?.message || 'Failed to save order. The previous order has been restored.');
            })
            .always(() => { saving = false; $tbody.data('order-busy', false); sortable.option('disabled', false); });
        }
      });
      $tbody.data('sortable', sortable);
    });
  }

  initPlayerOrder();
  document.addEventListener('shown.bs.tab', initPlayerOrder);
  document.addEventListener('roster:loaded', initPlayerOrder);
  $(document).on('click', '[data-order-move]', function () {
    const $row = $(this).closest('tr');
    const $tbody = $row.closest('tbody');
    const sortable = $tbody.data('sortable');
    if (!sortable || $tbody.data('order-busy')) return;
    const $next = this.dataset.orderMove === 'up' ? $row.prev('tr') : $row.next('tr');
    if (!$next.length) return;
    sortable.option('onStart')();
    if (this.dataset.orderMove === 'up') $row.insertBefore($next); else $row.insertAfter($next);
    this.focus();
    sortable.option('onEnd')();
  });

})(jQuery, window, document);
