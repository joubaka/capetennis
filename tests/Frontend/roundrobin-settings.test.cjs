const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');

function editor(counts = [4, 3], config = [{name: 'Main', slug: 'main', enabled: true, size: 4, positions: [4]}, {name: 'Unused', slug: 'unused', enabled: false, size: 4, positions: []}], roundRobinOnly = false) {
  const html = new Map(), text = new Map(), handlers = new Map(), data = new Map(), requests = [], values = new Map([['#match-duration-minutes', '20'], ['#changeover-minutes', '5']]);
  const document = {addEventListener() {}};
  function $(selector) {
    const node = {
      length: 0,
      on(event, target, fn) { handlers.set(typeof target === 'string' ? target : selector, fn || target); return this; },
      text(value) { if (value === undefined) return text.get(selector) || ''; text.set(selector, String(value)); return this; },
      html(value) { if (value === undefined) return selector === '<span>' ? (text.get(selector) || '').replaceAll('&', '&amp;').replaceAll('<', '&lt;') : html.get(selector); html.set(selector, value); return this; },
      val(value) { if (value === undefined) return values.get(selector) || ''; values.set(selector, String(value)); return this; }, prop(key, value) { return value === undefined ? false : this; }, each() { return this; }, ready() { return this; },
      remove() { html.delete(selector); return this; }, after(value) { html.set(selector, value); return this; },
      find() { return this; }, data(key, value) { if (value === undefined) return data.get(key); data.set(key, value); return this; }
    };
    return node;
  }
  $.ajax = request => requests.push(request);
  const window = {RR_GROUPS: counts.map((count, index) => ({name: String.fromCharCode(65 + index), registrations: Array.from({length: count}, (_, id) => ({id}))}))};
  const context = vm.createContext({window, document, jQuery: $, console: {log() {}, warn() {}},
    Swal: {fire: async () => ({isConfirmed: true})}, toastr: {success() {}, warning() {}}, APP_URL: '', DRAW_ID: 1});
  let source = fs.readFileSync('resources/views/backend/draw/roundrobin/setup-scripts.blade.php', 'utf8')
    .replace(/<\/?script>/g, '')
    .replace(/@unless\(\$roundRobinOnly\)([\s\S]*?)@endunless/g, (_, body) => roundRobinOnly ? '' : body)
    .replace('@json($competitionRulesEditable)', 'true').replace('@json(csrf_token())', '"token"')
    .replace('@json($playoffConfig ?? [])', JSON.stringify(config))
    .replaceAll('{{ $currentBoxes ?? 4 }}', String(counts.length))
    .replace("{{ $savedPresetKey ?? '' }}", '').replace("{{ $draw->drawType->name ?? 'Unknown' }}", 'Singles');
  // Expose the actual editor functions without changing the application API.
  if (!roundRobinOnly) source = source.replace('})(jQuery);', 'window.testEditor = {renderPlayoffTable, validateDrawConfiguration, updateFlowPreview, draft: () => playoffConfig}; })(jQuery);');
  vm.runInContext(source, context);
  return {window, html, text, handlers, data, requests, values, render() {window.testEditor.renderPlayoffTable();}};
}

test('previews use saved qualifiers in uneven groups and hide disabled rows and unused positions', () => {
  const e = editor(); e.render();
  const table = e.html.get('#playoff-config-body');
  assert.match(table, /1 players/);
  assert.match(table, /data-label="Group positions"/);
  assert.match(table, /aria-label="Bracket size for Main"/);
  assert.match(table, /aria-label="Remove Main"/);
  assert.doesNotMatch(table, /value="Unused"/);
  assert.doesNotMatch(table, /data-pos="5"/);
  assert.match(e.text.get('#rr-show-disabled-label'), /1/);
  e.handlers.get('#rr-show-disabled').call({checked: true});
  assert.match(e.html.get('#playoff-config-body'), /value="Unused"/);
});

