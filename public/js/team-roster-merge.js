/* One roster identity, with the existing authorized forms moved into its details. */
(function () {
  'use strict';
  const teams = new Map();
  const portals = [];
  const remember = (node, name, value, changes) => { changes.push([node, name, node[name]]); node[name] = value; };

  function restore() {
    for (const state of teams.values()) {
      for (const move of state.rows) {
        if (move.anchor.parentNode) move.anchor.replaceWith(move.row);
        move.detail.remove();
      }
      state.changes.reverse().forEach(([node, name, value]) => { node[name] = value; });
      state.workspace.classList.toggle('show', state.workspaceOpen);
      if (state.header && state.headerTarget !== null) state.header.setAttribute('data-team-workspace-target', state.headerTarget);
      state.body.replaceChildren(...state.original);
      state.canonical.remove();
      if (state.anchor.parentNode) state.anchor.replaceWith(state.card);
    }
    teams.clear();
    for (const portal of portals.splice(0)) if (portal.anchor.parentNode) portal.anchor.replaceWith(portal.modal);
    document.querySelector('[data-roster-modal-portal]')?.remove();
  }

  function portalModals(host) {
    let root = document.querySelector('[data-roster-modal-portal]');
    if (!root) { root = document.createElement('div'); root.dataset.rosterModalPortal = ''; document.querySelector('.team-admin-workspace').append(root); }
    host.querySelectorAll('.modal').forEach(modal => {
      const anchor = document.createComment('roster modal'); modal.before(anchor); portals.push({modal, anchor}); root.append(modal);
    });
  }

  function moveRow(state, row, canonical, imported) {
    const anchor = document.createComment('invitation identity'); row.before(anchor);
    const detail = document.createElement('tr'); detail.dataset.rosterDetailRow = canonical.dataset.rosterRow;
    const cell = document.createElement('td'); cell.colSpan = canonical.children.length;
    const disclosure = document.createElement('details'); disclosure.className = 'roster-identity-details';
    const summary = document.createElement('summary'); summary.textContent = imported ? 'Profile, contact & imported name' : 'Invitation, email & regional actions';
    const wrap = document.createElement('div'); wrap.className = 'table-responsive pt-2';
    const table = document.createElement('table'); table.className = 'table table-sm roster-identity-controls';
    const body = document.createElement('tbody'); body.append(row); table.append(body); wrap.append(table);
    disclosure.append(summary, wrap); cell.append(disclosure); detail.append(cell); canonical.after(detail);
    if (!imported) row.querySelectorAll('td[data-label="Rank"], td[data-label="Player"]').forEach(td => remember(td, 'hidden', true, state.changes));
    state.rows.push({row, anchor, detail});
  }

  function project() {
    if (!document.getElementById('tab-players')?.classList.contains('active')) return;
    const host = document.querySelector('[data-central-selection-host]');
    if (!host?.querySelector('[data-embedded-teams-selection]')) return;
    portalModals(host);
    document.querySelectorAll('[data-roster-team]').forEach(primary => {
      const teamId = primary.dataset.rosterTeam;
      if (teams.has(teamId)) return;
      const card = host.querySelector('[data-team-card-id="' + CSS.escape(teamId) + '"]');
      const body = primary.querySelector('.roster-team-body');
      const workspace = card?.querySelector('#team-workspace-' + CSS.escape(teamId));
      if (!card || !body || !workspace) return;
      const anchor = document.createComment('team invitation tools'); card.before(anchor);
      const state = {primary, card, body, anchor, workspace, workspaceOpen: workspace.classList.contains('show'), original: [...body.childNodes], rows: [], changes: []};
      const canonical = document.createElement('div'); canonical.dataset.canonicalRoster = ''; canonical.append(...state.original);
      state.canonical = canonical;
      workspace.prepend(canonical); workspace.classList.add('show');
      body.replaceChildren(card);
      const header = card.querySelector('[data-team-workspace-header]');
      state.header = header; state.headerTarget = header?.getAttribute('data-team-workspace-target') ?? null;
      if (header) {
        header.removeAttribute('data-team-workspace-target');
        if (header.firstElementChild) remember(header.firstElementChild, 'hidden', true, state.changes);
        header.querySelectorAll('.team-workspace-toggle').forEach(button => remember(button, 'hidden', true, state.changes));
      }
      card.querySelectorAll('[data-team-invitations] > tr[data-order-player-id], .imported-roster-players > tr[data-slot-id]').forEach(row => {
        if (canonical.contains(row)) return;
        const imported = !!row.dataset.slotId;
        const playerId = row.dataset.invitationPlayerId;
        const selector = imported ? '[data-roster-imported-id="' + CSS.escape(row.dataset.slotId) + '"]' : playerId ? '[data-roster-player-id="' + CSS.escape(playerId) + '"]' : '';
        const target = selector && canonical.querySelector(selector);
        if (target) moveRow(state, row, target, imported);
      });
      card.querySelectorAll('[data-team-invitations], .imported-roster-players').forEach(tbody => {
        const table = tbody.closest('.table-responsive');
        if (table && !tbody.querySelector('tr[data-order-player-id], tr[data-slot-id]')) remember(table, 'hidden', true, state.changes);
      });
      const reserves = canonical.querySelector('.roster-reserves');
      if (reserves && card.querySelector('[data-team-invitations] .reserve-row')) remember(reserves, 'hidden', true, state.changes);
      const history = card.querySelector('[data-team-invitations]')?.closest('.table-responsive');
      if (history && !history.hidden) {
        const label = document.createElement('p'); label.className = 'small fw-semibold px-3 mt-3'; label.textContent = 'Reserve queue & invitation history'; history.before(label); state.rows.push({row: label, anchor: document.createComment(''), detail: label});
      }
      teams.set(teamId, state);
    });
    syncVisibility();
  }

  function syncVisibility() {
    for (const state of teams.values()) for (const move of state.rows) {
      const owner = move.detail.dataset?.rosterDetailRow;
      if (owner) move.detail.hidden = state.primary.querySelector('[data-roster-row="' + CSS.escape(owner) + '"]')?.hidden || false;
    }
  }
  window.mergeTeamRoster = project;
  window.restoreMergedTeamRoster = restore;
  document.addEventListener('roster:loaded', project);
  document.addEventListener('teams:selection-ready', () => queueMicrotask(project));
  document.addEventListener('roster:filtered', syncVisibility);
})();
