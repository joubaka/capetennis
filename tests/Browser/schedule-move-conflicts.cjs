// Presentation fixture: real modal, renderer and Bootstrap; no application or external requests.
// Run with Node 20+, Playwright in NODE_PATH, and installed Microsoft Edge.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');

test('move conflict modal names blockers, fits phone and desktop, and acts only after a choice', async () => {
  const browser = await chromium.launch({ headless: true, channel: 'msedge' });
  try {
    const page = await browser.newPage();
    await page.route('**/*', route => route.abort());
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const css = read('public/assets/vendor/css/core.css') + read('public/assets/vendor/css/theme-default.css');
    const markup = read('resources/views/backend/schedule/partials/move-conflict-modal.blade.php');
    const report = {
      message: 'Move the later match first, then retry this move.',
      moving: {draw_name: 'U/19 Boys <img src=x onerror=alert(1)>', match: 5},
      requested: {scheduled_at: '2026-10-30 09:05:00', venue_name: 'Interpro test venue', court: '4'},
      issues: [{type: 'dependent', match: {fixture_key: 'individual:6', draw_name: 'U/19 Boys', match: 6,
        scheduled_at: '2026-10-30 09:30:00', venue_name: 'Interpro test venue', court: '5'},
        required_at: '2026-10-30 10:35:00', message: 'Match 6 needs the winner of Match 5 and enough player rest.'}],
      suggestions: [{fixture_key: 'individual:5', scheduled_at: '2026-10-30 08:15:00', venue_id: 1,
        venue_name: 'Interpro test venue', court: '4', current_slot: false}],
    };
    for (const width of [320, 390, 430, 768, 1280]) {
      await page.setViewportSize({width, height: 900});
      await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>${css} body {font-family:system-ui,sans-serif}</style>
        <button id="match-six">Match 6</button>${markup}
        <div class="modal fade" id="saved-edit" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-body">Saved match editor</div></div></div></div>`);
      await page.addScriptTag({path: require.resolve('bootstrap/dist/js/bootstrap.bundle.min.js')});
      await page.addScriptTag({path: path.join(root, 'public/js/schedule-move-conflicts.js')});
      await page.evaluate(async report => {
        window.actions = [];
        window.conflictModal = CapeScheduleMoveConflicts.mount({document, bootstrap,
          onSuggestion: slot => actions.push({type: 'suggestion', slot}),
          onReview: key => { actions.push({type: 'review', key}); document.getElementById('match-six').focus(); }});
        document.getElementById('match-six').focus();
        await new Promise(resolve => {
          document.getElementById('scheduleMoveConflictModal').addEventListener('shown.bs.modal', resolve, {once: true});
          conflictModal.show(report);
        });
      }, report);
      const modal = page.locator('#scheduleMoveConflictModal');
      await modal.waitFor({state: 'visible'});
      await page.waitForFunction(() => document.querySelector('#scheduleMoveConflictModal').classList.contains('show'));
      assert.match(await modal.innerText(), /Move this later match first/);
      assert.match(await modal.innerText(), /Match 6/);
      assert.match(await modal.innerText(), /10:35/);
      assert.equal(await modal.locator('img').count(), 0, 'untrusted names must remain text');
      assert.equal(await page.evaluate(() => actions.length), 0, 'opening the modal does not move a match');
      assert.deepEqual(await page.evaluate(() => {
        const failures = [];
        if (document.documentElement.scrollWidth > innerWidth) failures.push('page overflow');
        for (const element of document.querySelectorAll('#scheduleMoveConflictModal button, #scheduleMoveConflictLabel')) {
          const rect = element.getBoundingClientRect();
          if (rect.left < -1 || rect.right > innerWidth + 1) failures.push('clipped control');
        }
        return failures;
      }), [], `layout at ${width}px`);
      if (width === 320) {
        const directory = path.join(root, 'storage/app/testing');
        fs.mkdirSync(directory, {recursive: true});
        await page.screenshot({path: path.join(directory, 'schedule-move-conflicts-320.png')});
      }
      await modal.getByRole('button', {name: 'Return to schedule'}).click();
      await modal.waitFor({state: 'hidden'});
      await page.waitForFunction(() => document.activeElement.id === 'match-six');
      assert.equal(await page.evaluate(() => document.activeElement.id), 'match-six', 'closing returns to the originating control');
      assert.equal(await page.evaluate(() => actions.length), 0);
      await page.evaluate(report => new Promise(resolve => {
        document.getElementById('scheduleMoveConflictModal').addEventListener('shown.bs.modal', resolve, {once: true});
        conflictModal.show(report);
      }), report);
      await modal.locator('[data-conflict-suggestion="0"]').click();
      await modal.waitFor({state: 'hidden'});
      await page.waitForFunction(() => actions.length === 1);
      assert.equal(await page.evaluate(() => actions[0].slot.scheduled_at), report.suggestions[0].scheduled_at);
      await page.evaluate(report => new Promise(resolve => {
        document.getElementById('scheduleMoveConflictModal').addEventListener('shown.bs.modal', resolve, {once: true});
        conflictModal.show(report);
      }), report);
      await modal.locator('[data-conflict-review="individual:6"]').click();
      await modal.waitFor({state: 'hidden'});
      await page.waitForFunction(() => actions.length === 2);
      assert.equal(await page.evaluate(() => actions[1].key), 'individual:6');
      assert.equal(await page.evaluate(() => document.activeElement.id), 'match-six');
      await page.evaluate(() => new Promise(resolve => {
        const editor = document.getElementById('saved-edit');
        editor.addEventListener('shown.bs.modal', resolve, {once: true});
        bootstrap.Modal.getOrCreateInstance(editor).show();
      }));
      await page.evaluate(report => new Promise(resolve => {
        document.getElementById('scheduleMoveConflictModal').addEventListener('shown.bs.modal', resolve, {once: true});
        conflictModal.show(report);
      }), report);
      await modal.waitFor({state: 'visible'});
      await page.waitForFunction(() => !document.getElementById('saved-edit').classList.contains('show'));
      assert.equal(await page.locator('.modal.show').count(), 1, 'dialogs must not stack');
      await page.keyboard.press('Escape');
      await modal.waitFor({state: 'hidden'});
    }
    assert.deepEqual(errors, []);
  } finally {
    await browser.close();
  }
});

