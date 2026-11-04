const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/dashboard.blade.php', 'utf8');

const escape = value => String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#39;');

function tables() {
  const configs = {};
  const $ = selector => {
    if (selector === '<div>') return { text(value) { this.value = value; return this; }, html() { return escape(this.value); } };
    return { length: 1, DataTable(config) { configs[selector] = config; } };
  };
  $.fn = { dataTable: { render: { text: () => ({display: escape}) } } };
  const start = source.indexOf('  var dtSeries');
  const end = source.indexOf('  var dtActivity', start);
  const script = source.slice(start, end)
    .replace(/@json\(route\('series.settings'.*?\)\)/g, '"/backend/ranking/series/__SERIES__/settings"')
    .replace(/@json\(route\('series.show'.*?\)\)/g, '"/backend/series/__SERIES__"');
  vm.runInNewContext(script, { $, APP_URL: 'https://example.test', encodeURIComponent });
  return configs;
}

test('dashboard directories send server pagination, ordering and search rather than truncating to 25 client rows', () => {
  const configs = tables();
  for (const selector of ['.datatable-users', '.datatable-players']) {
    const config = configs[selector];
    assert.equal(config.serverSide, true);
    assert.equal(config.processing, true);
    assert.equal(config.searchDelay, 350);
    assert.equal(config.pageLength, 25);
    assert.deepEqual(Array.from(config.lengthMenu), [10, 25, 50, 100]);
    assert.deepEqual(Array.from(config.order[0]), [0, 'desc']);
  }
  assert.match(configs['.datatable-users'].ajax, /\/backend\/user\/data$/);
  const userDefs = configs['.datatable-users'].columnDefs;
  for (const index of [3, 4]) assert.equal(userDefs.find(def => def.targets === index).orderable, false);
  const playerActions = configs['.datatable-players'].columnDefs.find(def => Array.isArray(def.targets));
  assert.deepEqual(Array.from(playerActions.targets), [2, 3, 4]);
  assert.equal(playerActions.orderable, false);
});

test('dashboard names, emails and role labels render untrusted markup as text', () => {
  const configs = tables();
  const payload = '<img src=x onerror=alert(1)>&"';
  const user = configs['.datatable-users'];
  assert.equal(user.columns[1].render.display(payload), escape(payload));
  assert.equal(user.columns[2].render.display(payload), escape(payload));
  const roles = user.columnDefs.find(def => def.targets === 3).render(null, 'display', {roles: [{name: payload}]});
  assert.equal(roles.includes('<img'), false);
  assert.ok(roles.includes('&lt;img'));
  const playerName = configs['.datatable-players'].columnDefs.find(def => def.targets === 1)
    .render(null, 'display', {name: payload, surname: '<svg onload=alert(1)>'});
  assert.equal(playerName.includes('<'), false);
});

test('ranking controls navigate to canonical reviewed publication and settings pages without GET mutation', () => {
  const config = tables()['.datatable-series'];
  const render = index => config.columnDefs.find(def => def.targets === index).render;
  assert.match(render(2)(null, 'display', {id: 42}), /href="\/backend\/ranking\/series\/42\/settings"/);
  const publication = render(3)(null, 'display', {id: 42, leaderboard_published: false});
  assert.match(publication, /Not Published/);
  assert.match(publication, /href="\/backend\/series\/42"/);
  assert.match(publication, /Review publication/);
  assert.equal(config.initComplete, undefined);
  assert.equal(source.includes('/backend/series/publishLeaderboard/'), false);
});
