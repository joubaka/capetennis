<script src="{{ asset('js/team-roster-order.js') }}?v={{ filemtime(public_path('js/team-roster-order.js')) }}"></script>
<script>
if (!window.teamRosterControls) {
  window.teamRosterControls = true;
  let searchTimer, searchRequest;
  const actionModalAnchors = new WeakMap();
  document.addEventListener('show.bs.modal', event => {
    const modal = event.target;
    if (!modal.matches?.('[data-roster-actions-modal]') || actionModalAnchors.has(modal)) return;
    const anchor = document.createComment('player actions');
    modal.before(anchor); actionModalAnchors.set(modal, anchor);
    document.body.append(modal);
  });
  document.addEventListener('hidden.bs.modal', event => {
    const modal = event.target, anchor = actionModalAnchors.get(modal);
    if (!anchor) return;
    if (anchor.isConnected) anchor.replaceWith(modal);
    else { window.bootstrap?.Modal.getInstance(modal)?.dispose(); modal.remove(); }
    actionModalAnchors.delete(modal);
  });
  document.addEventListener('hide.bs.modal', event => {
    if (event.target.dataset.rosterBusy) { event.preventDefault(); return; }
    if (!event.target.matches?.('[data-roster-actions-modal]') || !window.jQuery?.fn?.select2) return;
    event.target.querySelectorAll('.select2-hidden-accessible').forEach(select => window.jQuery(select).select2('close'));
  });
  const waitForRosterSave = promise => {
    let timeout;
    return Promise.race([promise, new Promise((resolve, reject) => {
      timeout = setTimeout(() => reject(new Error('The server has not confirmed the save. It may still complete. Refresh the player list to check before making another change.')), 90000);
    })]).finally(() => clearTimeout(timeout));
  };
  const closeActionsModal = modal => {
    if (!modal?.classList.contains('show')) return Promise.resolve();
    const instance = window.bootstrap?.Modal.getInstance(modal);
    if (!instance) return Promise.reject(new Error('Close player actions, then retry updating the roster.'));
    return new Promise((resolve, reject) => {
      const hidden = () => { clearTimeout(timeout); resolve(); };
      const timeout = setTimeout(() => {
        modal.removeEventListener('hidden.bs.modal', hidden);
        reject(new Error('The player list is updated. Close player actions to continue.'));
      }, 5000);
      modal.addEventListener('hidden.bs.modal', hidden, {once:true}); instance.hide();
    });
  };
  const initReplacementSelects = root => {
    if (!window.jQuery?.fn?.select2) return;
    root.querySelectorAll('[data-roster-replacement-select]').forEach(element => {
      const select = window.jQuery(element);
      if (select.hasClass('select2-hidden-accessible')) return;
      select.select2({
        width: '100%',
        placeholder: 'Search for a player…',
        minimumInputLength: 2,
        allowClear: true,
        dropdownParent: window.jQuery(element.closest('.modal') || document.body),
        ajax: {
          url: element.dataset.rosterReplacementSelect,
          dataType: 'json',
          delay: 250,
          data: params => ({q: (params.term || '').trim()}),
          processResults: data => ({results: data.players.map(player => ({id: player.id, text: player.name}))})
        }
      });
    });
  };
  // Roster fragments are loaded and replaced dynamically. Initialize when
  // their replacement panel opens, including panels added after page load.
  document.addEventListener('toggle', event => {
    if (event.target.matches?.('details.roster-replacement, details.roster-player-editor') && event.target.open) initReplacementSelects(event.target);
  }, true);
  document.addEventListener('click', event => {
    const toggle = event.target.closest('[data-team-roster-remove-toggle]');
    const cancel = event.target.closest('[data-team-roster-remove-cancel]');
    if (!toggle && !cancel) return;
    const form = (toggle || cancel).form, confirmation = form.querySelector('[data-team-roster-remove-confirm]');
    const trigger = form.querySelector('[data-team-roster-remove-toggle]');
    confirmation.hidden = !!cancel; trigger.setAttribute('aria-expanded', toggle ? 'true' : 'false');
    if (toggle) confirmation.querySelector('button').focus(); else trigger.focus();
  });
  document.addEventListener('change', event => {
    const choice = event.target.closest('[data-replacement-type]');
    if (!choice) return;
    clearTimeout(searchTimer); searchRequest?.abort();
    choice.form.querySelectorAll('[data-replacement-fields]').forEach(fields => {
      const active = fields.dataset.replacementFields === choice.value;
      fields.hidden = !active; fields.disabled = !active;
      fields.querySelectorAll('[data-roster-replacement-select]').forEach(select => {
        select.disabled = !active;
        if (!active && window.jQuery?.fn?.select2 && window.jQuery(select).hasClass('select2-hidden-accessible')) window.jQuery(select).select2('close');
      });
    });
  });
  document.addEventListener('input', event => {
    const input = event.target.closest('[data-team-roster-search]'); if (!input) return;
    clearTimeout(searchTimer); searchRequest?.abort();
    const select = input.form.querySelector('[data-team-roster-results]');
    select.replaceChildren(new Option('Search for a player first', ''));
    if (input.value.trim().length < 2) return;
    searchTimer = setTimeout(async () => {
      searchRequest = new AbortController();
      try {
        const url = new URL(input.dataset.teamRosterSearch); url.searchParams.set('q', input.value.trim());
        const response = await fetch(url, {headers:{Accept:'application/json'}, signal:searchRequest.signal});
        if (!response.ok) throw new Error('Player search could not be loaded. Please try again.');
        const data = await response.json();
        if (select.closest('fieldset')?.disabled) return;
        select.replaceChildren(new Option(data.players.length ? 'Choose a player' : 'No players found', ''));
        data.players.forEach(player => select.add(new Option(player.name, player.id)));
      } catch (error) { if (error.name !== 'AbortError') select.replaceChildren(new Option(error.message, '')); }
    }, 250);
  });
  document.addEventListener('submit', async event => {
    const form = event.target.closest('[data-team-roster-change]'); if (!form) return;
    event.preventDefault(); if (form.dataset.busy) return;
    const modal = form.closest('[data-roster-actions-modal]');
    const owner = modal && document.querySelector('[data-roster-actions-target="#' + CSS.escape(modal.id) + '"]');
    const panel = form.closest('[data-roster-panel]') || owner?.closest('[data-roster-panel]');
    if (panel?.dataset.rosterSaving) return;
    if (form.elements.remove) {
      const confirmation = form.querySelector('[data-team-roster-remove-confirm]');
      if (confirmation.hidden) { form.querySelector('[data-team-roster-remove-toggle]').click(); return; }
    }
    form.dataset.busy = '1';
    if (modal) modal.dataset.rosterBusy = '1';
    if (panel) panel.dataset.rosterSaving = '1';
    const selectedPlayer = form.elements.player_id || form.elements['player_ids[]'];
    const refreshOtherRegions = !!selectedPlayer?.value && !selectedPlayer.disabled;
    const payload = new FormData(form), buttons = [...new Set([
      ...(panel || form).querySelectorAll('button'), ...(modal || form).querySelectorAll('button')
    ])].filter(button => !button.disabled);
    buttons.forEach(button => button.disabled = true);
    let feedback = form.parentElement.querySelector('[data-team-roster-feedback]');
    if (!feedback) { feedback = document.createElement('p'); feedback.dataset.teamRosterFeedback = ''; feedback.setAttribute('role','status'); form.after(feedback); }
    let progressTimer;
    const showProgress = (message, slowMessage) => {
      clearTimeout(progressTimer);
      feedback.className = 'alert alert-info d-flex align-items-center gap-2';
      feedback.setAttribute('aria-live', 'polite');
      feedback.innerHTML = '<span class="spinner-border spinner-border-sm flex-shrink-0" aria-hidden="true"></span><span><span data-roster-progress-message></span><span class="small d-block" aria-hidden="true" data-roster-progress-time></span></span>';
      const label = feedback.querySelector('[data-roster-progress-message]'), clock = feedback.querySelector('[data-roster-progress-time]');
      label.textContent = message;
      const started = Date.now();
      const tick = () => {
        const seconds = Math.floor((Date.now() - started) / 1000);
        clock.textContent = seconds + 's elapsed';
        if (seconds >= 10 && label.textContent !== slowMessage) label.textContent = slowMessage;
        progressTimer = setTimeout(tick, 1000);
      };
      tick();
    };
    const refreshRoster = async () => {
      showProgress(form.dataset.saved ? 'Saved. Updating the player list…' : 'Checking the current player list…', 'The player list is taking longer to load. Please wait…');
      if (refreshOtherRegions) window.invalidateTeamRosterPanels?.(panel, true);
      if (window.refreshTeamRoster && panel) await window.refreshTeamRoster(panel);
      else if (window.refreshTeamWorkspace) await window.refreshTeamWorkspace();
      else throw new Error('Reopen this player list to see the current roster.');
      if (modal) delete modal.dataset.rosterBusy;
      await closeActionsModal(modal);
    };
    let movedInPlace = false;
    try {
      if (!form.dataset.saved && !form.dataset.saveUnconfirmed) {
        showProgress('Saving player change…', 'Saving is taking longer than usual. Please wait; do not submit again…');
        // A lost response does not prove that the server rolled the mutation back.
        form.dataset.saveUnconfirmed = '1';
        const data = await waitForRosterSave((async () => {
          const response = await fetch(form.getAttribute('action'), {method:'POST', body:payload, headers:{Accept:'application/json'}});
          if (response.status >= 400 && response.status < 500 && response.status !== 408) {
            const data = await response.json().catch(() => ({}));
            const message = response.status === 404 ? 'This player list changed. Use Refresh roster to load its current players and try again.'
              : response.status === 401 || response.status === 419 ? 'Your session expired. Reload the page and sign in again before saving.'
              : Object.values(data.errors || {}).flat()[0] || data.message || 'The change could not be saved.';
            const error = new Error(message); error.confirmedNotSaved = true; throw error;
          }
          const data = await response.json();
          if (!response.ok || response.redirected) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'The change could not be confirmed.');
          return data;
        })());
        delete form.dataset.saveUnconfirmed;
        form.dataset.saved = '1';
        form.dataset.successMessage = data.message || 'Player list updated.';
        if (form.elements.direction && window.applyTeamRosterOrder?.(form, data)) {
          movedInPlace = true;
          delete form.dataset.saved;
          feedback.remove();
        }
      }
      if (!movedInPlace) {
        await refreshRoster();
      }
      window.toastr?.success(form.dataset.successMessage || 'Player list refreshed. Check the player before making another change.');
    } catch (error) {
      if (error.confirmedNotSaved) delete form.dataset.saveUnconfirmed;
      clearTimeout(progressTimer);
      feedback.className = 'alert alert-danger';
      feedback.textContent = form.dataset.saveUnconfirmed ? 'The save has not been confirmed. It may still complete. Refresh the player list to check before making another change.' : error.message;
      const withdrawalLink = modal?.querySelector('[data-roster-withdrawal-link]');
      if (error.confirmedNotSaved && form.elements.remove && withdrawalLink
          && error.message === 'This checkout has payment or settlement evidence and cannot be reset.') {
        feedback.textContent = 'There is a payment record for this player. To remove them from the team, use ';
        const link = document.createElement('a');
        link.href = withdrawalLink.href;
        link.textContent = withdrawalLink.textContent.trim();
        feedback.append(link, '.');
      }
      if (form.dataset.saved || form.dataset.saveUnconfirmed) {
        const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-outline-secondary ms-2'; retry.textContent = 'Refresh player list';
        retry.addEventListener('click', async () => {
          retry.disabled = true;
          if (modal) modal.dataset.rosterBusy = '1';
          try { await refreshRoster(); window.toastr?.success(form.dataset.saved ? 'Player list updated.' : 'Player list refreshed. Check the player before making another change.'); }
          catch (refreshError) { feedback.className = 'alert alert-danger'; feedback.textContent = refreshError.message; feedback.append(retry); retry.disabled = false; }
          finally { clearTimeout(progressTimer); if (modal) delete modal.dataset.rosterBusy; }
        });
        feedback.append(retry);
      }
    } finally {
      clearTimeout(progressTimer);
      delete form.dataset.busy;
      if (modal) delete modal.dataset.rosterBusy;
      if (panel) delete panel.dataset.rosterSaving;
      buttons.forEach(button => { if ((!form.dataset.saved && !form.dataset.saveUnconfirmed) || !form.contains(button)) button.disabled = false; });
      if (movedInPlace && event.submitter?.isConnected) event.submitter.focus({preventScroll:true});
    }
  });
}
</script>
