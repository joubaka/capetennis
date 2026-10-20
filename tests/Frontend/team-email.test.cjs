const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/js/team-email.js', 'utf8');

function harness({ loaded = true, pending = 1, individual = false, hasInvitation = true, draft = false } = {}) {
  const controls = new Map();
  const control = selector => {
    if (!controls.has(selector)) controls.set(selector, { hidden: false, disabled: false, textContent: '', value: '', querySelector() { return null; } });
    return controls.get(selector);
  };
  const modal = { querySelector: control, querySelectorAll: () => ['custom', 'invitation', 'reminder', 'pending'].map(key => Object.assign(control(`[data-team-email-purpose="${key}"]`), { dataset: { teamEmailPurpose: key } })), addEventListener() {} };
  const invitation = hasInvitation ? { dataset: { emailInvitationPlayer: '44', emailTeam: '841', emailPlayerName: 'Synthetic Child' } } : null;
  const panel = { querySelector(selector) {
    if (selector === '[data-bs-target^="#prepare-invitations-"]') return draft ? { getAttribute: () => '#prepare-invitations-5' } : null;
    if (selector.startsWith('[data-email-invitation-player') || selector.startsWith('form[data-email-invitation-player]')) return invitation;
    if (selector.includes('/send-pending-activated')) return pending ? { querySelector: () => ({ value: String(pending) }) } : null;
    return null;
  }, querySelectorAll: () => invitation ? [invitation] : [] };
  const region = { value: '127', selectedOptions: [{ textContent: 'North', dataset: { eventRegionId: '9' } }] };
  const team = { value: '841', selectedOptions: [{ textContent: 'Primary' }] };
  const document = { addEventListener() {}, getElementById(id) {
    if (id === 'team-email-purpose') return modal;
    if (id === 'region-panel-9') return loaded ? panel : null;
    if (id === 'final-team-reminders-9') return {};
    if (id === 'prepare-invitations-5') return draft ? { id } : null;
    return null;
  }, querySelector(selector) { return selector === '[data-roster-region]' ? region : selector === '[data-workspace-team]' ? team : null; } };
  const window = { addEventListener() {}, prepareTeamSelectionMail: async () => {} };
  const context = vm.createContext({ window, document, CSS: { escape: value => value }, bootstrap: { Modal: { getOrCreateInstance: () => ({ show() {}, hide() {} }) } }, Set });
  vm.runInContext(source.replace(/\}\)\(\);\s*$/, 'window.testMail = { currentContext, open, custom, invitationForm };})();'), context);
  const button = individual ? { dataset: { playerid: '44', name: 'Synthetic Child' }, matches: () => false, classList: { contains: () => false }, closest: () => null } : null;
  return { api: window.testMail, button, control, document, modal, team, region };
}

test('chooser preserves TeamRegion/EventRegion/team/player identity separately', () => {
  const h = harness({ individual: true });
  const result = h.api.currentContext(h.button);
  assert.equal(result.region, '127'); assert.equal(result.eventRegion, '9');
  assert.equal(result.team, '841'); assert.equal(result.player, '44');
});

test('region entry overrides selected team instead of silently narrowing recipients', () => {
  const h = harness();
  const result = h.api.currentContext({ dataset: { regionid: '127' }, matches: () => true, classList: { contains: () => false } });
  assert.equal(result.team, '');
});

test('sent campaign chooser exposes exact pending count and individual saved invitation', async () => {
  const h = harness({ individual: true, pending: 2 }); await h.api.open(h.button);
  assert.equal(h.control('[data-team-email-purpose="custom"]').disabled, false);
  assert.equal(h.control('[data-team-email-purpose="invitation"]').disabled, false);
  assert.equal(h.control('[data-team-email-purpose="pending"]').disabled, false);
  assert.match(h.control('[data-pending-purpose-copy]').textContent, /^2 newly added/);
});

test('draft campaign modal outside the region panel remains reachable through its scoped trigger', async () => {
  const h = harness({ draft: true, hasInvitation: false }); await h.api.open(null);
  assert.equal(h.control('[data-team-email-purpose="invitation"]').disabled, false);
  assert.match(h.control('[data-invitation-purpose-copy]').textContent, /Prepare the campaign for this region/);
});

test('loading failure keeps custom available and makes lifecycle unavailable with retry guidance', async () => {
  const h = harness({ loaded: false }); await h.api.open(null);
  assert.equal(h.control('[data-team-email-purpose="custom"]').disabled, false);
  assert.equal(h.control('[data-team-email-purpose="pending"]').disabled, true);
  assert.match(h.control('[data-team-email-error]').textContent, /reopen Email to retry/);
});

test('unlinked imported player cannot silently remind the entire selected team', async () => {
  const h = harness({ individual: true, hasInvitation: false, pending: 0 });
  delete h.button.dataset.playerid; h.button.dataset.targetType = 'imported_player';
  await h.api.open(h.button);
  assert.equal(h.control('[data-team-email-purpose="reminder"]').disabled, true);
  assert.equal(h.control('[data-team-email-purpose="invitation"]').disabled, true);
  assert.equal(h.control('[data-team-email-purpose="pending"]').disabled, true);
});

