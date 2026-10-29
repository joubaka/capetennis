/* Apply only server-confirmed positions; keep the current roster and editors alive. */
(function (root) {
  'use strict';
  function plan(rows, order, kind) {
    if (!Array.isArray(order) || !order.length || !['profile', 'imported'].includes(kind)) return null;
    const positions = new Map();
    const ranks = new Set();
    for (const item of order) {
      if (!Number.isSafeInteger(item.slot_id) || item.slot_id < 1 || !Number.isSafeInteger(item.rank) || item.rank < 1
          || !Number.isSafeInteger(item.player_id) || item.player_id < 0 || positions.has(String(item.slot_id)) || ranks.has(item.rank)) return null;
      positions.set(String(item.slot_id), item); ranks.add(item.rank);
    }
    const matched = new Set();
    const result = [];
    for (const row of rows) {
      const id = kind === 'imported' ? row.dataset.rosterImportedId : row.dataset.rosterRow;
      const position = positions.get(String(id));
      const rankCell = row.querySelector('[data-label="Rank"] .badge');
      const oldRank = Number(rankCell?.textContent.trim());
      if (!Number.isSafeInteger(oldRank) || oldRank < 1) return null;
      if (position) {
        if (matched.has(String(id)) || Number(row.dataset.rosterPlayerId || 0) !== position.player_id) return null;
        matched.add(String(id));
      } else if (row.dataset.occupied === 'true') return null;
      result.push({row, rankCell, rank: position?.rank || oldRank});
    }
    if (matched.size !== positions.size || new Set(result.map(item => item.rank)).size !== result.length) return null;
    return result.sort((a, b) => a.rank - b.rank);
  }

  function syncRelated(teamId, data) {
    if (!teamId || !Array.isArray(data.order)) return;
    const byPlayer = new Map(data.order.filter(item => item.player_id > 0).map(item => [String(item.player_id), item.rank]));
    const bySlot = new Map(data.order.map(item => [String(item.slot_id), item.rank]));
    root.document.querySelectorAll('[data-roster-modal-portal] form, [data-roster-actions-modal] form').forEach(form => {
      const modal = form.closest('.modal');
      const scope = form.querySelector('[name="expected_team_id"]')?.value || form.querySelector('[name="team_id"]')?.value || modal?.dataset.teamId;
      if (String(scope) !== String(teamId)) return;
      const playerId = form.querySelector('[name="expected_player_id"]')?.value;
      const rank = Number(playerId) > 0 ? byPlayer.get(String(playerId))
        : modal?.dataset.slotType === data.order_type ? bySlot.get(String(modal.dataset.slotId)) : undefined;
      if (!rank) return;
      form.querySelectorAll('input[type="hidden"][name="expected_rank"], input[type="hidden"][name="expected_roster_rank"]')
        .forEach(input => { input.value = String(rank); });
      modal?.querySelectorAll('[data-current-roster-rank]').forEach(label => { label.textContent = String(rank); });
    });
    if (data.order_type === 'imported') root.document.querySelectorAll('[data-rank-slot-id]').forEach(option => {
      const rank = bySlot.get(option.dataset.rankSlotId);
      if (rank) option.textContent = option.dataset.rankPlayerName + ' · Rank ' + rank;
    });
  }

  function apply(form, data) {
    const team = form.closest('[data-roster-team]');
    const body = team?.querySelector('.roster-compact-table .team-player-table > tbody');
    if (!body || team.isConnected === false) return false;
    const rows = [...body.children].filter(row => row.matches('[data-roster-row]'));
    const ordered = plan(rows, data.order, data.order_type);
    if (!ordered) return false;
    syncRelated(team.dataset?.rosterTeam, data);
    // Reserve promotion has conditional controls; reconcile that case using the
    // existing authorized AJAX fragment rather than guessing selection state.
    if (rows.some(row => row.querySelector('[name="confirm_main_slot_id"]')
        || [...row.querySelectorAll('[data-label="Rank"] .badge')].some(badge => badge.textContent.trim() === 'Reserve'))) return false;
    const blocks = ordered.map(item => {
      const details = [];
      let next = item.row.nextElementSibling;
      while (next?.dataset.rosterDetailRow === item.row.dataset.rosterRow) { details.push(next); next = next.nextElementSibling; }
      return {...item, details};
    });
    const scrollX = root.scrollX, scrollY = root.scrollY;
    for (const {row, rankCell, rank, details} of blocks) {
      rankCell.textContent = String(rank);
      for (const node of [row, ...details]) {
        node.querySelectorAll('input[type="hidden"][name="expected_rank"], input[type="hidden"][name="expected_roster_rank"]')
          .forEach(input => { input.value = String(rank); });
      }
      details.forEach(detail => {
        detail.querySelectorAll('[data-slot-id] > td:first-child > .badge').forEach(badge => { badge.textContent = 'Rank ' + rank; });
      });
      body.append(row, ...details);
    }
    const panel = form.closest('[data-roster-panel]');
    if (root.invalidateTeamRosterPanels) root.invalidateTeamRosterPanels(panel);
    else root.document.querySelectorAll('[data-roster-panel]').forEach(other => {
      if (other !== panel && other.dataset.regionId === panel?.dataset.regionId) delete other.dataset.loaded;
    });
    root.scrollTo(scrollX, scrollY);
    return true;
  }
  root.applyTeamRosterOrder = apply;
  if (typeof module !== 'undefined' && module.exports) module.exports = {plan, apply, syncRelated};
})(typeof window === 'undefined' ? globalThis : window);
