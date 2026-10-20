/* Contextual entry point only: canonical composers and review handlers own mail. */
(function () {
  'use strict';
  let source;
  let context;
  let bypass = false;
  let closing = false;
  let generation = 0;
  const selector = '.emailPlayer, .emailTeamBtn, .emailRegionBtn, .emailUnpaidRegionBtn, .roster-email-button';
  function purposeModal() { return document.getElementById('team-email-purpose'); }
  function currentContext(button) {
    const region = document.querySelector('[data-roster-region]');
    const team = document.querySelector('[data-workspace-team]');
    const selected = region?.selectedOptions[0];
    const regional = button?.matches('.emailRegionBtn, .emailUnpaidRegionBtn') || (button?.classList.contains('roster-email-button') && !['team', 'player', 'imported_player'].includes(button.dataset.targetType));
    return {
      region: region?.value || '', eventRegion: selected?.dataset.eventRegionId || '',
      team: regional ? '' : button?.dataset.teamid || button?.dataset.teamId || button?.closest('[data-roster-team]')?.dataset.rosterTeam || team?.value || '',
      player: button?.dataset.playerid || '', invitation: button?.dataset.invitationId || '',
      individual: !!(button?.dataset.playerid || ['player', 'imported_player'].includes(button?.dataset.targetType)),
      label: button?.dataset.name || button?.dataset.teamname || button?.dataset.recipient || (team?.value ? team.selectedOptions[0]?.textContent : selected?.textContent) || '',
    };
  }
  function regionPanel() { return document.getElementById('region-panel-' + context.eventRegion); }
  function invitationForm() {
    if (context.player) return regionPanel()?.querySelector('[data-email-invitation-player="' + CSS.escape(context.player) + '"]');
    if (context.invitation) return regionPanel()?.querySelector('form[data-email-invitation-player][action*="/' + CSS.escape(context.invitation) + '/"]');
    return null;
  }
  function pendingForm() { return regionPanel()?.querySelector('form[action$="/send-pending-activated"]'); }
  function draftModal() {
    const trigger = regionPanel()?.querySelector('[data-bs-target^="#prepare-invitations-"]');
    const target = trigger?.getAttribute('data-bs-target');
    return target?.startsWith('#prepare-invitations-') ? document.getElementById(target.slice(1)) : null;
  }
  function activeInvitationForms() {
    return [...(regionPanel()?.querySelectorAll('form[data-email-invitation-player]') || [])]
      .filter(form => !context.team || form.dataset.emailTeam === context.team);
  }
  function showError(message) {
    const error = purposeModal().querySelector('[data-team-email-error]'); error.textContent = message; error.hidden = false;
  }
  async function open(button) {
    if (closing) return;
    const modal = purposeModal(); if (!modal) return;
    source = button || null; context = currentContext(button);
    modal.querySelector('[data-team-email-context]').textContent = context.label;
    modal.querySelector('[data-team-email-error]').hidden = true;
    modal.querySelector('[data-team-email-invitation-picker]').hidden = true;
    const version = ++generation;
    modal.querySelectorAll('[data-team-email-purpose]').forEach(item => { item.disabled = item.dataset.teamEmailPurpose !== 'custom'; });
    modal.querySelector('[data-pending-purpose-copy]').textContent = 'Checking newly added players in this region…';
    bootstrap.Modal.getOrCreateInstance(modal).show();
    await window.prepareTeamSelectionMail?.();
    if (version !== generation) return;
    if (!regionPanel()) { showError('Invitation actions could not be loaded. Close and reopen Email to retry. Custom messages remain available.'); return; }
    const individual = context.individual || !!(context.player || context.invitation);
    const invitation = invitationForm();
    if (invitation && !context.player) context.player = invitation.dataset.emailInvitationPlayer;
    const draft = draftModal();
    const forms = activeInvitationForms();
    modal.querySelector('[data-team-email-purpose="invitation"]').disabled = individual ? !invitation : !draft && !forms.length;
    modal.querySelector('[data-invitation-purpose-copy]').textContent = individual ? 'Review this player’s saved invitation and personal registration link.' : draft ? 'Prepare the campaign for this region; review its selected players and deadlines.' : forms.length ? 'Choose an active player and review their saved invitation.' : 'No active unpaid invitation is available for this selection.';
    modal.querySelector('[data-team-email-purpose="reminder"]').disabled = !document.getElementById('final-team-reminders-' + context.eventRegion) || (individual && !context.player);
    const pending = pendingForm();
    const count = Number(pending?.querySelector('[name="recipient_count"]')?.value || 0);
    modal.querySelector('[data-team-email-purpose="pending"]').disabled = !count;
    modal.querySelector('[data-pending-purpose-copy]').textContent = count ? count + ' newly added player(s) ready in this region. Review the saved campaign and deadlines.' : 'No newly added invitations are ready in this region.';
  }
  function afterClose(action) {
    if (closing) return;
    closing = true;
    const modal = purposeModal();
    modal.addEventListener('hidden.bs.modal', () => { closing = false; action(); }, {once: true});
    bootstrap.Modal.getOrCreateInstance(modal).hide();
  }
  function clickOriginal(button) { bypass = true; try { button.click(); } finally { bypass = false; } }
  function submitForReview(form) {
    if (!form) return;
    form.querySelectorAll('[name="confirm_recipients"]').forEach(input => { input.checked = true; input.required = false; });
    form.requestSubmit();
  }
  function custom() {
    function blank(button) {
      clickOriginal(button);
      const target = button.dataset.bsTarget && document.querySelector(button.dataset.bsTarget);
      target?.querySelectorAll('[name="subject"], [name="message"], [name="email_subject"], [name="email_message"]').forEach(input => { input.value = ''; });
    }
    if (source) { blank(source); return; }
    const panel = document.querySelector('[data-central-selection-host]');
    const imported = context.team && regionPanel()?.querySelector('.roster-email-button[data-target-type="team"][data-team-id="' + CSS.escape(context.team) + '"]');
    if (imported) { showSelection(); blank(imported); return; }
    const button = document.createElement('button'); button.type = 'button'; button.hidden = true;
    button.className = context.team ? 'emailTeamBtn' : 'emailRegionBtn';
    if (context.team) { button.dataset.teamid = context.team; button.dataset.teamname = context.label; }
    else { button.dataset.regionid = context.region; button.dataset.regionname = context.label; }
    (panel || document.body).append(button); clickOriginal(button); button.remove();
  }
  function showSelection() {
    const tab = document.querySelector('[data-bs-target="#tab-invitations"]');
    if (tab) bootstrap.Tab.getOrCreateInstance(tab).show();
  }
  function openModal(modal) {
    // Use the original trigger so its canonical recipient/default initializer runs.
    const trigger = regionPanel()?.querySelector('[data-bs-target="#' + CSS.escape(modal.id) + '"]');
    if (trigger) trigger.click(); else bootstrap.Modal.getOrCreateInstance(modal).show();
  }
  // Bootstrap delegates modal triggers in document capture. Intercept on the
  // earlier window path so its data API cannot open the composer first.
  window.addEventListener('click', event => {
    if (bypass || !purposeModal()) return;
    const button = event.target.closest(selector + ', [data-team-email-open]');
    if (!button) return;
    event.preventDefault(); event.stopImmediatePropagation();
    open(button.matches('[data-team-email-open]') ? null : button);
  }, true);
  document.addEventListener('click', event => {
    const review = event.target.closest('[data-team-email-review-invitation]');
    if (review) {
      const player = purposeModal().querySelector('[data-team-email-invitation-player]').value;
      const form = activeInvitationForms().find(item => item.dataset.emailInvitationPlayer === player);
      if (form) afterClose(() => { showSelection(); submitForReview(form); });
      return;
    }
    const button = event.target.closest('[data-team-email-purpose]'); if (!button || button.disabled) return;
    if (button.dataset.teamEmailPurpose === 'invitation' && !invitationForm() && !draftModal()) {
      const picker = purposeModal().querySelector('[data-team-email-invitation-picker]');
      const select = picker.querySelector('select'); select.replaceChildren();
      const seen = new Set();
      activeInvitationForms().forEach(form => {
        const id = form.dataset.emailInvitationPlayer; if (seen.has(id)) return; seen.add(id);
        const option = document.createElement('option'); option.value = id; option.textContent = form.dataset.emailPlayerName; select.append(option);
      });
      picker.hidden = false; select.focus(); return;
    }
    afterClose(() => {
      const purpose = button.dataset.teamEmailPurpose;
      if (purpose === 'custom') { custom(); return; }
      showSelection();
      if (purpose === 'invitation') {
        const form = invitationForm();
        if (form) submitForReview(form);
        else { const modal = draftModal(); if (modal) openModal(modal); }
      } else if (purpose === 'pending') submitForReview(pendingForm());
      else {
        const modal = document.getElementById('final-team-reminders-' + context.eventRegion); if (!modal) return;
        openModal(modal);
        const form = modal.querySelector('form');
        for (const [name, value] of Object.entries({team_id: context.team, player_id: context.player})) {
          let input = form.querySelector('[name="' + name + '"]');
          if (!input) { input = document.createElement('input'); input.type = 'hidden'; input.name = name; form.append(input); }
          input.value = value;
        }
        const audience = form.querySelector('[name="audience"]'); if (audience) { audience.value = 'unregistered'; audience.dispatchEvent(new Event('change', {bubbles:true})); }
        const summary = form.querySelector('[data-reminder-summary]');
        if (summary && (context.team || context.player)) summary.textContent = 'Players matching ' + context.label + ' will be resolved and listed exactly at review. Other players in this region are excluded.';
      }
    });
  });
  document.addEventListener('show.bs.modal', event => {
    if (!event.target.id?.startsWith('final-team-reminders-')) return;
    event.target.querySelectorAll('[name="team_id"], [name="player_id"]').forEach(input => { input.value = ''; });
  });
})();
