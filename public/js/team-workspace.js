/* Keep roster reads and setup changes in place; mutations stay in canonical endpoints. */
(function ($, window, document) {
  'use strict';
  let refreshing = false;
  let refreshAgain = false;
  let refreshTimer;
  let restoring = false;
  const filterNames = ['category', 'payment', 'profile', 'publication'];
  const requests = new Map();
  const savedOpen = new Map();
  let resultRequest;

  function workspaceUrl(parameters) {
    const url = new URL(window.location.href);
    ['workspace', 'roster_region', 'panel'].forEach(key => url.searchParams.delete(key));
    Object.entries(parameters).forEach(([key, value]) => url.searchParams.set(key, value));
    return url.href;
  }

  function feedback(message, retry) {
    let box = document.getElementById('team-workspace-feedback');
    if (!box) {
      box = document.createElement('div');
      box.id = 'team-workspace-feedback';
      box.setAttribute('role', 'status');
      document.getElementById('team-workspace-content')?.before(box);
    }
    box.className = 'alert ' + (retry ? 'alert-warning' : 'alert-info');
    box.replaceChildren(document.createTextNode(message));
    if (retry) {
      const button = document.createElement('button');
      button.type = 'button'; button.className = 'btn btn-outline-secondary ms-2';
      button.textContent = 'Refresh roster'; button.addEventListener('click', window.refreshTeamWorkspace);
      box.append(button);
    }
    box.hidden = !message;
  }

  function saveUrl(push = false) {
    const url = new URL(window.location.href);
    const values = { region: $('[data-roster-region]').val(), search: $('[data-roster-search]').val() };
    filterNames.forEach(name => { values[name] = $('[data-roster-filter="' + name + '"]').val(); });
    values.tab = $('.team-admin-workspace .tabs-wrap .nav-link.active').attr('data-bs-target')?.replace('#tab-', '');
    values.result_category = $('.category-radio:checked').val();
    Object.entries(values).forEach(([key, value]) => {
      if (value) url.searchParams.set('roster_' + key, value); else url.searchParams.delete('roster_' + key);
    });
    // roster_region is also an endpoint parameter; use a distinct browser-state key.
    if (url.searchParams.has('roster_region')) {
      url.searchParams.set('selected_region', url.searchParams.get('roster_region'));
      url.searchParams.delete('roster_region');
    }
    if (push && url.href !== window.location.href) window.history.pushState({}, '', url);
    else window.history.replaceState({}, '', url);
  }

  function applyFilters() {
    const panel = document.querySelector('[data-roster-panel="players"]:not([hidden])');
    if (!panel || panel.dataset.loaded !== 'true') return;
    const terms = String($('[data-roster-search]').val() || '').trim().toLowerCase().split(/\s+/).filter(Boolean);
    const filters = Object.fromEntries(filterNames.map(name => [name, $('[data-roster-filter="' + name + '"]').val()]));
    let matches = 0; let teams = 0;
    panel.querySelectorAll('[data-roster-team]').forEach(team => {
      const teamVisible = (!filters.category || team.dataset.category === filters.category) && (!filters.publication || team.dataset.publication === filters.publication);
      let rowMatches = 0;
      team.querySelectorAll('[data-roster-row]').forEach(row => {
        const haystack = (team.dataset.teamName + ' ' + row.dataset.search).toLowerCase();
        const visible = teamVisible && terms.every(term => haystack.includes(term)) && (!filters.payment || row.dataset.payment === filters.payment) && (!filters.profile || row.dataset.profile === filters.profile);
        row.hidden = !visible;
        if (visible && row.dataset.occupied === 'true') rowMatches++;
      });
      const filtering = terms.length || filters.payment || filters.profile;
      team.hidden = !teamVisible || (filtering && rowMatches === 0);
      if (!team.hidden) { teams++; matches += rowMatches; }
      if (filtering && !team.hidden) team.open = true;
    });
    const count = panel.querySelector('[data-roster-match-count]');
    if (count) count.textContent = matches + ' roster places shown in ' + teams + ' teams';
    const empty = panel.querySelector('[data-roster-empty]');
    if (empty) empty.hidden = teams > 0;
  }

  function loadPanel(panelName, regionId) {
    const panel = document.getElementById(panelName + '-region-' + regionId);
    if (!panel) return Promise.resolve();
    document.querySelectorAll('[data-roster-panel="' + panelName + '"]').forEach(element => { element.hidden = element !== panel; });
    if (panel.dataset.loaded === 'true') { applyFilters(); return Promise.resolve(); }
    const key = panelName + ':' + regionId;
    if (requests.has(key)) return requests.get(key);
    panel.setAttribute('aria-busy', 'true');
    panel.innerHTML = '<div class="p-4" role="status"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading ' + (panelName === 'order' ? 'player order' : 'roster') + '…</div>';
    const request = $.ajax({ url: workspaceUrl({ roster_region: regionId, panel: panelName }), dataType: 'html' })
      .done(html => {
        if (!panel.isConnected) return;
        panel.innerHTML = html;
        panel.dataset.loaded = 'true';
        panel.querySelectorAll('[data-roster-team], [data-order-team]').forEach(team => {
          const id = team.dataset.rosterTeam || team.dataset.orderTeam;
          if (savedOpen.has(panelName + ':' + id)) team.open = savedOpen.get(panelName + ':' + id);
        });
        applyFilters();
        document.dispatchEvent(new Event('roster:loaded'));
      })
      .fail(xhr => {
        if (!panel.isConnected || xhr.statusText === 'abort') return;
        panel.replaceChildren();
        const error = document.createElement('div'); error.className = 'alert alert-warning'; error.setAttribute('role', 'alert');
        error.textContent = xhr.status === 403 ? 'You no longer have access to this roster.' : 'The roster could not be loaded.';
        const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-outline-secondary ms-2'; retry.textContent = 'Retry';
        retry.addEventListener('click', () => loadPanel(panelName, regionId)); error.append(retry); panel.append(error);
      })
      .always(() => { panel.removeAttribute('aria-busy'); requests.delete(key); });
    requests.set(key, request);
    return request;
  }

  function selectRegion(regionId) {
    const select = document.querySelector('[data-roster-region]');
    if (!select) return;
    if (![...select.options].some(option => option.value === String(regionId))) regionId = select.value;
    $('[data-roster-region], [data-order-region]').val(regionId);
    if ($('#tab-order').hasClass('active')) loadPanel('order', regionId);
    else if ($('#tab-players').hasClass('active')) loadPanel('players', regionId);
  }

  function restoreState() {
    restoring = true;
    const url = new URL(window.location.href);
    $('[data-roster-search]').val(url.searchParams.get('roster_search') || '');
    filterNames.forEach(name => { $('[data-roster-filter="' + name + '"]').val(url.searchParams.get('roster_' + name) || ''); });
    const resultCategory = url.searchParams.get('roster_result_category');
    if (resultCategory) { const category = document.querySelector('.category-radio[value="' + Number(resultCategory) + '"]'); if (category) category.checked = true; }
    const tab = url.searchParams.get('roster_tab');
    const button = document.querySelector('.team-admin-workspace [data-bs-target="#tab-' + (['regions', 'order', 'result-rank'].includes(tab) ? tab : 'players') + '"]');
    if (button) bootstrap.Tab.getOrCreateInstance(button).show();
    selectRegion(url.searchParams.get('selected_region') || $('[data-roster-region]').val());
    initCategorySelect();
    if ($('#tab-result-rank').hasClass('active')) loadResults();
    restoring = false;
  }

  function initCategorySelect() {
    const select = $('#category-select');
    if (select.length && !select.hasClass('select2-hidden-accessible')) select.select2({ dropdownParent: $('#add-category-modal'), width: '100%', placeholder: 'Select categories' });
  }

  function loadResults() {
    const category = document.querySelector('.category-radio:checked');
    const target = document.getElementById('category-table');
    if (!category || !target) { if (target) target.textContent = 'No categories added.'; return; }
    resultRequest?.abort();
    $('#category-name').text(category.dataset.name);
    target.setAttribute('aria-busy', 'true'); target.setAttribute('aria-live', 'polite');
    target.textContent = 'Loading category results…';
    resultRequest = $.post(document.getElementById('team-workspace-content').dataset.resultUrl, { event_id: category.dataset.event_id, categoryEvent: category.value })
      .done(response => { target.innerHTML = response.html; })
      .fail(xhr => { if (xhr.statusText !== 'abort') { target.textContent = xhr.responseJSON?.message || 'Results could not be loaded.'; const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-outline-secondary ms-2'; retry.textContent = 'Retry'; retry.addEventListener('click', loadResults); target.append(retry); } })
      .always(() => target.removeAttribute('aria-busy'));
  }

  function refreshWorkspace() {
    if (refreshing) { refreshAgain = true; return; }
    refreshing = true;
    saveUrl();
    const scroll = window.scrollY;
    const focusId = document.activeElement?.id;
    const openRegions = [...document.querySelectorAll('#regionsAccordion .accordion-collapse.show')].map(region => region.id);
    document.querySelectorAll('[data-roster-team], [data-order-team]').forEach(team => savedOpen.set((team.dataset.rosterTeam ? 'players:' : 'order:') + (team.dataset.rosterTeam || team.dataset.orderTeam), team.open));
    feedback('Updating team workspace…');
    $.ajax({ url: workspaceUrl({ workspace: 1 }), dataType: 'html' })
      .done(html => {
        requests.forEach(request => request.abort()); requests.clear();
        const current = document.getElementById('team-workspace-content');
        current.querySelectorAll('.modal').forEach(modal => bootstrap.Modal.getInstance(modal)?.dispose());
        const parsed = $($.parseHTML(html, document, true)).filter('#team-workspace-content');
        if (!parsed.length) { feedback('Changes were saved, but the view could not be refreshed.', true); return; }
        $(current).replaceWith(parsed);
        restoreState();
        openRegions.forEach(id => { const region = document.getElementById(id); if (region) bootstrap.Collapse.getOrCreateInstance(region, { toggle: false }).show(); });
        if (focusId) document.getElementById(focusId)?.focus({ preventScroll: true });
        window.scrollTo(0, scroll);
        feedback('');
        document.dispatchEvent(new Event('roster:loaded'));
      })
      .fail(() => feedback('Changes were saved, but the view is out of date. Refresh the roster to reconcile.', true))
      .always(() => { refreshing = false; if (refreshAgain) { refreshAgain = false; queueRefresh(); } });
  }

  function queueRefresh() {
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(() => {
      if ($('#team-workspace-content .modal.show').length) { queueRefresh(); return; }
      refreshWorkspace();
    }, 400);
  }
  window.refreshTeamWorkspace = queueRefresh;

  $(document).on('ajaxSuccess', function (event, xhr, settings) {
    if (!document.getElementById('team-workspace-content') || !/^(POST|PATCH|PUT|DELETE)$/i.test(settings.type || 'GET')) return;
    if (xhr.responseJSON?.requires_confirmation || /\/orderPlayerList$/.test(settings.url)) return;
    const path = new URL(settings.url, window.location.href).pathname;
    if (/\/backend\/(team(?:\/|$)|teams\/|eventRegion(?:\/|$)|event\/category\/|event\/\d+\/(?:categories\/attach|region\/\d+\/(?:external-teams|teams\/publish)|team\/\d+\/(?:external-roster|name)))/.test(path) && xhr.responseJSON?.success !== false) queueRefresh();
  });
  document.addEventListener('roster:changed', function () {
    document.querySelectorAll('[data-roster-panel="players"]').forEach(panel => { delete panel.dataset.loaded; });
  });

  $(document).on('change', '[data-roster-region], [data-order-region]', function () { selectRegion(this.value); saveUrl(true); });
  $(document).on('input change', '[data-roster-search], [data-roster-filter]', function () { applyFilters(); saveUrl(); });
  $(document).on('click', '[data-roster-clear]', function () { $('[data-roster-search], [data-roster-filter]').val(''); applyFilters(); saveUrl(); });
  $(document).on('click', '[data-workspace-refresh]', queueRefresh);
  $(document).on('click', '[data-roster-expand]', function () { document.querySelectorAll('[data-roster-panel="players"]:not([hidden]) [data-roster-team]:not([hidden])').forEach(team => { team.open = this.dataset.rosterExpand === 'true'; }); });
  $(document).on('shown.bs.tab', '.team-admin-workspace .tabs-wrap [data-bs-toggle="tab"]', function () { if (!restoring) { selectRegion($('[data-roster-region]').val()); saveUrl(true); if (this.dataset.bsTarget === '#tab-result-rank') loadResults(); } });
  $(document).on('change', '.team-admin-workspace .category-radio', function () { loadResults(); saveUrl(); });
  $(document).on('click', '[data-copy-contact]', async function () {
    try { await navigator.clipboard.writeText(this.dataset.copyContact); toastr.success('Contact copied.'); }
    catch (_) { toastr.info('Select the contact text to copy it.'); }
  });
  $(document).on('submit', '#team-workspace-content form[action][method="POST"]', function (event) {
    if (!this.querySelector('[name="rename_team_id"]')) return;
    event.preventDefault();
    const form = this; const button = form.querySelector('[type="submit"]');
    if (button.disabled) return;
    button.disabled = true;
    $.ajax({ url: form.action, type: 'POST', data: $(form).serialize(), headers: { Accept: 'application/json' } })
      .done(() => toastr.success('Team name saved.'))
      .fail(xhr => toastr.error(xhr.responseJSON?.errors?.name?.[0] || xhr.responseJSON?.message || 'Unable to save the team name.'))
      .always(() => { button.disabled = false; });
  });
  window.addEventListener('popstate', restoreState);
  $(restoreState);
})(jQuery, window, document);
