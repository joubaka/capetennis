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
/*!*******************************************!*\
  !*** ./resources/js/pages/playerOrder.js ***!
  \*******************************************/
/*
 * Admin — Player Order (Sortable) JS
 */

(function ($, window, document) {
  'use strict';

  console.log('↕️ playerOrder.js loaded');
  var APP_URL = window.APP_URL || window.location.origin;
  function initPlayerOrder() {
    if (typeof Sortable === 'undefined') return;
    $('tbody.sortablePlayers').each(function () {
      var $tbody = $(this);
      if ($tbody.data('init')) return;
      var teamId = $tbody.data('team-id');
      if (!teamId) return;
      console.log('↕️ Init order', teamId);
      $tbody.data('init', true);
      new Sortable(this, {
        animation: 150,
        handle: '.drag-handle',
        draggable: 'tr.drag-item',
        onEnd: function onEnd() {
          var debugRows = [];
          var mismatches = [];
          $tbody.find('tr.drag-item').each(function (i) {
            var $row = $(this);
            var position = i + 1;
            var id = $row.data('playerteamid');
            var teamPlayerId = $row.data('teamplayerid');
            var noProfileId = $row.data('noprofileid');
            var type = $row.data('type');
            var $cells = $row.find('td');
            var profileName = $cells.eq(2).text().trim();
            var hasNoProfileCol = $cells.length > 6;
            var noProfileName = hasNoProfileCol ? $cells.eq(3).text().trim() : null;
            var $rankBadge = $cells.eq(1).find('.badge').first();
            var badgeBefore = $rankBadge.text().trim();
            $rankBadge.text(position);
            var profileOk = type === 'profile' ? id === teamPlayerId : true;
            var noProfileOk = type === 'noprofile' ? id === noProfileId : true;
            if (!profileOk || !noProfileOk) {
              mismatches.push({
                position: position,
                id: id,
                teamPlayerId: teamPlayerId,
                noProfileId: noProfileId,
                type: type
              });
            }
            debugRows.push({
              position: position,
              id: id,
              teamPlayerId: teamPlayerId,
              noProfileId: noProfileId,
              type: type,
              profileName: profileName,
              noProfileName: noProfileName,
              badgeBefore: badgeBefore
            });
          });
          var order = debugRows.map(function (row) {
            return {
              id: row.id,
              team_player_id: row.teamPlayerId,
              no_profile_id: row.noProfileId,
              type: row.type,
              position: row.position
            };
          });
          console.log('↕️ Drag debug rows', debugRows);
          if (mismatches.length) {
            console.warn('↕️ Drag ID mismatches', mismatches);
          }
          console.log('↕️ Save order', order);
          $.post("".concat(APP_URL, "/backend/team/orderPlayerList"), {
            team_id: teamId,
            order: order
          }).done(function (res) {
            console.log('↕️ Save order response', res);
            var responsePlayers = (res === null || res === void 0 ? void 0 : res.players) || [];
            var responseMap = new Map(responsePlayers.map(function (p) {
              return ["".concat(p.type, ":").concat(Number(p.id)), Number(p.rank)];
            }));
            var compare = order.map(function (o) {
              var entityId = o.type === 'noprofile' ? Number(o.no_profile_id || o.id) : Number(o.team_player_id || o.id);
              var key = "".concat(o.type, ":").concat(entityId);
              return {
                key: key,
                clientRank: Number(o.position),
                serverRank: responseMap.get(key)
              };
            });
            var rankMismatches = compare.filter(function (c) {
              return c.serverRank && c.serverRank !== c.clientRank;
            });
            console.log('↕️ Drag compare (client vs server)', compare);
            if (rankMismatches.length) {
              console.warn('↕️ Rank mismatches', rankMismatches);
            }

            // Update Players tab to reflect new order
            var $playersTab = $('#tab-players');
            order.forEach(function (item) {
              var entityId = item.type === 'noprofile' ? item.no_profile_id || item.id : item.team_player_id || item.id;
              var $playersRow = $playersTab.find("tr[data-playerteamid=\"".concat(entityId, "\"]"));
              if ($playersRow.length) {
                var $rankBadge = $playersRow.find('td:first .badge');
                if ($rankBadge.length) {
                  $rankBadge.text(item.position);
                }
              }
            });

            // Re-sort rows in each affected tbody
            $playersTab.find('tbody').each(function () {
              var $tbody = $(this);
              var $rows = $tbody.find('tr[data-playerteamid]');
              if ($rows.length < 2) return;

              // Check if any row in this tbody was part of the reorder
              var orderIds = new Set(order.map(function (o) {
                return String(o.type === 'noprofile' ? o.no_profile_id || o.id : o.team_player_id || o.id);
              }));
              var hasMatch = $rows.toArray().some(function (r) {
                return orderIds.has(String($(r).data('playerteamid')));
              });
              if (!hasMatch) return;

              // Sort rows by their rank badge number
              var sorted = $rows.toArray().sort(function (a, b) {
                var rankA = parseInt($(a).find('td:first .badge').text()) || 0;
                var rankB = parseInt($(b).find('td:first .badge').text()) || 0;
                return rankA - rankB;
              });
              sorted.forEach(function (row) {
                return $tbody.append(row);
              });
              console.log('↕️ Re-sorted Players tab tbody');
            });
            toastr.success('Order saved');
          }).fail(function () {
            return toastr.error('Failed to save order');
          });
        }
      });
    });
  }
  initPlayerOrder();
  document.addEventListener('shown.bs.tab', initPlayerOrder);
})(jQuery, window, document);
/******/ 	return __webpack_exports__;
/******/ })()
;
});