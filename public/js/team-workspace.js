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
  let rankingCriteriaFingerprint = null;
  let savedDraft;
  let applyingDraft;
  let resultGeneration = 0;
  let draftReady = false;
  let draftFinalized = false;
  let draftFetchSequence = 0;
  let editingGroup;
  let saveRequestBusy = false;
  let draftDirty = false;
  let editRevision = 0;
  let renderedSetup;
  let draftBaseline;
  let pendingEvidenceCard;
  let autosaveTimer;
  let autosavePending = false;
  let autosavePaused = false;
  let previewPendingUrl;

  function continueSelectionPreview() {
    if (!previewPendingUrl || saveRequestBusy) return;
    if (autosavePaused && !draftFinalized) { previewPendingUrl = null; $('[data-selection-preview]').prop('disabled', false); return; }
    if (draftFinalized || !draftDirty) { const url = previewPendingUrl; previewPendingUrl = null; $('[data-selection-preview]').prop('disabled', false); window.location.assign(url); return; }
    autosavePending = true;
    flushDraftAutosave();
  }
  $(document).on('click', '[data-selection-preview]', function () {
    if (autosavePaused && !draftFinalized) { selectionStatus('Resolve the paused draft save before opening the preview. Your edits are kept.'); return; }
    if (!document.querySelector('.result-player-list, .result-empty') || document.getElementById('category-table')?.getAttribute('aria-busy') === 'true') { selectionStatus('Wait for the results to load before opening the preview.'); return; }
    const group = /^(\d{1,2})-(boys|girls)$/.exec(selectionGroup() || '');
    const previewUrl = new URL(this.dataset.selectionPreview, window.location.href);
    if (group) { previewUrl.searchParams.set('age', group[1]); previewUrl.searchParams.set('gender', group[2]); }
    previewPendingUrl = previewUrl.href;
    $('[data-selection-preview]').prop('disabled', true);
    selectionStatus('Saving your current choices before opening Preview Selections…');
    continueSelectionPreview();
  });

  function scheduleDraftAutosave() {
    window.clearTimeout(autosaveTimer);
    autosavePending = draftDirty;
    if (!autosavePending || autosavePaused) return;
    autosaveTimer = window.setTimeout(flushDraftAutosave, 850);
  }
  function flushDraftAutosave() {
    if (!autosavePending || autosavePaused || saveRequestBusy || !draftReady || document.getElementById('category-table')?.getAttribute('aria-busy') === 'true') return;
    $('[data-selection-save]').first().trigger('click');
  }
  function resetDraftAutosave() {
    window.clearTimeout(autosaveTimer); autosavePending = false; autosavePaused = false; previewPendingUrl = null; $('[data-selection-preview]').prop('disabled', false);
  }
  let eventSettingsBaseline;
  let eventSettingsBusy = false;
  let eventSettingsSaved = false;

  function eventSettings() {
    const values = selector => [...document.querySelectorAll(selector + ':checked')].map(input => input.value);
    return { region_ids: values('[data-result-region]').map(Number), excluded_result_region_ids: values('[data-result-excluded-region]').map(Number), formats: values('[data-result-format]') };
  }
  function updateEventSettingsStatus() {
    if (!eventSettingsBusy) $('[data-result-settings-status]').text(JSON.stringify(eventSettings()) === eventSettingsBaseline ? (eventSettingsSaved ? 'Event settings saved.' : 'Default settings. Save to keep them for this event.') : 'Unsaved event settings.');
  }
  $(document).on('click', '[data-result-settings-save]', function () {
    if (eventSettingsBusy) return;
    const payload = eventSettings();
    eventSettingsBusy = true;
    $('[data-result-settings-save]').prop('disabled', true);
    $('[data-result-settings-status]').text('Saving event settings…');
    $.ajax({ url: document.getElementById('tab-result-rank').dataset.settingsUrl, type: 'PUT', contentType: 'application/json', data: JSON.stringify(payload) })
      .done(() => {
        eventSettingsBaseline = JSON.stringify(payload); eventSettingsSaved = true; eventSettingsBusy = false;
        [['region', payload.region_ids.map(String)], ['excluded-region', payload.excluded_result_region_ids.map(String)], ['format', payload.formats]].forEach(([name, values]) => {
          document.querySelectorAll('[data-result-' + name + ']').forEach(input => { input.defaultChecked = values.includes(input.value); });
        });
        updateEventSettingsStatus();
      })
      .fail(xhr => { eventSettingsBusy = false; $('[data-result-settings-status]').text(xhr.responseJSON?.message || 'Event settings could not be saved. Try again.'); })
      .always(() => { $('[data-result-settings-save]').prop('disabled', false); });
  });

  function draftFingerprint() { return JSON.stringify([setupState(), draftRows().map(row => {
    const card = draftCards().find(card => card.dataset.resultPlayer === row.dataset.draftKey);
    return [row.dataset.draftKey, !!card?.querySelector('[data-selection-player]').checked, card?.querySelector('[data-selection-reason]').value.trim() || ''];
  })]); }
  function markDraftChanged() { draftDirty = draftFingerprint() !== draftBaseline; editRevision++; updateDraftProgress(); scheduleDraftAutosave(); }
  function confirmDraftDiscard() { if (saveRequestBusy) { selectionStatus('Wait for the draft save to finish before changing the setup or loading a draft.'); return false; } return !draftDirty || window.confirm('Discard your unsaved draft order, selections and comments?'); }
  function setupState() {
    return ['.category-radio', '[data-result-region]', '[data-result-excluded-region]', '[data-result-format]'].map(selector =>
      [selector, [...document.querySelectorAll(selector)].filter(input => input.checked).map(input => input.value)]);
  }
  function restoreSetup(state) {
    (state || []).forEach(([selector, values]) => document.querySelectorAll(selector).forEach(input => { input.checked = values.includes(input.value); }));
    const select = document.querySelector('[data-result-group-select]'); if (select) select.value = selectionGroup();
  }
  function updateDraftProgress() {
    const rows = draftRows();
    let selected = 0;
    rows.forEach((row, index) => {
      const card = draftCards().find(card => card.dataset.resultPlayer === row.dataset.draftKey);
      if (!card) return;
      const included = card.querySelector('[data-selection-player]').checked;
      if (included) selected++;
      const field = row.querySelector('[data-draft-inline-reason]');
      if (field) { field.closest('label').hidden = false; field.required = false; field.setAttribute('aria-label', 'Comment (optional) for ' + card.dataset.playerName); row.querySelector('[data-draft-reason-label]').textContent = 'Comment (optional)'; }
      const marker = row.querySelector('[data-draft-changed]'); if (marker) marker.textContent = field?.value.trim() ? 'Comment recorded' : '';
    });
    $('[data-draft-count]').text(selected + ' / 12 selected');
    $('[data-draft-state]').text(saveRequestBusy ? 'Saving private draft…' : (!draftReady ? 'Checking saved draft before saving' : (draftDirty ? (autosavePaused ? 'Autosave paused · unsaved changes' : 'Unsaved changes · autosave pending') : (selectionVersion ? 'Private draft · saved version ' + selectionVersion + ' available' : 'Private draft · not saved'))));
    $('[data-draft-reasons]').text('Comments are optional.');
    updateDraftChrome();
  }
  function updateDraftChrome() {
    let bottom = 12;
    document.querySelectorAll('#layout-navbar, #layout-menu').forEach(element => {
      const style = window.getComputedStyle(element), rect = element.getBoundingClientRect();
      if (['fixed', 'sticky'].includes(style.position) && rect.top <= 130 && rect.bottom > 0 && rect.height < window.innerHeight / 2) bottom = Math.max(bottom, rect.bottom + 12);
    });
    document.querySelector('#tab-result-rank')?.style.setProperty('--result-sticky-top', bottom + 'px');
    const actions = document.querySelector('.result-persistent-actions');
    document.querySelector('#tab-result-rank')?.style.setProperty('--result-toolbar-space', (actions?.getBoundingClientRect().height || 100) + 35 + 'px');
  }
  window.addEventListener('beforeunload', event => { if (draftDirty || saveRequestBusy || eventSettingsBusy || (eventSettingsBaseline !== undefined && JSON.stringify(eventSettings()) !== eventSettingsBaseline)) { event.preventDefault(); event.returnValue = ''; } });
  window.addEventListener('resize', updateDraftChrome);
  window.addEventListener('scroll', updateDraftChrome, { passive: true });

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
    if (eventSettingsBaseline === undefined) { eventSettingsBaseline = JSON.stringify(eventSettings()); eventSettingsSaved = document.getElementById('tab-result-rank')?.dataset.settingsSaved === '1'; }
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
      document.querySelectorAll('[data-result-' + (name === 'regions' ? 'region' : 'format') + ']').forEach(input => { input.checked = saved === null ? input.defaultChecked : saved.split(',').includes(input.value); });
    });
    const excludedRegions = url.searchParams.get('roster_result_excluded_regions');
    document.querySelectorAll('[data-result-excluded-region]').forEach(input => { input.checked = excludedRegions === null ? input.defaultChecked : excludedRegions.split(',').includes(input.value); });
    updateEventSettingsStatus();
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
    resetDraftAutosave();
    const category = document.querySelector('.category-radio:checked');
    const target = document.getElementById('category-table');
    if (!category || !target) { if (target) target.textContent = 'No singles fixture age groups found.'; return; }
    resultRequest?.abort();
    const generation = ++resultGeneration;
    const group = category.value;
    renderedSetup = setupState();
    draftDirty = false;
    const groupSelect = document.querySelector('[data-result-group-select]'); if (groupSelect) groupSelect.value = group;
    const draftToApply = applyingDraft?.group_key === group ? applyingDraft : null;
    applyingDraft = null;
    if (editingGroup !== group) { editingGroup = group; draftReady = false; selectionVersion = 0; savedDraft = null; }
    $('[data-selection-save]').prop('disabled', true);
    $('#category-name, [data-draft-category]').text(category.dataset.name);
    target.setAttribute('aria-busy', 'true'); target.setAttribute('aria-live', 'polite');
    const orderList = document.querySelector('[data-draft-order]');
    if (orderList) { window.Sortable?.get(orderList)?.destroy(); orderList.replaceChildren(); }
    target.textContent = 'Loading category results…';
    resultRequest = $.ajax({ url: document.getElementById('team-workspace-content').dataset.resultUrl, type: 'POST', contentType: 'application/json', data: JSON.stringify({ event_id: category.dataset.event_id, result_group: category.value, regions: $('[data-result-region]:checked').map(function () { return this.value; }).get(), formats: $('[data-result-format]:checked').map(function () { return this.value; }).get(), excluded_result_region_ids: $('[data-result-excluded-region]:checked').map(function () { return this.value; }).get() }) })
      .done(response => { if (generation !== resultGeneration) return; rankingCriteriaFingerprint = response.criteria_fingerprint; target.innerHTML = response.html; buildDraftOrder(); $('[data-selection-save]').prop('disabled', !draftReady || saveRequestBusy); if (draftToApply) { savedDraft = draftToApply; applyDraftPlayers(); } else if (!draftReady) fetchDraft(false); })
      .fail(xhr => { if (generation !== resultGeneration) return; if (xhr.statusText !== 'abort') { target.textContent = xhr.responseJSON?.message || 'Results could not be loaded.'; const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-outline-secondary ms-2'; retry.textContent = 'Retry'; retry.addEventListener('click', loadResults); target.append(retry); } })
      .always(() => { if (generation === resultGeneration) target.removeAttribute('aria-busy'); });
  }

  function selectionStatus(message) { $('[data-selection-status]').text(message); updateDraftChrome(); }
  function selectionGroup() { return $('.category-radio:checked').val(); }
  function draftRows() { return [...document.querySelectorAll('[data-draft-key]')]; }
  function draftCards() { return [...document.querySelectorAll('[data-result-player]')]; }
  function draftOrderChanged(announce = true) {
    const calculated = draftCards().map(card => card.dataset.resultPlayer);
    draftRows().forEach((row, index, rows) => {
      row.querySelector('[data-draft-position]').textContent = index + 1;
      row.querySelector('[data-draft-up]').disabled = index === 0;
      row.querySelector('[data-draft-down]').disabled = index === rows.length - 1;
      const changed = calculated[index] !== row.dataset.draftKey;
      row.classList.toggle('is-reordered', changed);
      row.querySelector('[data-draft-changed]').hidden = !changed;
      if (changed) draftCards().find(card => card.dataset.resultPlayer === row.dataset.draftKey)?.querySelector('.result-reason')?.setAttribute('open', '');
    });
    if (announce) { markDraftChanged(); selectionStatus('Draft order changed. Your private draft saves automatically; comments are optional.'); }
    updateDraftProgress();
  }
  function buildDraftOrder() {
    const list = document.querySelector('[data-draft-order]');
    if (!list) return;
    if (window.Sortable?.get(list)) window.Sortable.get(list).destroy();
    list.replaceChildren();
    draftCards().forEach(card => {
      const row = document.createElement('li'); row.dataset.draftKey = card.dataset.resultPlayer;
      const handle = document.createElement('button'); handle.type = 'button'; handle.className = 'result-drag-handle'; handle.textContent = '⠿'; handle.setAttribute('aria-label', 'Drag ' + card.dataset.playerName);
      const position = document.createElement('span'); position.dataset.draftPosition = '';
      const identity = document.createElement('button'); identity.type = 'button'; identity.className = 'result-draft-identity'; identity.textContent = card.dataset.playerName + ' · ' + card.dataset.playerRegion;
      identity.addEventListener('click', () => {
        const drawer = document.getElementById('result-draft-drawer');
        if (drawer?.classList.contains('show')) { pendingEvidenceCard = card; bootstrap.Offcanvas.getInstance(drawer)?.hide(); return; }
        showDraftEvidence(card);
      });
      const selected = document.createElement('input'); selected.type = 'checkbox'; selected.className = 'form-check-input'; selected.checked = card.querySelector('[data-selection-player]').checked; selected.dataset.draftSelect = ''; selected.setAttribute('aria-label', 'Select ' + card.dataset.playerName);
      selected.addEventListener('change', () => { card.querySelector('[data-selection-player]').checked = selected.checked; markDraftChanged(); selectionStatus('Draft selection changed. Your private draft saves automatically.'); });
      const controls = document.createElement('div'); controls.className = 'result-draft-moves';
      ['up', 'down'].forEach(direction => {
        const button = document.createElement('button'); button.type = 'button'; button.textContent = direction === 'up' ? '↑' : '↓'; button.dataset[direction === 'up' ? 'draftUp' : 'draftDown'] = ''; button.setAttribute('aria-label', 'Move ' + card.dataset.playerName + ' ' + direction);
        button.addEventListener('click', () => { const neighbour = direction === 'up' ? row.previousElementSibling : row.nextElementSibling; if (!neighbour) return; list.insertBefore(direction === 'up' ? row : neighbour, direction === 'up' ? neighbour : row); draftOrderChanged(); button.focus(); }); controls.append(button);
      });
      const changed = document.createElement('span'); changed.dataset.draftChanged = ''; changed.className = 'result-draft-changed'; changed.textContent = '';
      const reasonLabel = document.createElement('label'); reasonLabel.className = 'result-inline-reason';
      const reasonHeading = document.createElement('span'); reasonHeading.dataset.draftReasonLabel = ''; reasonHeading.textContent = 'Comment (optional)'; reasonLabel.append(reasonHeading);
      const reason = document.createElement('input'); reason.type = 'text'; reason.maxLength = 2000; reason.className = 'form-control'; reason.dataset.draftInlineReason = ''; reason.value = card.querySelector('[data-selection-reason]').value; reason.setAttribute('aria-label', 'Comment (optional) for ' + card.dataset.playerName);
      reason.addEventListener('input', () => { card.querySelector('[data-selection-reason]').value = reason.value; markDraftChanged(); });
      reasonLabel.append(reason);
      row.append(handle, position, selected, identity, controls, changed, reasonLabel); list.append(row);
    });
    if (window.Sortable) new window.Sortable(list, { animation: 150, handle: '.result-drag-handle', draggable: '[data-draft-key]', ghostClass: 'result-draft-ghost', onEnd: () => draftOrderChanged() });
    draftOrderChanged(false);
    if (!selectionVersion) draftBaseline = draftFingerprint();
    draftDirty = draftFingerprint() !== draftBaseline;
    updateDraftProgress();
    updateDraftChrome();
  }
  function applyDraftPlayers() {
    if (!savedDraft) return;
    document.querySelectorAll('[data-selection-player]').forEach(input => { input.checked = savedDraft.selected_keys.includes(input.value); });
    document.querySelectorAll('[data-selection-reason]').forEach(input => { input.value = savedDraft.reasons?.[input.dataset.selectionReason] || ''; });
    const list = document.querySelector('[data-draft-order]');
    const order = savedDraft.draft_order || [...(savedDraft.snapshot || [])].sort((a, b) => (a.draft_position || a.position || 0) - (b.draft_position || b.position || 0)).map(row => String(row.id));
    order.forEach(key => { const row = draftRows().find(row => row.dataset.draftKey === key); if (row) list.append(row); });
    draftRows().forEach(row => { row.querySelector('[data-draft-select]').checked = savedDraft.selected_keys.includes(row.dataset.draftKey); row.querySelector('[data-draft-inline-reason]').value = savedDraft.reasons?.[row.dataset.draftKey] || ''; });
    draftDirty = false;
    draftBaseline = draftFingerprint();
    draftOrderChanged(false);
    selectionStatus('Saved draft loaded. Review the current results before saving changes.');
  }
  function fetchDraft(apply) {
    if (apply && !confirmDraftDiscard()) return;
    const requestedRevision = editRevision;
    const requestedVersion = selectionVersion;
    const group = selectionGroup();
    const generation = resultGeneration;
    const fetchSequence = ++draftFetchSequence;
    const url = document.getElementById('tab-result-rank')?.dataset.selectionUrl;
    if (!url || !group) return;
    $('[data-selection-retry]').prop('hidden', true);
    $.getJSON(url, { group_key: group }).done(response => {
      if (group !== selectionGroup() || generation !== resultGeneration || fetchSequence !== draftFetchSequence) return;
      draftFinalized = !!response.finalized;
      if (draftFinalized) {
        draftReady = false; autosavePaused = true; autosavePending = false; window.clearTimeout(autosaveTimer);
        $('[data-selection-save], [data-selection-player], [data-selection-reason], [data-draft-select], [data-draft-inline-reason], [data-draft-up], [data-draft-down]').prop('disabled', true);
        const list = document.querySelector('[data-draft-order]'); if (list) window.Sortable?.get(list)?.option('disabled', true);
        updateDraftProgress(); selectionStatus('Finalized—ranking locked. Open Preview Selections to see the final ranking.'); $('[data-selection-preview]').prop('disabled', false); continueSelectionPreview(); return;
      }
      if (saveRequestBusy || selectionVersion !== requestedVersion) { selectionStatus('The draft was saved while checking. Retry the draft check to get its latest version.'); return; }
      if (!apply) {
        draftReady = true; selectionVersion = response.draft?.version || 0;
        $('[data-selection-save]').prop('disabled', !draftReady || saveRequestBusy);
        updateDraftProgress();
        scheduleDraftAutosave();
        continueSelectionPreview();
        selectionStatus(response.draft ? 'Saved draft found. Save your current choices to update it, or Load draft to restore its choices.' : 'No saved draft for this group. Your current choices can be saved.'); return;
      }
      if (editRevision !== requestedRevision && !confirmDraftDiscard()) return;
      savedDraft = response.draft; selectionVersion = savedDraft?.version || 0;
      if (!savedDraft) { draftReady = true; $('[data-selection-save]').prop('disabled', saveRequestBusy); updateDraftProgress(); scheduleDraftAutosave(); selectionStatus('No saved draft for this group. Your current choices can be saved.'); return; }
      document.querySelectorAll('[data-result-region]').forEach(input => { input.checked = savedDraft.region_ids.map(String).includes(input.value); });
      document.querySelectorAll('[data-result-excluded-region]').forEach(input => { input.checked = (savedDraft.excluded_result_region_ids || []).map(String).includes(input.value); });
      document.querySelectorAll('[data-result-format]').forEach(input => { input.checked = savedDraft.formats.includes(input.value); });
      applyingDraft = savedDraft; draftReady = true; updateEventSettingsStatus(); saveUrl(); loadResults();
    }).fail(xhr => {
      if (group !== selectionGroup() || generation !== resultGeneration || fetchSequence !== draftFetchSequence) return;
      $('[data-selection-retry]').prop('hidden', false);
      previewPendingUrl = null; $('[data-selection-preview]').prop('disabled', false);
      selectionStatus((xhr.responseJSON?.message || 'Saved draft could not be checked.') + ' Retry the draft check before saving. Your current choices are kept.');
    });
  }
  $(document).on('click', '[data-selection-retry]', () => fetchDraft(false));
  $(document).on('click', '[data-selection-load]', () => fetchDraft(true));
  $(document).on('click', '[data-selection-save]', function () {
    const button = this;
    const group = selectionGroup();
    const generation = resultGeneration;
    const submittedFingerprint = draftFingerprint();
    if (saveRequestBusy || !draftReady || !group || document.getElementById('category-table')?.getAttribute('aria-busy') === 'true' || !document.querySelector('.result-player-list, .result-empty')) return;
    window.clearTimeout(autosaveTimer); autosavePending = false; autosavePaused = false;
    const order = draftRows().map(row => row.dataset.draftKey);
    const selected = order.filter(key => draftCards().find(card => card.dataset.resultPlayer === key)?.querySelector('[data-selection-player]').checked);
    if (selected.length > 12) { previewPendingUrl = null; $('[data-selection-preview]').prop('disabled', false); selectionStatus('Choose no more than 12 players.'); return; }
    const reasons = {};
    document.querySelectorAll('[data-selection-reason]').forEach(input => { if (input.value.trim()) reasons[input.dataset.selectionReason] = input.value.trim(); });
    saveRequestBusy = true; button.disabled = true; updateDraftProgress();
    $.ajax({ url: document.getElementById('tab-result-rank').dataset.selectionUrl, type: 'PUT', contentType: 'application/json', data: JSON.stringify({
      group_key: group, region_ids: $('[data-result-region]:checked').map(function () { return Number(this.value); }).get(),
      excluded_result_region_ids: $('[data-result-excluded-region]:checked').map(function () { return Number(this.value); }).get(),
      formats: $('[data-result-format]:checked').map(function () { return this.value; }).get(), selected_keys: selected, draft_order: order, reasons: reasons, version: selectionVersion, criteria_fingerprint: rankingCriteriaFingerprint
    }) }).done(response => { if (group !== selectionGroup() || generation !== resultGeneration) return; savedDraft = response.draft; selectionVersion = savedDraft.version; draftBaseline = submittedFingerprint; draftDirty = draftFingerprint() !== draftBaseline; selectionStatus(draftDirty ? 'Earlier changes saved. You have newer unsaved changes.' : 'Draft selection saved. It has not been published.'); })
      .fail(xhr => { if (group !== selectionGroup() || generation !== resultGeneration) return; autosavePaused = true; previewPendingUrl = null; $('[data-selection-preview]').prop('disabled', false); window.clearTimeout(autosaveTimer); autosavePending = false; const errors = Object.values(xhr.responseJSON?.errors || {}).flat().join(' '); selectionStatus(xhr.status === 409 ? 'Autosave paused: this draft changed elsewhere. Your edits are kept. Load the saved draft before saving again.' : ('Autosave paused. Your edits are kept. Use Save draft to retry. ' + (errors || xhr.responseJSON?.message || 'Draft could not be saved.'))); })
      .always(() => { saveRequestBusy = false; $('[data-selection-save]').prop('disabled', !draftReady || document.getElementById('category-table')?.getAttribute('aria-busy') === 'true' || !document.querySelector('.result-player-list, .result-empty')); updateDraftProgress(); if (!autosavePaused) scheduleDraftAutosave(); continueSelectionPreview(); });
  });
  $(document).on('change', '[data-selection-player]', function () {
    const row = draftRows().find(row => row.dataset.draftKey === this.value);
    if (row) row.querySelector('[data-draft-select]').checked = this.checked;
    markDraftChanged();
    selectionStatus('Draft selection changed. Your private draft saves automatically.');
  });
  $(document).on('input', '[data-selection-reason]', function () {
    const row = draftRows().find(row => row.dataset.draftKey === this.dataset.selectionReason);
    if (row) row.querySelector('[data-draft-inline-reason]').value = this.value;
    markDraftChanged();
  });
  $(document).on('click', '[data-draft-open]', function () {
    const rail = document.querySelector('.result-order-rail'); if (!rail) return;
    if (window.innerWidth >= 768) { rail.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); rail.querySelector('button')?.focus({ preventScroll: true }); return; }
    const drawer = document.getElementById('result-draft-drawer');
    drawer.querySelector('[data-draft-drawer-body]').append(rail);
    bootstrap.Offcanvas.getOrCreateInstance(drawer).show(this);
  });
  $(document).on('hidden.bs.offcanvas', '#result-draft-drawer', function () {
    const rail = this.querySelector('.result-order-rail'); if (rail) document.querySelector('.result-group-rail')?.append(rail);
    if (pendingEvidenceCard) { showDraftEvidence(pendingEvidenceCard); pendingEvidenceCard = null; }
    else document.querySelector('[data-draft-open]')?.focus({ preventScroll: true });
  });
  function showDraftEvidence(card) { card.querySelector('.result-reason')?.setAttribute('open', ''); card.scrollIntoView({ behavior: 'smooth', block: 'center' }); card.querySelector('[data-selection-reason]')?.focus({ preventScroll: true }); }
  $(document).on('change', '[data-result-group-select]', function () {
    const radio = [...document.querySelectorAll('.category-radio')].find(input => input.value === this.value);
    if (radio) { radio.checked = true; $(radio).trigger('change'); }
  });

  function refreshWorkspace() {
    if (draftDirty || saveRequestBusy) { feedback('Save or load your Result Ranks draft before refreshing the team workspace.', true); return; }
    const refreshRevision = editRevision;
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
        if (draftDirty || saveRequestBusy || editRevision !== refreshRevision) { feedback('Team changes were saved. Your Result Ranks edits were kept; refresh after saving the draft.', true); return; }
        requests.forEach(request => request.abort()); requests.clear();
        const current = document.getElementById('team-workspace-content');
        current.querySelectorAll('.modal').forEach(modal => bootstrap.Modal.getInstance(modal)?.dispose());
        const parsed = $($.parseHTML(html, document, true)).filter('#team-workspace-content');
        if (!parsed.length) { feedback('Changes were saved, but the view could not be refreshed.', true); return; }
        $(current).replaceWith(parsed);
        editingGroup = null; draftReady = false; selectionVersion = 0; savedDraft = null;
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
  $(document).on('show.bs.tab', '.team-admin-workspace .tabs-wrap [data-bs-toggle="tab"]', function (event) { if (saveRequestBusy) { event.preventDefault(); selectionStatus('Wait for the draft save to finish before switching tabs.'); } });
  $(document).on('shown.bs.tab', '.team-admin-workspace .tabs-wrap [data-bs-toggle="tab"]', function () { if (!restoring) { selectRegion($('[data-roster-region]').val()); saveUrl(true); if (this.dataset.bsTarget === '#tab-result-rank' && !document.querySelector('.result-player-list, .result-empty')) loadResults(); } });
  $(document).on('change', '.team-admin-workspace .category-radio, [data-result-region], [data-result-excluded-region], [data-result-format]', function () { if (!confirmDraftDiscard()) { restoreSetup(renderedSetup); return; } loadResults(); updateEventSettingsStatus(); saveUrl(); });
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
  window.addEventListener('popstate', () => { if (!confirmDraftDiscard()) { saveUrl(); return; } restoreState(); });
  $(restoreState);
})(jQuery, window, document);
