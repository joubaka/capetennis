/* Native browser module: served directly, no bundler or third-party drag dependency. */
(() => {
  'use strict';
  const config = JSON.parse(document.getElementById('fm-config').textContent);
  const $ = id => document.getElementById(id);
  const clone = value => JSON.parse(JSON.stringify(value));
  const bracketLayout = window.TennisBracketLayout;
  const bracketDimensions = bracketLayout.dimensions;
  let state = config.state,
    draft = clone(state.draft),
    history = [],
    selected = null,
    selectedPlayer = null,
    draggedPlayer = null;
  let dirty = false,
    busy = false,
    scoreKey = null,
    demoGraph = null;
  const name = id => {
    const player = state.players.find(p => Number(p.id) === Number(id));
    return player ? player.name + (player.withdrawn ? ' (withdrawn)' : '') : (id ? 'Player unavailable' : 'Awaiting result');
  };
  const roundName = depth => ['Final', 'Semifinals', 'Quarterfinals'][depth] || `Round of ${2 ** (depth + 1)}`;
  const editable = () => config.canEdit && !state.generated && !state.published && !state.locked && !busy;
  const customStarts = !['playoffs', 'monrad'].includes(config.workflow);
  const allowedStart = path => customStarts || path.length === Math.log2(draft.size);
  const message = (text, error = false) => {
    $('fm-message').textContent = text;
    $('fm-message').className = error ? 'error' : '';
    if ($('fm-slot-dialog').open) $('fm-slot-error').textContent = error ? text : '';
  };
  const el = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  };
  const assignedPath = id =>
    Object.keys(draft.slots).find(
      path => draft.slots[path].type === 'player' && Number(draft.slots[path].id) === Number(id)
    );
  const descendants = path => Object.keys(draft.slots).filter(p => p.startsWith(path) && p !== path);
  const suppressed = path => {
    for (let i = 1; i <= path.length; i++) if (draft.slots[path.slice(0, i)]) return true;
    return false;
  };
  function mutate(fn) {
    if (!editable()) return;
    const before = clone(draft);
    try {
      fn();
    } catch (error) {
      draft = before;
      message(error.message, true);
      return false;
    }
    history.push(before);
    if (history.length > 60) history.shift();
    dirty = true;
    message('Unsaved changes.');
    render();
    return true;
  }
  function place(id, path, swap = false) {
    if (!path || !allowedStart(path)) return;
    if (state.players.find(p => Number(p.id) === Number(id))?.eligible === false) {
      message('This player is no longer eligible. Remove their starting assignment.', true);
      return;
    }
    if (!id || !path) {
      message('Choose a player from the list first.', true);
      return;
    }
    const success = mutate(() => {
      const source = assignedPath(id),
        target = draft.slots[path];
      if (source === path) return;
      if (descendants(path).some(p => draft.slots[p].type === 'player' && p !== source))
        throw Error(
          'This qualifying path already contains players. Move them individually before placing a direct entrant here.'
        );
      if (source && (path.startsWith(source) || source.startsWith(path)))
        throw Error('Remove this player first, then place them in the earlier or later round.');
      if (target?.type === 'player' && !swap)
        throw Error('This slot is occupied. Click the box and choose Swap players.');
      if (swap && (!source || target?.type !== 'player'))
        throw Error('To swap, select a player already assigned elsewhere and an occupied destination.');
      if (source) {
        if (swap) draft.slots[source] = target;
        else delete draft.slots[source];
      }
      descendants(path).forEach(p => delete draft.slots[p]);
      draft.slots[path] = { type: 'player', id: Number(id) };
      selectedPlayer = null;
    });
    if (success) {
      $('fm-slot-dialog').close();
      selectedPlayer = null;
    }
  }
  function clearDrag() {
    draggedPlayer = null;
    $('fm-sidebar').classList.remove('drop-target');
    document.querySelectorAll('.fm-slot.drop-target').forEach(slot => slot.classList.remove('drop-target'));
  }
  function draggablePlayer(button, id, enabled) {
    button.draggable = enabled;
    button.addEventListener('dragstart', event => {
      if (!editable() || !button.draggable) {
        event.preventDefault();
        return;
      }
      draggedPlayer = Number(id);
      event.dataTransfer.setData('text/plain', String(id));
      event.dataTransfer.effectAllowed = 'move';
    });
    button.addEventListener('dragend', clearDrag);
  }
  function allowReturnToList(event) {
    if (!editable() || draggedPlayer === null || !assignedPath(draggedPlayer)) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    $('fm-sidebar').classList.add('drop-target');
  }
  function returnToList(event) {
    event.preventDefault();
    event.stopPropagation();
    const path = draggedPlayer === null ? null : assignedPath(draggedPlayer);
    clearDrag();
    if (path && mutate(() => {
      delete draft.slots[path];
      selectedPlayer = null;
    })) message('Player returned to unplaced. Save draft to keep this change.');
  }
  function pickerOptions() {
    const query = $('fm-pick-search').value.toLowerCase(),
      picker = $('fm-pick');
    picker.replaceChildren();
    state.players
      .filter(p => p.eligible !== false && p.name.toLowerCase().includes(query))
      .forEach(p => {
        const path = assignedPath(p.id);
        const option = el('option', '', p.name + (path ? ` · ${roundName(path.length - 1)}` : ''));
        option.value = p.id;
        picker.append(option);
      });
    if (selectedPlayer) picker.value = selectedPlayer;
  }
  function openSlot(path) {
    if (!editable() || !allowedStart(path)) return;
    selected = path;
    $('fm-slot-error').textContent = '';
    $('fm-slot-title').textContent = roundName(path.length - 1) + ' starting position';
    $('fm-slot-description').textContent =
      draft.slots[path]?.type === 'player'
        ? `Currently: ${name(draft.slots[path].id)}`
        : 'Choose a direct entrant, or leave this position to its qualifying path.';
    $('fm-pick-search').value = '';
    pickerOptions();
    $('fm-remove').disabled = !draft.slots[path];
    $('fm-slot-dialog').showModal();
    $('fm-pick-search').focus();
  }
  function renderPlayers() {
    const list = $('fm-players'),
      query = $('fm-search').value.toLowerCase();
    list.replaceChildren();
    let assigned = 0;
    state.players.forEach(player => {
      const path = assignedPath(player.id);
      if (path) assigned++;
      if (!player.name.toLowerCase().includes(query)) return;
      const button = el(
        'button',
        `fm-player${path ? ' assigned' : ''}${selectedPlayer === player.id ? ' selected' : ''}`
      );
      button.type = 'button';
      button.append(el('span', '', name(player.id)), el('small', '', path ? 'Placed' : 'Unplaced'));
      button.title = path ? `${name(player.id)} · Placed in ${roundName(path.length - 1)}` : name(player.id);
      draggablePlayer(button, player.id, editable() && player.eligible !== false);
      button.addEventListener('dragover', allowReturnToList);
      button.addEventListener('drop', returnToList);
      button.disabled = !editable() || player.eligible === false;
      button.addEventListener('click', () => {
        selectedPlayer = player.id;
        message(`${player.name} selected. Click their starting position.`);
        renderPlayers();
      });
      list.append(button);
    });
    $('fm-count').textContent = `${assigned} / ${state.players.length} placed`;
  }
  function indexOfPath(path) {
    return [...path].reduce((sum, char) => sum * 2 + (char === 'b' ? 1 : 0), 0);
  }
  function draftBoard() {
    const board = $('fm-board'),
      depth = Math.log2(draft.size);
    const entries = [];
    for (let level = depth - 1; level >= 0; level--) {
      for (let i = 0; i < 2 ** level; i++) {
        const path = level ? i.toString(2).padStart(level, '0').replaceAll('0', 'a').replaceAll('1', 'b') : '';
        if (!suppressed(path)) entries.push({ key: path, column: depth - 1 - level, sources: ['a', 'b'].map(side => ({ match: path + side })) });
      }
    }
    const { positions: boxes, width, height: boardHeight } = bracketLayout.layout(entries);
    board.style.width = width + 'px';
    board.style.height = boardHeight + 'px';
    for (let level = depth - 1; level >= 0; level--) {
      const x = (depth - 1 - level) * bracketDimensions.column;
      const title = el('div', 'fm-round-title', roundName(level));
      title.style.left = x + 'px';
      board.append(title);
      for (let i = 0; i < 2 ** level; i++) {
        const path = level ? i.toString(2).padStart(level, '0').replaceAll('0', 'a').replaceAll('1', 'b') : '';
        if (suppressed(path)) continue;
        const card = bracketMatch(boxes.get(path), `${roundName(level)} · ${i + 1}`);
        ['a', 'b'].forEach((side, slot) => {
          const key = path + side,
            source = draft.slots[key];
          const label =
            source?.type === 'player'
              ? name(source.id)
              : source?.type === 'bye'
              ? 'BYE'
              : level === depth - 1
              ? '+ Place player'
              : customStarts ? '+ Direct entrant / qualifying path' : 'Winner from previous round';
          const button = el(
            'button',
            `fm-slot ${source?.type === 'player' ? 'direct' : source?.type === 'bye' ? 'bye' : 'empty'}`,
            label
          );
          button.type = 'button';
          button.style.top = (slot ? boxes.get(path).bottom - boxes.get(path).top : 0) + 'px';
          button.title = label;
          button.dataset.slot = key;
          button.disabled = !editable() || !allowedStart(key);
          if (source?.type === 'player') draggablePlayer(button, source.id, editable() && allowedStart(key));
          button.setAttribute('aria-label', `${roundName(level)} ${i + 1}, ${slot === 0 ? 'top' : 'bottom'}: ${label}`);
          button.addEventListener('click', () => {
            if (selectedPlayer && source?.type !== 'player') place(selectedPlayer, key);
            else openSlot(key);
          });
          button.addEventListener('dragover', event => {
            if (editable() && allowedStart(key) && draggedPlayer !== null) {
              event.preventDefault();
              event.dataTransfer.dropEffect = 'move';
              button.classList.add('drop-target');
            }
          });
          button.addEventListener('dragleave', () => button.classList.remove('drop-target'));
          button.addEventListener('drop', event => {
            event.preventDefault();
            button.classList.remove('drop-target');
            const id = draggedPlayer;
            clearDrag();
            if (id !== null && state.players.some(p => Number(p.id) === id)) place(id, key);
          });
          card.append(button);
        });
        board.append(card);
      }
    }
    const lines = [];
    boxes.forEach((box, path) => {
      if (!path) return;
      const target = boxes.get(path.slice(0, -1));
      if (!target) return;
      lines.push([box.x + bracketDimensions.width, box.middle, target.x, path.endsWith('a') ? target.top : target.bottom]);
    });
    connectors(board, boxes.values(), lines, width, boardHeight);
  }
  function bracketMatch(position, label) {
    const match = el('div', 'fm-match');
    match.style.left = position.x + 'px';
    match.style.top = position.top - bracketDimensions.slotHeight + 'px';
    match.style.width = bracketDimensions.width + 'px';
    match.style.height = position.bottom - position.top + bracketDimensions.slotHeight + 'px';
    match.append(el('div', 'fm-match-head', label));
    return match;
  }
  function connectors(board, positions, lines, width, height) {
    const ns = 'http://www.w3.org/2000/svg',
      svg = document.createElementNS(ns, 'svg');
    svg.classList.add('fm-links', 'ct-bracket-svg');
    svg.setAttribute('width', width);
    svg.setAttribute('height', height);
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('aria-hidden', 'true');
    const path = document.createElementNS(ns, 'path');
    path.setAttribute('data-ct-edge', '');
    path.setAttribute('d', bracketLayout.linePath(positions, lines, { raw: true }));
    svg.append(path);
    board.prepend(svg);
    window.TennisBracket.refresh();
  }
  function sourceLabel(source) {
    if (source.type === 'player') return 'Direct entry';
    return `${source.type === 'winner' ? 'Winner' : 'Loser'} of Match ${state.matches[source.match]?.number ?? '?'}`;
  }
  function generatedBoard() {
    const board = $('fm-board'),
      sections = new Map(),
      coords = new Map();
    let yOffset = 0,
      width = 500;
    Object.entries(state.matches).forEach(([key, match]) => {
      if (!sections.has(match.section)) sections.set(match.section, []);
      sections.get(match.section).push([key, match]);
    });
    sections.forEach((entries, section) => {
      const title = el('div', 'fm-section-label', section);
      title.style.top = yOffset + 'px';
      board.append(title);
      const rounds = [...new Set(entries.map(([, m]) => Number(m.round)))].sort((a, b) => a - b);
      const layout = bracketLayout.layout(entries.map(([key, match]) => ({ key, column: rounds.indexOf(Number(match.round)), sources: match.sources })), yOffset + 96);
      layout.positions.forEach((position, key) => coords.set(key, { ...position, section }));
      rounds.forEach((round, col) => {
        const group = entries.filter(([, m]) => Number(m.round) === round),
          x = col * bracketDimensions.column;
        const heading = el('div', 'fm-round-title', group[0][1].label);
        heading.style.top = yOffset + 32 + 'px';
        heading.style.left = x + 'px';
        board.append(heading);
        group.forEach(([key, match]) => {
          const position = coords.get(key);
          const card = bracketMatch(position, `Match ${match.number}`);
          match.players.forEach((id, slot) => {
            const line = el('div', `fm-slot${id && Number(id) === Number(match.winner) ? ' winner' : ''}`);
            line.style.top = (slot ? position.bottom - position.top : 0) + 'px';
            const label = el('span', 'fm-slot-name', id ? name(id) : match.vacant?.[slot] ? 'No active entrant' : sourceLabel(match.sources[slot]));
            line.append(label);
            if (match.sets.length) line.append(el('strong', '', match.sets.map(s => s[slot]).join(' ')));
            line.title = label.textContent + ' · ' + sourceLabel(match.sources[slot]);
            card.append(line);
          });
          if (match.automatic) card.append(el('div', 'fm-match-note', match.automatic === 'walkover' ? 'Walkover · no score played' : 'Closed · no active players'));
          if (config.canScore && !state.locked && !match.automatic && match.players.every(Boolean)) {
            const score = el('button', 'fm-score-button', match.winner ? 'Edit result' : 'Enter result');
            score.type = 'button';
            score.disabled = busy;
            score.addEventListener('click', () => openScore(key));
            card.append(score);
          }
          board.append(card);
        });
      });
      width = Math.max(width, layout.width);
      yOffset = layout.height + 35;
    });
    board.style.width = width + 'px';
    board.style.height = Math.max(yOffset, 250) + 'px';
    const lines = [];
    Object.entries(state.matches).forEach(([key, match]) => {
      const target = coords.get(key);
      match.sources.forEach((source, slot) => {
        const from = coords.get(source.match);
        if (from && from.section === target.section)
          lines.push([from.x + bracketDimensions.width, from.middle, target.x, slot ? target.bottom : target.top]);
      });
    });
    connectors(board, coords.values(), lines, width, yOffset);
    $('fm-positions').replaceChildren();
    state.positions.forEach(p => {
      const chip = el('div', 'fm-position');
      chip.append(el('strong', '', p.position), el('span', '', p.player ? name(p.player) : p.vacant ? 'Unassigned after withdrawal' : 'Awaiting results'));
      $('fm-positions').append(chip);
    });
  }
  function render() {
    $('fm-app').dataset.dirty = String(dirty || busy);
    // PHP encodes an empty associative array as []; always edit a keyed object.
    draft.slots = Object.assign({}, draft.slots);
    $('fm-board').replaceChildren();
    $('fm-size').value = draft.size;
    $('fm-status').textContent = state.locked
      ? 'Locked'
      : state.published
      ? 'Published'
      : state.generated
      ? config.demo
        ? 'Demo preview'
        : 'Ready to publish'
      : dirty
      ? 'Unsaved draft'
      : 'Draft';
    $('fm-phase').textContent = state.generated
      ? (config.workflow === 'playoffs' ? 'Play the knockout draw' : 'Play the draw and complete every position')
      : 'Step 2 · Bracket size and starting positions';
    $('fm-board-title').textContent = state.generated ? (config.workflow === 'playoffs' ? 'Playoff bracket' : 'Main draw & placement matches') : 'Starting positions';
    $('fm-sidebar').hidden = state.generated || config.readOnly;
    $('fm-workspace')?.classList.toggle('generated', state.generated);
    document.querySelector('.fm-workspace').style.gridTemplateColumns =
      state.generated || config.readOnly ? 'minmax(0,1fr)' : '';
    ['fm-size', 'fm-undo', 'fm-byes', 'fm-save', 'fm-generate'].forEach(id => {
      $(id).hidden = state.generated || config.readOnly;
      $(id).disabled = !editable();
    });
    $('fm-size').parentElement.hidden = state.generated || config.readOnly;
    $('fm-undo').disabled = !editable() || !history.length;
    $('fm-example').hidden = !config.demo || state.generated;
    $('fm-save').textContent = config.demo ? 'Save demo on this device' : 'Save draft';
    $('fm-generate').textContent = config.demo ? 'Preview fixtures' : 'Generate fixtures';
    $('fm-publish').hidden = !state.generated || !config.canPublish || state.locked;
    $('fm-publish').textContent = state.published ? 'Unpublish draw' : 'Publish draw';
    $('fm-publish').disabled = busy;
    $('fm-reopen').hidden = !state.generated || config.readOnly || !config.canEdit || state.published || state.locked;
    $('fm-reopen').disabled = busy;
    const withdrawn = state.players.filter(p => p.withdrawn);
    $('fm-withdrawn').hidden = !withdrawn.length;
    $('fm-withdrawn').textContent = `Withdrawn: ${withdrawn.map(p => p.name).join(', ')}. ` +
      (state.generated ? 'Completed results remain recorded. Unplayed matches advance active opponents by walkover; withdrawn players receive no final position.' :
        'Remove their starting assignments before saving or generating.') +
      (state.generated && state.withdrawals_pending && config.canScore && !state.locked ? ' Apply withdrawals to update fixtures and schedules, or continue scoring another match.' : '');
    $('fm-withdrawals').hidden = !state.generated || config.readOnly || !config.canScore || state.locked || !state.withdrawals_pending;
    $('fm-withdrawals').disabled = busy;
    if ($('fm-public')) $('fm-public').hidden = !state.published;
    $('fm-results').hidden = !state.generated;
    if (state.generated) generatedBoard();
    else {
      renderPlayers();
      draftBoard();
    }
    renderPrint();
    renderTimetable();
    renderRoster();
    document.querySelectorAll('[data-share-draw]').forEach(button => {
      button.disabled = !state.published;
      button.title = state.published ? 'Share public draw link' : 'Publish the draw before sharing';
    });
    document.querySelectorAll('[data-workspace-public]').forEach(link => { link.hidden = !state.published; });
    document.querySelectorAll('[data-workspace-status]').forEach(label => { label.textContent = state.locked ? 'Locked' : state.published ? 'Published' : dirty ? 'Unsaved draft' : 'Draft'; });
    // Fit the complete diagram to landscape paper; vertical pagination stays native.
    $('fm-board').style.setProperty('--ct-print-scale', Math.min(1, 980 / (parseFloat($('fm-board').style.width) || $('fm-board').offsetWidth || 980)));
  }
  function renderTimetable() {
    const root = $('fm-timetable');
    if (!root) return;
    root.replaceChildren();
    const matches = Object.values(state.matches || {});
    if (!matches.length) { root.append(el('p', '', 'Generate fixtures before scheduling matches.')); return; }
    const table = el('table');
    const head = el('thead');
    const titles = el('tr');
    ['Match', 'Players / feeder paths', 'Time', 'Venue', 'Court'].forEach(title => titles.append(el('th', '', title)));
    head.append(titles); table.append(head);
    const body = el('tbody');
    matches.sort((a, b) => String(a.schedule?.time || '9999').localeCompare(String(b.schedule?.time || '9999')) || a.number - b.number).forEach(match => {
      const row = el('tr');
      const players = match.players.map((id, i) => id ? name(id) : match.vacant?.[i] ? 'No active entrant' : sourceLabel(match.sources[i])).join(' vs ');
      [String(match.number), players, match.schedule?.time || 'Not scheduled', match.schedule?.venue || '—', match.schedule?.court || '—'].forEach(value => row.append(el('td', '', String(value))));
      body.append(row);
    });
    table.append(body); root.append(table);
  }
  function renderRoster() {
    const roster = $('fm-generated-roster');
    if (!roster) return;
    roster.hidden = !state.generated || location.hash !== '#groups';
    roster.replaceChildren();
    if (!state.generated) return;
    roster.append(el('h2', '', 'Generated draw roster'));
    roster.append(el('p', '', 'Starting positions are read-only after generation. Use Edit starting positions below when the draw is unpublished and unlocked.'));
    const ids = new Set(Object.values(draft.slots).filter(slot => slot.type === 'player').map(slot => Number(slot.id)));
    const list = el('ul');
    state.players.filter(player => ids.has(Number(player.id))).forEach(player => list.append(el('li', '', player.name + (player.withdrawn ? ' (withdrawn)' : ''))));
    roster.append(list);
  }
  function renderPrint() {
    const root = $('fm-print-content');
    root.replaceChildren();
    const sections = new Map();
    if (state.generated)
      Object.values(state.matches).forEach(match => {
        if (!sections.has(match.section)) sections.set(match.section, []);
        sections
          .get(match.section)
          .push([
            `Match ${match.number}`,
            match.label,
            ...match.players.map((id, i) => (id ? name(id) : match.vacant?.[i] ? 'No active entrant' : sourceLabel(match.sources[i]))),
            match.automatic === 'walkover' ? 'Walkover' : match.automatic === 'void' ? 'Closed — no active players' : match.sets.map(s => s.join('-')).join(', ')
          ]);
      });
    else
      Object.entries(draft.slots)
        .filter(([, s]) => s.type === 'player')
        .sort(([a], [b]) => a.localeCompare(b))
        .forEach(([path, source]) => {
          if (!sections.has('Starting positions')) sections.set('Starting positions', []);
          sections
            .get('Starting positions')
            .push([
              roundName(path.length - 1),
              String(indexOfPath(path.slice(0, -1)) + 1),
              path.endsWith('a') ? 'Top' : 'Bottom',
              name(source.id)
            ]);
        });
    sections.forEach((rows, title) => {
      const section = el('section', 'fm-print-section');
      section.append(el('h2', '', title));
      const table = el('table');
      const head = el('thead'),
        tr = el('tr');
      (state.generated
        ? ['Match', 'Round', 'Player 1 / source', 'Player 2 / source', 'Result']
        : ['Starting round', 'Match', 'Slot', 'Player']
      ).forEach(t => tr.append(el('th', '', t)));
      head.append(tr);
      table.append(head);
      const body = el('tbody');
      rows.forEach(row => {
        const r = el('tr');
        row.forEach(cell => r.append(el('td', '', cell)));
        body.append(r);
      });
      table.append(body);
      section.append(table);
      root.append(section);
    });
    Object.entries(config.notes || {}).forEach(([title, note]) => {
      if (!note) return;
      const section = el('section', 'fm-print-section');
      section.append(el('h2', '', title.replace(/_/g, ' ') + ' rules & notes'));
      const text = el('p', '', note);
      text.style.whiteSpace = 'pre-wrap';
      section.append(text); root.append(section);
    });
  }
  async function request(url, body, method = 'POST') {
    const response = await fetch(url, {
      method,
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
      },
      body: JSON.stringify(body)
    });
    const data = await response
      .json()
      .catch(() => ({ message: 'The server returned an unexpected response. Reload and try again.' }));
    if (!response.ok)
      throw Error(data.errors ? Object.values(data.errors).flat().join(' ') : data.message || 'Unable to save.');
    return data;
  }
  async function run(fn) {
    if (busy) return;
    busy = true;
    render();
    try {
      await fn();
    } catch (error) {
      message(error.message, true);
    } finally {
      busy = false;
      render();
    }
  }
  async function saveDraft() {
    if (config.demo) {
      localStorage.setItem('ct-flexible-monrad-demo', JSON.stringify(draft));
      dirty = false;
      message('Demo saved on this device. No event data was changed.');
      return;
    }
    state = await request(config.urls.save, { revision: state.revision, draft }, 'PUT');
    draft = clone(state.draft);
    dirty = false;
    history = [];
    message('Draft saved.');
  }
  function openScore(key) {
    scoreKey = key;
    const match = state.matches[key];
    $('fm-score-title').textContent = `Match ${match.number}`;
    $('fm-score-players').textContent = match.players.map(name).join(' vs ');
    const bestOf = state.best_of || 1;
    $('fm-score-format').textContent = `Best of ${bestOf}: ${Math.floor(bestOf / 2) + 1} set wins needed. Enter completed sets separated by commas: 6–0 to 6–4, 7–5 or 7–6.`;
    $('fm-sets').placeholder = bestOf === 1 ? '6-4' : '6-4, 6-3';
    $('fm-sets').value = match.sets.map(s => s.join('-')).join(', ');
    $('fm-score-error').textContent = '';
    $('fm-reset').checked = false;
    $('fm-reset-label').hidden = true;
    $('fm-score-dialog').showModal();
    $('fm-sets').focus();
  }
  function demoResolve() {
    const resolve = source => {
      if (source.type === 'player') return source.id;
      const from = state.matches[source.match];
      if (!from?.winner) return null;
      return source.type === 'winner' ? from.winner : from.players.find(id => id !== from.winner);
    };
    Object.values(state.matches).forEach(match => {
      match.players = match.sources.map(resolve);
    });
    state.positions = Object.entries(demoGraph.positions).map(([position, source]) => ({
      position: Number(position),
      player: resolve(source)
    }));
  }
  async function submitScore(remove) {
    if (busy) return;
    const match = state.matches[scoreKey];
    let sets = null;
    if (!remove) {
      const chunks = $('fm-sets')
        .value.split(',')
        .map(s => s.trim());
      if (!chunks.length || chunks.length > (state.best_of || 1) || chunks.some(s => !/^\d{1,2}\s*-\s*\d{1,2}$/.test(s))) {
        $('fm-score-error').textContent = `Enter up to ${state.best_of || 1} sets, using scores such as 6-4.`;
        return;
      }
      sets = chunks.map(s => s.split('-').map(Number));
    }
    busy = true;
    try {
      if (sets) {
        const bestOf = state.best_of || 1;
        const needed = Math.floor(bestOf / 2) + 1;
        const wins = [0, 0];
        for (const set of sets) {
          if (Math.max(...wins) === needed) throw Error('Remove sets entered after the match was already won.');
          const high = Math.max(...set), low = Math.min(...set);
          if (!((high === 6 && low >= 0 && low <= 4) || (high === 7 && [5, 6].includes(low))))
            throw Error('Enter a completed set: 6–0 to 6–4, 7–5 or 7–6.');
          wins[set[0] > set[1] ? 0 : 1]++;
        }
        if (Math.max(...wins) !== needed) throw Error(`This match needs ${needed} set wins to finish.`);
      }
      if (config.demo) {
        const winner = sets ? match.players[sets.filter(s => s[0] > s[1]).length > sets.length / 2 ? 0 : 1] : null;
        if (match.winner && match.winner !== winner) {
          const changed = new Set([scoreKey]);
          let more = true;
          while (more) {
            more = false;
            Object.entries(state.matches).forEach(([k, m]) => {
              if (!changed.has(k) && m.sources.some(s => changed.has(s.match))) {
                changed.add(k);
                more = true;
              }
            });
          }
          const affected = [...changed].filter(k => k !== scoreKey && state.matches[k].winner);
          if (affected.length && !$('fm-reset').checked)
            throw Error(
              `This correction changes later scored matches: ${affected
                .map(k => 'Match ' + state.matches[k].number)
                .join(', ')}. Confirm resetting these results before continuing.`
            );
          changed.forEach(k => {
            if (k !== scoreKey) {
              state.matches[k].sets = [];
              state.matches[k].winner = null;
            }
          });
        }
        match.sets = sets || [];
        match.winner = winner;
        demoResolve();
      } else
        state = await request(
          config.urls.score.replace('__FIXTURE__', match.id),
          { revision: state.revision, sets, reset_dependents: $('fm-reset').checked },
          'PUT'
        );
      $('fm-score-dialog').close();
      message(remove ? 'Result deleted.' : 'Result saved; winner and loser paths updated.');
      render();
    } catch (error) {
      $('fm-score-error').textContent = error.message;
      $('fm-reset-label').hidden = !error.message.includes('Confirm resetting');
    } finally {
      busy = false;
      render();
    }
  }
  $('fm-sidebar').addEventListener('dragover', allowReturnToList);
  $('fm-sidebar').addEventListener('dragleave', event => {
    if (!$('fm-sidebar').contains(event.relatedTarget)) $('fm-sidebar').classList.remove('drop-target');
  });
  $('fm-sidebar').addEventListener('drop', returnToList);
  $('fm-search').addEventListener('input', renderPlayers);
  $('fm-pick-search').addEventListener('input', pickerOptions);
  $('fm-place').addEventListener('click', () => place(Number($('fm-pick').value), selected));
  $('fm-swap').addEventListener('click', () => place(Number($('fm-pick').value), selected, true));
  $('fm-remove').addEventListener('click', () => {
    if (mutate(() => delete draft.slots[selected])) $('fm-slot-dialog').close();
  });
  $('fm-bye').addEventListener('click', () => {
    if (
      mutate(() => {
        if (
          draft.slots[selected]?.type === 'player' ||
          descendants(selected).some(p => draft.slots[p].type === 'player')
        )
          throw Error('Remove or move assigned players before marking this path as a bye.');
        descendants(selected).forEach(p => delete draft.slots[p]);
        draft.slots[selected] = { type: 'bye' };
      })
    )
      $('fm-slot-dialog').close();
  });
  $('fm-undo').addEventListener('click', () => {
    if (editable() && history.length) {
      draft = history.pop();
      dirty = true;
      render();
      message('Last placement change undone.');
    }
  });
  $('fm-size').addEventListener('change', event => {
    const size = Number(event.target.value);
    if (Object.values(draft.slots).some(s => s.type === 'player')) {
      message('Remove assigned players before changing bracket size.', true);
      render();
      return;
    }
    mutate(() => {
      draft = { size, slots: {} };
    });
  });
  $('fm-byes').addEventListener('click', () =>
    mutate(() => {
      const depth = Math.log2(draft.size);
      const fill = path => {
        if (draft.slots[path]) return;
        if (allowedStart(path) && !descendants(path).some(p => draft.slots[p].type === 'player')) {
          descendants(path).forEach(p => delete draft.slots[p]);
          draft.slots[path] = { type: 'bye' };
          return;
        }
        if (path.length < depth) {
          fill(path + 'a');
          fill(path + 'b');
        }
      };
      fill('a');
      fill('b');
    })
  );
  $('fm-example').addEventListener('click', () => {
    if (
      Object.values(draft.slots).some(s => s.type === 'player') &&
      !confirm('Replace this demo layout with the 22-player example? You can Undo this change.')
    )
      return;
    mutate(() => {
      draft = { size: 32, slots: { aaa: { type: 'player', id: 1 }, bbb: { type: 'player', id: 2 } } };
      let id = 3;
      ['aaba', 'abba', 'baaa', 'baba'].forEach(p => (draft.slots[p] = { type: 'player', id: id++ }));
      const fill = path => {
        if (draft.slots[path]) return;
        if (path.length === 5) {
          draft.slots[path] = { type: 'player', id: id++ };
          return;
        }
        fill(path + 'a');
        fill(path + 'b');
      };
      fill('a');
      fill('b');
    });
  });
  $('fm-save').addEventListener('click', () => run(saveDraft));
  $('fm-generate').addEventListener('click', () =>
    run(async () => {
      if (config.demo) {
        demoGraph = await request(config.urls.generate, { draft });
        state.matches = {};
        Object.entries(demoGraph.nodes).forEach(([key, node], index) => {
          state.matches[key] = { ...node, id: index + 1, number: index + 1, players: [], winner: null, sets: [] };
        });
        state.generated = true;
        demoResolve();
        dirty = false;
        message('Demo fixtures generated. Try entering results to see both paths advance.');
      } else {
        if (dirty || state.revision === 0) await saveDraft();
        state = await request(config.urls.generate, { revision: state.revision });
        dirty = false;
        message('Fixtures generated. Review the draw before publishing. Starting assignments are now frozen.');
      }
    })
  );
  $('fm-publish').addEventListener('click', () =>
    run(async () => {
      state = await request(config.urls.publish, { revision: state.revision, published: !state.published });
      message(state.published ? 'Draw published. Authorized scoring remains available.' : 'Draw unpublished.');
    })
  );
  $('fm-withdrawals').addEventListener('click', () => run(async () => {
    state = await request(config.urls.withdrawals, { revision: state.revision });
    message('Withdrawals applied. Completed results are preserved; active opponents advance by walkover.');
  }));
  $('fm-reopen').addEventListener('click', () =>
    run(async () => {
      if (config.demo) {
        state.generated = false;
        state.matches = {};
        state.positions = [];
        demoGraph = null;
      } else state = await request(config.urls.reopen, { revision: state.revision });
      message('Starting positions reopened. Generate fixtures again when ready.');
    })
  );
  $('fm-score-save').addEventListener('click', () => submitScore(false));
  $('fm-score-delete').addEventListener('click', () => submitScore(true));
  $('fm-print')?.addEventListener('click', () => window.print());
  window.addEventListener('hashchange', renderRoster);
  window.addEventListener('draw-workspace-notes-saved', event => { config.notes = event.detail; renderPrint(); });
  window.addEventListener('beforeunload', event => {
    if (dirty && !config.demo) {
      event.preventDefault();
      event.returnValue = '';
    }
  });
  if (config.demo) {
    try {
      const saved = JSON.parse(localStorage.getItem('ct-flexible-monrad-demo'));
      if (saved && [4, 8, 16, 32, 64].includes(saved.size) && saved.slots) draft = saved;
    } catch (_) {}
    message('Demo only: these are dummy players. No event data is changed.');
  }
  render();
})();
