const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/js/team-workspace.js', 'utf8');
const loadSource = source.slice(source.indexOf('  function loadResults()'), source.indexOf('  function selectionStatus('));

function requestFor(values) {
  let request;
  const category = { value: '10-boys', dataset: { event_id: '241', name: 'u/10 Boys' } };
  const target = { setAttribute() {} };
  const document = {
    querySelector: () => category,
    getElementById: id => id === 'category-table' ? target : { dataset: { resultUrl: '/results' } },
  };
  const $ = selector => ({
    prop() {}, text() {},
    map(callback) { return { get: () => (values[selector] || []).map(value => callback.call({ value })) }; },
  });
  $.ajax = options => {
    request = options;
    const chain = { done() { return chain; }, fail() { return chain; }, always() { return chain; } };
    return chain;
  };
  new Function('$', 'document', `let resultRequest, applyingDraft, editingGroup, savedDraft;
    let resultGeneration = 0, draftReady = false, selectionVersion = 0, saveRequestBusy = false;
    ${loadSource}; loadResults();`)($, document);
  return request;
}

test('initial setup sends empty exclusions as a JSON array', () => {
  const request = requestFor({ '[data-result-region]:checked': ['120'], '[data-result-format]:checked': ['singles'] });
  assert.equal(request.type, 'POST');
  assert.equal(request.contentType, 'application/json');
  assert.deepEqual(JSON.parse(request.data), {
    event_id: '241', result_group: '10-boys', regions: ['120'], formats: ['singles'], excluded_result_region_ids: [],
  });
});

test('setup preserves populated exclusions and deliberate empty candidate filters', () => {
  const request = requestFor({ '[data-result-excluded-region]:checked': ['121', '122'] });
  const payload = JSON.parse(request.data);
  assert.deepEqual(payload.regions, []);
  assert.deepEqual(payload.formats, []);
  assert.deepEqual(payload.excluded_result_region_ids, ['121', '122']);
});