for (const targetType of ['imported_player', 'team', 'region']) {
  test(`${targetType} click intercepts an earlier Bootstrap document-capture listener and replays once after the close transition`, async () => {
    const listeners = { window: [], document: [] };
    const hidden = [];
    const controls = new Map();
    let loaded = false;
    let resolveLoad;
    let composerOpens = 0;
    let chooserOpens = 0;
    let hideCalls = 0;
    let recipient;
    const control = selector => {
      if (!controls.has(selector)) controls.set(selector, { hidden: false, disabled: false, textContent: '' });
      return controls.get(selector);
    };
    const fields = [{ value: 'Old invitation subject' }, { value: 'Old invitation body' }];
    const composer = { querySelectorAll: () => fields };
    const modal = { querySelector: control,
      querySelectorAll: () => ['custom', 'invitation', 'reminder', 'pending'].map(purpose => Object.assign(control(`[data-team-email-purpose="${purpose}"]`), { dataset: { teamEmailPurpose: purpose } })),
      addEventListener(name, callback) { if (name === 'hidden.bs.modal') hidden.push(callback); } };
    const panel = { querySelector: () => null, querySelectorAll: () => [] };
    const region = { value: '127', selectedOptions: [{ textContent: 'North', dataset: { eventRegionId: '9' } }] };
    const team = { value: '841', selectedOptions: [{ textContent: 'Primary' }] };
    const button = { dataset: { bsTarget: '#roster-email-9', targetType, teamId: targetType === 'region' ? '' : '841', slotId: targetType === 'imported_player' ? '99' : '', recipient: 'Synthetic recipient' },
      matches: () => false, classList: { contains: value => value === 'roster-email-button' },
      closest(selector) { return selector.includes('.roster-email-button') || selector === '[data-bs-toggle="modal"]' ? button : null; },
      click() { click(button); } };
    function click(target) {
      const event = { target, stopped: false, preventDefault() {}, stopImmediatePropagation() { this.stopped = true; } };
      for (const [surface, capture] of [['window', true], ['document', true], ['document', false], ['window', false]]) {
        for (const listener of listeners[surface]) {
          if (event.stopped) return;
          if (listener.capture === capture) listener.callback(event);
        }
      }
    }
    // Bootstrap's real modal data API registers a delegated document capture
    // listener before application scripts. A later document listener is too late.
    listeners.document.push({ capture: true, callback(event) {
      const trigger = event.target.closest('[data-bs-toggle="modal"]'); if (!trigger) return;
      composerOpens++; recipient = { ...trigger.dataset };
    } });
    const document = {
      addEventListener(name, callback, capture = false) { if (name === 'click') listeners.document.push({ callback, capture: !!capture }); },
      getElementById(id) { return id === 'team-email-purpose' ? modal : id === 'region-panel-9' && loaded ? panel : id === 'final-team-reminders-9' ? {} : null; },
      querySelector(selector) { return selector === '[data-roster-region]' ? region : selector === '[data-workspace-team]' ? team : selector === '#roster-email-9' ? composer : null; },
    };
    const window = {
      addEventListener(name, callback, capture = false) { if (name === 'click') listeners.window.push({ callback, capture: !!capture }); },
      prepareTeamSelectionMail: () => new Promise(resolve => { resolveLoad = () => { loaded = true; resolve(); }; }),
    };
    const bootstrap = { Modal: { getOrCreateInstance: () => ({ show() { chooserOpens++; }, hide() { hideCalls++; } }) } };
    vm.runInNewContext(source, { window, document, bootstrap, CSS: { escape: value => value }, Set });
    click(button);
    assert.equal(chooserOpens, 1);
    assert.equal(composerOpens, 0, 'Bootstrap must not open a composer under the chooser');
    resolveLoad(); await Promise.resolve(); await Promise.resolve();
    const choice = control('[data-team-email-purpose="custom"]');
    choice.closest = selector => selector === '[data-team-email-purpose]' ? choice : null;
    click(choice); click(choice);
    assert.equal(hideCalls, 1, 'Repeated purpose clicks must share one close transition');
    assert.equal(composerOpens, 0, 'Composer waits for hidden.bs.modal, not hide()');
    hidden.splice(0).forEach(callback => callback());
    assert.equal(composerOpens, 1);
    assert.equal(chooserOpens, 1, 'Replay bypasses the chooser without adding another listener');
    assert.equal(recipient.targetType, targetType);
    assert.equal(recipient.teamId, targetType === 'region' ? '' : '841');
    assert.equal(recipient.slotId, targetType === 'imported_player' ? '99' : '');
    assert.deepEqual(fields.map(field => field.value), ['', '']);
  });
}
