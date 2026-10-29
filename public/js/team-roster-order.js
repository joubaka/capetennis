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

  function apply(form, data) {
    const team = form.closest('[data-roster-team]');
    const body = team?.querySelector('.roster-compact-table .team-player-table > tbody');
    if (!body) return false;
    const rows = [...body.children].filter(row => row.matches('[data-roster-row]'));
    // Reserve promotion has conditional controls; reconcile that case using the
    // existing authorized AJAX fragment rather than guessing selection state.
    if (rows.some(row => row.querySelector('[name="confirm_main_slot_id"]')
        || [...row.querySelectorAll('[data-label="Rank"] .badge')].some(badge => badge.textContent.trim() === 'Reserve'))) return false;
    const ordered = plan(rows, data.order, data.order_type);
    if (!ordered) return false;
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
    root.document.querySelectorAll('[data-roster-panel]').forEach(other => {
      if (other !== panel && other.dataset.regionId === panel?.dataset.regionId) delete other.dataset.loaded;
    });
    root.scrollTo(scrollX, scrollY);
    return true;
  }
  root.applyTeamRosterOrder = apply;
  if (typeof module !== 'undefined' && module.exports) module.exports = {plan, apply};
})(typeof window === 'undefined' ? globalThis : window);
