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

              toastr.success('Order saved');
            })
            .fail(() => toastr.error('Failed to save order'));
        }
      });
    });
  }

  initPlayerOrder();
  document.addEventListener('shown.bs.tab', initPlayerOrder);

})(jQuery, window, document);
