(() => {
  'use strict';
  const placements = document.getElementById('ctr-placement-form');
  const confirm = document.getElementById('ctr-confirm');
  if (!placements || !confirm) return;
  const status = document.getElementById('ctr-board-status');
  const review = document.getElementById('ctr-review-changes');
  const acknowledgement = placements.querySelector('[data-manual-ack]');
  const shortfallAck = placements.querySelector('[data-shortfall-ack]');
  let savedReady = !confirm.disabled;
  const validation = document.getElementById('ctr-blocked');
  const originalValidation = validation?.textContent || '';
  const saveErrorBox = document.getElementById('ctr-save-error');
  const confirmForm = document.getElementById('ctr-confirm-form');
  const token = placements.querySelector('[name="token"]').value;
  let savedRevision = placements.querySelector('[name="revision"]').value;
  let savedTotals = null;
  let dirty = placements.dataset.hasErrors === '1';
  let generation = 0;
  let activeGeneration = -1;
  let timer = null;
  let inFlight = false;
  let stopped = false;
  let pauseAutomatic = false;
  let saveError = '';
  let dragged = null;
  const categories = () => [...placements.querySelectorAll('[data-ctr-category]')];
  const groups = category => [...category.querySelectorAll('[data-ctr-group]')].sort((a, b) => Number(a.dataset.groupId) - Number(b.dataset.groupId));
  const players = group => [...group.querySelectorAll('[data-ctr-player]')];
  const announce = message => { status.textContent = message; };
  const markDirty = () => {
    generation++;
    dirty = true;
    if (timer !== null) { clearTimeout(timer); timer = null; }
    if (!stopped) { pauseAutomatic = false; saveError = ''; }
    confirm.disabled = true;
  };
  const straightGroups = category => (category.dataset.straightGroups || '').split(',').filter(Boolean).map(Number);

  function sortPlayers(group) {
    const members = players(group);
    const components = new Set(members.map(player => player.dataset.component).filter(Boolean));
    // Different comparison bands have no common numeric ordering.
    members.sort((a, b) => {
      const aKnown = a.dataset.score !== '';
      const bKnown = b.dataset.score !== '';
      if (aKnown !== bKnown) return aKnown ? -1 : 1;
      if (components.size > 1) return Number(a.dataset.order) - Number(b.dataset.order);
      return (aKnown ? Number(b.dataset.score) - Number(a.dataset.score) : 0) || Number(a.dataset.entryId) - Number(b.dataset.entryId);
    }).forEach((player, index) => {
      const list = group.querySelector('[data-player-list]');
      if (list.children[index] !== player) list.insertBefore(player, list.children[index] || null);
    });
  }

  function refresh(allowSchedule = true) {
    let valid = true;
    let mixed = false;
    let shortage = false;
    let competitions = 0;
    let matches = 0;
    let includedPlayers = 0;
    const reasons = [];
    categories().forEach(category => {
      const excluded = category.querySelector('[data-exclude-category]').checked;
      const orderedGroups = groups(category);
      const straight = straightGroups(category);
      category.querySelector('[data-excluded-note]').hidden = !excluded;
      category.querySelector('[data-exclusion-next-step]')?.toggleAttribute('hidden', !excluded || !dirty);
      category.querySelector('[data-category-minimum]')?.toggleAttribute('hidden', excluded);
      category.classList.toggle('is-excluded', excluded);
      category.querySelector('[data-add-group]').disabled = excluded;
      let usedStraightGroups = 0;
      const straightComponents = new Set();
      const categoryPlayers = [];
      orderedGroups.forEach((group, groupIndex) => {
        const board = category.querySelector('[data-group-board]');
        if (board.children[groupIndex] !== group) board.insertBefore(group, board.children[groupIndex] || null);
        sortPlayers(group);
        const members = players(group);
        const count = members.length;
        const isStraight = straight.includes(Number(group.dataset.groupId));
        const inRange = count === 0 || (isStraight ? count === 3 : count >= 3 && count <= 5);
        const shortGroup = !isStraight && count === 3;
        group.querySelector('[data-group-count]').textContent = String(count);
        group.querySelector('[data-group-matches]').textContent = String(count * (count - 1) / 2);
        group.querySelector('[data-group-games]').textContent = String(Math.max(0, count - 1) + (isStraight && count ? 1 : 0));
        group.querySelector('[data-remove-group]').hidden = count > 0 || isStraight;
        const warning = group.querySelector('[data-group-warning]');
        warning.textContent = shortGroup ? '2 matches per player — below the target of three. Review this exception.' : (isStraight ? 'This shared competition needs exactly three players in each group.' : 'Each included group needs 3–5 players.');
        warning.hidden = excluded || (inRange && !shortGroup);
        group.classList.toggle('is-invalid', !excluded && (!inRange || (shortGroup && !shortfallAck.checked)));
        members.forEach(player => {
          const select = player.querySelector('[data-placement]');
          const current = String(group.dataset.groupId);
          if (select.options.length !== orderedGroups.length || orderedGroups.some((destination, index) => select.options[index].value !== destination.dataset.groupId)) {
            select.replaceChildren(...orderedGroups.map(destination => {
              const option = document.createElement('option');
              option.value = destination.dataset.groupId;
              option.textContent = `Group ${destination.dataset.groupId}`;
              option.selected = option.value === current;
              return option;
            }));
          } else if (select.value !== current) select.value = current;
          player.draggable = !excluded;
          player.querySelector('[data-move-player]').disabled = excluded;
          // Keep select fields submitted for excluded categories so exact coverage is retained.
          select.setAttribute('aria-disabled', excluded ? 'true' : 'false');
        });
        if (!excluded && count) {
          includedPlayers += count;
          matches += count * (count - 1) / 2;
          if (isStraight) {
            usedStraightGroups++;
            members.map(player => player.dataset.component).filter(Boolean).forEach(component => straightComponents.add(component));
          } else competitions++;
          if (!inRange) { valid = false; reasons.push(`${category.querySelector('h3').textContent.trim()}, Group ${group.dataset.groupId}: ${isStraight ? 'exactly three players are needed for the linked finals.' : 'each included group needs three to five players.'}`); }
          if (shortGroup) shortage = true;
          const components = new Set(members.map(player => player.dataset.component).filter(Boolean));
          if (components.size > 1) mixed = true;
          if (members.some(player => player.querySelector('.ctr-player-issue'))) { valid = false; reasons.push('Resolve the highlighted player identity or ball-level issue.'); }
          categoryPlayers.push(...members.map(player => player.dataset.playerId).filter(Boolean));
        }
      });
      if (!excluded && straight.length) {
        competitions++;
        matches += 3;
        if (usedStraightGroups !== 2) { valid = false; reasons.push('The linked finals need two included groups of three.'); }
        if (straightComponents.size > 1) mixed = true;
      }
      if (!excluded && new Set(categoryPlayers).size !== categoryPlayers.length) { valid = false; reasons.push('A player appears more than once in an included category.'); }
    });
    const manual = placements.querySelector('[data-manual-review]');
    manual.hidden = !mixed;
    placements.querySelector('[data-short-review]').hidden = !shortage;
    if (mixed && !acknowledgement.checked) { valid = false; reasons.push('Review and acknowledge placements across different CTR comparison bands.'); }
    if (shortage && !shortfallAck.checked) { valid = false; reasons.push('Review and accept the highlighted two-match exception, rebalance, or exclude that category.'); }
    if (!competitions || !includedPlayers) { valid = false; reasons.push('Include at least one category with enough players.'); }
    if (placements.dataset.stale === '1') { valid = false; reasons.push('This saved preview expired or its source changed. Press Plan all draws for a fresh preview.'); }
    review.disabled = !valid || inFlight || stopped || !dirty;
    review.classList.toggle('btn-primary', dirty);
    review.classList.toggle('btn-outline-primary', !dirty);
    review.textContent = inFlight ? 'Saving…' : (stopped ? 'Reload to continue' : (pauseAutomatic ? 'Retry save' : (dirty ? 'Save now' : 'Saved')));
    if (validation) {
      validation.hidden = valid && (dirty || savedReady);
      validation.textContent = reasons.length ? [...new Set(reasons)].join(' ') : originalValidation;
    }
    saveErrorBox.hidden = !saveError;
    saveErrorBox.querySelector('[data-save-error-message]').textContent = saveError;
    let state = 'saved';
    let message = 'Saved. Confirm and create all draws when you are happy with the plan.';
    if (stopped || pauseAutomatic) { state = 'error'; message = 'Changes not saved. '+saveError; }
    else if (inFlight) { state = 'saving'; message = activeGeneration === generation ? 'Saving changes…' : 'Saving earlier changes. Your latest edits are waiting to save.'; }
    else if (!valid) { state = 'invalid'; message = dirty ? 'Changes not saved. Check the highlighted included groups.' : 'Check the highlighted included groups before creating draws.'; }
    else if (dirty) { state = 'pending'; message = 'Changes will save automatically…'; }
    status.dataset.saveState = state;
    announce(message);
    const totals = !dirty && savedTotals ? savedTotals : { competitions, matches, included_players: includedPlayers, winner_prizes: competitions, sportsmanship_prizes: competitions };
    document.getElementById('ctr-prize-total').textContent = `${totals.winner_prizes} winner prizes + ${totals.sportsmanship_prizes} sportsmanship prizes = ${totals.winner_prizes + totals.sportsmanship_prizes} prizes`;
    document.getElementById('ctr-programme-total').textContent = `${totals.competitions} competitions · ${totals.matches} matches · ${totals.included_players} players included`;
    confirm.disabled = dirty || inFlight || stopped || pauseAutomatic || !savedReady || !valid;
    confirm.textContent = inFlight ? 'Saving changes…' : (dirty ? 'Waiting for changes to save' : 'Confirm and create all draws');
    if (allowSchedule && valid && dirty && !inFlight && !stopped && !pauseAutomatic && timer === null) {
      timer = setTimeout(() => { timer = null; saveChanges(); }, 400);
    }
    if (!valid && timer !== null) { clearTimeout(timer); timer = null; }
    return valid;
  }

  function validReceipt(data) {
    const keys = ['competitions', 'matches', 'included_players', 'winner_prizes', 'sportsmanship_prizes'];
    return data && data.saved === true && data.ready === true && data.token === token && data.event_id === Number(placements.dataset.eventId)
      && typeof data.revision === 'string' && /^[a-f0-9]{64}$/.test(data.revision)
      && Array.isArray(data.excluded_category_ids) && data.excluded_category_ids.every(Number.isInteger)
      && data.totals && keys.every(key => Number.isInteger(data.totals[key]) && data.totals[key] >= 0)
      && typeof data.expires_at === 'string' && Number.isFinite(Date.parse(data.expires_at));
  }

  async function requestReview(body) {
    const response = await fetch(placements.action, {
      method: 'POST', body, credentials: 'same-origin', redirect: 'error',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    let data;
    try { data = await response.json(); } catch (_) { throw new Error('The save response could not be verified. Reload the saved plan before continuing.'); }
    return { response, data };
  }

  async function saveChanges() {
    if (timer !== null) { clearTimeout(timer); timer = null; }
    if (inFlight || stopped || !dirty || !refresh(false)) return;
    const sentGeneration = generation;
    const body = new FormData(placements);
    body.set('revision', savedRevision);
    inFlight = true;
    activeGeneration = sentGeneration;
    pauseAutomatic = false;
    saveError = '';
    refresh(false);
    let timeout;
    try {
      // Do not abort/retry on edits: this request may already have committed.
      const { response, data } = await Promise.race([
        requestReview(body),
        new Promise((_, reject) => { timeout = setTimeout(() => reject(new Error('Saving timed out. Reload the saved plan to verify what was saved.')), 15000); }),
      ]);
      if (!response.ok) {
        const details = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
        if (response.status === 422) {
          if (generation === sentGeneration) {
            pauseAutomatic = true;
            saveError = details || 'These changes could not be saved. Correct the highlighted groups or retry the save.';
          }
        } else {
          stopped = true;
          const reason = [401, 403, 419].includes(response.status) ? 'Your session or permission needs to be checked.'
            : (response.status === 409 ? 'The saved plan or its source changed.' : (response.status === 429 ? 'Too many save attempts. Wait before reloading.' : 'The save result could not be verified.'));
          saveError = reason+' Reload the saved plan before continuing.';
        }
      } else {
        if (!validReceipt(data)) throw new Error('The save response could not be verified. Reload the saved plan before continuing.');
        savedRevision = data.revision;
        placements.querySelector('[name="revision"]').value = savedRevision;
        confirmForm.querySelector('[name="revision"]').value = savedRevision;
        placements.dataset.savedExcludedCategories = JSON.stringify(data.excluded_category_ids);
        // An older acknowledgment advances only the revision used by the queue.
        // It must never replace the latest board or authorize its creation.
        if (generation === sentGeneration) {
          dirty = false;
          savedReady = data.ready;
          savedTotals = data.totals;
          placements.dataset.hasErrors = '0';
          placements.querySelectorAll('[data-placement]').forEach(select => { select.dataset.savedGroup = select.value; });
        }
      }
    } catch (error) {
      stopped = true;
      saveError = error.message && /Reload the saved plan/.test(error.message) ? error.message
        : 'The connection was interrupted and the save result is unknown. Reload the saved plan before continuing.';
    } finally {
      clearTimeout(timeout);
      inFlight = false;
      refresh();
    }
  }

  function move(player, destination) {
    const category = player.closest('[data-ctr-category]');
    if (!destination || destination.dataset.categoryId !== player.dataset.categoryId || category.querySelector('[data-exclude-category]').checked) {
      announce('Move players only between included groups in their own category.');
      refresh();
      return;
    }
    const previousGroup = player.closest('[data-ctr-group]');
    if (previousGroup !== destination) {
      destination.querySelector('[data-player-list]').append(player);
      markDirty();
    }
    refresh();
    player.querySelector('[data-placement]').focus();
  }

  placements.addEventListener('change', event => {
    if (event.target.matches('[data-placement]')) {
      const category = event.target.closest('[data-ctr-category]');
      move(event.target.closest('[data-ctr-player]'), groups(category).find(group => group.dataset.groupId === event.target.value));
    } else if (event.target.matches('[data-exclude-category],[data-manual-ack],[data-shortfall-ack]')) {
      markDirty(); refresh();
    }
  });
  placements.addEventListener('click', event => {
    const exclusionLink = event.target.closest('[data-review-exclusion]');
    if (exclusionLink) {
      event.preventDefault();
      status.scrollIntoView({ block: 'center', behavior: 'smooth' });
      return;
    }
    const moveButton = event.target.closest('[data-move-player]');
    if (moveButton) {
      const player = moveButton.closest('[data-ctr-player]');
      move(player, groups(player.closest('[data-ctr-category]')).find(group => group.dataset.groupId === player.querySelector('[data-placement]').value));
      return;
    }
    const add = event.target.closest('[data-add-group]');
    if (add && !add.disabled) {
      const category = add.closest('[data-ctr-category]');
      const number = Math.max(0, ...groups(category).map(group => Number(group.dataset.groupId))) + 1;
      if (number > 1600) { announce('The group limit was reached.'); return; }
      const group = document.getElementById('ctr-empty-group-template').content.firstElementChild.cloneNode(true);
      group.dataset.groupId = String(number);
      group.dataset.categoryId = category.dataset.categoryId;
      group.querySelector('h4').textContent = `Group ${number}`;
      group.querySelector('[data-player-list]').setAttribute('aria-label', `Group ${number} players`);
      category.querySelector('[data-group-board]').append(group);
      markDirty(); refresh(); announce(`Empty Group ${number} added. Move players into it to include it.`);
      return;
    }
    const remove = event.target.closest('[data-remove-group]');
    if (remove) {
      const group = remove.closest('[data-ctr-group]');
      if (players(group).length || straightGroups(group.closest('[data-ctr-category]')).includes(Number(group.dataset.groupId))) return;
      group.remove(); markDirty(); refresh(); announce('Empty group removed. No players were removed.');
    }
  });
  placements.addEventListener('dragstart', event => {
    const player = event.target.closest('[data-ctr-player]');
    if (!player || !player.draggable || event.target.closest('select,button')) return;
    dragged = player;
    event.dataTransfer.setData('text/plain', player.dataset.entryId);
    event.dataTransfer.effectAllowed = 'move';
    player.classList.add('is-dragging');
  });
  placements.addEventListener('dragover', event => {
    const destination = event.target.closest('[data-ctr-group]');
    if (!dragged || !destination) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = destination.dataset.categoryId === dragged.dataset.categoryId ? 'move' : 'none';
    destination.classList.add('is-drop-target');
  });
  placements.addEventListener('dragleave', event => {
    const destination = event.target.closest('[data-ctr-group]');
    if (destination && !destination.contains(event.relatedTarget)) destination.classList.remove('is-drop-target');
  });
  placements.addEventListener('drop', event => {
    const destination = event.target.closest('[data-ctr-group]');
    if (!dragged || !destination) return;
    event.preventDefault(); move(dragged, destination);
    destination.classList.remove('is-drop-target');
  });
  placements.addEventListener('dragend', () => {
    placements.querySelectorAll('.is-dragging,.is-drop-target').forEach(element => element.classList.remove('is-dragging', 'is-drop-target'));
    dragged = null;
  });
  placements.addEventListener('submit', event => {
    event.preventDefault();
    saveChanges();
  });
  confirmForm.addEventListener('submit', event => {
    if (dirty || inFlight || stopped || pauseAutomatic || !savedReady || !refresh(false)) { event.preventDefault(); status.scrollIntoView({ block: 'center' }); }
  });
  refresh();
})();
