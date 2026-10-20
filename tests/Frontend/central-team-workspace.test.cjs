const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/js/team-workspace.js', 'utf8');
function extract(name) {
  const start = source.indexOf(`  function ${name}(`);
  assert.notEqual(start, -1);
  const next = source.indexOf('\n  function ', start + 4);
  return source.slice(start, next < 0 ? undefined : next);
}
function harness({ endpoint = '/selection?workspace_fragment=1', managed = '1', mode = 'order' } = {}) {
  const state = { requests: 0, initialized: 0, syncs: 0, region: '127', fail: false, root: false };
  const team = { value: '841', selectedOptions: [{ dataset: { managed } }], options: [] };
  const context = { dataset: { selectionUrl: endpoint, rosterUrl: 'http://localhost/backend/event/242/teams' } };
  const host = { children: [], parent: null, hidden: false, querySelector() { return state.root ? {} : null; }, setAttribute() {}, removeAttribute() {},
    set innerHTML(value) { state.root = value.includes('data-embedded-teams-selection'); },
    replaceChildren() { state.root = false; this.children = []; }, append(node) { this.children.push(node); } };
  const panels = Object.fromEntries(['players', 'order', 'invitations', 'regions', 'result-rank'].map(name => ['tab-' + name, { classList: { contains: () => name === mode }, append(node) { node.parent = name; } }]));
  const window = { location: { href: 'http://localhost/backend/team-selection/events/242?selected_team=841&workspace=invitations' } };
  const document = {
    querySelector(selector) { return selector === '[data-workspace-team]' ? team : selector === '[data-team-context]' ? context : selector === '[data-central-selection-host]' ? host : null; },
    querySelectorAll() { return []; }, getElementById(id) { return panels[id] || null; },
    head: { append(node) { if (node.tag === 'script') { state.initialized++; window.teamSelectionWorkspaceInitialized = true; window.syncTeamSelectionContext = () => state.syncs++; } } },
    createElement(tag) {
      const node = { tag, children: [], append(child) { this.children.push(child); }, remove() {}, addEventListener(name, callback) { this[name] = callback; }, setAttribute() {} };
      if (tag === 'template') node.content = { querySelector(selector) { return selector === 'script' ? { src: '', textContent: 'trusted server initializer' } : { tag: 'style' }; } };
      return node;
    },
  };
  const fetch = async () => { state.requests++; await Promise.resolve(); return { ok: !state.fail, status: state.fail ? 403 : 200, json: async () => ({ html: '<div data-embedded-teams-selection></div>', style: '<style></style>', script: '<script></script>' }) }; };
  const $ = () => ({ val: () => state.region });
  const sandbox = { window, document, fetch, $, URL, Promise };
  vm.runInNewContext('let selectionRequest;\n' + ['selectedTeam', 'activeMode', 'syncSelectionContext', 'selectionUsesOrder', 'loadSelection', 'syncTeamOptions', 'workspaceUrl'].map(extract).join('\n'), sandbox);
  return { ...sandbox, state, team, host, context };
}

test('regional managed teams keep canonical ordering without invitation capability', () => {
  const h = harness({ endpoint: '' });
  assert.equal(h.selectionUsesOrder(), false);
  assert.equal(h.state.requests, 0);
});

test('lazy invitations share one request and one initializer while preserving selected managed team order', async () => {
  const h = harness();
  const first = h.loadSelection();
  const second = h.loadSelection();
  assert.equal(first, second);
  await first;
  assert.equal(h.state.requests, 1);
  assert.equal(h.state.initialized, 1);
  assert.equal(h.host.parent, 'order');
  assert.equal(h.state.root, true);
  await h.loadSelection();
  assert.equal(h.state.requests, 1);
  assert.equal(h.state.initialized, 1);
  assert.ok(h.state.syncs >= 2);
});

test('failed invitation authorization shows retry without running scripts and a later retry can recover', async () => {
  const h = harness({ mode: 'invitations' });
  h.state.fail = true;
  await h.loadSelection();
  assert.equal(h.state.initialized, 0);
  assert.equal(h.state.root, false);
  assert.match(h.host.children[0].textContent, /no longer have access/);
  assert.equal(h.host.children[0].children[0].textContent, 'Retry');
  h.state.fail = false;
  await h.loadSelection();
  assert.equal(h.state.requests, 2);
  assert.equal(h.state.initialized, 1);
  assert.equal(h.host.parent, 'invitations');
});

test('team context disables other region options and clears an incompatible team selection', () => {
  const h = harness();
  h.team.options = [{ value: '', dataset: {} }, { value: '841', dataset: { region: '127' } }, { value: '900', dataset: { region: '128' } }];
  h.team.selectedOptions = [h.team.options[2]];
  h.team.value = '900';
  h.syncTeamOptions();
  assert.equal(h.team.options[0].disabled, false);
  assert.equal(h.team.options[1].disabled, false);
  assert.equal(h.team.options[2].disabled, true);
  assert.equal(h.team.value, '');
});

test('legacy invitation URL roster reads use canonical Teams endpoint and preserve team context', () => {
  const h = harness();
  const url = new URL(h.workspaceUrl({ roster_region: '127', panel: 'players' }));
  assert.equal(url.pathname, '/backend/event/242/teams');
  assert.equal(url.searchParams.get('selected_team'), '841');
  assert.equal(url.searchParams.get('panel'), 'players');
  assert.equal(url.searchParams.has('workspace'), false);
});
