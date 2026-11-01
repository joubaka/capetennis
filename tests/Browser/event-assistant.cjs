// Real rendered Blade/assets with isolated in-memory HTTP responses; no live data/provider calls.
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');
const root = path.resolve(__dirname, '../..');

test('event assistant preview, confirmation, privacy and voice recovery at phone and desktop widths', async () => {
  const html = fs.readFileSync(path.join(root, 'storage/framework/testing/event-assistant.html'), 'utf8');
  assert.match(html, /data-publication-url=/, 'regenerate the current Blade fixture');
  const browser = await chromium.launch({ headless: true, channel: 'msedge' });
  try {
    for (const width of [320, 390, 768, 1280]) {
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      const errors = [], confirmations = [], posts = [];
      page.on('pageerror', error => errors.push(error.message));
      let confirmationFails = false, confirmationServerError = false;
      const proposal = { uuid: 'proposal-1', type: 'schedule', confirmation_token: 'token-1', can_confirm: true,
        preview: { matches: [{ fixture_key: 'individual:1', draw_name: 'U14 Boys <img src=x>', scheduled_at: '2026-11-07 08:00:00', venue_name: 'Main courts', court: '1' }], existing_matches: [{ fixture_key: 'individual:2' }], warnings: [], unscheduled: [] } };
      await page.route('**/*', async route => {
        const request = route.request(), url = new URL(request.url());
        if (url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: html });
        if (url.pathname === '/css/event-assistant.css' || url.pathname === '/js/event-assistant.js') return route.fulfill({ contentType: url.pathname.endsWith('.css') ? 'text/css' : 'application/javascript', body: fs.readFileSync(path.join(root, 'public', url.pathname), 'utf8') });
        const action = url.pathname.split('/').pop();
        if (action === 'context') return route.fulfill({ json: { available: true, dates: ['2026-11-07'], defaults: { end_time: '17:00' }, event: { name: 'Demo' }, draws: [{ id: 1, name: 'U14 Boys' }], venues: [{ id: 1, name: 'Main courts' }], capabilities: { ai_available: true, voice_available: true } } });
        if (action === 'transcribe') return route.fulfill({ json: { transcript: 'Start the matches at nine instead' } });
        const data = request.method() === 'GET' ? {} : request.postDataJSON(); posts.push({ action, data });
        if (action === 'preview') return route.fulfill({ json: { conversation_uuid: 'test-conversation', message: { id: 1, content: 'Review your schedule.' }, proposal } });
        if (action === 'publication-preview') return route.fulfill({ json: { proposal: { ...proposal, type: 'publication' }, message: { id: 3, content: 'Review the public order of play.' } } });
        if (action === 'generation-preview') return route.fulfill({ json: { proposal: { ...proposal, type: 'draw_generate', preview: { name: 'U14 Boys', count: 6, groups: [{ name: 'A', entrants: 4, matches: 6 }] } } } });
        if (action === 'improvements') return route.fulfill({ json: { feedback: [{ rating: 'unhelpful', category: 'understanding', count: 2 }] } });
        if (action === 'message') return route.fulfill({ json: { message: { id: 2, content: 'Which date should I use?' }, suggestions: ['2026-11-07'] } });
        if (action === 'confirm') { confirmations.push(data); return route.fulfill({ status: confirmationServerError ? 500 : confirmationFails ? 409 : 200, json: confirmationServerError ? { message: 'SQLSTATE private debug database query' } : confirmationFails ? { message: 'The preview changed. Review a new proposal.' } : { status: 'applied', message: 'Schedule saved.' } }); }
        if (action === 'feedback') return route.fulfill({ json: { message: 'Feedback recorded.' } });
        return route.fulfill({ json: {} });
      });
      await page.goto('http://assistant.test/');
      await page.getByRole('button', { name: 'Ask assistant', exact: true }).click();
      const panel = page.getByRole('dialog'); await panel.waitFor();
      await page.waitForFunction(() => document.querySelector('[name=date]').value === '2026-11-07');
      await panel.locator('[data-ea-planner]').evaluate(el => { el.open = true; });
      await panel.getByRole('checkbox', { name: 'U14 Boys', exact: true }).uncheck();
      await panel.getByRole('button', { name: 'Calculate preview', exact: true }).click();
      await panel.getByText('Choose at least one draw in Schedule details.', { exact: true }).waitFor();
      assert.equal(posts.some(p => p.action === 'preview'), false, 'empty draw selection does not send a request');
      await panel.getByRole('checkbox', { name: 'U14 Boys', exact: true }).check();
      await panel.getByRole('checkbox', { name: 'Main courts', exact: true }).uncheck();
      await panel.getByRole('button', { name: 'Calculate preview', exact: true }).click();
      await panel.getByText('Choose at least one venue in Schedule details.', { exact: true }).waitFor();
      assert.equal(posts.some(p => p.action === 'preview'), false, 'empty venue selection does not send a request');
      await panel.getByRole('checkbox', { name: 'Main courts', exact: true }).check();
      await panel.getByRole('button', { name: 'Calculate preview', exact: true }).click();
      await panel.getByRole('heading', { name: 'Review schedule' }).waitFor();
      const save = panel.getByRole('button', { name: 'Save schedule', exact: true });
      assert.deepEqual(posts.find(p => p.action === 'preview').data.options, { start: '2026-11-07 08:00:00', end: '2026-11-07 17:00:00', duration: 60, wave_minutes: 65, court_gap: 5, player_rest: 60, draw_ids: [1], venue_ids: [1] }, 'real form values survive loading state');
      assert.match(await panel.locator('[data-ea-proposal]').innerText(), /U14 Boys <img src=x> · individual:1/);
      assert.equal(await save.isDisabled(), true, 'preview cannot save before acknowledgement');
      assert.equal(confirmations.length, 0);
      await panel.getByText('1 existing booking is retained. Only the new assignments below are proposed.', { exact: true }).waitFor();
      assert.equal(await panel.locator('img').count(), 0, 'names remain plain text');
      await panel.locator('[data-ea-proposal] input[type=checkbox]').check(); await save.click();
      await page.waitForFunction(() => document.querySelector('[data-ea-status]').textContent === 'Schedule saved.');
      assert.deepEqual(confirmations[0], { proposal_uuid: 'proposal-1', confirmation_token: 'token-1', confirmed: true });
      assert.equal(posts.some(p => p.action === 'publication-preview'), false);
      await panel.getByRole('button', { name: 'Calculate preview', exact: true }).click();
      await panel.getByRole('heading', { name: 'Review schedule' }).waitFor();
      confirmationFails = true; await panel.locator('[data-ea-proposal] input[type=checkbox]').check(); await save.click();
      await page.waitForFunction(() => document.querySelector('[data-ea-status]').textContent.includes('preview changed'));
      assert.equal(await panel.locator('[data-ea-proposal]').isHidden(), true, 'stale preview is removed');
      confirmationFails = false; confirmationServerError = true;
      await panel.getByRole('button', { name: 'Calculate preview', exact: true }).click();
      await panel.getByRole('heading', { name: 'Review schedule' }).waitFor();
      await panel.locator('[data-ea-proposal] input[type=checkbox]').check(); await save.click();
      await page.waitForFunction(() => document.querySelector('[data-ea-status]').textContent.includes('before retrying a confirmation'));
      assert.equal((await panel.locator('[data-ea-status]').innerText()).includes('SQLSTATE'), false, 'server debug details stay private');
      assert.equal((await panel.locator('[data-ea-status]').innerText()).includes('Nothing was confirmed'), false, 'an uncertain confirmation is not reported as rollback');
      confirmationServerError = false;
      await panel.locator('#ea-input').fill('Only that draw tomorrow'); await panel.getByRole('button', { name: 'Send', exact: true }).click();
      await panel.getByText('Which date should I use?', { exact: true }).waitFor();
      assert.equal(posts.find(p => p.action === 'message').data.use_ai, false, 'typed input does not opt in automatically');
      await panel.getByRole('button', { name: 'Review selected order of play', exact: true }).click();
      await panel.getByRole('heading', { name: 'Review public order of play' }).waitFor();
      assert.deepEqual(posts.find(p => p.action === 'publication-preview').data.options, { date: '2026-11-07', draw_ids: [1] });
      assert.equal(await panel.getByRole('button', { name: 'Confirm publication' }).isDisabled(), true);
      await panel.getByText('Preferences and improvement', { exact: true }).evaluate(el => { el.closest('details').open = true; });
      await panel.getByRole('button', { name: 'Show feedback summary', exact: true }).click();
      await panel.getByText('unhelpful / understanding: 2', { exact: true }).waitFor();
      await panel.getByRole('button', { name: 'Review round-robin generation', exact: true }).click();
      await panel.getByText('6 fixtures to generate.', { exact: true }).waitFor();
      await panel.getByText('A · 4 players · 6 matches', { exact: true }).waitFor();
      assert.equal(await panel.getByRole('button', { name: 'Generate round-robin fixtures', exact: true }).isDisabled(), true);
      await page.evaluate(() => {
        Object.defineProperty(window, 'MediaRecorder', { configurable: true, value: class { static isTypeSupported() { return true; } } });
        Object.defineProperty(navigator, 'mediaDevices', { configurable: true, value: { getUserMedia: async () => { throw Object.assign(new Error('denied'), { name: 'NotAllowedError' }); } } });
      });
      await panel.getByRole('button', { name: 'Speak', exact: true }).click();
      await page.waitForFunction(() => document.querySelector('[data-ea-status]').textContent.includes('Microphone access was denied'));
      assert.equal(await panel.locator('#ea-input').isEnabled(), true);
      const messageCount = posts.filter(p => p.action === 'message').length;
      await page.evaluate(() => {
        window.stoppedTracks = 0;
        Object.defineProperty(window, 'MediaRecorder', { configurable: true, value: class {
          static isTypeSupported() { return true; }
          constructor(stream, options) { this.mimeType = options.mimeType; this.state = 'inactive'; }
          start() { this.state = 'recording'; }
          stop() { this.state = 'inactive'; this.ondataavailable({ data: new Blob(['synthetic-audio'], { type: this.mimeType }) }); this.onstop(); }
        } });
        Object.defineProperty(navigator, 'mediaDevices', { configurable: true, value: { getUserMedia: async () => ({ getTracks: () => [{ stop() { window.stoppedTracks++; } }] }) } });
      });
      await panel.getByRole('button', { name: 'Speak', exact: true }).click();
      assert.equal(await panel.getByRole('button', { name: 'Remember current timing defaults', exact: true }).isDisabled(), true, 'recording blocks unrelated requests');
      await panel.getByRole('button', { name: 'Stop recording', exact: true }).click();
      await page.waitForFunction(() => document.getElementById('ea-input').value === 'Start the matches at nine instead');
      assert.equal(posts.filter(p => p.action === 'message').length, messageCount, 'transcript is not automatically submitted');
      assert.equal(await panel.locator('[data-ea-ai]').isChecked(), false, 'transcription does not opt in interpretation');
      assert.equal(await page.evaluate(() => window.stoppedTracks), 1, 'microphone tracks released');
      assert.deepEqual(await page.evaluate(() => {
        const rect = document.getElementById('event-assistant-panel').getBoundingClientRect();
        return { overflow: document.documentElement.scrollWidth > innerWidth, outside: rect.left < -1 || rect.right > innerWidth + 1 };
      }), { overflow: false, outside: false }, 'contained panel at ' + width);
      if (width === 320) await page.screenshot({ path: path.join(root, 'storage/framework/testing/event-assistant-320.png') });
      await panel.getByRole('button', { name: 'Close assistant', exact: true }).click();
      assert.equal(await page.getByRole('button', { name: 'Ask assistant', exact: true }).evaluate(el => el === document.activeElement), true);
      assert.deepEqual(errors, []);
      await page.close();
    }
  } finally { await browser.close(); }
});
