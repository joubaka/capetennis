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
      const voiceLocal = width === 320 || width === 768;
      const page = await browser.newPage({ viewport: { width, height: 900 } });
      const errors = [], confirmations = [], posts = [], transcriptions = [];
      page.on('pageerror', error => errors.push(error.message));
      let confirmationFails = false, confirmationServerError = false;
      let holdTranscription = false, releaseTranscription = null;
      const proposal = { uuid: 'proposal-1', type: 'schedule', confirmation_token: 'token-1', can_confirm: true,
        preview: { matches: [{ fixture_key: 'individual:1', draw_name: 'U14 Boys <img src=x>', scheduled_at: '2026-11-07 08:00:00', venue_name: 'Main courts', court: '1' }], existing_matches: [{ fixture_key: 'individual:2' }], warnings: [], unscheduled: [] } };
      await page.route('**/*', async route => {
        const request = route.request(), url = new URL(request.url());
        if (url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: html });
        if (url.pathname === '/css/event-assistant.css' || url.pathname === '/js/event-assistant.js') return route.fulfill({ contentType: url.pathname.endsWith('.css') ? 'text/css' : 'application/javascript', body: fs.readFileSync(path.join(root, 'public', url.pathname), 'utf8') });
        const action = url.pathname.split('/').pop();
        if (action === 'context') return route.fulfill({ json: { available: true, dates: ['2026-11-07'], defaults: { end_time: '17:00' }, event: { name: 'Demo' }, email_audiences: [{ id: 'registrations', name: 'Registered player contacts' }, { id: 'direct', name: 'One email address' }], draws: [{ id: 1, name: 'U14 Boys' }], venues: [{ id: 1, name: 'Main courts' }], capabilities: { ai_available: !voiceLocal, voice_available: true, voice_local: voiceLocal } } });
        if (action === 'transcribe') {
          transcriptions.push(request.postDataBuffer());
          if (holdTranscription) await new Promise(resolve => { releaseTranscription = resolve; });
          return route.fulfill({ json: { transcript: 'Start the matches at nine instead' } });
        }
        const data = request.method() === 'GET' ? {} : request.postDataJSON(); posts.push({ action, data });
        if (action === 'preview') return route.fulfill({ json: { conversation_uuid: 'test-conversation', message: { id: 1, content: 'Review your schedule.' }, proposal } });
        if (action === 'publication-preview') return route.fulfill({ json: { proposal: { ...proposal, type: 'publication' }, message: { id: 3, content: 'Review the public order of play.' } } });
        if (action === 'generation-preview') return route.fulfill({ json: { proposal: { ...proposal, type: 'draw_generate', preview: { name: 'U14 Boys', count: 6, groups: [{ name: 'A', entrants: 4, matches: 6 }] } } } });
        if (action === 'improvements') return route.fulfill({ json: { feedback: [{ rating: 'unhelpful', category: 'understanding', count: 2 }] } });
        if (action === 'message' && data.message.startsWith('Copy categories')) return route.fulfill({ json: { message: { id: 4, content: 'Review the category copy.' }, proposal: { ...proposal, uuid: 'category-copy', type: 'category_copy', preview: { source: { id: 10, name: 'Panarottis/Spur Leg 1' }, count: 1, scope: 'Copy setup only.', destinations: [{ id: 11, name: 'Panarottis/Spur Leg 2', categories: [{ name: 'Boys <img src=x>', action: 'skip_existing', result_entry_fee: 99, result_effective_fee: 99, result_ordering: 8 }] }, { id: 12, name: 'Panarottis/Spur Leg 3', categories: [{ name: 'Boys', action: 'add', result_entry_fee: null, result_effective_fee: 175, result_ordering: 3 }] }] } } } });
        if (action === 'message' && (data.message.startsWith('Draft an email') || data.email_draft)) return route.fulfill({ json: { conversation_uuid: 'email-conversation', email_draft: { subject: data.email_draft ? 'Revised rain plan' : 'Rain plan <img src=x>', body: data.email_draft ? 'Under 13 may play until 14:00 Sunday.' : 'Under 30 may play on Sunday.' }, message: { id: 5, content: 'Review the draft and select recipients. No email has been sent.' } } });
        if (action === 'message') return route.fulfill({ json: { message: { id: 2, content: 'Which date should I use?' }, suggestions: ['2026-11-07'] } });
        if (action === 'confirm') { confirmations.push(data); return route.fulfill({ status: confirmationServerError ? 500 : confirmationFails ? 409 : 200, json: confirmationServerError ? { message: 'SQLSTATE private debug database query' } : confirmationFails ? { message: 'The preview changed. Review a new proposal.' } : data.proposal_uuid === 'category-copy' ? { status: 'applied', type: 'category_copy', result: { count: 1 } } : { status: 'applied', message: 'Schedule saved.' } }); }
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
      await panel.locator('#ea-input').fill('Copy categories from Panarottis/Spur Leg 1 to Legs 2 and 3');
      await panel.getByRole('button', { name: 'Send', exact: true }).click();
      await panel.getByRole('heading', { name: 'Review category copy', exact: true }).waitFor();
      await panel.getByText('Source: Panarottis/Spur Leg 1 (#10)', { exact: true }).waitFor();
      await panel.getByRole('heading', { name: 'Panarottis/Spur Leg 2 (#11)' }).waitFor();
      await panel.getByRole('heading', { name: 'Panarottis/Spur Leg 3 (#12)' }).waitFor();
      assert.match(await panel.locator('[data-ea-proposal]').innerText(), /Skip existing; keep destination settings · Fee: R99 \(category override\) · Order: 8/);
      assert.match(await panel.locator('[data-ea-proposal]').innerText(), /Fee: R175 \(destination event fee; no override\)/);
      assert.equal(await panel.locator('img').count(), 0, 'category names stay plain text');
      const copy = panel.getByRole('button', { name: 'Copy categories', exact: true });
      assert.equal(await copy.isDisabled(), true);
      assert.equal(confirmations.some(data => data.proposal_uuid === 'category-copy'), false);
      await panel.locator('[data-ea-proposal] input[type=checkbox]').check();
      await copy.click();
      await panel.getByText('Categories copied. Existing categories were kept unchanged.', { exact: true }).waitFor();
      assert.deepEqual(confirmations.at(-1), { proposal_uuid: 'category-copy', confirmation_token: 'token-1', confirmed: true });
      await panel.locator('#ea-input').fill('Draft an email about rain delays');
      await panel.getByRole('button', { name: 'Send', exact: true }).click();
      const email = panel.locator('[data-ea-email]'), emailForm = email.locator('form');
      await email.waitFor({ state: 'visible' });
      assert.equal(await email.getByRole('textbox', { name: 'Email subject', exact: true }).inputValue(), 'Rain plan <img src=x>');
      assert.equal(await email.locator('img').count(), 0, 'draft subjects remain plain text');
      assert.equal(await email.getByRole('combobox', { name: /Email audience/ }).inputValue(), '', 'audience requires explicit choice');
      assert.equal(await emailForm.evaluate(form => form.checkValidity()), false);
      await email.getByRole('textbox', { name: 'Email subject', exact: true }).fill('Edited by organiser');
      await email.getByRole('textbox', { name: 'Email message', exact: true }).fill('Under 30 may play Sunday; keep all other details.');
      await panel.locator('#ea-input').fill('I meant Under 13, not Under 30; finish by 14:00 Sunday.');
      await panel.getByRole('button', { name: 'Send', exact: true }).click();
      await page.waitForFunction(() => document.querySelector('[data-ea-email-form] [name=subject]').value === 'Revised rain plan');
      assert.equal(posts.at(-1).data.email_draft.subject, 'Edited by organiser');
      assert.equal(posts.at(-1).data.email_draft.body, 'Under 30 may play Sunday; keep all other details.');
      assert.equal(await email.getByRole('textbox', { name: 'Email message', exact: true }).inputValue(), 'Under 13 may play until 14:00 Sunday.');
      await email.getByRole('combobox', { name: /Email audience/ }).selectOption('direct');
      await email.getByRole('textbox', { name: 'Recipient email', exact: true }).fill('review@example.test');
      assert.equal(await emailForm.evaluate(form => form.checkValidity()), true);
      const handoff = await emailForm.evaluate(form => ({ action: new URL(form.action).pathname, data: Object.fromEntries(new FormData(form)) }));
      assert.match(handoff.action, /\/backend\/event\/801\/communications\/preview$/);
      assert.equal(handoff.data.assistant_conversation_uuid, 'email-conversation');
      assert.equal(handoff.data.subject, 'Revised rain plan');
      assert.equal(handoff.data.body, 'Under 13 may play until 14:00 Sunday.');
      assert.equal(handoff.data.direct_email, 'review@example.test');
      assert.equal(handoff.data._token, 'test');
      assert.equal(posts.some(post => post.action === 'send'), false, 'drafting/revising never sends email');
      await email.getByRole('button', { name: 'Close email draft', exact: true }).click();
      assert.equal(await email.isVisible(), false);
      await page.evaluate(() => {
        Object.defineProperty(navigator, 'mediaDevices', { configurable: true, value: { getUserMedia: async () => { throw Object.assign(new Error('denied'), { name: 'NotAllowedError' }); } } });
      });
      await panel.getByRole('button', { name: 'Speak', exact: true }).click();
      await page.waitForFunction(() => document.querySelector('[data-ea-status]').textContent.includes('Microphone access was denied'));
      assert.equal(await panel.locator('#ea-input').isEnabled(), true);
      const messageCount = posts.filter(p => p.action === 'message').length;
      await page.evaluate(() => {
        window.stoppedTracks = 0;
        window.closedAudioContexts = 0; window.voiceNow = 100;
        Object.defineProperty(performance, 'now', { configurable: true, value: () => window.voiceNow });
        const originalTimeout = window.setTimeout;
        window.setTimeout = (callback, delay, ...args) => {
          if (delay === 30000) window.voiceLimit = callback;
          return originalTimeout(callback, delay, ...args);
        };
        Object.defineProperty(window, 'AudioContext', { configurable: true, value: class {
          // Deliberately keep a native 48 kHz rate to exercise 16 kHz WAV resampling.
          constructor(options) { window.requestedSampleRate = options.sampleRate; this.sampleRate = 48000; this.state = 'running'; this.destination = {}; }
          createMediaStreamSource() { return { connect() {}, disconnect() {} }; }
          createScriptProcessor() { const processor = { connect() {}, disconnect() {}, onaudioprocess: null }; window.voiceProcessor = processor; return processor; }
          createGain() { return { gain: { value: 1 }, connect() {}, disconnect() {} }; }
          close() { this.state = 'closed'; window.closedAudioContexts++; return Promise.resolve(); }
        } });
        window.emitVoice = (level = 0.1, elapsed = 100) => {
          window.voiceNow += elapsed;
          window.voiceProcessor.onaudioprocess?.({ inputBuffer: { getChannelData: () => new Float32Array(4800).fill(level) } });
        };
        Object.defineProperty(navigator, 'mediaDevices', { configurable: true, value: { getUserMedia: async () => ({ getTracks: () => [{ stop() { window.stoppedTracks++; } }] }) } });
      });
      await panel.getByRole('button', { name: 'Speak', exact: true }).click();
      assert.equal(await panel.getByRole('button', { name: 'Remember current timing defaults', exact: true }).isDisabled(), true, 'recording blocks unrelated requests');
      await page.evaluate(() => window.emitVoice());
      await panel.getByRole('button', { name: 'Stop recording', exact: true }).click();
      await page.waitForFunction(() => document.getElementById('ea-input').value === 'Start the matches at nine instead');
      assert.equal(posts.filter(p => p.action === 'message').length, messageCount, 'transcript is not automatically submitted');
      assert.equal(await panel.locator('[data-ea-ai]').isChecked(), false, 'transcription does not opt in interpretation');
      assert.equal(await panel.locator('[data-ea-ai]').isDisabled(), voiceLocal, 'local voice remains independent of external AI availability');
      assert.equal(transcriptions.length, 1);
      const multipart = transcriptions[0].toString('latin1');
      assert.match(multipart, voiceLocal ? /name="transcription_consent"/ : /name="use_ai"/);
      assert.doesNotMatch(multipart, voiceLocal ? /name="use_ai"/ : /name="transcription_consent"/);
      assert.match(multipart, /filename="request.wav"/); assert.match(multipart, /Content-Type: audio\/wav/);
      const wav = transcriptions[0].subarray(transcriptions[0].indexOf(Buffer.from('RIFF')));
      assert.equal(wav.toString('ascii', 8, 12), 'WAVE');
      assert.equal(wav.readUInt16LE(20), 1, 'PCM encoding'); assert.equal(wav.readUInt16LE(22), 1, 'mono audio');
      assert.equal(wav.readUInt32LE(24), 16000, 'native 48 kHz audio is resampled to 16 kHz');
      assert.equal(wav.readUInt16LE(34), 16, '16-bit samples'); assert.equal(wav.readUInt32LE(40), 3200, '0.1 second duration is preserved after resampling');
      assert.equal(await page.evaluate(() => window.requestedSampleRate), 16000);
      assert.equal(await page.evaluate(() => window.stoppedTracks), 1, 'microphone tracks released');
      assert.equal(await page.evaluate(() => window.closedAudioContexts), 1, 'audio context released');

      await panel.getByRole('button', { name: 'Speak', exact: true }).click();
      await page.evaluate(() => { window.emitVoice(); window.emitVoice(0, 1501); });
      await page.waitForFunction(() => document.querySelector('[data-ea-status]').textContent === 'Review the transcript, then Send.');
      assert.equal(transcriptions.length, 2, 'speech followed by silence stops and transcribes');
      await panel.getByRole('button', { name: 'Speak', exact: true }).click();
      await page.evaluate(() => { window.emitVoice(); window.voiceLimit(); });
      await page.waitForFunction(() => document.querySelector('[data-ea-status]').textContent === 'Review the transcript, then Send.');
      assert.equal(transcriptions.length, 3, '30-second cap stops and transcribes');
      await panel.getByRole('button', { name: 'Speak', exact: true }).click();
      await panel.getByRole('button', { name: 'Stop recording', exact: true }).click();
      await page.waitForFunction(() => document.querySelector('[data-ea-status]').textContent.includes('No speech was heard'));
      assert.equal(transcriptions.length, 3, 'silence alone is not uploaded');
      await panel.getByRole('button', { name: 'Speak', exact: true }).click();
      await page.evaluate(() => window.emitVoice());
      await panel.locator('[data-ea-cancel-voice]').click();
      assert.equal(transcriptions.length, 3, 'cancel discards recorded speech');
      await panel.getByRole('button', { name: 'Speak', exact: true }).click();
      await page.evaluate(() => window.emitVoice());
      await panel.getByRole('button', { name: 'Close assistant', exact: true }).click();
      assert.equal(transcriptions.length, 3, 'closing discards recorded speech');
      assert.equal(await page.evaluate(() => window.stoppedTracks), 6, 'all recording paths release tracks');
      assert.equal(await page.evaluate(() => window.closedAudioContexts), 6, 'all recording paths release audio contexts');
      await page.getByRole('button', { name: 'Ask assistant', exact: true }).click();
      await page.evaluate(() => {
        window.originalGetUserMedia = navigator.mediaDevices.getUserMedia;
        navigator.mediaDevices.getUserMedia = () => new Promise(resolve => { window.finishVoicePermission = resolve; });
      });
      await panel.getByRole('button', { name: 'Speak', exact: true }).click();
      await panel.getByRole('button', { name: 'Close assistant', exact: true }).click();
      await page.evaluate(() => {
        window.finishVoicePermission({ getTracks: () => [{ stop() { window.stoppedTracks++; } }] });
        navigator.mediaDevices.getUserMedia = window.originalGetUserMedia;
      });
      await page.waitForFunction(() => window.stoppedTracks === 7);
      assert.equal(transcriptions.length, 3, 'closing during microphone permission prevents recording/upload');
      await page.getByRole('button', { name: 'Ask assistant', exact: true }).click();
      await panel.locator('#ea-input').fill('Keep this typed text');
      holdTranscription = true;
      await panel.getByRole('button', { name: 'Speak', exact: true }).click();
      await page.evaluate(() => window.emitVoice());
      await panel.getByRole('button', { name: 'Stop recording', exact: true }).click();
      for (let wait = 0; !releaseTranscription && wait < 50; wait++) await page.waitForTimeout(10);
      assert.equal(typeof releaseTranscription, 'function', 'transcription request is pending');
      await panel.getByRole('button', { name: 'Close assistant', exact: true }).click();
      holdTranscription = false; releaseTranscription();
      await page.getByRole('button', { name: 'Ask assistant', exact: true }).click();
      await page.waitForTimeout(50);
      assert.equal(await panel.locator('#ea-input').inputValue(), 'Keep this typed text', 'late transcript after close cannot overwrite input');
      assert.equal(posts.filter(p => p.action === 'message').length, messageCount, 'automatic stops never submit messages');
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
