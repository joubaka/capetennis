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
    if (!event.target.matches?.('[data-roster-actions-modal]') || !window.jQuery?.fn?.select2) return;
    event.target.querySelectorAll('.select2-hidden-accessible').forEach(select => window.jQuery(select).select2('close'));
  });
  const closeActionsModal = modal => {
    if (!modal?.classList.contains('show')) return Promise.resolve();
    const instance = window.bootstrap?.Modal.getInstance(modal);
    if (!instance) return Promise.reject(new Error('Close player actions, then retry updating the roster.'));
    return new Promise(resolve => { modal.addEventListener('hidden.bs.modal', resolve, {once:true}); instance.hide(); });
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
    if (panel) panel.dataset.rosterSaving = '1';
    const payload = new FormData(form), buttons = [...new Set([
      ...(panel || form).querySelectorAll('button'), ...(modal || form).querySelectorAll('button')
    ])].filter(button => !button.disabled);
    buttons.forEach(button => button.disabled = true);
    let feedback = form.parentElement.querySelector('[data-team-roster-feedback]');
    if (!feedback) { feedback = document.createElement('p'); feedback.dataset.teamRosterFeedback = ''; feedback.setAttribute('role','status'); form.after(feedback); }
    feedback.className = 'alert alert-info'; feedback.textContent = 'Saving…';
    let movedInPlace = false;
    try {
      if (!form.dataset.saved) {
        const response = await fetch(form.getAttribute('action'), {method:'POST', body:payload, headers:{Accept:'application/json'}});
        if (response.status === 404) throw new Error('This player list changed. Use Refresh roster to load its current players and try again.');
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'The change could not be saved.');
        form.dataset.saved = '1';
        if (form.elements.direction && window.applyTeamRosterOrder?.(form, data)) {
          movedInPlace = true;
          delete form.dataset.saved;
          feedback.remove();
        }
      }
      if (!movedInPlace) {
        await closeActionsModal(modal);
        if (window.refreshTeamRoster && panel) await window.refreshTeamRoster(panel);
        else if (window.refreshTeamWorkspace) await window.refreshTeamWorkspace();
        else throw new Error('The change was saved. Reopen this player list to see the updated roster.');
      }
      window.toastr?.success('Player list updated.');
    } catch (error) {
      feedback.className = 'alert alert-danger'; feedback.textContent = error.message;
      if (form.dataset.saved) {
        const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-outline-secondary ms-2'; retry.textContent = 'Refresh player list';
        retry.addEventListener('click', async () => {
          retry.disabled = true;
          try { await closeActionsModal(modal); await window.refreshTeamRoster(panel || form); window.toastr?.success('Player list updated.'); }
          catch (refreshError) { feedback.firstChild.textContent = refreshError.message; retry.disabled = false; }
        });
        feedback.append(retry);
      }
    } finally {
      delete form.dataset.busy;
      if (panel) delete panel.dataset.rosterSaving;
      buttons.forEach(button => { if (!form.dataset.saved || !form.contains(button)) button.disabled = false; });
      if (movedInPlace && event.submitter?.isConnected) event.submitter.focus({preventScroll:true});
    }
  });
}
</script>
