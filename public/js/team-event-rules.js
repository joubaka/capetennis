(function () {
  'use strict';
  const rulesForm = document.getElementById('team-event-rules-form');
  if (rulesForm) rulesForm.addEventListener('submit', function () {
    rulesForm.querySelectorAll('select[name="rules[standings_order][]"]').forEach(select => { if (!select.value) select.disabled = true; });
  });
  const form = document.getElementById('team-format-form');
  if (!form) return;
  const rows = document.getElementById('format-rubbers');
  const presetsElement = document.getElementById('team-format-presets');
  const presets = presetsElement ? JSON.parse(presetsElement.textContent) : {};
  const codes = ['singles', 'reverse_singles', 'doubles', 'reverse_doubles', 'mixed_doubles', 'reverse_mixed_doubles'];
  const message = document.getElementById('format-message');
  let modified = false;
  let nextId = 0;
  function maxRoster() { return Number(form.elements.max_roster_size.value); }
  function positions(row, side) {
    return Array.from(row.querySelectorAll('.' + side + '-positions select')).map(select => Number(select.value));
  }
  function rankSelect(side, slot, value, id) {
    const wrapper = document.createElement('div');
    wrapper.className = 'col-6';
    const label = document.createElement('label');
    label.className = 'form-label';
    label.htmlFor = id;
    label.textContent = side + ' player ' + slot;
    const select = document.createElement('select');
    select.id = id;
    select.className = 'form-select';
    select.required = true;
    select.add(new Option('Choose rank', ''));
    for (let rank = 1; rank <= Math.min(12, maxRoster()); rank++) select.add(new Option('Rank ' + rank, String(rank)));
    if (value && (value > maxRoster() || value < 1)) select.add(new Option('Rank ' + value + ' (outside roster)', String(value)));
    select.value = value ? String(value) : '';
    wrapper.append(label, select);
    return wrapper;
  }
  function renderPositions(row, home, away) {
    const count = row.querySelector('.rubber-code').value.includes('doubles') ? 2 : 1;
    row.querySelector('.gender-rule option[value="mixed"]').disabled = count === 1;
    if (count === 1 && row.querySelector('.gender-rule').value === 'mixed') row.querySelector('.gender-rule').value = '';
    ['home', 'away'].forEach(side => {
      const area = row.querySelector('.' + side + '-positions');
      area.replaceChildren();
      const values = side === 'home' ? home : away;
      for (let slot = 0; slot < count; slot++) area.append(rankSelect(side === 'home' ? 'Home' : 'Away', slot + 1, values[slot], row.dataset.id + '-' + side + '-' + slot));
    });
  }
  function warnings() {
    const problems = [];
    const max = maxRoster();
    const min = Number(form.elements.min_roster_size.value);
    if (!Number.isInteger(min) || !Number.isInteger(max) || min < 1 || max > 12 || min > max) problems.push('Choose roster sizes from 1 to 12, with minimum no greater than maximum.');
    if (!rows.children.length) problems.push('Add at least one rubber.');
    const used = {home: new Set(), away: new Set()};
    Array.from(rows.children).forEach((row, index) => {
      if (!row.querySelector('.rubber-name').value.trim()) problems.push('Rubber ' + (index + 1) + ': enter a name.');
      ['home', 'away'].forEach(side => {
        const values = positions(row, side);
        if (values.some(value => !Number.isInteger(value) || value < 1 || value > max)) problems.push('Rubber ' + (index + 1) + ': choose ' + side + ' ranks within the maximum roster.');
        if (new Set(values).size !== values.length) problems.push('Rubber ' + (index + 1) + ': ' + side + ' partners must have different ranks.');
        values.filter(Boolean).forEach(value => {
          if (!form.elements.allow_player_reuse.checked && used[side].has(value)) problems.push('Rubber ' + (index + 1) + ': ' + side + ' rank ' + value + ' is reused. Enable player reuse or change the pairing.');
          used[side].add(value);
        });
      });
      if (row.querySelector('.rubber-code').value.includes('mixed') && row.querySelector('.gender-rule').value !== 'mixed') problems.push('Rubber ' + (index + 1) + ': mixed doubles requires Mixed gender.');
    });
    return [...new Set(problems)];
  }
  function refresh() {
    Array.from(rows.children).forEach((row, index) => {
      row.querySelector('.rubber-order').textContent = 'Rubber ' + (index + 1);
      row.querySelector('.move-up').disabled = index === 0;
      row.querySelector('.move-down').disabled = index === rows.children.length - 1;
      const describe = side => positions(row, side).map(value => (side === 'home' ? 'Home ' : 'Away ') + (value || '?')).join(' + ');
      row.querySelector('.pairing-summary').textContent = describe('home') + ' vs ' + describe('away');
    });
    document.getElementById('format-summary').textContent = rows.children.length + ' rubbers in this draft.';
    const problems = warnings();
    const warning = document.getElementById('format-warnings');
    warning.replaceChildren();
    if (problems.length) {
      const list = document.createElement('ul');
      list.className = 'mb-0';
      problems.forEach(problem => { const item = document.createElement('li'); item.textContent = problem; list.append(item); });
      warning.append(list);
    }
    warning.classList.toggle('d-none', !problems.length);
  }
  function addRow(definition = {}) {
    const row = document.createElement('div');
    row.className = 'border rounded p-3 mb-3';
    row.dataset.id = 'rubber-draft-' + (++nextId);
    row.innerHTML = `<div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-2"><strong class="rubber-order"></strong><div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-outline-secondary move-up" aria-label="Move rubber up">Move up</button><button type="button" class="btn btn-outline-secondary move-down" aria-label="Move rubber down">Move down</button><button type="button" class="btn btn-outline-danger remove-rubber">Remove</button></div></div><div class="row g-3"><div class="col-sm-6"><label class="form-label" for="${row.dataset.id}-name">Rubber name</label><input id="${row.dataset.id}-name" class="form-control rubber-name" maxlength="100" required></div><div class="col-sm-6"><label class="form-label" for="${row.dataset.id}-code">Rubber type</label><select id="${row.dataset.id}-code" class="form-select rubber-code">${codes.map(code => `<option value="${code}">${code.replaceAll('_', ' ')}</option>`).join('')}</select></div><div class="col-md-6"><div class="row g-2 home-positions"></div></div><div class="col-md-6"><div class="row g-2 away-positions"></div></div><div class="col-sm-6"><label class="form-label" for="${row.dataset.id}-gender">Gender rule</label><select id="${row.dataset.id}-gender" class="form-select gender-rule"><option value="">Any</option><option value="male">Male</option><option value="female">Female</option><option value="mixed">Mixed</option></select></div><div class="col-sm-6 d-flex align-items-end"><label class="form-check"><input class="form-check-input rubber-required" type="checkbox" checked> Required rubber</label></div></div><p class="pairing-summary mb-0 mt-3"></p>`;
    row.querySelector('.rubber-code').value = definition.rubber_code || 'singles';
    row.querySelector('.rubber-name').value = definition.name || 'Singles';
    row.querySelector('.gender-rule').value = definition.gender_rule || '';
    row.querySelector('.rubber-required').checked = definition.is_required !== false;
    renderPositions(row, definition.home_positions || [], definition.away_positions || []);
    row.querySelector('.rubber-code').addEventListener('change', () => {
      const home = positions(row, 'home');
      const away = positions(row, 'away');
      const mixed = row.querySelector('.rubber-code').value.includes('mixed');
      if (mixed) row.querySelector('.gender-rule').value = 'mixed';
      else if (row.querySelector('.gender-rule').value === 'mixed') row.querySelector('.gender-rule').value = '';
      renderPositions(row, home, away);
    });
    row.querySelector('.remove-rubber').addEventListener('click', () => { row.remove(); modified = true; refresh(); });
    row.querySelector('.move-up').addEventListener('click', () => { if (row.previousElementSibling) rows.insertBefore(row, row.previousElementSibling); modified = true; refresh(); });
    row.querySelector('.move-down').addEventListener('click', () => { if (row.nextElementSibling) rows.insertBefore(row.nextElementSibling, row); modified = true; refresh(); });
    rows.append(row);
  }
  form.addEventListener('input', () => { modified = true; refresh(); });
  form.addEventListener('change', () => { modified = true; refresh(); });
  form.elements.max_roster_size.addEventListener('input', () => {
    Array.from(rows.children).forEach(row => renderPositions(row, positions(row, 'home'), positions(row, 'away')));
    refresh();
  });
  document.getElementById('add-rubber').addEventListener('click', () => { addRow(); modified = true; refresh(); });
  document.getElementById('apply-preset').addEventListener('click', () => {
    const preset = presets[document.getElementById('format-preset').value];
    if (!preset || (modified && !window.confirm('Replace the current unsaved pairing draft with this preset?'))) return;
    form.elements.name.value = preset.name;
    form.elements.min_roster_size.value = preset.min_roster_size;
    form.elements.max_roster_size.value = preset.max_roster_size;
    form.elements.allow_player_reuse.checked = preset.allow_player_reuse;
    rows.replaceChildren();
    preset.rubbers.forEach(addRow);
    modified = true;
    message.textContent = 'Preset loaded into draft. Customize it, then create a new format when ready.';
    refresh();
  });
  addRow();
  refresh();
  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    refresh();
    const problems = warnings();
    if (problems.length) { message.textContent = 'Resolve the draft warnings before creating this format.'; return; }
    const payload = {name: form.elements.name.value.trim(), min_roster_size: Number(form.elements.min_roster_size.value), max_roster_size: maxRoster(), allow_player_reuse: form.elements.allow_player_reuse.checked, is_default: form.elements.is_default.checked,
      rubbers: Array.from(rows.children).map((row, index) => {
        const code = row.querySelector('.rubber-code').value;
        return {sequence: index + 1, name: row.querySelector('.rubber-name').value.trim(), rubber_code: code, player_count_per_team: code.includes('doubles') ? 2 : 1, home_positions: positions(row, 'home'), away_positions: positions(row, 'away'), gender_rule: row.querySelector('.gender-rule').value || null, is_required: row.querySelector('.rubber-required').checked};
      })};
    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    try {
      const response = await fetch(form.action, {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify(payload)});
      const result = await response.json();
      message.textContent = response.ok ? 'Format created. Select it when creating a new draw.' : Object.values(result.errors || {}).flat().join(' ') || result.message || 'Could not create format.';
      if (response.ok) modified = false;
    } catch (error) { message.textContent = 'Could not create format. Please retry.'; }
    finally { button.disabled = false; }
  });
})();
