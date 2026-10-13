const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/js/team-workspace.js', 'utf8');
const loadSource = source.slice(source.indexOf('  function loadResults()'), source.indexOf('  function selectionStatus('));

function autosaveEditor() {
  let timerId = 0;
  const timers = new Map(), requests = [], messages = [], handlers = {};
  const window = { location: { href: 'http://localhost/workspace', assign(url) { messages.push('navigate:' + new URL(url).pathname + new URL(url).search); } }, clearTimeout(id) { timers.delete(id); }, setTimeout(callback) { timers.set(++timerId, callback); return timerId; } };
  const document = { getElementById: id => id === 'tab-result-rank' ? { dataset: { selectionUrl: '/draft' } } : { getAttribute() { return null; } }, querySelector() { return {}; } };
  const values = { comment: '', selected: true };
  const button = { disabled: false };
  const $ = selector => ({
    on(type, name, callback) { handlers[name] = callback; }, prop() {},
    first() { return this; }, trigger() { handlers['[data-selection-save]'].call(button); },
    map(callback) { return { get: () => selector.includes('excluded') ? [] : [callback.call({ value: selector.includes('format') ? 'singles' : '1' })] }; },
  });
  $.ajax = options => {
    const request = { options };
    requests.push(request);
    return { done(callback) { request.done = callback; return this; }, fail(callback) { request.fail = callback; return this; }, always(callback) { request.always = callback; return this; } };
  };
  const autoSource = source.slice(source.indexOf('  function continueSelectionPreview()'), source.indexOf('  let eventSettingsBaseline;'));
  const saveSource = source.slice(source.indexOf("  $(document).on('click', '[data-selection-save]'"), source.indexOf("  $(document).on('change', '[data-selection-player]'"));
  const editor = new Function('$', 'window', 'document', 'values', 'selectionStatus', `
    let draftFinalized = false, previewPendingUrl, autosaveTimer, autosavePending = false, autosavePaused = false, draftDirty = false, saveRequestBusy = false, draftReady = true, selectionVersion = 3, resultGeneration = 1, savedDraft, draftBaseline = '';
    function updateDraftProgress() {} function selectionGroup() { return '10-boys'; }
    function draftFingerprint() { return JSON.stringify(values); }
    function draftRows() { return [{ dataset: { draftKey: '5' } }]; }
    function draftCards() { return [{ dataset: { resultPlayer: '5' }, querySelector() { return { checked: values.selected }; } }]; }
    document.querySelectorAll = () => [{ dataset: { selectionReason: '5' }, value: values.comment }];
    ${autoSource}
    ${saveSource}
    draftBaseline = draftFingerprint();
    return { edit(comment) { values.comment = comment; draftDirty = draftFingerprint() !== draftBaseline; scheduleDraftAutosave(); }, ready(value) { draftReady = value; if(value) scheduleDraftAutosave(); }, discard: resetDraftAutosave, finalized() { draftFinalized = true; autosavePaused = true; continueSelectionPreview(); }, state() { return { draftDirty, autosavePaused, selectionVersion }; } };
  `)($, window, document, values, message => messages.push(message));
  return { editor, requests, messages, tick() { const callbacks = [...timers.values()]; timers.clear(); callbacks.forEach(callback => callback()); }, success(index) { const request = requests[index]; request.done({ draft: { version: JSON.parse(request.options.data).version + 1 } }); request.always(); }, fail(index, status) { requests[index].fail({ status }); requests[index].always(); }, manual() { handlers['[data-selection-save]'].call(button); }, preview() { handlers['[data-selection-preview]'].call({ dataset: { selectionPreview: '/preview' } }); } };
}

test('rapid comments debounce into one private draft save', () => {
  const f = autosaveEditor();
  f.editor.edit('A'); f.editor.edit('AB'); f.editor.edit('ABC'); f.tick();
  assert.equal(f.requests.length, 1);
  assert.equal(JSON.parse(f.requests[0].options.data).reasons['5'], 'ABC');
  f.success(0); f.tick(); assert.equal(f.requests.length, 1);
});

test('inflight edits save serially afterward with latest server version', () => {
  const f = autosaveEditor();
  f.editor.edit('First'); f.tick(); f.editor.edit('Latest'); f.tick();
  assert.equal(f.requests.length, 1);
  f.success(0); f.tick(); assert.equal(f.requests.length, 2);
  const payload = JSON.parse(f.requests[1].options.data);
  assert.equal(payload.version, 4); assert.equal(payload.reasons['5'], 'Latest');
});

