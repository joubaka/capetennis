(() => {
  const workspace = document.getElementById('regional-preview-workspace');
  if (!workspace) return;
  const filter = () => {
    const region = workspace.querySelector('[data-roster-region]')?.value;
    workspace.querySelectorAll('[data-roster-panel]').forEach(panel => {
      panel.hidden = panel.dataset.regionId !== region;
      const query = (workspace.querySelector(panel.dataset.rosterPanel === 'order' ? '[data-order-search]' : '[data-roster-search]')?.value || '').trim().toLowerCase();
      panel.querySelectorAll('[data-roster-team], [data-order-team]').forEach(team => {
        const name = (team.dataset.teamName || team.dataset.orderTeamName || '').toLowerCase();
        let matches = name.includes(query);
        team.querySelectorAll('[data-roster-row]').forEach(row => {
          row.hidden = query !== '' && !name.includes(query) && !(row.dataset.search || '').toLowerCase().includes(query);
          matches ||= !row.hidden;
        });
        team.hidden = !matches;
      });
    });
  };
  workspace.addEventListener('click', event => {
    const tab = event.target.closest('[data-bs-toggle="tab"]');
    if (tab) {
      event.preventDefault();
      const target = workspace.querySelector(tab.dataset.bsTarget);
      if (!target) return;
      target.parentElement.querySelectorAll(':scope > .tab-pane').forEach(panel => panel.classList.remove('active', 'show'));
      target.classList.add('active', 'show');
      workspace.querySelectorAll('[data-bs-toggle="tab"]').forEach(link => {
        const selected = link === tab;
        link.classList.toggle('active', selected); link.setAttribute('aria-selected', selected ? 'true' : 'false');
      });
    }
    if (event.target.closest('[data-roster-clear], [data-order-clear]')) {
      workspace.querySelectorAll('[data-roster-search], [data-order-search]').forEach(input => input.value = ''); filter();
    }
  });
  workspace.addEventListener('change', event => {
    if (event.target.matches('[data-roster-region], [data-order-region]')) {
      workspace.querySelectorAll('[data-roster-region], [data-order-region]').forEach(select => select.value = event.target.value); filter();
    }
  });
  workspace.addEventListener('input', event => {
    if (event.target.matches('[data-roster-search], [data-order-search]')) filter();
  });
  filter();
})();
