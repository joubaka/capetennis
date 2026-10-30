const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/js/draw-workspace-navigation.js', 'utf8');
function control(dataset = {}, textContent = '') {
  const attrs = {}, listeners = {}, classes = new Set();
  const status = {textContent: '', classList: {toggle() {}}};
  return {dataset, textContent, attrs, listeners, disabled: false,
    addEventListener(name, callback) {listeners[name] = callback;},
    setAttribute(name, value) {attrs[name] = value;},
    classList: {toggle(name, enabled) {enabled ? classes.add(name) : classes.delete(name);}},
    closest() {return {querySelector: () => status};}, status,
  };
}
function page({draws = [], schedules = [], proxies = [], links = [], drawBadges = [], scheduleBadges = [], editor = null, fetch = async () => {throw Error('Unexpected request');}, hash = '#schedule'} = {}) {
  const map = {'[data-workspace-publish-draw]': draws, '[data-workspace-publish-schedule]': schedules,
    '[data-workspace-publish-draw-proxy]': proxies, '[data-workspace-switch]': links,
    '[data-workspace-draw-status]': drawBadges, '[data-workspace-schedule-status]': scheduleBadges};
  const context = {URLSearchParams, location: {search: '', hash}, document: {
    querySelectorAll: selector => map[selector] || [],
    querySelector: selector => selector === 'meta[name="csrf-token"]' ? {content: 'csrf'} : null,
    getElementById: id => id === 'fm-publish' ? editor : null,
  }, window: {confirm: () => true}, fetch, navigator: {}, console};
  vm.runInNewContext(source, context);
  return context;
}
test('switch draw links keep recognized workspace context only', () => {
  for (const [hash, expected] of [['#schedule', 'schedule'], ['#matrix', 'matrix'], ['#unknown', undefined]]) {
    const links = [control(), control()]; page({links, hash});
    links.forEach(link => link.listeners.click());
    assert.equal(links[0].hash, expected); assert.equal(links[1].hash, expected);
  }
});
test('flexible toolbar delegates to the revision-aware editor and respects disabled action', () => {
  let clicks = 0;
  const proxy = control(), editor = {disabled: false, hidden: false, click() {clicks++;}};
  page({proxies: [proxy], editor}); proxy.listeners.click();
  assert.equal(clicks, 1); editor.disabled = true; proxy.listeners.click(); assert.equal(clicks, 1);
});
test('standalone flexible publication sends rendered revision and keeps schedule separate', async () => {
  const draw = control({workspacePublishDraw: '/draw/19/flexible/publish', flexibleRevision: '2', published: '0'}, 'Publish draw');
  const schedule = control({workspacePublishSchedule: '/draw/19/schedule'}, 'Publish schedule');
  let request;
  const drawBadge = control({}, 'Draw: unpublished'), scheduleBadge = control({}, 'Schedule: published');
  page({draws: [draw], schedules: [schedule], drawBadges: [drawBadge], scheduleBadges: [scheduleBadge], fetch: async (url, options) => {
    request = {url, body: JSON.parse(options.body)};
    return {ok: true, json: async () => ({published: true, revision: 3})};
  }});
  await draw.listeners.click();
  assert.deepEqual(request, {url: '/draw/19/flexible/publish', body: {revision: 2, published: true}});
  assert.equal(draw.dataset.flexibleRevision, '3'); assert.equal(draw.attrs['aria-pressed'], 'true');
  assert.equal(draw.textContent, 'Unpublish draw'); assert.equal(schedule.textContent, 'Publish schedule');
  assert.equal(drawBadge.textContent, 'Draw: published'); assert.equal(scheduleBadge.textContent, 'Schedule: published');
});
test('stale publication does not retry with a fresh revision or change displayed state', async () => {
  const draw = control({workspacePublishDraw: '/flexible/publish', flexibleRevision: '2', published: '0'}, 'Publish draw');
  let calls = 0;
  page({draws: [draw], fetch: async () => {calls++; return {ok: false, status: 409, json: async () => ({message: 'The draw changed. Reload first.'})};}});
  await draw.listeners.click();
  assert.equal(calls, 1); assert.equal(draw.dataset.flexibleRevision, '2'); assert.equal(draw.dataset.published, '0');
  assert.equal(draw.textContent, 'Publish draw'); assert.equal(draw.disabled, false); assert.match(draw.status.textContent, /Reload first/);
});
test('schedule publication synchronizes duplicate controls and blocks duplicate pending requests', async () => {
  const first = control({workspacePublishSchedule: '/draw/19/schedule'}, 'Publish schedule');
  const second = control({workspacePublishSchedule: '/draw/19/schedule'}, 'Publish schedule');
  const foreign = control({workspacePublishSchedule: '/draw/20/schedule'}, 'Publish schedule');
  let resolve, calls = 0;
  const drawBadge = control({}, 'Draw: unpublished'), scheduleBadge = control({}, 'Schedule: unpublished');
  page({schedules: [first, second, foreign], drawBadges: [drawBadge], scheduleBadges: [scheduleBadge], fetch: () => {calls++; return new Promise(done => {resolve = done;});}});
  const pending = first.listeners.click();
  assert.equal(first.disabled, true); assert.equal(second.disabled, true); assert.equal(foreign.disabled, false);
  await second.listeners.click(); assert.equal(calls, 1);
  resolve({ok: true, json: async () => ({oop_published: true, preview_only: true})}); await pending;
  assert.equal(first.textContent, 'Unpublish schedule'); assert.equal(second.attrs['aria-pressed'], 'true');
  assert.equal(foreign.textContent, 'Publish schedule'); assert.equal(first.disabled, false);
  assert.equal(drawBadge.textContent, 'Draw: unpublished'); assert.equal(scheduleBadge.textContent, 'Schedule: published');
});
test('flexible editor state keeps the visible draw toggle synchronized', () => {
  const editorSource = fs.readFileSync('public/js/flexible-monrad.js', 'utf8');
  const start = editorSource.indexOf("    document.querySelectorAll('[data-workspace-publish-draw-proxy]').forEach");
  const end = editorSource.indexOf('\n    $(\'fm-reopen\')', start);
  assert.ok(start > 0 && end > start);
  const button = control();
  const context = {document: {querySelectorAll: () => [button]}, state: {published: true, generated: true, locked: false}, config: {canPublish: true}, busy: false};
  vm.runInNewContext(editorSource.slice(start, end), context);
  assert.equal(button.textContent, 'Unpublish draw'); assert.equal(button.attrs['aria-pressed'], 'true'); assert.equal(button.disabled, false);
  context.state.locked = true; vm.runInNewContext(editorSource.slice(start, end), context); assert.equal(button.disabled, true);
});