test('preset load preserves high positions, enabled state and repeated group order', async () => {
  const e = editor([0]);
  const preset = [{name: 'Future', slug: 'future', size: 4, enabled: true, positions: [10, 20], group_order: ['A', 'A', 'A', 'A']}];
  e.data.set('config', preset); e.data.set('max-positions', 20); e.data.set('groups', 1);
  e.handlers.get('#btn-load-preset')();
  await new Promise(resolve => setImmediate(resolve));
  assert.deepEqual(JSON.parse(JSON.stringify(e.window.testEditor.draft())), preset);
  assert.match(e.html.get('#playoff-config-body'), /data-pos="20"/);
  assert.match(e.html.get('#playoff-config-body'), /Future positions preserved/);
  e.handlers.get('#drawSettingsForm')({preventDefault() {}});
  assert.equal(e.requests.length, 1);
  assert.deepEqual(JSON.parse(e.requests[0].data).playoff_config, preset);
});

test('acknowledged group refresh updates warnings, qualifiers and overview without altering playoff draft', () => {
  const e = editor([0, 0]);
  e.window.testEditor.validateDrawConfiguration();
  assert.match(e.html.get('#settings-pane details.card.border-info'), /No players assigned/);
  e.window.RR_SAVED_GROUPS = [4, 4].map((count, index) => ({name: ['A', 'B'][index], registrations: Array.from({length: count}, (_, id) => ({id}))}));
  e.window.RRSettingsGroupsChanged();
  assert.equal(e.text.get('#rr-overview-player-count'), '8');
  assert.equal(e.text.get('#rr-overview-match-count'), '12');
  assert.match(e.html.get('#playoff-config-body'), /2 players/);
  assert.equal(e.html.get('#rr-settings-validation'), undefined);
  assert.equal(e.window.testEditor.draft()[0].positions[0], 4);
});

test('round-robin-only saved refresh updates the shared overview without playoff code', () => {
  const e = editor([0, 0], [], true);
  e.window.RR_SAVED_GROUPS = [4, 4].map((count, index) => ({name: ['A', 'B'][index], registrations: Array.from({length: count}, (_, id) => ({id}))}));
  e.window.RRSettingsGroupsChanged();
  assert.equal(e.text.get('#rr-overview-player-count'), '8');
  assert.equal(e.text.get('#rr-overview-group-count'), '2');
  assert.equal(e.text.get('#rr-overview-match-count'), '12');
  assert.match(e.html.get('#rr-overview-group-distribution'), /Group A: 4 players/);
  assert.equal(e.handlers.has('#rr-show-disabled'), false);
});

test('future-only enabled stages stay stored but hidden until current assignments qualify', () => {
  const config = [{name: 'Main', slug: 'main', enabled: true, size: 4, positions: [1, 2]}, {name: 'Plate', slug: 'plate', enabled: true, size: 4, positions: [3, 4]}, {name: 'Third bracket', slug: 'cons', enabled: true, size: 4, positions: [5, 6]}];
  const e = editor([4, 4], config); e.render();
  assert.doesNotMatch(e.html.get('#playoff-config-body'), /value="Third bracket"/);
  assert.equal(e.text.get('#rr-show-disabled-label'), 'Show unused playoffs (1)');
  e.handlers.get('#rr-show-disabled').call({checked: true});
  assert.match(e.html.get('#playoff-config-body'), /value="Third bracket"/);
  assert.equal(e.window.testEditor.draft()[2].enabled, true);
  assert.deepEqual(JSON.parse(JSON.stringify(e.window.testEditor.draft()[2].positions)), [5, 6]);
});

test('timed settings save playing time and changeover separately and reject invalid minutes', () => {
  const e = editor();
  e.values.set('#score-format', 'timed');
  e.values.set('#match-duration-minutes', '25');
  e.values.set('#changeover-minutes', '4');
  e.handlers.get('#match-duration-minutes, #changeover-minutes')();
  assert.match(e.text.get('#timed-slot-summary'), /29 minutes per court slot/);
  e.handlers.get('#drawSettingsForm')({preventDefault() {}});
  const payload = JSON.parse(e.requests[0].data);
  assert.equal(payload.match_duration_minutes, 25);
  assert.equal(payload.changeover_minutes, 4);
  const invalid = editor(); invalid.values.set('#score-format', 'timed'); invalid.values.set('#match-duration-minutes', '0');
  invalid.handlers.get('#drawSettingsForm')({preventDefault() {}});
  assert.equal(invalid.requests.length, 0);
});