test('failed and conflicting autosaves keep edits and stop automatic retries', () => {
  for (const status of [500, 409]) {
    const f = autosaveEditor(); f.editor.edit('Keep'); f.tick(); f.fail(0, status); f.tick();
    assert.equal(f.requests.length, 1); assert.equal(f.editor.state().draftDirty, true); assert.equal(f.editor.state().autosavePaused, true);
    f.editor.edit('Keep newer'); f.tick(); assert.equal(f.requests.length, 1);
    assert.match(f.messages.at(-1), /paused.*edits are kept/);
  }
});

test('metadata readiness defers autosave and discarded setup cancels pending save', () => {
  const f = autosaveEditor(); f.editor.ready(false); f.editor.edit('Waiting'); f.tick(); assert.equal(f.requests.length, 0);
  f.editor.ready(true); f.tick(); assert.equal(f.requests.length, 1); f.success(0);
  f.editor.edit('Discard'); f.editor.discard(); f.tick(); assert.equal(f.requests.length, 1);
});

test('clean Preview only navigates and never overwrites stored draft choices', () => {
  const f = autosaveEditor(); f.preview();
  assert.equal(f.requests.length, 0);
  assert.ok(f.messages.includes('navigate:/preview?age=10&gender=boys'));
});

test('Preview flushes dirty choices and waits for newer inflight edits before navigating', () => {
  const f = autosaveEditor(); f.editor.edit('First'); f.preview();
  assert.equal(f.requests.length, 1); assert.ok(!f.messages.includes('navigate:/preview?age=10&gender=boys'));
  f.editor.edit('Latest'); f.success(0);
  assert.equal(f.requests.length, 2); assert.ok(!f.messages.includes('navigate:/preview?age=10&gender=boys'));
  assert.equal(JSON.parse(f.requests[1].options.data).reasons['5'], 'Latest');
  f.success(1); assert.ok(f.messages.includes('navigate:/preview?age=10&gender=boys'));
});

test('Preview never navigates after a failed or conflicting save', () => {
  for (const status of [500, 409]) {
    const f = autosaveEditor(); f.editor.edit('Keep'); f.preview(); f.fail(0, status); f.tick();
    assert.ok(!f.messages.includes('navigate:/preview?age=10&gender=boys'));
    assert.equal(f.editor.state().draftDirty, true);
  }
});

test('dirty Preview waits for metadata readiness and saves before navigation', () => {
  const f = autosaveEditor(); f.editor.ready(false); f.editor.edit('Waiting for version'); f.preview(); f.tick();
  assert.equal(f.requests.length, 0); assert.ok(!f.messages.includes('navigate:/preview?age=10&gender=boys'));
  f.editor.ready(true); f.tick(); assert.equal(f.requests.length, 1);
  f.success(0); assert.ok(f.messages.includes('navigate:/preview?age=10&gender=boys'));
});

test('finalized metadata resolves pending Preview without saving client edits', () => {
  const f = autosaveEditor(); f.editor.ready(false); f.editor.edit('Unsaveable edit'); f.preview();
  f.editor.finalized();
  assert.equal(f.requests.length, 0);
  assert.ok(f.messages.includes('navigate:/preview?age=10&gender=boys'));
});

function readinessEditor() {
  const properties = {}, messages = [];
  let done, fail;
  const $ = selector => ({ prop(name, value) { properties[selector + ':' + name] = value; } });
  $.getJSON = () => ({ done(callback) { done = callback; return this; }, fail(callback) { fail = callback; return this; } });
  const document = { querySelector() { return null; }, getElementById: () => ({ dataset: { selectionUrl: '/draft' } }) };
  const fetchSource = source.slice(source.indexOf('  function fetchDraft('), source.indexOf("  $(document).on('click', '[data-selection-retry]'"));
  const editor = new Function('$', 'document', 'selectionStatus', `
    let draftFinalized = false, autosavePaused = false, autosavePending = false, autosaveTimer, draftReady = false, selectionVersion = 0, editRevision = 1, resultGeneration = 1, draftFetchSequence = 0, saveRequestBusy = false, savedDraft;
    const choices = ['player-5', 'player-6'];
    const window = { clearTimeout() {}, Sortable: { get() { return null; } } }; function confirmDraftDiscard() { return true; } function selectionGroup() { return '10-boys'; } function updateDraftProgress() {} function scheduleDraftAutosave() {} function continueSelectionPreview() {}
    ${fetchSource}
    return { fetch: fetchDraft, state() { return { draftReady, draftFinalized, autosavePaused, selectionVersion, choices }; }, edit() { editRevision++; choices.reverse(); }, saved(version) { selectionVersion = version; draftReady = true; } };
  `)($, document, message => messages.push(message));
  return { editor, properties, messages, success(response) { done(response); }, failure(response = {}) { fail(response); } };
}