test('checking details show context and elapsed time and survive fast responses and modal transitions', async () => {
  const browser = await chromium.launch({headless: true, channel: 'msedge'});
  try {
    const page = await browser.newPage();
    await page.route('**/*', route => route.abort());
    const css = read('public/assets/vendor/css/core.css') + read('public/assets/vendor/css/theme-default.css');
    const markup = read('resources/views/backend/schedule/partials/move-conflict-modal.blade.php');
    for (const width of [320, 390, 430, 768, 1280]) {
      await page.setViewportSize({width, height: 900});
      await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>${css} body {font-family:system-ui,sans-serif}</style><button id="origin">Move Match 5</button>${markup}
        <div class="modal fade" id="saved-edit" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-body">Saved match editor</div></div></div></div>`);
      await page.addScriptTag({path: require.resolve('bootstrap/dist/js/bootstrap.bundle.min.js')});
      await page.addScriptTag({path: path.join(root, 'public/js/schedule-move-conflicts.js')});
      await page.evaluate(() => {
        window.testNow = 0;
        window.clockTick = null;
        window.checkingReport = {
          moving: {draw_name: 'U/19 Boys Draw', match: 5},
          requested: {scheduled_at: '2026-10-30 09:05:00', venue_name: 'Interpro test courts', court: '4'},
          related: [{relationship: 'dependent', draw_name: 'U/19 Boys Draw', match: 6,
            scheduled_at: '2026-10-30 09:30:00', venue_name: 'Interpro test courts', court: '5'}],
        };
        window.dialog = CapeScheduleMoveConflicts.mount({document, bootstrap, now: () => testNow,
          setInterval: callback => {window.clockTick = callback; return 1;},
          clearInterval: () => {window.clockTick = null;}});
        window.waitShown = action => new Promise(resolve => {
          document.getElementById('scheduleMoveConflictModal').addEventListener('shown.bs.modal', resolve, {once: true});
          action();
        });
        window.waitHidden = action => new Promise(resolve => {
          document.getElementById('scheduleMoveConflictModal').addEventListener('hidden.bs.modal', resolve, {once: true});
          action();
        });
        document.getElementById('origin').focus();
      });
      await page.evaluate(() => waitShown(() => dialog.checking(checkingReport)));
      const modal = page.locator('#scheduleMoveConflictModal');
      const body = page.locator('#schedule-move-conflict-body');
      assert.equal(await body.getAttribute('aria-busy'), 'true');
      assert.match(await modal.innerText(), /Match 5/);
      assert.match(await modal.innerText(), /Match 6/);
      assert.match(await modal.innerText(), /09:05/);
      assert.match(await modal.innerText(), /Court 4/);
      assert.match(await modal.innerText(), /request will continue/i);
      await page.evaluate(() => {window.testNow = 65000; clockTick();});
      assert.equal(await page.locator('[data-move-check-elapsed]').innerText(), 'Elapsed: 1m 5s');
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, `checking panel fits ${width}px`);
      if (width === 320) await page.screenshot({path: path.join(root, 'storage/app/testing/schedule-move-checking-320.png')});
      await page.evaluate(() => waitHidden(() => bootstrap.Modal.getOrCreateInstance(document.getElementById('scheduleMoveConflictModal')).hide()));
      assert.equal(await body.getAttribute('aria-busy'), 'true', 'hiding details does not cancel an outstanding check');
      await page.evaluate(() => {window.testNow = 85000; clockTick();});
      await page.evaluate(() => waitShown(() => dialog.reopenChecking()));
      assert.equal(await page.locator('[data-move-check-elapsed]').innerText(), 'Elapsed: 1m 25s');
      await page.evaluate(() => {
        dialog.show({message: 'Move Match 6 later first.', issues: [], suggestions: []});
        dialog.finishChecking();
      });
      assert.equal(await body.getAttribute('aria-busy'), 'false');
      assert.equal(await page.evaluate(() => clockTick), null, 'the elapsed timer is cleared on a response');
      assert.equal(await modal.isVisible(), true, 'request cleanup must not close conflict details');
      assert.match(await modal.innerText(), /Move Match 6 later first/);
      await page.evaluate(() => waitHidden(() => bootstrap.Modal.getOrCreateInstance(document.getElementById('scheduleMoveConflictModal')).hide()));
      // A fast response must close the pending panel even while Bootstrap is opening it.
      await page.evaluate(() => waitHidden(() => {
        dialog.checking(checkingReport);
        dialog.finishChecking();
      }));
      assert.equal(await modal.isVisible(), false);
      assert.equal(await page.locator('.modal-backdrop').count(), 0);
      // A rejected response arriving during Hide must reopen the final details.
      await page.evaluate(() => waitShown(() => dialog.checking(checkingReport)));
      await page.evaluate(() => waitShown(() => {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('scheduleMoveConflictModal')).hide();
        dialog.show({message: 'Late rejected move: review Match 6.', issues: [], suggestions: []});
      }));
      assert.equal(await modal.isVisible(), true);
      assert.match(await modal.innerText(), /Late rejected move/);
      await page.evaluate(() => waitHidden(() => bootstrap.Modal.getOrCreateInstance(document.getElementById('scheduleMoveConflictModal')).hide()));
      await page.evaluate(() => new Promise(resolve => {
        const editor = document.getElementById('saved-edit');
        editor.addEventListener('shown.bs.modal', resolve, {once: true});
        bootstrap.Modal.getOrCreateInstance(editor).show();
      }));
      await page.evaluate(() => waitShown(() => {
        dialog.checking(checkingReport);
        dialog.show({message: 'Rejected while the saved editor is closing.', issues: [], suggestions: []});
        dialog.finishChecking();
      }));
      assert.equal(await page.locator('.modal.show').count(), 1, 'quick rejection must wait for the saved editor to close');
      assert.match(await modal.innerText(), /saved editor is closing/);
      await page.evaluate(() => waitHidden(() => bootstrap.Modal.getOrCreateInstance(document.getElementById('scheduleMoveConflictModal')).hide()));
    }
  } finally {
    await browser.close();
  }
});
