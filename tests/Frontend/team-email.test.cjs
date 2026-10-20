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
  const window = { prepareTeamSelectionMail: async () => {} };
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
