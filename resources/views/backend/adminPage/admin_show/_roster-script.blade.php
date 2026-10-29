<script>
if (!window.teamRosterControls) {
  window.teamRosterControls = true;
  let searchTimer, searchRequest;
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
    const panel = form.closest('[data-roster-panel]');
    if (panel?.dataset.rosterSaving) return;
    if (form.elements.remove) {
      const confirmation = form.querySelector('[data-team-roster-remove-confirm]');
      if (confirmation.hidden) { form.querySelector('[data-team-roster-remove-toggle]').click(); return; }
    }
    form.dataset.busy = '1';
    if (panel) panel.dataset.rosterSaving = '1';
    const payload = new FormData(form), buttons = [...(panel || form).querySelectorAll('button')].filter(button => !button.disabled);
    buttons.forEach(button => button.disabled = true);
    let feedback = form.parentElement.querySelector('[data-team-roster-feedback]');
    if (!feedback) { feedback = document.createElement('p'); feedback.dataset.teamRosterFeedback = ''; feedback.setAttribute('role','status'); form.after(feedback); }
    feedback.className = 'alert alert-info'; feedback.textContent = 'Saving…';
    try {
      if (!form.dataset.saved) {
        const response = await fetch(form.getAttribute('action'), {method:'POST', body:payload, headers:{Accept:'application/json'}});
        if (response.status === 404) throw new Error('This player list changed. Use Refresh roster to load its current players and try again.');
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat()[0] || data.message || 'The change could not be saved.');
        form.dataset.saved = '1';
      }
      if (window.refreshTeamRoster && panel) await window.refreshTeamRoster(form);
      else if (window.refreshTeamWorkspace) window.refreshTeamWorkspace();
      else throw new Error('The change was saved. Reopen this player list to see the updated roster.');
      window.toastr?.success('Player list updated.');
    } catch (error) {
      feedback.className = 'alert alert-danger'; feedback.textContent = error.message;
      if (form.dataset.saved) {
        const retry = document.createElement('button'); retry.type = 'button'; retry.className = 'btn btn-outline-secondary ms-2'; retry.textContent = 'Refresh player list';
        retry.addEventListener('click', async () => {
          retry.disabled = true;
          try { await window.refreshTeamRoster(form); window.toastr?.success('Player list updated.'); }
          catch (refreshError) { feedback.firstChild.textContent = refreshError.message; retry.disabled = false; }
        });
        feedback.append(retry);
      }
    } finally {
      delete form.dataset.busy;
      if (panel) delete panel.dataset.rosterSaving;
      buttons.forEach(button => { if (!form.dataset.saved || !form.contains(button)) button.disabled = false; });
    }
  });
}
</script>
