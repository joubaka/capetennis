(() => {
  'use strict';
  const placements = document.getElementById('ctr-placement-form');
  const confirm = document.getElementById('ctr-confirm');
  if (!placements || !confirm) return;
  const status = document.getElementById('ctr-board-status');
  const review = document.getElementById('ctr-review-changes');
  const acknowledgement = placements.querySelector('[data-manual-ack]');
  const shortfallAck = placements.querySelector('[data-shortfall-ack]');
  const originalConfirmDisabled = confirm.disabled;
  let dirty = placements.dataset.hasErrors === '1';
  let dragged = null;
  const categories = () => [...placements.querySelectorAll('[data-ctr-category]')];
  const groups = category => [...category.querySelectorAll('[data-ctr-group]')].sort((a, b) => Number(a.dataset.groupId) - Number(b.dataset.groupId));
  const players = group => [...group.querySelectorAll('[data-ctr-player]')];
  const announce = message => { status.textContent = message; };
  const markDirty = () => { dirty = true; confirm.disabled = true; confirm.textContent = 'Review placements before confirming'; };
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
    }).forEach(player => group.querySelector('[data-player-list]').append(player));
  }

  function refresh() {
    let valid = true;
    let mixed = false;
    let shortage = false;
    let competitions = 0;
    let matches = 0;
    let includedPlayers = 0;
    categories().forEach(category => {
      const excluded = category.querySelector('[data-exclude-category]').checked;
      const orderedGroups = groups(category);
      const straight = straightGroups(category);
      category.querySelector('[data-excluded-note]').hidden = !excluded;
      category.classList.toggle('is-excluded', excluded);
      category.querySelector('[data-add-group]').disabled = excluded;
      let usedStraightGroups = 0;
      orderedGroups.forEach(group => {
        category.querySelector('[data-group-board]').append(group);
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
          select.replaceChildren(...orderedGroups.map(destination => {
            const option = document.createElement('option');
            option.value = destination.dataset.groupId;
            option.textContent = `Group ${destination.dataset.groupId}`;
            option.selected = option.value === current;
            return option;
          }));
          player.draggable = !excluded;
          player.querySelector('[data-move-player]').disabled = excluded;
          // Keep select fields submitted for excluded categories so exact coverage is retained.
          select.setAttribute('aria-disabled', excluded ? 'true' : 'false');
        });
        if (!excluded && count) {
          includedPlayers += count;
          matches += count * (count - 1) / 2;
          if (isStraight) usedStraightGroups++; else competitions++;
          if (!inRange) valid = false;
          if (shortGroup) shortage = true;
          const components = new Set(members.map(player => player.dataset.component).filter(Boolean));
          if (components.size > 1) mixed = true;
          if (members.some(player => player.querySelector('.ctr-player-issue'))) valid = false;
        }
      });
      if (!excluded && straight.length) {
        competitions++;
        matches += 3;
        if (usedStraightGroups !== 2) valid = false;
      }
    });
    const manual = placements.querySelector('[data-manual-review]');
    manual.hidden = !mixed;
    placements.querySelector('[data-short-review]').hidden = !shortage;
    if (mixed && !acknowledgement.checked) valid = false;
    if (shortage && !shortfallAck.checked) valid = false;
    if (!competitions || !includedPlayers) valid = false;
    review.disabled = !valid;
    document.getElementById('ctr-prize-total').textContent = `${competitions} winner prizes + ${competitions} sportsmanship prizes = ${competitions * 2} prizes`;
    document.getElementById('ctr-programme-total').textContent = `${competitions} competitions · ${matches} matches · ${includedPlayers} players included`;
    if (dirty) { confirm.disabled = true; confirm.textContent = 'Review placements before confirming'; }
    else confirm.disabled = originalConfirmDisabled || !valid;
    return valid;
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
    announce(`Player moved to Group ${destination.dataset.groupId}. Review placements before confirming.`);
    player.querySelector('[data-placement]').focus();
  }

  placements.addEventListener('change', event => {
    if (event.target.matches('[data-placement]')) {
      const category = event.target.closest('[data-ctr-category]');
      move(event.target.closest('[data-ctr-player]'), groups(category).find(group => group.dataset.groupId === event.target.value));
    } else if (event.target.matches('[data-exclude-category],[data-manual-ack],[data-shortfall-ack]')) {
      markDirty(); refresh();
      announce('Plan options changed. Review placements before confirming.');
    }
  });
  placements.addEventListener('click', event => {
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
    if (!refresh()) { event.preventDefault(); announce('Check group sizes, included categories and comparison-band review before saving.'); }
  });
  document.getElementById('ctr-confirm-form').addEventListener('submit', event => {
    if (dirty || !refresh()) { event.preventDefault(); review.focus(); }
  });
  refresh();
})();
