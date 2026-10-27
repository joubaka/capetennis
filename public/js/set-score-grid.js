(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.CTSetScoreGrid = api;
})(typeof window !== 'undefined' ? window : globalThis, function () {
  'use strict';
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
  return {populate, read};
});