test('existing draft check enables saving with its version while keeping edits made during lookup', () => {
  const { editor, properties, success } = readinessEditor();
  editor.fetch(false); editor.edit(); success({ draft: { version: 7, selected_keys: ['other-player'] } });
  assert.equal(editor.state().draftReady, true);
  assert.equal(editor.state().selectionVersion, 7);
  assert.deepEqual(editor.state().choices, ['player-6', 'player-5']);
  assert.equal(properties['[data-selection-save]:disabled'], false);
});

test('finalized draft lookup disables changes and pauses autosave while Preview remains available', () => {
  const { editor, properties, success } = readinessEditor();
  editor.fetch(false); success({ finalized: true, draft: { version: 1 } });
  assert.equal(editor.state().draftFinalized, true);
  assert.equal(editor.state().draftReady, false);
  assert.equal(editor.state().autosavePaused, true);
  assert.equal(properties['[data-selection-preview]:disabled'], false);
});

test('late draft lookup cannot replace a version updated by saving', () => {
  const { editor, success } = readinessEditor();
  editor.fetch(false); editor.saved(8); success({ draft: { version: 7 } });
  assert.equal(editor.state().selectionVersion, 8);
  assert.equal(editor.state().draftReady, true);
});

test('Load draft with no stored draft enables saving current choices at version zero', () => {
  const { editor, properties, success } = readinessEditor();
  editor.fetch(true); success({ draft: null });
  assert.equal(editor.state().draftReady, true);
  assert.equal(editor.state().selectionVersion, 0);
  assert.equal(properties['[data-selection-save]:disabled'], false);
});

test('failed draft lookup exposes retry and preserves choices; retry establishes safe version', () => {
  const { editor, properties, messages, success, failure } = readinessEditor();
  editor.fetch(false); failure();
  assert.equal(editor.state().draftReady, false);
  assert.equal(properties['[data-selection-retry]:hidden'], false);
  assert.match(messages.at(-1), /Retry.*current choices are kept/);
  editor.fetch(false); success({ draft: { version: 4 } });
  assert.equal(editor.state().selectionVersion, 4);
  assert.equal(properties['[data-selection-retry]:hidden'], true);
  assert.equal(properties['[data-selection-save]:disabled'], false);
  assert.deepEqual(editor.state().choices, ['player-5', 'player-6']);
});

