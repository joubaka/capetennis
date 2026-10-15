(() => {
  'use strict';
  const root = document.getElementById('placement-competition');
  if (!root) return;
  const groups = JSON.parse(document.getElementById('competition-groups').textContent);
  const groupSelect = document.getElementById('competition-group');
  const submit = document.getElementById('competition-submit');
  const message = document.getElementById('competition-message');
  let group = null;
  function element(tag, attributes = {}, text = '') {
    const node = document.createElement(tag);
    Object.entries(attributes).forEach(([key, value]) => node.setAttribute(key, value));
    node.textContent = text;
    return node;
  }
  function counts() {
    root.querySelectorAll('[data-phase]').forEach(list => {
      root.querySelector(`[data-count="${list.dataset.phase}"]`).textContent = `(${list.children.length})`;
    });
  }
  function add(phase, data = {}) {
    const list = root.querySelector(`[data-phase="${phase}"]`);
    const card = element('div', { class: 'match-row' });
    const row = element('div', { class: 'row g-3' });
    function field(label, input, width = 'col-sm-6') {
      const holder = element('label', { class: width });
      holder.append(element('span', { class: 'form-label d-block' }, label), input);
      row.append(holder);
      return input;
    }
    const name = field('Match name', element('input', { class: 'form-control', required: '', maxlength: '100', 'data-field': 'name' }));
    name.value = data.name || 'New match';
    const code = field('Match type', element('select', { class: 'form-select', 'data-field': 'rubber_code' }));
    [['singles', 'Singles'], ['doubles', 'Doubles'], ['mixed_doubles', 'Mixed doubles']].forEach(([value, label]) => code.append(element('option', { value }, label)));
    code.value = data.rubber_code || 'doubles';
    const gender = field('Players', element('select', { class: 'form-select', 'data-field': 'gender_rule' }));
    [['male', 'Boys'], ['female', 'Girls'], ['mixed', 'Mixed']].forEach(([value, label]) => gender.append(element('option', { value }, label)));
    gender.value = data.gender_rule || 'male';
    ['home', 'away'].forEach(side => {
      const holder = element('div', { class: 'col-sm-6' });
      holder.append(element('div', { class: 'form-label' }, `${side === 'home' ? 'Home' : 'Away'} pairing positions`));
      const slots = element('div', { class: 'd-flex gap-2', 'data-side': side });
      for (let index = 0; index < 2; index++) {
        const select = element('select', { class: 'form-select', 'aria-label': `${side} player ${index + 1}` });
        for (let rank = 1; rank <= (group?.size || 2) * 2; rank++) {
          select.append(element('option', { value: rank }, `${rank % 2 ? 'Boy' : 'Girl'} ${Math.ceil(rank / 2)} (${rank})`));
        }
        select.value = data[`${side}_positions`]?.[index] || (index + 1);
        slots.append(select);
      }
      holder.append(slots); row.append(holder);
    });
    function sync() {
      card.querySelectorAll('[data-side]').forEach(slots => { slots.lastChild.hidden = code.value === 'singles'; });
      if (code.value === 'mixed_doubles') gender.value = 'mixed';
      gender.disabled = code.value === 'mixed_doubles';
    }
    code.addEventListener('change', sync); sync();
    const remove = element('button', { type: 'button', class: 'btn btn-outline-danger mt-3' }, 'Remove match');
    remove.addEventListener('click', () => { card.remove(); counts(); });
    const up = element('button', { type: 'button', class: 'btn btn-outline-secondary mt-3 me-2', 'aria-label': 'Move match earlier' }, 'Move up');
    const down = element('button', { type: 'button', class: 'btn btn-outline-secondary mt-3 me-2', 'aria-label': 'Move match later' }, 'Move down');
    up.addEventListener('click', () => { if (card.previousElementSibling) list.insertBefore(card, card.previousElementSibling); });
    down.addEventListener('click', () => { if (card.nextElementSibling) list.insertBefore(card.nextElementSibling, card); });
    card.append(row, up, down, remove); list.append(card); counts();
  }
  groupSelect.addEventListener('change', () => {
    group = groups[groupSelect.value] || null;
    message.textContent = '';
    root.querySelectorAll('[data-phase]').forEach(list => { list.replaceChildren(); (group?.[list.dataset.phase] || []).forEach(data => add(list.dataset.phase, data)); });
    const summary = document.getElementById('competition-team-summary'); summary.replaceChildren();
    if (group) {
      summary.append(element('p', {}, `Configured players per gender team: ${group.size || 'sizes differ'}.`));
      const list = element('ul'); group.teams.forEach(team => list.append(element('li', {}, `${team.name}: ${team.size} players`))); summary.append(list);
      if (!group.round_robin.length) message.textContent = 'Use equal, even configured team sizes between 2 and 12 before creating this group.';
    }
    submit.disabled = !group?.round_robin.length; counts();
  });
  root.querySelectorAll('[data-add]').forEach(button => button.addEventListener('click', () => { if (group) add(button.dataset.add); }));
  document.getElementById('competition-form').addEventListener('submit', async event => {
    event.preventDefault(); if (!group || submit.disabled) return;
    const input = { category_ids: group.category_ids };
    root.querySelectorAll('[data-phase]').forEach(list => {
      input[list.dataset.phase] = Array.from(list.children).map(card => {
        const row = {}; card.querySelectorAll('[data-field]').forEach(field => { row[field.dataset.field] = field.value; });
        ['home', 'away'].forEach(side => { row[`${side}_positions`] = Array.from(card.querySelectorAll(`[data-side="${side}"] select`)).slice(0, row.rubber_code === 'singles' ? 1 : 2).map(select => Number(select.value)); });
        return row;
      });
    });
    submit.disabled = true; message.textContent = 'Creating competition…';
    try {
      const response = await fetch(root.dataset.url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': root.querySelector('[name="_token"]').value }, body: JSON.stringify(input) });
      const result = await response.json();
      if (!response.ok) throw new Error(Object.values(result.errors || {}).flat().join(' ') || result.message || 'Unable to create competition.');
      window.location.assign(result.url);
    } catch (error) { message.textContent = error.message; submit.disabled = false; }
  });
})();
