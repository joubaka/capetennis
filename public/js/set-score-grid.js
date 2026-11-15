(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.CTSetScoreGrid = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
  'use strict';
  const bindings = new WeakMap();
  function opponentScore(type, value) {
    if (!/^\d+$/.test(String(value))) return null;
    const score = Number(value);
    if (!Number.isSafeInteger(score)) return null;
    if (/^target[3-6]$/.test(type)) {
      const target = Number(type.slice(6));
      return score < target ? target : null;
    }
    if (type === 'match_tiebreak') return score <= 8 ? 10 : (score === 9 ? 11 : null);
    const target = type === 'full' ? 6 : (type === 'pro8' ? 8 : null);
    return target === null ? null : (score <= target - 2 ? target : (score === target - 1 ? target + 1 : null));
  }
  function bind(root, options = {}) {
    const previous = bindings.get(root);
    if (previous) root.removeEventListener('input', previous.listener);
    const state = {enabled: true, generated: new WeakMap()};
    state.listener = event => {
      const rows = [...root.querySelectorAll(options.rowSelector || '[data-score-row]')];
      const row = rows.find(row => [...row.querySelectorAll('input')].includes(event.target));
      if (!row || row.hidden || event.target.disabled) return;
      const inputs = [...row.querySelectorAll('input')];
      if (inputs.length !== 2) return;
      const source = event.target;
      const other = inputs.find(input => input !== source);
      // Touching a suggestion makes it a manual score, even if its value is unchanged.
      state.generated.delete(source);
      const generated = state.generated.get(other);
      if (generated && String(other.value) !== generated) state.generated.delete(other);
      if (!state.enabled || other.disabled || (String(other.value) !== '' && !state.generated.has(other))) return;
      const suggestion = opponentScore(options.setTypes?.[rows.indexOf(row)], source.value);
      if (suggestion === null) {
        if (state.generated.has(other)) other.value = '';
        state.generated.delete(other);
      } else {
        other.value = String(suggestion);
        state.generated.set(other, String(suggestion));
      }
    };
    root.addEventListener('input', state.listener);
    bindings.set(root, state);
  }
  function setCompletionEnabled(root, enabled) {
    const state = bindings.get(root);
    if (state) state.enabled = enabled;
  }
  function populate(root, options) {
    const maximum = Math.max(1, Math.min(5, Number(options.maxSets) || 1));
    root.querySelector('[data-score-home-label]').textContent = options.names[0];
    root.querySelector('[data-score-away-label]').textContent = options.names[1];
    root.querySelectorAll('[data-score-row]').forEach((row, index) => {
      row.hidden = index >= maximum;
      row.querySelectorAll('input').forEach((input, side) => {
        input.disabled = row.hidden;
        input.value = !row.hidden && options.scores?.[index]?.[side] !== undefined ? options.scores[index][side] : '';
      });
    });
    bind(root, options);
  }
  function read(root) {
    const sets = [];
    let empty = false;
    root.querySelectorAll('[data-score-row]').forEach((row, index) => {
      if (row.hidden) return;
      const inputs = [...row.querySelectorAll('input')];
      if (inputs.some(input => input.disabled)) return;
      const values = inputs.map(input => String(input.value).trim());
      if (values.every(value => value === '')) { empty = true; return; }
      if (values.some(value => value === '')) throw Error(`Complete both scores for set ${index + 1}.`);
      if (empty) throw Error('Enter sets in order, without empty sets between them.');
      const scores = values.map(Number);
      if (values.some(value => !/^\d+$/.test(value)) || scores.some(score => !Number.isSafeInteger(score) || score < 0))
        throw Error('Each set needs two non-negative whole-number scores.');
      sets.push(scores);
    });
    if (!sets.length) throw Error('Enter at least one completed set.');
    return sets;
  }
  return {populate, read, bind, setCompletionEnabled, opponentScore};
});
