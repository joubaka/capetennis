<script>
(function (initialize) { if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize); else initialize(); })(function () {
  const embeddedWorkspace = @json($embeddedTeamsWorkspace ?? false);
  if (embeddedWorkspace && window.teamSelectionWorkspaceInitialized) return;
  if (embeddedWorkspace) window.teamSelectionWorkspaceInitialized = true;
  document.querySelectorAll('[id^="final-team-reminders-"]').forEach(function (modal) {
    document.body.appendChild(modal);
  });
  document.querySelectorAll('[id^="custom-player-email-modal-"]').forEach(function (modal) {
    document.body.appendChild(modal);
  });
  const workspaceStateKey = 'team-selection-workspace-{{ $event->id }}';
  const regionSelect = document.querySelector('[data-region-select]');
  const openWorkspace = function (regionId, taskKey, updateLocation = true) {
    const regionTab = document.querySelector(`[data-bs-target="#region-panel-${regionId}"]`);
    const taskTab = document.querySelector(`#region-${regionId}-${taskKey}-tab`);
    if (!taskTab || !window.bootstrap?.Tab) return;
    if (regionSelect) regionSelect.value = regionId;
    if (regionTab) bootstrap.Tab.getOrCreateInstance(regionTab).show();
    const moreOptions = taskTab.closest('details');
    if (moreOptions) moreOptions.open = true;
    bootstrap.Tab.getOrCreateInstance(taskTab).show();
    const state = `${regionId}/${taskKey}`;
    sessionStorage.setItem(workspaceStateKey, state);
    if (embeddedWorkspace && updateLocation) {
      const url = new URL(window.location.href); url.searchParams.set('selection_task', taskKey); history.replaceState(null, '', url);
    }
    if (updateLocation && !embeddedWorkspace) history.replaceState(null, '', `${window.location.pathname}${window.location.search}#region-${state}`);
  };
  const regionMap = @json($eventRegions->pluck('id', 'region_id'));
  if (embeddedWorkspace) {
    window.syncTeamSelectionContext = function ({regionId, teamId, mode, task}) {
      const eventRegionId = regionMap[regionId];
      if (!eventRegionId) return;
      const activeTask = task || document.querySelector(`#region-panel-${eventRegionId} [data-region-task].active`)?.dataset.regionTask || 'teams';
      openWorkspace(eventRegionId, mode === 'order' ? 'teams' : activeTask, false);
      document.querySelectorAll('.team-admin-workspace [data-team-card-id]').forEach(card => {
        card.parentElement.hidden = !!teamId && card.dataset.teamCardId !== String(teamId);
        if (!card.parentElement.hidden && teamId) {
          const collapse = card.querySelector(`#team-workspace-${teamId}`);
          if (collapse) bootstrap.Collapse.getOrCreateInstance(collapse, {toggle:false}).show();
          const tab = card.querySelector(`[data-bs-target="#team-${mode === 'order' ? 'order' : 'players'}-${teamId}"]`);
          if (tab) bootstrap.Tab.getOrCreateInstance(tab).show();
        }
      });
    };
  }
  const requestedTeamRegion = new URLSearchParams(window.location.search).get('selected_region');
  const requestedEventRegion = regionMap[requestedTeamRegion] || regionSelect?.value || document.querySelector('[data-region-task-tabs]')?.dataset.regionTaskTabs;
  const requestedWorkspace = window.location.hash.match(/^#region-(\d+)\/(overview|teams|invitations|messages|setup)$/)?.slice(1).join('/')
    || (new URLSearchParams(window.location.search).get('workspace') === 'invitations'
      ? `${requestedEventRegion}/invitations`
      : sessionStorage.getItem(workspaceStateKey));
  if (requestedWorkspace) {
    const [requestedRegion, requestedTask] = requestedWorkspace.replace(/^region-/, '').split('/');
    openWorkspace(requestedRegion, requestedTask, false);
  }
  document.querySelectorAll('[data-region-task-tabs]').forEach(function (tabs) {
    tabs.addEventListener('keydown', function (event) {
      if (event.target.matches('[data-region-task]') && ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(event.key)) {
        const moreOptions = tabs.querySelector('details');
        if (moreOptions) moreOptions.open = true;
      }
    }, true);
  });
  document.querySelectorAll('[data-region-task]').forEach(function (taskTab) {
    taskTab.addEventListener('shown.bs.tab', function () {
      const regionId = taskTab.closest('[data-region-task-tabs]')?.dataset.regionTaskTabs;
      if (regionId) openWorkspace(regionId, taskTab.dataset.regionTask);
    });
  });
  document.querySelectorAll('[data-region-tabs] [data-region-id]').forEach(function (regionTab) {
    regionTab.addEventListener('shown.bs.tab', function () {
      const regionId = regionTab.dataset.regionId;
      if (regionSelect) regionSelect.value = regionId;
      const activeTask = document.querySelector(`#region-panel-${regionId} [data-region-task].active`)?.dataset.regionTask || 'teams';
      const state = `${regionId}/${activeTask}`;
      sessionStorage.setItem(workspaceStateKey, state);
      if (!embeddedWorkspace) history.replaceState(null, '', `${window.location.pathname}${window.location.search}#region-${state}`);
    });
  });
  regionSelect?.addEventListener('change', function () {
    const regionId = regionSelect.value;
    const activeTask = document.querySelector(`#region-panel-${regionId} [data-region-task].active`)?.dataset.regionTask || 'teams';
    openWorkspace(regionId, activeTask);
  });
  document.querySelectorAll('[data-open-region-task]').forEach(function (button) {
    button.addEventListener('click', function () {
      const regionId = button.closest('[data-region-task-tabs]')?.dataset.regionTaskTabs
        || button.closest('.region-workspace-card')?.querySelector('[data-region-task-tabs]')?.dataset.regionTaskTabs;
      if (regionId) openWorkspace(regionId, button.dataset.openRegionTask);
    });
  });
  document.querySelectorAll('[data-final-reminder-form]').forEach(function (reminderForm) {
    const summaries = JSON.parse(reminderForm.dataset.reminderSummaries || '{}');
    const hashes = JSON.parse(reminderForm.dataset.reminderHashes || '{}');
    const clothingEnabled = reminderForm.dataset.clothingEnabled === '1';
    const kind = reminderForm.querySelector('[data-reminder-kind]');
    const audience = reminderForm.querySelector('[data-reminder-audience]');
    const refreshReminder = function () {
      const summary = summaries[kind.value]?.[audience.value] || { emails: 0, players: 0 };
      reminderForm.querySelector('[data-reminder-summary]').textContent = `${summary.emails} email(s) will cover ${summary.players} player(s).`;
      reminderForm.querySelector('[data-reminder-hash]').value = hashes[kind.value]?.[audience.value] || '';
      const incomplete = kind.value === 'incomplete_clothing';
      reminderForm.querySelector('[data-reminder-preview-title]').textContent = incomplete ? 'Clothing ordering is closing' : 'Registration is closing';
      reminderForm.querySelector('[data-reminder-preview-copy]').textContent = incomplete
        ? 'Registered players without a completed clothing decision are reminded to order, finish payment, or confirm that no clothing is required. Unregistered recipients are told to register first.'
        : (clothingEnabled
          ? 'Unregistered players receive their registration/payment link. Registered players receive their clothing action link.'
          : 'Unregistered players receive their registration/payment link.');
      reminderForm.querySelector('[data-reminder-submit]').disabled = summary.emails === 0;
    };
    kind.addEventListener('change', refreshReminder);
    audience.addEventListener('change', refreshReminder);
    reminderForm.closest('.modal')?.addEventListener('show.bs.modal', function (event) {
      const selectedKind = event.relatedTarget?.dataset?.reminderOpenKind;
      if (selectedKind) kind.value = selectedKind;
      refreshReminder();
    });
    refreshReminder();
  });
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const ajaxHeaders = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': csrfToken,
  };
  document.addEventListener('submit', async function (event) {
    const form = event.target.closest('[data-add-team-player-form]');
    if (!form) return;
    event.preventDefault();
    if (!form.reportValidity()) return;

    const button = form.querySelector('[data-add-team-player-submit]');
    const buttonLabel = button?.querySelector('span');
    const originalLabel = buttonLabel?.textContent || 'Add as reserve';
    button?.setAttribute('disabled', 'disabled');
    if (buttonLabel) buttonLabel.textContent = 'Adding…';

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(form),
      });
      if (!response.ok) throw await AppFeedback.responseError(response, 'The player could not be added.');
      const data = await response.json();
      const card = form.closest('[data-team-card-id]');
      const tbody = card?.querySelector('[data-team-invitations]');
      const template = document.createElement('template');
      template.innerHTML = data.row_html.trim();
      const row = template.content.firstElementChild;
      if (!tbody || !row) throw new Error('The updated reserve row could not be displayed. Refresh the page to see the saved player.');

      tbody.querySelector('[data-empty-team-invitations]')?.remove();
      tbody.appendChild(row);
      const queueTable = tbody.closest('.table-responsive');
      if (queueTable) queueTable.hidden = false;
      const mergedQueue = card.querySelector('[data-canonical-roster] .roster-reserves');
      if (mergedQueue) mergedQueue.hidden = true;
      const summary = card.querySelector('[data-team-roster-summary]');
      if (summary) summary.textContent = `${data.summary.selected} selected · ${data.summary.reserves} reserves · ${data.summary.configured_places} configured places`;
      const openPlaces = card.querySelector('[data-team-open-places]');
      if (openPlaces) {
        openPlaces.textContent = `${data.summary.open_places} open ${data.summary.open_places === 1 ? 'place' : 'places'}`;
        openPlaces.classList.toggle('d-none', data.summary.open_places === 0);
      }

      form.reset();
      const playerSelect = form.querySelector('[name="player_id"]');
      if (playerSelect && window.jQuery?.fn?.select2) window.jQuery(playerSelect).val(null).trigger('change');
      AppFeedback.success(data.message);
    } catch (error) {
      AppFeedback.fromError(error, 'The player could not be added.');
    } finally {
      button?.removeAttribute('disabled');
      if (buttonLabel) buttonLabel.textContent = originalLabel;
    }
  });
  const renderTeamPublication = function (button, published) {
    const card = button.closest('.regional-team-card');
    const badge = card?.querySelector('[data-team-publication-status]');
    const settingsCheckbox = card?.querySelector('input[name="published"][type="checkbox"]');
    button.dataset.published = published ? '1' : '0';
    if (button.classList.contains('btn')) {
      button.classList.toggle('btn-outline-danger', published);
      button.classList.toggle('btn-outline-success', !published);
    }
    button.querySelector('span').textContent = published ? 'Unpublish' : 'Publish';
    button.querySelector('i').className = `ti ti-${published ? 'world-off' : 'world-upload'} me-1`;
    if (badge) {
      badge.textContent = published ? 'Published' : 'Not published';
      badge.classList.toggle('bg-label-success', published);
      badge.classList.toggle('bg-label-secondary', !published);
    }
    if (settingsCheckbox) settingsCheckbox.checked = published;
  };

  document.querySelectorAll('.team-publication-button').forEach(function (button) {
    button.addEventListener('click', async function () {
      const published = button.dataset.published !== '1';
      button.disabled = true;
      try {
        const response = await fetch(button.dataset.url, {
          method: 'PATCH', headers: ajaxHeaders, body: JSON.stringify({ published }),
        });
        if (!response.ok) throw await AppFeedback.responseError(response, 'The team publication could not be changed.');
        const data = await response.json();
        renderTeamPublication(button, data.published);
        AppFeedback.success(data.message);
      } catch (error) {
        AppFeedback.fromError(error, 'The team publication could not be changed.');
      } finally {
        button.disabled = false;
      }
    });
  });

  document.querySelectorAll('.publish-all-teams').forEach(function (button) {
    button.addEventListener('click', async function () {
      button.disabled = true;
      try {
        const response = await fetch(button.dataset.url, { method: 'POST', headers: ajaxHeaders, body: '{}' });
        if (!response.ok) throw await AppFeedback.responseError(response, 'The teams could not be published.');
        const data = await response.json();
        const teamIds = new Set((data.team_ids || []).map(String));
        button.closest('.region-workspace-card')?.querySelectorAll('.team-publication-button').forEach(function (teamButton) {
          if (teamIds.has(teamButton.dataset.teamId)) renderTeamPublication(teamButton, true);
        });
        AppFeedback.success(data.message);
      } catch (error) {
        AppFeedback.fromError(error, 'The teams could not be published.');
      } finally {
        button.disabled = false;
      }
    });
  });

  document.querySelectorAll('.clothing-order-form').forEach(function (form) {
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const button = form.querySelector('.clothing-order-toggle');
      const status = form.parentElement.querySelector('[data-clothing-status]');
      button.disabled = true;
      try {
        const response = await fetch(form.action, {
          method: 'PATCH',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: new FormData(form),
        });
        if (!response.ok) throw await AppFeedback.responseError(response, 'Clothing ordering could not be changed.');
        const data = await response.json();
        const open = Boolean(data.state);
        status.textContent = open ? 'Clothing ordering open' : 'Clothing ordering closed';
        if (status.classList.contains('badge')) {
          status.classList.toggle('bg-label-success', open);
          status.classList.toggle('bg-label-secondary', !open);
        }
        if (button.classList.contains('btn')) {
          button.classList.toggle('btn-danger', open);
          button.classList.toggle('btn-success', !open);
          button.classList.remove('btn-warning');
        }
        button.innerHTML = `<i class="ti ti-${open ? 'lock' : 'shopping-cart'} me-2"></i>${open ? 'Close ordering' : 'Open ordering'}`;
        AppFeedback.success(data.message);
      } catch (error) {
        AppFeedback.fromError(error, 'Clothing ordering could not be changed.');
      } finally {
        button.disabled = !Boolean(Number(button.dataset.catalogueReady)) && button.textContent.includes('Open');
      }
    });
  });

  document.querySelectorAll('[id^="team-workspace-"]').forEach(function (workspace) {
    const toggle = document.querySelector(`[data-bs-target="#${workspace.id}"]`);
    const header = document.querySelector(`[data-team-workspace-target="#${workspace.id}"]`);
    const label = toggle?.querySelector('span');
    const icon = toggle?.querySelector('i');
    header?.addEventListener('click', function (event) {
      if (workspace.querySelector('[data-canonical-roster]')) return;
      if (event.target.closest('a, button, input, select, textarea, summary, details, form, label')) return;
      toggle?.click();
    });
    workspace.addEventListener('shown.bs.collapse', function () {
      if (label) label.textContent = 'Hide team';
      icon?.classList.replace('ti-eye', 'ti-eye-off');
    });
    workspace.addEventListener('hidden.bs.collapse', function () {
      if (label) label.textContent = 'Show team';
      icon?.classList.replace('ti-eye-off', 'ti-eye');
    });
  });

  document.querySelectorAll('[id^="team-settings-"]').forEach(function (settings) {
    const toggle = document.querySelector(`[data-bs-target="#${settings.id}"]`);
    const label = toggle?.querySelector('span');
    settings.addEventListener('shown.bs.collapse', function () {
      if (label) label.textContent = 'Close settings';
    });
    settings.addEventListener('hidden.bs.collapse', function () {
      if (label) label.textContent = 'Team settings';
    });
  });

  const updateImportedEmailAction = function (row, email) {
    const action = row.querySelector('[data-imported-email-action]');
    if (!action) return;
    const name = `${row.querySelector('[name="name"]').value} ${row.querySelector('[name="surname"]').value}`.trim();
    action.dataset.recipient = `${name} · ${email}`;
  };
  document.querySelectorAll('.imported-name-form').forEach(function (form) {
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"], button:not([type])');
      button?.setAttribute('disabled', 'disabled');
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: new FormData(form),
        });
        if (!response.ok) throw await AppFeedback.responseError(response, 'The player name could not be saved.');
        const data = await response.json();
        const orderName = document.querySelector(`.imported-roster-order [data-slot-id="${form.dataset.slotId}"] [data-imported-player-name]`);
        if (orderName) orderName.textContent = `${data.player.name} ${data.player.surname}`.trim();
        const row = form.closest('tr[data-slot-id]');
        updateImportedEmailAction(row, row.querySelector('[data-effective-email]').textContent);
        AppFeedback.success(data.message);
      } catch (error) {
        AppFeedback.fromError(error, 'The player name could not be saved.');
      } finally {
        button?.removeAttribute('disabled');
      }
    });
  });

  document.querySelectorAll('.imported-email-form').forEach(function (form) {
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const button = form.querySelector('button');
      button.disabled = true;
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: new FormData(form),
        });
        if (!response.ok) throw await AppFeedback.responseError(response, 'The email could not be saved.');
        const data = await response.json();
        form.querySelector('input[name="email"]').value = data.imported_email;
        const row = form.closest('tr[data-slot-id]');
        row.querySelector('[data-effective-email]').textContent = data.effective_email || 'No email';
        const source = row.querySelector('[data-effective-email-source]');
        source.textContent = data.effective_email_source;
        source.classList.toggle('bg-label-success', data.effective_email_source === 'Linked profile email');
        source.classList.toggle('bg-label-info', data.effective_email_source !== 'Linked profile email');
        const action = row.querySelector('[data-imported-email-action]');
        action?.classList.toggle('d-none', !data.effective_email);
        updateImportedEmailAction(row, data.effective_email);
        AppFeedback.success(data.message);
      } catch (error) {
        AppFeedback.fromError(error, 'The email could not be saved.');
      } finally {
        button.disabled = false;
      }
    });
  });

  const reviewRosterMove = function (review, ordered) {
    return new Promise(resolve => {
      const dialog = document.createElement('dialog');
      dialog.style.cssText = 'border:1px solid #dbe2ea;border-radius:12px;padding:24px;width: min(620px, calc(100vw - 24px));max-height:85vh;overflow:auto;color:#34445a;';
      const addText = (tag, text) => { const element = document.createElement(tag); element.textContent = text; dialog.appendChild(element); return element; };
      addText('h4', review.can_confirm ? 'Review playing order' : 'Move cannot be saved');
      addText('p', review.message);
      addText('h6', 'Proposed order');
      const list = document.createElement('ol');
      ordered.forEach(row => { const item = document.createElement('li'); item.textContent = row.querySelector('strong')?.textContent?.trim() || row.cells[1]?.textContent?.trim() || 'Player'; list.appendChild(item); });
      dialog.appendChild(list);
      addText('p', `${review.affected_matches.length} upcoming match(s) will change players. Match times and courts must stay unchanged. Started matches keep their original players.`);
      if (review.affected_matches.length) {
        const details = document.createElement('details');
        const summary = document.createElement('summary'); summary.textContent = 'Affected matches'; details.appendChild(summary);
        review.affected_matches.forEach(match => { const item = document.createElement('p'); item.textContent = `Match #${match.id} · ${match.scheduled_at || 'Not scheduled'}${match.court ? ' · Court ' + match.court : ''} · ${match.before_players || 'Current players'} → ${match.after_players || 'New players'}`; details.appendChild(item); });
        dialog.appendChild(details);
      }
      [...review.blockers, ...review.warnings].filter((value, index, all) => all.indexOf(value) === index).forEach(warning => addText('p', warning));
      const actions = document.createElement('div'); actions.className = 'd-flex gap-2 justify-content-end mt-3';
      const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'btn btn-outline-secondary'; cancel.style.minHeight = '44px'; cancel.textContent = review.can_confirm ? 'Cancel' : 'Close';
      const finish = approved => { dialog.close(); dialog.remove(); resolve(approved); };
      cancel.addEventListener('click', () => finish(false)); actions.appendChild(cancel);
      if (review.can_confirm) { const approve = document.createElement('button'); approve.type = 'button'; approve.className = 'btn btn-primary'; approve.style.minHeight = '44px'; approve.textContent = 'Confirm move'; approve.addEventListener('click', () => finish(true)); actions.appendChild(approve); }
      dialog.appendChild(actions); dialog.addEventListener('cancel', event => { event.preventDefault(); finish(false); });
      document.body.appendChild(dialog); dialog.showModal();
    });
  };
  document.querySelectorAll('.roster-order-sortable').forEach(function (tbody) {
    let dragged = null;
    let originalRows = [];
    let busy = false;
    let busyControls = [];
    const teamContent = tbody.closest('.tab-content');
    teamContent.addEventListener('submit', function (event) {
      if (busy) { event.preventDefault(); event.stopImmediatePropagation(); }
    }, true);
    const rows = () => Array.from(tbody.querySelectorAll('tr[data-order-id]'));
    const restore = () => originalRows.forEach(row => tbody.appendChild(row));
    const refreshButtons = function () {
      const current = rows();
      current.forEach(function (row, index) {
        row.querySelector('[name="direction"][value="up"]')?.closest('form')?.querySelector('button')?.toggleAttribute('disabled', busy || index === 0);
        row.querySelector('[name="direction"][value="down"]')?.closest('form')?.querySelector('button')?.toggleAttribute('disabled', busy || index === current.length - 1);
        row.draggable = !busy;
      });
      tbody.setAttribute('aria-busy', String(busy));
    };
    const save = async function () {
      const ordered = rows();
      if (ordered.every((row, index) => row === originalRows[index])) { refreshButtons(); return; }
      busy = true;
      busyControls = Array.from(teamContent.querySelectorAll('form button, form input, form select, form textarea')).filter(control => !control.disabled);
      busyControls.forEach(control => { control.disabled = true; });
      refreshButtons();
      let confirmationAttempted = false;
      try {
        const proposal = { [tbody.dataset.reorderField]: ordered.map(row => Number(row.dataset.orderId)), expected_ids: originalRows.map(row => Number(row.dataset.orderId)) };
        const send = body => fetch(tbody.dataset.reorderUrl, {
          method: 'PUT',
          headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
          body: JSON.stringify(body),
        });
        const previewResponse = await send({ ...proposal, preview: true });
        if (!previewResponse.ok) throw await AppFeedback.responseError(previewResponse, 'The move could not be checked.');
        const review = await previewResponse.json();
        if (!await reviewRosterMove(review, ordered)) { restore(); return; }
        confirmationAttempted = true;
        const response = await send({ ...proposal, fingerprint: review.fingerprint });
        if (!response.ok) { if (response.status >= 400 && response.status < 500) confirmationAttempted = false; throw await AppFeedback.responseError(response, 'The player order could not be saved.'); }
        const data = await response.json();
        const order = data.order;
        if (!Array.isArray(order) || order.length !== ordered.length || new Set(order.map(item => String(item.id))).size !== ordered.length
          || order.some(item => !ordered.some(row => row.dataset.orderId === String(item.id)) || !Number.isInteger(Number(item.rank)) || Number(item.rank) < 1)) throw new Error('The saved roster could not be displayed. Refresh the page.');
        const content = tbody.closest('.tab-content');
        const players = content.querySelector('.imported-roster-players, [data-team-invitations]');
        const playerRows = [];
        order.forEach(function (item) {
          const row = ordered.find(candidate => candidate.dataset.orderId === String(item.id));
          row.querySelector('.badge').textContent = `Rank ${item.rank}`;
          tbody.appendChild(row);
          const playerRow = players?.querySelector(`[data-slot-id="${item.id}"], [data-order-player-id="${item.id}"]`);
          if (playerRow) {
            const rank = playerRow.querySelector('[data-label="Rank"] .badge') || playerRow.querySelector('.badge');
            rank.textContent = `Rank ${item.rank}`;
            playerRow.querySelectorAll('[name="expected_rank"], [name="expected_roster_rank"]').forEach(input => { input.value = item.rank; });
            playerRow.querySelectorAll('[data-current-roster-rank]').forEach(label => { label.textContent = item.rank; });
            playerRows.push(playerRow);
          }
          content.querySelectorAll(`[data-rank-slot-id="${item.id}"]`).forEach(option => { option.textContent = `${option.dataset.rankPlayerName} · Rank ${item.rank}`; });
        });
        if (players) playerRows.reverse().forEach(row => players.prepend(row));
        AppFeedback.success(`${data.message} Confirmed: ${review.affected_matches.length} upcoming match(s) updated; match times and courts retained.`);
      } catch (error) {
        if (confirmationAttempted) {
          AppFeedback.warning('The move response could not be verified. Reloading to check the saved playing order.');
          window.location.reload();
          return;
        }
        restore();
        AppFeedback.fromError(error, 'The player order could not be saved.');
      } finally {
        busy = false;
        busyControls.forEach(control => { control.disabled = false; });
        busyControls = [];
        refreshButtons();
      }
    };
    tbody.addEventListener('submit', function (event) {
      event.preventDefault();
      event.stopPropagation();
      if (busy || dragged) return;
      const row = event.target.closest('tr[data-order-id]');
      const direction = event.target.querySelector('[name="direction"]')?.value;
      if (!row || !['up', 'down'].includes(direction)) return;
      originalRows = rows();
      const neighbor = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
      if (!neighbor) return;
      tbody.insertBefore(row, direction === 'up' ? neighbor : neighbor.nextElementSibling);
      void save();
    });
    tbody.addEventListener('dragstart', function (event) {
      if (busy || event.target.closest('button, input, form')) { event.preventDefault(); return; }
      dragged = event.target.closest('tr[data-order-id]');
      if (!dragged || dragged.parentElement !== tbody) { dragged = null; return; }
      originalRows = rows();
      dragged.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
      event.dataTransfer.setData('text/plain', dragged.dataset.orderId);
    });
    tbody.addEventListener('dragover', function (event) {
      if (!dragged || busy) return;
      event.preventDefault();
      const target = event.target.closest('tr[data-order-id]');
      if (!target || target.parentElement !== tbody || target === dragged) return;
      const below = event.clientY > target.getBoundingClientRect().top + target.offsetHeight / 2;
      tbody.insertBefore(dragged, below ? target.nextSibling : target);
    });
    tbody.addEventListener('drop', function (event) {
      if (!dragged || busy) return;
      event.preventDefault();
      dragged.classList.remove('is-dragging');
      dragged = null;
      void save();
    });
    tbody.addEventListener('dragend', function () {
      if (!dragged) return;
      dragged.classList.remove('is-dragging');
      dragged = null;
      restore();
      refreshButtons();
    });
    refreshButtons();
  });

  document.querySelectorAll('.roster-contact-import-form').forEach(function (form) {
    const escape = value => { const node = document.createElement('div'); node.textContent = value ?? ''; return node.innerHTML; };
    const confirmed = form.querySelector('[data-import-confirmed]');
    const file = form.querySelector('[data-import-file]');
    const preview = form.querySelector('[data-import-preview]');
    const previewBody = preview.querySelector('tbody');
    const errors = form.querySelector('[data-import-errors]');
    const anomalies = form.querySelector('[data-import-anomalies]');
    const fingerprint = form.querySelector('[data-import-fingerprint]');
    const submit = form.querySelector('[data-import-submit]');
    const resetPreview = function () {
      confirmed.value = '0';
      preview.classList.add('d-none');
      previewBody.innerHTML = '';
      errors.classList.add('d-none');
      anomalies.classList.add('d-none');
      anomalies.querySelector('ul').innerHTML = '';
      anomalies.querySelector('input').checked = false;
      anomalies.querySelector('input').required = false;
      fingerprint.value = '';
      submit.textContent = 'Preview workbook';
    };
    file.addEventListener('change', resetPreview);
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      if (!form.reportValidity()) return;
      submit.disabled = true;
      errors.classList.add('d-none');
      try {
        const response = await fetch(form.action, {
          method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form),
        });
        if (!response.ok) throw await AppFeedback.responseError(response, 'The workbook could not be processed.');
        const data = await response.json();
        if (data.requires_confirmation) {
          previewBody.innerHTML = (data.updates || []).map(row => `<tr><td>${escape(row.name)}</td><td>${escape(row.email)}</td></tr>`).join('')
            || '<tr><td colspan="2" class="text-center text-muted">No blank emails can be safely enriched.</td></tr>';
          preview.classList.remove('d-none');
          fingerprint.value = data.preview_fingerprint || '';
          if ((data.issues || []).length) {
            anomalies.querySelector('ul').innerHTML = data.issues.map(message => `<li>${escape(message)}</li>`).join('');
            anomalies.classList.remove('d-none');
            anomalies.querySelector('input').required = true;
          } else {
            anomalies.querySelector('input').required = false;
          }
          confirmed.value = '1';
          submit.textContent = `Add ${data.updates.length} missing email${data.updates.length === 1 ? '' : 's'}`;
          AppFeedback.info(`${data.updates.length} safe email update(s) found. Review before applying.`);
        } else {
          AppFeedback.success(data.message);
          bootstrap.Modal.getInstance(form.closest('.modal'))?.hide();
          window.setTimeout(() => window.location.reload(), 700);
        }
      } catch (error) {
        const messages = error?.messages?.length ? error.messages : [error?.message || 'The workbook could not be processed.'];
        errors.innerHTML = `<strong>Nothing was imported.</strong><ul class="mb-0 mt-1">${messages.map(message => `<li>${escape(message)}</li>`).join('')}</ul>`;
        errors.classList.remove('d-none');
        AppFeedback.fromError(error, 'The workbook could not be processed.');
      } finally {
        submit.disabled = false;
      }
    });
  });

  document.querySelectorAll('[data-event-roster-email-form]').forEach(function (form) {
    const panel = form.querySelector('[data-event-roster-filter-panel]');
    const hash = form.querySelector('[data-event-roster-email-hash]');
    const token = form.querySelector('[data-event-roster-email-token]');
    const confirmation = form.querySelector('[name="confirm_recipients"]');
    const result = form.querySelector('[data-event-roster-result]');
    const review = form.querySelector('[data-event-roster-review]');
    const list = form.querySelector('[data-event-roster-list]');
    const teamsWrap = form.querySelector('[data-event-roster-teams]');
    const playersWrap = form.querySelector('[data-event-roster-players]');
    let loadedTeams = [];
    const reset = function () {
      hash.value = '';
      token.value = '';
      confirmation.checked = false;
      result.classList.add('d-none');
      review.classList.add('d-none');
      list.innerHTML = '';
    };
    const showStep = function (step) {
      let activeSection = null;
      form.querySelectorAll('[data-event-roster-step]').forEach(section => section.classList.toggle('d-none', section.dataset.eventRosterStep !== String(step)));
      activeSection = form.querySelector(`[data-event-roster-step="${step}"]`);
      form.querySelectorAll('[data-event-roster-step-badge]').forEach(badge => {
        const active = badge.dataset.eventRosterStepBadge === String(step);
        badge.classList.toggle('bg-primary', active);
        badge.classList.toggle('bg-label-secondary', !active);
      });
      activeSection?.focus();
    };
    const selectedRegions = () => Array.from(form.querySelectorAll('[data-event-roster-region]:checked'));
    const selectedTeams = () => Array.from(form.querySelectorAll('[data-event-roster-team]:checked'));
    const selectedPlayers = () => Array.from(form.querySelectorAll('[data-event-roster-player]:checked'));
    const audienceMode = () => form.querySelector('[data-event-roster-mode]:checked')?.value || 'roster';
    const rankingStatus = () => form.querySelector('[data-event-ranking-status]')?.value || 'all';
    const postPreview = async function (formData) {
      formData.append('_token', form.querySelector('[name="_token"]').value);
      const response = await fetch(panel.dataset.previewUrl, { method: 'POST', headers: { Accept: 'application/json' }, body: formData });
      const data = await response.json();
      if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Audience selection failed.');
      return data;
    };
    form.querySelectorAll('[data-event-roster-region], [data-event-roster-filter], [data-event-roster-mode]').forEach(input => input.addEventListener('change', reset));
    form.querySelectorAll('[data-event-roster-mode]').forEach(input => input.addEventListener('change', function () {
      form.querySelector('[data-event-ranking-status-wrap]')?.classList.toggle('d-none', audienceMode() !== 'ranking');
    }));
    form.querySelector('[data-event-roster-load-teams]').addEventListener('click', async function () {
      const button = this;
      const formData = new FormData();
      formData.append('selection_stage', 'teams');
      formData.append('audience_mode', audienceMode());
      formData.append('ranking_status', rankingStatus());
      selectedRegions().forEach(input => formData.append('event_region_ids[]', input.value));
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      try {
        const data = await postPreview(formData);
        loadedTeams = data.teams || [];
        teamsWrap.innerHTML = loadedTeams.map(team => { const players = audienceMode() === 'ranking' ? team.ranking_players : team.players; return `<div class="col-12 col-lg-6 event-audience-min-width"><label class="form-check border rounded p-2 h-100 event-audience-min-width"><input class="form-check-input ms-0 me-2 flex-shrink-0" type="checkbox" name="team_ids[]" value="${team.team_id}" data-event-roster-team><span class="form-check-label event-audience-min-width event-audience-wrap"><strong class="d-block event-audience-wrap">${escape(team.category)}</strong><span class="d-block small text-muted event-audience-wrap">${escape(team.region)} · ${players.length} ${audienceMode() === 'ranking' ? 'ranked' : 'roster'} player${players.length === 1 ? '' : 's'}</span></span></label></div>`; }).join('');
        if (!loadedTeams.length) throw new Error('No teams with players are available in the selected regions.');
        form.querySelectorAll('[data-event-roster-team]').forEach(input => input.addEventListener('change', reset));
        form.querySelector('[data-event-roster-all-teams]').checked = false;
        showStep(2);
      } catch (error) { AppFeedback.fromError(error, 'Teams could not be loaded.'); }
      finally { button.disabled = false; button.removeAttribute('aria-busy'); }
    });
    form.querySelector('[data-event-roster-all-teams]').addEventListener('change', function () {
      form.querySelectorAll('[data-event-roster-team]').forEach(input => { input.checked = this.checked; });
      reset();
    });
    form.querySelector('[data-event-roster-show-players]').addEventListener('click', function () {
      const teamIds = selectedTeams().map(input => Number(input.value));
      if (!teamIds.length) { AppFeedback.info('Select at least one team.'); return; }
      const mode = audienceMode();
      playersWrap.innerHTML = loadedTeams.filter(team => teamIds.includes(Number(team.team_id))).map(team => { const players = mode === 'ranking' ? team.ranking_players : team.players; return `<fieldset class="border rounded p-3 mb-3 event-audience-min-width" data-event-roster-team-players="${team.team_id}"><div class="d-flex flex-wrap justify-content-between gap-2"><legend class="h6 mb-0 event-audience-wrap">${escape(team.category)}</legend><label class="form-check mb-0"><input class="form-check-input" type="checkbox" data-event-roster-all-players="${team.team_id}"> Select all available</label></div><div class="small text-muted mb-2 event-audience-wrap">${escape(team.region)}</div><div class="row g-2">${players.map(player => `<div class="col-12 col-lg-6 event-audience-min-width"><label class="form-check event-audience-min-width"><input class="form-check-input flex-shrink-0" type="checkbox" name="${mode === 'ranking' ? 'series_ranking_ids' : (player.roster_key ? 'roster_keys' : 'invitation_ids')}[]" value="${mode === 'ranking' ? player.series_ranking_id : (player.roster_key || player.invitation_id)}" data-event-roster-player data-team-id="${team.team_id}" ${player.has_email ? 'checked' : 'disabled'}><span class="form-check-label event-audience-wrap">${escape(player.name)} <span class="small text-muted">· ${escape(player.status)}${mode === 'ranking' ? ` · ${escape((player.event_state || '').replaceAll('_', ' '))}` : ''}${player.has_email ? '' : ' · no email available'}</span></span></label></div>`).join('')}</div></fieldset>`; }).join('');
      form.querySelector('[data-event-roster-status-filters]').classList.toggle('d-none', mode === 'ranking');
      form.querySelectorAll('[data-event-roster-player]').forEach(input => input.addEventListener('change', reset));
      form.querySelectorAll('[data-event-roster-all-players]').forEach(toggle => {
        toggle.checked = true;
        toggle.addEventListener('change', function () {
          form.querySelectorAll(`[data-event-roster-player][data-team-id="${this.dataset.eventRosterAllPlayers}"]:not(:disabled)`).forEach(input => { input.checked = this.checked; });
          reset();
        });
      });
      showStep(3);
    });
    form.querySelectorAll('[data-event-roster-back]').forEach(button => button.addEventListener('click', function () { reset(); showStep(this.dataset.eventRosterBack); }));
    form.querySelector('[data-event-roster-preview]').addEventListener('click', async function () {
      const button = this;
      const formData = new FormData();
      formData.append('audience_mode', audienceMode());
      selectedRegions().forEach(input => formData.append('event_region_ids[]', input.value));
      selectedTeams().forEach(input => formData.append('team_ids[]', input.value));
      selectedPlayers().forEach(input => formData.append(input.name, input.value));
      form.querySelectorAll('[data-event-roster-filter]').forEach(input => formData.append(input.name, input.value));
      button.disabled = true;
      button.setAttribute('aria-busy', 'true');
      try {
        const data = await postPreview(formData);
        hash.value = data.recipient_hash;
        token.value = data.send_token;
        result.textContent = `${data.count} unique recipient${data.count === 1 ? '' : 's'} matched across the selected regions.`;
        result.classList.remove('d-none');
        result.classList.toggle('alert-warning', data.count === 0);
        result.classList.toggle('alert-success', data.count > 0);
        list.innerHTML = data.recipients.map(recipient => `<div>${escape(recipient.name)} · ${escape(recipient.email)} · ${escape(recipient.region)} · ${escape(recipient.category)}</div>`).join('');
        review.classList.toggle('d-none', data.count === 0);
      } catch (error) {
        reset();
        AppFeedback.fromError(error, 'Recipient preview failed.');
      } finally {
        button.disabled = false;
        button.removeAttribute('aria-busy');
      }
    });
  });

  document.querySelectorAll('[id^="roster-email-"]').forEach(function (modal) {
    const filterPanel = modal.querySelector('[data-roster-filter-panel]');
    const filterInputs = filterPanel ? Array.from(filterPanel.querySelectorAll('[data-roster-filter-input]')) : [];
    const hashInput = modal.querySelector('[data-roster-email-hash]');
    const confirmation = modal.querySelector('input[name="confirm_recipients"]');
    const resetFilteredPreview = function () {
      if (!filterPanel || filterPanel.classList.contains('d-none')) return;
      hashInput.value = '';
      confirmation.checked = false;
      const result = filterPanel.querySelector('[data-roster-filter-result]');
      const review = filterPanel.querySelector('[data-roster-filter-review]');
      result.classList.add('d-none');
      review.classList.add('d-none');
      filterPanel.querySelector('[data-roster-filter-list]').innerHTML = '';
    };
    filterInputs.forEach(input => input.addEventListener('change', resetFilteredPreview));
    filterPanel?.querySelector('[data-roster-filter-preview]')?.addEventListener('click', async function () {
      const button = this;
      const formData = new FormData();
      formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.content || modal.querySelector('input[name="_token"]')?.value || '');
      filterInputs.forEach(input => {
        if (input.type === 'checkbox') {
          if (input.checked) formData.append(input.name, input.value);
          return;
        }
        Array.from(input.selectedOptions).forEach(option => formData.append(input.name, option.value));
      });
      button.disabled = true;
      try {
        const response = await fetch(filterPanel.dataset.previewUrl, { method: 'POST', headers: { 'Accept': 'application/json' }, body: formData });
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'Recipient preview failed.');
        hashInput.value = data.recipient_hash;
        const result = filterPanel.querySelector('[data-roster-filter-result]');
        result.textContent = `${data.count} unique recipient${data.count === 1 ? '' : 's'} matched.`;
        result.classList.remove('d-none');
        result.classList.toggle('alert-warning', data.count === 0);
        result.classList.toggle('alert-success', data.count > 0);
        const list = filterPanel.querySelector('[data-roster-filter-list]');
        list.innerHTML = data.recipients.map(recipient => `<div>${escape(recipient.name)} · ${escape(recipient.email)} · ${escape(recipient.category)}</div>`).join('');
        filterPanel.querySelector('[data-roster-filter-review]').classList.toggle('d-none', data.count === 0);
      } catch (error) {
        resetFilteredPreview();
        AppFeedback.fromError(error, 'Recipient preview failed.');
      } finally {
        button.disabled = false;
      }
    });
    modal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) return;
      modal.querySelector('[data-roster-email-target]').value = button.dataset.targetType || 'team';
      modal.querySelector('[data-roster-email-team]').value = button.dataset.teamId || '';
      modal.querySelector('[data-roster-email-slot]').value = button.dataset.slotId || '';
      modal.querySelector('[data-roster-email-invitation]').value = button.dataset.invitationId || '';
      modal.querySelector('[data-roster-email-hash]').value = button.dataset.recipientHash || '';
      modal.querySelector('[data-roster-email-recipient]').textContent = button.dataset.recipient || '';
      const filtered = button.dataset.targetType === 'filtered';
      filterPanel?.classList.toggle('d-none', !filtered);
      filterInputs.forEach(input => { input.disabled = !filtered; });
      if (filtered) {
        resetFilteredPreview();
        const unanswered = button.dataset.unansweredInvitations === '1';
        filterPanel.querySelectorAll('[name="category_event_ids[]"]').forEach(input => { input.checked = unanswered; });
        filterPanel.querySelector('[name="gender"]').value = 'any';
        filterPanel.querySelector('[name="audience_status"]').value = unanswered ? 'not_accepted' : 'active';
        if (unanswered) {
          modal.querySelector('[name="subject"]').value = @json($event->name) + ' — invitation reminder';
          modal.querySelector('[name="message"]').value = 'Please respond to your invitation. Click Accept to register and pay, or Decline to confirm that you are unavailable.';
          filterPanel.querySelector('[data-roster-filter-preview]').click();
        }
      }
      modal.querySelector('[data-roster-region-review]')?.classList.toggle('d-none', button.dataset.targetType !== 'region');
      modal.querySelectorAll('[data-roster-cohort-review]').forEach(function (review) {
        review.classList.toggle('d-none', review.dataset.rosterCohortReview !== button.dataset.targetType);
      });
    });
  });

  document.querySelectorAll('[data-custom-email-team-toolbar]').forEach(function (toolbar) {
    const teamId = toolbar.dataset.customEmailTeamToolbar;
    const checkAll = toolbar.querySelector(`[data-custom-email-check-all="${teamId}"]`);
    const button = toolbar.querySelector(`[data-custom-email-team-button="${teamId}"]`);
    const boxes = Array.from(document.querySelectorAll(`[data-custom-email-player="${teamId}"]`));
    const sync = function () {
      const checked = boxes.filter(box => box.checked).length;
      checkAll.checked = boxes.length > 0 && checked === boxes.length;
      checkAll.indeterminate = checked > 0 && checked < boxes.length;
      button.disabled = checked === 0;
      button.querySelector('span').textContent = checked === 0
        ? 'Email checked players'
        : `Email ${checked} checked player${checked === 1 ? '' : 's'}`;
    };
    checkAll.addEventListener('change', function () {
      boxes.forEach(box => { box.checked = checkAll.checked; });
      sync();
    });
    boxes.forEach(box => box.addEventListener('change', sync));
    button.addEventListener('click', function () {
      document.querySelectorAll('[data-custom-email-player]').forEach(function (box) {
        if (box.dataset.customEmailPlayer !== teamId) box.checked = false;
      });
      document.querySelectorAll('[data-custom-email-team-toolbar]').forEach(function (otherToolbar) {
        if (otherToolbar !== toolbar) {
          const otherCheckAll = otherToolbar.querySelector('[data-custom-email-check-all]');
          const otherButton = otherToolbar.querySelector('[data-custom-email-team-button]');
          otherCheckAll.checked = false;
          otherCheckAll.indeterminate = false;
          otherButton.disabled = true;
          otherButton.querySelector('span').textContent = 'Email checked players';
        }
      });
    });
    sync();
  });

  // Escape positioned dropdowns and scrollable team tables before opening dialogs.
  document.querySelectorAll('[data-replacement-modal]').forEach(function (modal) {
    document.body.appendChild(modal);
  });

  const syncReplacementProfile = function (modeSelect) {
    const form = modeSelect.closest('[data-replacement-player-form]');
    const profileWrap = form?.querySelector('[data-custom-replacement-profile]');
    const profileSelect = form?.querySelector('.replacement-profile-select');
    if (!profileWrap || !profileSelect) return;
    const customProfile = modeSelect.value === 'custom_profile';
    profileWrap.classList.toggle('d-none', !customProfile);
    profileSelect.disabled = !customProfile;
    profileSelect.required = customProfile;
    if (!customProfile) profileSelect.value = '';
    if (window.jQuery?.fn?.select2 && window.jQuery(profileSelect).hasClass('select2-hidden-accessible')) {
      window.jQuery(profileSelect).trigger('change.select2');
    }
  };
  document.querySelectorAll('[data-replacement-mode]').forEach(function (modeSelect) {
    syncReplacementProfile(modeSelect);
    modeSelect.addEventListener('change', function () { syncReplacementProfile(modeSelect); });
  });

  if (window.jQuery?.fn?.select2) {
    window.jQuery('.region-manager-select').select2({
      width: '100%',
      placeholder: function () { return window.jQuery(this).data('placeholder'); },
      allowClear: true,
      minimumInputLength: 2,
      ajax: {
        url: @json(route('backend.team-selection.users.search', $event)),
        dataType: 'json',
        delay: 250,
        data: function (params) { return { q: params.term }; },
        processResults: function (data) { return data; },
        cache: true
      }
    });
    window.jQuery('.team-player-select').each(function () {
      const select = window.jQuery(this);
      select.select2({
        width: '100%',
        placeholder: select.data('placeholder'),
        dropdownParent: select.closest('.modal').length ? select.closest('.modal') : window.jQuery(document.body),
        minimumInputLength: 2,
        ajax: {
          url: select.data('search-url'),
          dataType: 'json',
          delay: 250,
          data: function (params) { return { q: params.term }; },
          processResults: function (data) { return data; },
          cache: true
        }
      });
    });
  }

  document.querySelectorAll('[data-replacement-modal][data-reopen]').forEach(function (modal) {
    if (typeof bootstrap !== 'undefined') bootstrap.Modal.getOrCreateInstance(modal).show();
  });

  document.querySelectorAll('[data-show-payment-transfer]').forEach(function (button) {
    button.addEventListener('click', function () {
      const transfer = document.getElementById(button.dataset.showPaymentTransfer);
      if (!transfer) return;
      transfer.open = true;
      transfer.scrollIntoView({ behavior: 'smooth', block: 'center' });
      const control = transfer.querySelector('select, summary');
      if (control) control.focus({ preventScroll: true });
    });
  });
  document.querySelectorAll('[data-invitation-payment-transfer][open]').forEach(function (transfer) {
    const regionPanel = transfer.closest('[id^="region-panel-"]');
    const regionId = regionPanel?.id.replace('region-panel-', '');
    if (regionId) openWorkspace(regionId, 'teams');
    const workspace = transfer.closest('[id^="team-workspace-"]');
    if (workspace && window.bootstrap?.Collapse) {
      bootstrap.Collapse.getOrCreateInstance(workspace, { toggle: false }).show();
    }
  });

  const addTeamId = @json(old('add_team_id'));
  if (addTeamId && typeof bootstrap !== 'undefined') {
    const workspace = document.getElementById(`team-workspace-${addTeamId}`);
    if (workspace) bootstrap.Collapse.getOrCreateInstance(workspace, { toggle: false }).show();
  }

  const settingsTeamId = @json(old('settings_team_id'));
  if (settingsTeamId && typeof bootstrap !== 'undefined') {
    const settings = document.getElementById(`team-settings-${settingsTeamId}`);
    if (settings) bootstrap.Collapse.getOrCreateInstance(settings, { toggle: false }).show();
  }

  const sourceId = @json(session('open_team_setup_source') ?: old('setup_source'));
  if (sourceId && typeof bootstrap !== 'undefined') {
    const modal = document.getElementById(`ranking-category-setup-${sourceId}`);
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  const invitationImportId = @json(old('selection_import_id'));
  if (invitationImportId && typeof bootstrap !== 'undefined') {
    const invitationModal = document.getElementById(`prepare-invitations-${invitationImportId}`);
    if (invitationModal) bootstrap.Modal.getOrCreateInstance(invitationModal).show();
  }

  document.querySelectorAll('[id^="ranking-category-form-"]').forEach(function (form) {
    const defaultSize = form.querySelector('.default-team-size');
    const applyDefault = form.querySelector('.apply-default-team-size');
    if (defaultSize && applyDefault) {
      applyDefault.addEventListener('click', function () {
        const value = defaultSize.value;
        if (!value) return;
        form.querySelectorAll('input[type="checkbox"][name$="[selected]"]:checked').forEach(function (checkbox) {
          const input = checkbox.closest('tr').querySelector('.team-size-input');
          if (input) input.value = value;
        });
      });
    }

    form.querySelectorAll('input[type="checkbox"][name$="[selected]"]').forEach(function (checkbox) {
      const row = checkbox.closest('tr');
      const toggleInputs = function () {
        row.querySelectorAll('input[name$="[team_name]"], input[name$="[num_players]"]').forEach(function (input) {
          input.disabled = !checkbox.checked;
        });
      };
      checkbox.addEventListener('change', toggleInputs);
      toggleInputs();
    });
  });
  if (embeddedWorkspace) document.dispatchEvent(new Event('teams:selection-ready'));
});
</script>