function requestFor(values) {
  let request;
  const category = { value: '10-boys', dataset: { event_id: '241', name: 'u/10 Boys' } };
  const target = { setAttribute() {} };
  const document = {
    querySelector: selector => selector === '.category-radio:checked' ? category : null,
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
    let resultGeneration = 0, draftReady = false, selectionVersion = 0, saveRequestBusy = false, draftDirty, renderedSetup;
    function setupState() { return []; } function resetDraftAutosave() {}
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

test('manual order renumbers candidates, bounds move controls and opens changed-player comments', () => {
  const changedSource = source.slice(source.indexOf('  function draftOrderChanged('), source.indexOf('  function buildDraftOrder('));
  const disclosures = new Set();
  const cards = ['1', '2', '3'].map(key => ({ dataset: { resultPlayer: key }, querySelector: () => ({ setAttribute: () => disclosures.add(key) }) }));
  const rows = ['2', '1', '3'].map(key => {
    const elements = { '[data-draft-position]': {}, '[data-draft-up]': {}, '[data-draft-down]': {}, '[data-draft-changed]': {} };
    return { dataset: { draftKey: key }, elements, querySelector: selector => elements[selector], classList: { toggle(name, value) { this[name] = value; } } };
  });
  let status;
  new Function('draftCards', 'draftRows', 'selectionStatus', `function markDraftChanged() {} function updateDraftProgress() {} function scheduleDraftAutosave() {} function continueSelectionPreview() {} ${changedSource}; draftOrderChanged();`)(() => cards, () => rows, value => { status = value; });
  assert.deepEqual(rows.map(row => row.elements['[data-draft-position]'].textContent), [1, 2, 3]);
  assert.equal(rows[0].elements['[data-draft-up]'].disabled, true);
  assert.equal(rows[2].elements['[data-draft-down]'].disabled, true);
  assert.deepEqual([...disclosures], ['2', '1']);
  assert.equal(rows[2].elements['[data-draft-changed]'].hidden, true);
  assert.match(status, /Save|save/);
});

test('reordered and selection-departure players always have optional comments without missing-comment warnings', () => {
  const progressSource = source.slice(source.indexOf('  function updateDraftProgress()'), source.indexOf('  function updateDraftChrome()'));
  const text = {};
  const rows = ['6', '5'].map(key => {
    const field = { value: key === '5' ? 'Moved down' : '', required: true, closest: () => ({}), setAttribute(name, value) { this[name] = value; } };
    const elements = { '[data-draft-inline-reason]': field, '[data-draft-reason-label]': {}, '[data-draft-changed]': {} };
    return { dataset: { draftKey: key }, elements, querySelector: selector => elements[selector] };
  });
  const cards = ['5', '6'].map(key => ({ dataset: { resultPlayer: key, playerName: 'Player ' + key, playerSuggested: key === '5' ? '1' : '0', playerCutoff: '1' }, querySelector: () => ({ checked: key === '6', value: '' }) }));
  new Function('$', 'draftRows', 'draftCards', `let saveRequestBusy = false, draftDirty = true, draftReady = true, draftFinalized = false, autosavePaused = false, selectionVersion = 0; function updateDraftChrome() {} ${progressSource}; updateDraftProgress();`)(selector => ({ text(value) { text[selector] = value; } }), () => rows, () => cards);
  rows.forEach(row => {
    assert.equal(row.elements['[data-draft-inline-reason]'].required, false);
    assert.equal(row.elements['[data-draft-reason-label]'].textContent, 'Comment (optional)');
  });
  assert.equal(rows[0].elements['[data-draft-changed]'].textContent, '');
  assert.equal(rows[1].elements['[data-draft-changed]'].textContent, 'Comment recorded');
  assert.equal(text['[data-draft-reasons]'], 'Comments are optional.');
});

function draftEditor() {
  const inputs = { '1': { checked: true, value: '' }, '2': { checked: true, value: '' } };
  const rows = ['1', '2'].map(key => ({ dataset: { draftKey: key } }));
  const cards = rows.map(row => ({ dataset: { resultPlayer: row.dataset.draftKey }, querySelector: selector => selector === '[data-selection-player]' ? inputs[row.dataset.draftKey] : inputs[row.dataset.draftKey] }));
  let regions = ['1'];
  const fingerprintFunctions = source.slice(source.indexOf('  function draftFingerprint()'), source.indexOf('  function confirmDraftDiscard()'));
  const saveLine = source.split('\n').find(line => line.includes('draftBaseline = submittedFingerprint;'));
  const callback = saveLine.slice(saveLine.indexOf('.done(') + 6, saveLine.lastIndexOf(')'));
  const editor = new Function('draftRows', 'draftCards', 'setupState', `
    let draftDirty = false, editRevision = 0, draftBaseline, submittedFingerprint;
    let group = '10-boys', generation = 1, resultGeneration = 1, savedDraft, selectionVersion = 1;
    function updateDraftProgress() {} function scheduleDraftAutosave() {} function continueSelectionPreview() {} function selectionStatus() {} function selectionGroup() { return group; }
    ${fingerprintFunctions}
    draftBaseline = draftFingerprint();
    return { change: markDraftChanged, beginSave() { submittedFingerprint = draftFingerprint(); }, completeSave: ${callback}, state() { return { draftDirty, selectionVersion, editRevision }; } };
  `)(() => rows, () => cards, () => regions);
  return { editor, inputs, rows, changeSetup(value) { regions = value; editor.change(); } };
}

test('dirty state compares complete setup and draft contents, so reverted edits are clean', () => {
  const { editor, inputs, changeSetup } = draftEditor();
  inputs['1'].value = 'Review'; editor.change(); assert.equal(editor.state().draftDirty, true);
  inputs['1'].value = ''; editor.change(); assert.equal(editor.state().draftDirty, false);
  changeSetup(['2']); assert.equal(editor.state().draftDirty, true);
  changeSetup(['1']); assert.equal(editor.state().draftDirty, false);
});

test('an asynchronous save updates version while retaining edits made after submission', () => {
  const { editor, inputs } = draftEditor();
  inputs['1'].value = 'Submitted reason'; editor.change(); editor.beginSave();
  inputs['2'].checked = false; editor.change();
  editor.completeSave({ draft: { version: 2 } });
  assert.equal(editor.state().selectionVersion, 2);
  assert.equal(editor.state().draftDirty, true);
  inputs['2'].checked = true; editor.change(); assert.equal(editor.state().draftDirty, false);
});

test('cancelling a setup discard restores the rendered filters without requesting results', () => {
  const handlerLine = source.split('\n').find(line => line.includes("'.team-admin-workspace .category-radio, [data-result-region]"));
  const callback = handlerLine.slice(handlerLine.indexOf('function ()'), handlerLine.lastIndexOf(')'));
  let restored, loaded = 0;
  new Function('confirmDraftDiscard', 'restoreSetup', 'renderedSetup', 'loadResults', 'saveUrl', `(${callback})();`)(() => false, state => { restored = state; }, ['saved-filters'], () => { loaded++; }, () => {});
  assert.deepEqual(restored, ['saved-filters']); assert.equal(loaded, 0);
});
