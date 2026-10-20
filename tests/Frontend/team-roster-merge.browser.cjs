// Run explicitly with Playwright available in NODE_PATH; no application server,
// database, network requests, or mail transport is used by this DOM regression.
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

test('merged roster moves linked/raw invitation forms once and restores exact identities for Order', async () => {
  const browser = await chromium.launch({ headless: true, ...(process.env.CT_BROWSER_EXECUTABLE ? { executablePath: process.env.CT_BROWSER_EXECUTABLE } : {}) });
  try {
    const page = await browser.newPage({ viewport: { width: 390, height: 844 } });
    await page.setContent(`<div class="team-admin-workspace">
      <div id="tab-players" class="active"><div data-roster-panel="players" data-region-id="127">
        <details data-roster-team="841" open><summary>North team</summary><div class="roster-team-body">
          <table><tbody><tr data-roster-row="11" data-roster-player-id="44" data-roster-imported-id="9"><td>1</td><td>Linked player <span>Invited</span></td></tr>
            <tr data-roster-row="12" data-roster-imported-id="10"><td>2</td><td>Raw player</td></tr><tr data-roster-row="13"><td>3</td><td>Empty place <button id="fill">Add player</button></td></tr></tbody></table>
          <details class="roster-reserves"><summary>Reserve queue</summary>Old reserve summary</details>
        </div></details>
      </div><details><summary>Campaigns</summary><div data-central-selection-host><div data-embedded-teams-selection>
        <div id="region-panel-9"><div><div data-team-card-id="841" class="regional-team-card">
          <div data-team-workspace-header data-team-workspace-target="#team-workspace-841"><div>Duplicate team title</div><button class="team-workspace-toggle">Show team</button></div>
          <div class="collapse" id="team-workspace-841">
            <div class="table-responsive"><table><tbody data-team-invitations>
              <tr data-order-player-id="99" data-invitation-player-id="44"><td data-label="Rank">1</td><td data-label="Player">Linked player</td><td data-label="Regional action"><form id="invitation-action"><input name="reason" value="Keep draft"><button type="button" id="original-action">Manage</button></form><div id="original-modal" class="modal"><input value="Modal draft"></div></td></tr>
              <tr data-order-player-id="100" class="reserve-row"><td>Reserve</td></tr>
              <tr data-order-player-id="101" data-invitation-player-id="55"><td>Declined history</td></tr>
            </tbody></table></div>
            <div class="table-responsive"><table><tbody class="imported-roster-players">
              <tr data-slot-id="9"><td>Linked raw identity</td><td><form id="linked-profile"><input value="Original raw name"></form></td></tr>
              <tr data-slot-id="10"><td>Raw identity</td><td><form id="raw-profile"><input value="Unlinked name"></form></td></tr>
            </tbody></table></div>
          </div>
        </div></div></div>
      </div></div></details></div></div>`);
    await page.evaluate(() => {
      window.originals = Object.fromEntries(['invitation-action', 'linked-profile', 'raw-profile', 'original-modal', 'fill'].map(id => [id, document.getElementById(id)]));
      window.clicks = 0; document.getElementById('original-action').addEventListener('click', () => { window.clicks++; });
    });
    await page.addScriptTag({ path: 'public/js/team-roster-merge.js' });
    const merged = await page.evaluate(() => {
      window.mergeTeamRoster(); window.mergeTeamRoster();
      const primary = document.querySelector('[data-roster-team]');
      document.getElementById('original-action').click();
      return {
        rows: primary.querySelectorAll('[data-roster-row]').length,
        details: primary.querySelectorAll('[data-roster-detail-row]').length,
        formSame: document.getElementById('invitation-action') === window.originals['invitation-action'],
        linkedInline: !!document.getElementById('linked-profile').closest('[data-roster-detail-row="11"]'),
        rawInline: !!document.getElementById('raw-profile').closest('[data-roster-detail-row="12"]'),
        ancestor: document.getElementById('invitation-action').closest('[data-team-card-id]')?.dataset.teamCardId,
        portal: !!document.getElementById('original-modal').closest('[data-roster-modal-portal]'),
        activeHistoryRows: primary.querySelectorAll('[data-team-invitations] > tr[data-invitation-player-id="44"]').length,
        reserve: primary.querySelectorAll('[data-team-invitations] > .reserve-row').length,
        clicks: window.clicks, fillSame: document.getElementById('fill') === window.originals.fill,
        ids: document.querySelectorAll('#invitation-action').length,
      };
    });
    assert.deepEqual(merged, { rows: 3, details: 3, formSame: true, linkedInline: true, rawInline: true, ancestor: '841', portal: true, activeHistoryRows: 0, reserve: 1, clicks: 1, fillSame: true, ids: 1 });
    const restored = await page.evaluate(() => {
      document.querySelector('#invitation-action input').value = 'Updated retained draft';
      window.restoreMergedTeamRoster();
      const card = document.querySelector('[data-central-selection-host] [data-team-card-id]');
      const result = { card: !!card, same: document.getElementById('invitation-action') === window.originals['invitation-action'],
        draft: document.querySelector('#invitation-action input').value, rows: card.querySelectorAll('[data-team-invitations] > tr').length,
        header: card.querySelector('[data-team-workspace-header]').dataset.teamWorkspaceTarget,
        open: card.querySelector('#team-workspace-841').classList.contains('show'), portal: document.querySelectorAll('[data-roster-modal-portal]').length,
        wrappers: card.querySelectorAll('[data-canonical-roster]').length,
        modalSame: document.getElementById('original-modal') === window.originals['original-modal'] };
      window.mergeTeamRoster();
      document.querySelector('[data-roster-row="11"]').hidden = true;
      document.dispatchEvent(new Event('roster:filtered'));
      result.filtered = [...document.querySelectorAll('[data-roster-detail-row="11"]')].every(row => row.hidden);
      return result;
    });
    assert.deepEqual(restored, { card: true, same: true, draft: 'Updated retained draft', rows: 3, header: '#team-workspace-841', open: false, portal: 0, wrappers: 0, modalSame: true, filtered: true });
  } finally { await browser.close(); }
});
