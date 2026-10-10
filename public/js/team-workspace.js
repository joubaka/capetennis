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
  let selectionVersion = 0;
  let savedDraft;
  let applyingDraft;
  let resultGeneration = 0;
  let draftReady = false;
  let draftFetchSequence = 0;
  let editingGroup;
  let saveRequestBusy = false;

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
    const values = { region: $('[data-roster-region]').val(), search: $('[data-roster-search]').val(), order_search: $('[data-order-search]').val() };
    filterNames.forEach(name => { values[name] = $('[data-roster-filter="' + name + '"]').val(); });
    values.tab = $('.team-admin-workspace .tabs-wrap .nav-link.active').attr('data-bs-target')?.replace('#tab-', '');
    values.result_category = $('.category-radio:checked').val();
    values.result_regions = $('[data-result-region]:checked').map(function () { return this.value; }).get().join(',') || 'none';
    values.result_formats = $('[data-result-format]:checked').map(function () { return this.value; }).get().join(',') || 'none';
    values.result_excluded_regions = $('[data-result-excluded-region]:checked').map(function () { return this.value; }).get().join(',') || 'none';
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

  function updateAdvancedFilters(reveal = false) {
    const count = ['category', 'profile', 'publication'].filter(name => $('[data-roster-filter="' + name + '"]').val()).length;
    $('[data-roster-advanced-count]').text(count ? '(' + count + ' active)' : '');
    if (reveal && count) $('[data-roster-more-filters]').prop('open', true);
  }

  function applyOrderSearch() {
    const panel = document.querySelector('[data-roster-panel="order"]:not([hidden])');
    if (!panel || panel.dataset.loaded !== 'true') return;
    const terms = String($('[data-order-search]').val() || '').trim().toLowerCase().split(/\s+/).filter(Boolean);
    let matches = 0;
    panel.querySelectorAll('[data-order-team]').forEach(team => {
      team.hidden = !terms.every(term => (team.dataset.orderTeamName || '').toLowerCase().includes(term));
      if (!team.hidden) { matches++; if (terms.length) team.open = true; }
    });
    const count = panel.querySelector('[data-order-match-count]');
    if (count) count.textContent = matches + ' teams shown';
    const empty = panel.querySelector('[data-order-empty]');
    if (empty) empty.hidden = matches > 0;
  }

  function applyFilters() {
    applyOrderSearch();
    updateAdvancedFilters();
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
    $('[data-order-search]').val(url.searchParams.get('roster_order_search') || '');
    filterNames.forEach(name => { $('[data-roster-filter="' + name + '"]').val(url.searchParams.get('roster_' + name) || ''); });
    updateAdvancedFilters(true);
    const resultCategory = url.searchParams.get('roster_result_category');
    if (resultCategory) { const category = [...document.querySelectorAll('.category-radio')].find(input => input.value === resultCategory); if (category) category.checked = true; }
    ['regions', 'formats'].forEach(name => {
      const saved = url.searchParams.get('roster_result_' + name);
      document.querySelectorAll('[data-result-' + (name === 'regions' ? 'region' : 'format') + ']').forEach(input => { input.checked = saved === null || saved.split(',').includes(input.value); });
    });
    const excludedRegions = (url.searchParams.get('roster_result_excluded_regions') || '').split(',');
    document.querySelectorAll('[data-result-excluded-region]').forEach(input => { input.checked = excludedRegions.includes(input.value); });
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
    if (!category || !target) { if (target) target.textContent = 'No singles fixture age groups found.'; return; }
    resultRequest?.abort();
    const generation = ++resultGeneration;
    const group = category.value;
    const draftToApply = applyingDraft?.group_key === group ? applyingDraft : null;
    applyingDraft = null;
    if (editingGroup !== group) { editingGroup = group; draftReady = false; selectionVersion = 0; savedDraft = null; }
    $('[data-selection-save]').prop('disabled', !draftReady || saveRequestBusy);
    $('#category-name').text(category.dataset.name);
    target.setAttribute('aria-busy', 'true'); target.setAttribute('aria-live', 'polite');
    target.textContent = 'Loading category results…';
    resultRequest = $.ajax({ url: document.getElementById('team-workspace-content').dataset.resultUrl, type: 'POST', contentType: 'application/json', data: JSON.stringify({ event_id: category.dataset.event_id, result_group: category.value, regions: $('[data-result-region]:checked').map(function () { return this.value; }).get(), formats: $('[data-result-format]:checked').map(function () { return this.value; }).get(), excluded_result_region_ids: $('[data-result-excluded-region]:checked').map(function () { return this.value; }).get() }) })
      .done(response => { if (generation !== resultGeneration) return; target.innerHTML = response.html; if (draftToApply) { savedDraft = draftToApply; applyDraftPlayers(); } else if (!draftReady) fetchDraft(false); })
      .fail(xhr => { if (xhr.statusText !== 'abort') { target.textContent = xhr.responseJSON?.message || 'Results could not be loaded.'; const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-outline-secondary ms-2'; retry.textContent = 'Retry'; retry.addEventListener('click', loadResults); target.append(retry); } })
      .always(() => { if (generation === resultGeneration) target.removeAttribute('aria-busy'); });
  }

  function selectionStatus(message) { $('[data-selection-status]').text(message); }
  function selectionGroup() { return $('.category-radio:checked').val(); }
  function applyDraftPlayers() {
    if (!savedDraft) return;
    document.querySelectorAll('[data-selection-player]').forEach(input => { input.checked = savedDraft.selected_keys.includes(input.value); });
    document.querySelectorAll('[data-selection-reason]').forEach(input => { input.value = savedDraft.reasons?.[input.dataset.selectionReason] || ''; });
    selectionStatus('Saved draft loaded. Review the current results before saving changes.');
  }
  function fetchDraft(apply) {
    const group = selectionGroup();
    const generation = resultGeneration;
    const fetchSequence = ++draftFetchSequence;
    const url = document.getElementById('tab-result-rank')?.dataset.selectionUrl;
    if (!url || !group) return;
    $.getJSON(url, { group_key: group }).done(response => {
      if (group !== selectionGroup() || generation !== resultGeneration || fetchSequence !== draftFetchSequence) return;
      if (!apply) {
        draftReady = !response.draft; selectionVersion = 0;
        $('[data-selection-save]').prop('disabled', !draftReady || saveRequestBusy);
        selectionStatus(response.draft ? 'A saved draft is available. Load it before editing or saving.' : 'No saved draft for this group.'); return;
      }
      savedDraft = response.draft; selectionVersion = savedDraft?.version || 0;
      if (!savedDraft) { selectionStatus('No saved draft for this group.'); return; }
      document.querySelectorAll('[data-result-region]').forEach(input => { input.checked = savedDraft.region_ids.map(String).includes(input.value); });
      document.querySelectorAll('[data-result-excluded-region]').forEach(input => { input.checked = (savedDraft.excluded_result_region_ids || []).map(String).includes(input.value); });
      document.querySelectorAll('[data-result-format]').forEach(input => { input.checked = savedDraft.formats.includes(input.value); });
      applyingDraft = savedDraft; draftReady = true; $('[data-selection-save]').prop('disabled', false); saveUrl(); loadResults();
    }).fail(xhr => { if (group === selectionGroup() && generation === resultGeneration && fetchSequence === draftFetchSequence) selectionStatus(xhr.responseJSON?.message || 'Draft could not be loaded.'); });
  }
  $(document).on('click', '[data-selection-load]', () => fetchDraft(true));
  $(document).on('click', '[data-selection-save]', function () {
    const button = this;
    const group = selectionGroup();
    const generation = resultGeneration;
    if (saveRequestBusy || !draftReady || !group || document.getElementById('category-table')?.getAttribute('aria-busy') === 'true') return;
    const selected = $('[data-selection-player]:checked').map(function () { return this.value; }).get();
    if (selected.length > 10) { selectionStatus('Choose no more than 10 players.'); return; }
    const reasons = {};
    document.querySelectorAll('[data-selection-reason]').forEach(input => { if (input.value.trim()) reasons[input.dataset.selectionReason] = input.value.trim(); });
    saveRequestBusy = true; button.disabled = true;
    $.ajax({ url: document.getElementById('tab-result-rank').dataset.selectionUrl, type: 'PUT', contentType: 'application/json', data: JSON.stringify({
      group_key: group, region_ids: $('[data-result-region]:checked').map(function () { return Number(this.value); }).get(),
      excluded_result_region_ids: $('[data-result-excluded-region]:checked').map(function () { return Number(this.value); }).get(),
      formats: $('[data-result-format]:checked').map(function () { return this.value; }).get(), selected_keys: selected, reasons: reasons, version: selectionVersion
    }) }).done(response => { if (group !== selectionGroup() || generation !== resultGeneration) return; savedDraft = response.draft; selectionVersion = savedDraft.version; selectionStatus('Draft selection saved. It has not been published.'); })
      .fail(xhr => { if (group !== selectionGroup() || generation !== resultGeneration) return; const errors = Object.values(xhr.responseJSON?.errors || {}).flat().join(' '); selectionStatus(xhr.status === 409 ? 'This draft changed elsewhere. Load the saved draft before saving again.' : (errors || xhr.responseJSON?.message || 'Draft could not be saved.')); })
      .always(() => { saveRequestBusy = false; $('[data-selection-save]').prop('disabled', !draftReady); });
  });

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
  $(document).on('input change', '[data-order-search]', function () { applyOrderSearch(); saveUrl(); });
  $(document).on('click', '[data-order-clear]', function () { $('[data-order-search]').val(''); applyOrderSearch(); saveUrl(); });
  $(document).on('click', '[data-workspace-refresh]', queueRefresh);
  $(document).on('click', '[data-roster-expand]', function () { document.querySelectorAll('[data-roster-panel="players"]:not([hidden]) [data-roster-team]:not([hidden])').forEach(team => { team.open = this.dataset.rosterExpand === 'true'; }); });
  $(document).on('shown.bs.tab', '.team-admin-workspace .tabs-wrap [data-bs-toggle="tab"]', function () { if (!restoring) { selectRegion($('[data-roster-region]').val()); saveUrl(true); if (this.dataset.bsTarget === '#tab-result-rank') loadResults(); } });
  $(document).on('change', '.team-admin-workspace .category-radio, [data-result-region], [data-result-excluded-region], [data-result-format]', function () { loadResults(); saveUrl(); });
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
