(function (root) {
  'use strict';
  function mount(panel, document, fetcher) {
    if (!panel || panel.dataset.mounted) return;
    panel.dataset.mounted = 'true';
    const find = selector => panel.querySelector(selector);
    const input = find('#ea-input'), status = find('[data-ea-status]');
    const log = find('[data-ea-messages]'), proposalView = find('[data-ea-proposal]');
    const form = find('[data-ea-plan-form]'), messageForm = find('[data-ea-message-form]');
    const emailView = find('[data-ea-email]'), emailForm = find('[data-ea-email-form]');
    const mic = find('[data-ea-mic]'), ai = find('[data-ea-ai]');
    const backdrop = document.getElementById('event-assistant-backdrop');
    const openers = [...document.querySelectorAll('[data-event-assistant-open]')];
    let conversation = null, context = null, busy = false, currentProposal = null;
    let returnFocus = null, previousOverflow = '', recorder = null;
    let requestController = null, voiceStarting = false, voiceTranscribing = false, voiceEpoch = 0;
    function node(tag, text, className) {
      const element = document.createElement(tag);
      if (text !== undefined) element.textContent = String(text);
      if (className) element.className = className;
      return element;
    }
    function say(text, error) { status.textContent = text; status.dataset.error = String(!!error); }
    function setBusy(value) {
      busy = value;
      [...panel.querySelectorAll('button,input,textarea,select')].forEach(element => {
        if (!element.matches('[data-ea-close], [data-ea-shortcut], [data-ea-shortcut-copy], [data-ea-shortcut-url]')) element.disabled = value || context?.available === false;
      });
      if (!value) {
        ai.disabled = context?.available === false || context?.capabilities?.ai_available === false;
        mic.disabled = context?.available === false || context?.capabilities?.voice_available === false;
        const confirmation = proposalView.querySelector('[data-ea-confirm]');
        if (confirmation) confirmation.disabled = confirmation.dataset.blocked === 'true' || !proposalView.querySelector('input[type=checkbox]')?.checked;
      }
    }
    async function request(url, payload, method) {
      const controller = new AbortController(); requestController = controller;
      const timeout = root.setTimeout(() => controller.abort(), url === panel.dataset.transcribeUrl && context?.capabilities?.voice_local ? 125000 : 60000);
      try {
        const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' };
        const options = { method: method || 'POST', headers, signal: controller.signal, credentials: 'same-origin' };
        if (payload instanceof FormData) options.body = payload;
        else if (payload !== undefined) { headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(payload); }
        const response = await fetcher(url, options);
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
          if (response.status >= 500 && response.status !== 503) {
            throw new Error('The assistant could not complete this request. Review the saved calendar or draw status before retrying a confirmation.');
          }
          if (response.status === 409) { currentProposal = null; proposalView.replaceChildren(); proposalView.hidden = true; }
          const validation = data.errors ? Object.values(data.errors).flat().join(' ') : null;
          throw new Error(validation || data.message || (response.status === 419 ? 'Your session expired. Reload the page and sign in again.' : 'The request could not be completed. Please try again.'));
        }
        return data;
      } catch (error) {
        if (error.name === 'AbortError') throw new Error('The request timed out. Review the saved calendar before retrying a confirmation.');
        throw error;
      } finally { root.clearTimeout(timeout); if (requestController === controller) requestController = null; }
    }
    function safeLink(link) {
      try {
        const url = new URL(link.url || link.href, root.location.href);
        if (url.origin !== root.location.origin || !['http:', 'https:'].includes(url.protocol)) return null;
        const a = node('a', link.label || link.title || 'Open workspace'); a.href = url.href; return a;
      } catch (_) { return null; }
    }
    function addMessage(role, content, id) {
      const item = node('div', undefined, 'ea-message'); item.dataset.role = role;
      item.append(node('strong', role === 'user' ? 'You' : 'Assistant'), node('span', content));
      if (role === 'assistant' && id) {
        const feedback = node('div', undefined, 'ea-feedback');
        [['Helpful', 'helpful'], ['Needs improvement', 'unhelpful']].forEach(([label, rating]) => {
          const button = node('button', label); button.type = 'button';
          button.addEventListener('click', async () => {
            if (busy) return;
            // Reasons are explicit user feedback, never inferred from a schedule edit.
            if (rating === 'unhelpful') {
              const existing = item.querySelector('select'); if (existing) { existing.focus(); return; }
              const reason = node('select'); reason.setAttribute('aria-label', 'Reason for feedback');
              [['understanding', 'Misunderstood my request'], ['schedule_quality', 'Schedule needs improvement'], ['voice', 'Transcription problem'], ['missing_capability', 'Missing capability'], ['other', 'Other']].forEach(([value, title]) => { const option = node('option', title); option.value = value; reason.append(option); });
              const submit = node('button', 'Submit feedback'); submit.type = 'button';
              submit.addEventListener('click', () => sendFeedback(reason.value)); feedback.append(reason, submit); reason.focus();
            } else await sendFeedback(null);
            async function sendFeedback(reason) {
              setBusy(true);
              try { const result = await request(panel.dataset.feedbackUrl, { message_id: id, rating, reason }); feedback.replaceChildren(node('span', result.message || 'Feedback recorded.')); }
              catch (error) { say(error.message, true); } finally { setBusy(false); }
            }
          }); feedback.append(button);
        }); item.append(feedback);
      }
      log.append(item); item.scrollIntoView?.({ block: 'nearest' });
    }
    function populateContext(data) {
      context = data;
      if (emailForm) {
        emailForm.elements.scope.replaceChildren();
        const blank = node('option', 'Choose recipients to review'); blank.value = ''; emailForm.elements.scope.append(blank);
        (data.email_audiences || []).forEach(choice => { const option = node('option', choice.name); option.value = choice.id; emailForm.elements.scope.append(option); });
      }
      const defaults = { ...(data.defaults || {}), ...(data.preferences?.preferences || data.preferences || {}) };
      for (const name of ['duration', 'court_gap', 'player_rest', 'start_time', 'end_time']) if (defaults[name] !== undefined) form.elements[name].value = defaults[name];
      const initialDate = data.event?.start_date || data.event?.start || data.dates?.[0] || data.current_date;
      if (initialDate) form.elements.date.value = String(initialDate).slice(0, 10);
      function choices(selector, values, kind) {
        const target = find(selector); target.replaceChildren();
        values.forEach(value => {
          const label = node('label'), checkbox = node('input'); checkbox.type = 'checkbox'; checkbox.name = kind; checkbox.value = value.id; checkbox.checked = true;
          label.append(checkbox, node('span', value.name || value.drawName || String(value.id))); target.append(label);
        });
        if (!values.length) target.append(node('span', kind === 'draw_ids' ? 'No draws yet. Prepare a draw first.' : 'No venues configured. Open the scheduling workspace to configure courts.'));
      }
      choices('[data-ea-draws]', data.draws || [], 'draw_ids'); choices('[data-ea-venues]', data.venues || [], 'venue_ids');
      ai.disabled = data.capabilities?.ai_available === false; if (ai.disabled) ai.checked = false;
      mic.disabled = data.capabilities?.voice_available === false;
      const voicePrivacy = find('[data-ea-voice-privacy]');
      if (voicePrivacy && data.capabilities?.voice_local) voicePrivacy.textContent = 'Speak transcribes on this computer using Whisper, with event player names as hints. Review the transcript before sending. AI interpretation is optional; confirmations always use Cape Tennis permissions.';
    }
    function planningOptions() {
      const data = new FormData(form), date = data.get('date');
      const duration = Number(data.get('duration')), gap = Number(data.get('court_gap'));
      return { start: date + ' ' + data.get('start_time') + ':00', end: date + ' ' + data.get('end_time') + ':00',
        duration, wave_minutes: duration + gap, court_gap: gap, player_rest: Number(data.get('player_rest')),
        draw_ids: data.getAll('draw_ids').map(Number), venue_ids: data.getAll('venue_ids').map(Number) };
    }
    function selectionError(options, needsVenues) {
      if (!options.draw_ids.length) return context?.draws?.length
        ? 'Choose at least one draw in Schedule details.'
        : 'This event has no draws yet. Prepare a draw before planning its schedule.';
      if (needsVenues && !options.venue_ids.length) return context?.venues?.length
        ? 'Choose at least one venue in Schedule details.'
        : 'This event has no venues yet. Open the scheduling workspace to configure venues and courts first.';
      return null;
    }
    function syncPlan(options) {
      if (!options) return;
      if (options.date) form.elements.date.value = options.date;
      if (options.start_time) form.elements.start_time.value = options.start_time;
      if (options.end_time) form.elements.end_time.value = options.end_time;
      if (options.start) { form.elements.date.value = options.start.slice(0, 10); form.elements.start_time.value = options.start.slice(11, 16); }
      if (options.end) form.elements.end_time.value = options.end.slice(11, 16);
      for (const key of ['duration', 'court_gap', 'player_rest']) if (options[key] !== undefined) form.elements[key].value = options[key];
      for (const key of ['draw_ids', 'venue_ids']) if (Array.isArray(options[key])) form.querySelectorAll('input[name=' + key + ']').forEach(el => { el.checked = options[key].map(Number).includes(Number(el.value)); });
    }
    function renderProposal(proposal) {
      currentProposal = proposal; proposalView.replaceChildren(); proposalView.hidden = !proposal;
      if (!proposal) return;
      const type = proposal.type || proposal.action;
      const preview = proposal.preview || proposal.summary || {};
      syncPlan(proposal.options || preview.input);
      const title = type === 'category_copy' ? 'Review category copy' : type === 'publication' ? 'Review public order of play' : type === 'draw_draft' ? 'Create draft draw' : type === 'draw_generate' ? 'Review round-robin generation' : 'Review schedule';
      proposalView.append(node('h3', title));
      proposalView.append(node('p', typeof proposal.description === 'string' ? proposal.description : type === 'category_copy' ? preview.scope : type === 'publication' ? 'Confirming this proposal publishes the displayed scope.' : type === 'draw_draft' ? 'Creates an empty draft. Player selection, seeding and bracket generation continue in the draw guide.' : type === 'draw_generate' ? 'Generates first round-robin fixtures from the reviewed groups and seeds. It does not schedule or publish matches.' : 'Confirming saves the proposed working schedule. Publication is a separate action.'));
      if (type === 'category_copy') {
        proposalView.append(node('p', 'Source: ' + preview.source.name + ' (#' + preview.source.id + ')'));
        proposalView.append(node('p', preview.count + ' categories to add. Existing categories and their fees stay unchanged.'));
        (preview.destinations || []).forEach(destination => {
          proposalView.append(node('h4', destination.name + ' (#' + destination.id + ')'));
          const list = node('ul');
          destination.categories.forEach(category => list.append(node('li', category.name + ' · ' + (category.action === 'add' ? 'Add' : 'Skip existing; keep destination settings') + ' · Fee: R' + category.result_effective_fee + (category.result_entry_fee === null ? ' (destination event fee; no override)' : ' (category override)') + ' · Order: ' + (category.result_ordering ?? 'Default'))));
          proposalView.append(list);
        });
      }
      const rows = Array.isArray(preview.matches) ? preview.matches : Array.isArray(proposal.matches) ? proposal.matches : [];
      if (Array.isArray(preview.existing_matches) && preview.existing_matches.length) {
        const retained = preview.existing_matches.length;
        proposalView.append(node('p', retained + (retained === 1 ? ' existing booking is' : ' existing bookings are') + ' retained. Only the new assignments below are proposed.'));
      }
      if (type === 'draw_draft') {
        const details = proposal.options || proposal.payload || proposal.preview || proposal.summary || {};
        if (details.drawName || details.name) proposalView.append(node('p', details.drawName || details.name));
        if (details.scope) proposalView.append(node('p', details.scope));
      }
      if (type === 'draw_generate') {
        if (preview.name || preview.draw_name) proposalView.append(node('p', preview.name || preview.draw_name));
        if (preview.match_count !== undefined || preview.fixture_count !== undefined || preview.count !== undefined) proposalView.append(node('p', (preview.match_count ?? preview.fixture_count ?? preview.count) + ' fixtures to generate.'));
        if (Array.isArray(preview.groups)) {
          const groups = node('ul'); preview.groups.forEach(group => groups.append(node('li', [group.name || group.group_name, (group.players ?? group.entrants ?? group.player_count ?? group.count ?? '') + ' players', (group.matches ?? group.match_count ?? group.fixtures ?? '') + ' matches'].join(' · ')))); proposalView.append(groups);
        }
      }
      if (rows.length) {
        proposalView.append(node('p', rows.length + ' proposed match assignments.'));
        const wrap = node('div', undefined, 'ea-table-wrap'), table = node('table'), head = node('thead'), header = node('tr');
        ['Match', 'Start', 'Court'].forEach(label => header.append(node('th', label))); head.append(header); table.append(head);
        const body = node('tbody'); rows.slice(0, 100).forEach(row => {
          const matchRef = row.fixture_key || (row.fixture_id ? (row.fixture_kind || 'match') + ':' + row.fixture_id : 'Match ' + (row.match || row.match_nr || '?'));
          const tr = node('tr'); tr.append(node('td', [row.draw_name || row.draw || row.label, matchRef].filter(Boolean).join(' · ')), node('td', row.scheduled_at || row.time || ''), node('td', [row.venue_name, row.court || row.court_label].filter(Boolean).join(' / '))); body.append(tr);
        }); table.append(body); wrap.append(table); proposalView.append(wrap);
        if (rows.length > 100) proposalView.append(node('p', 'Showing the first 100 assignments. Review the full calendar before publication.'));
      }
      const warnings = [...(preview.warnings || []), ...(preview.unscheduled || [])];
      if (warnings.length) { const list = node('ul'); warnings.slice(0, 30).forEach(w => list.append(node('li', typeof w === 'string' ? w : w.message || w.reason || 'A match requires review.'))); proposalView.append(list); }
      if (proposal.expires_at) proposalView.append(node('p', 'Proposal expires: ' + proposal.expires_at));
      const label = node('label', undefined, 'ea-ack'), ack = node('input'); ack.type = 'checkbox';
      label.append(ack, node('span', type === 'publication' ? 'I reviewed this scope and want to publish it.' : 'I reviewed these changes and want to apply them.'));
      const confirm = node('button', type === 'category_copy' ? 'Copy categories' : type === 'publication' ? 'Confirm publication' : type === 'draw_draft' ? 'Create draft draw' : type === 'draw_generate' ? 'Generate round-robin fixtures' : 'Save schedule', 'ea-primary'); confirm.type = 'button'; confirm.dataset.eaConfirm = ''; confirm.dataset.blocked = String(proposal.can_confirm === false); confirm.disabled = true;
      ack.addEventListener('change', () => { confirm.disabled = !ack.checked || busy || proposal.can_confirm === false; });
      confirm.addEventListener('click', async () => {
        if (busy || !ack.checked || proposal.can_confirm === false || currentProposal !== proposal) return;
        setBusy(true); say('Applying your confirmed proposal…');
        try {
          const result = await request(panel.dataset.confirmUrl, { proposal_uuid: proposal.uuid || proposal.proposal_uuid, confirmation_token: proposal.confirmation_token, confirmed: true });
          currentProposal = null; proposalView.replaceChildren(); proposalView.hidden = true;
          say(result.message || (type === 'category_copy' ? 'Categories copied. Existing categories were kept unchanged.' : type === 'publication' ? 'Order of play published.' : type === 'draw_draft' ? 'Draft draw created. Continue in the draw guide.' : type === 'draw_generate' ? 'Round-robin fixtures generated. Review the draw before scheduling or publishing.' : 'Schedule saved. Review publication separately.'));
          renderLinks(result.action_links || result.result?.action_links || (result.result?.setup_url ? [{ url: result.result.setup_url, label: 'Continue draw setup' }] : []));
        } catch (error) { say(error.message, true); ack.checked = false; }
        finally { setBusy(false); confirm.disabled = !ack.checked; }
      });
      proposalView.append(label, confirm);
    }
    function renderLinks(links) {
      if (!Array.isArray(links)) return;
      const target = node('div', undefined, 'ea-action-links');
      links.forEach(link => { const a = safeLink(link); if (a) target.append(a); }); if (target.children.length) log.append(target);
    }
    function accept(data) {
      if (data.conversation_uuid) conversation = data.conversation_uuid;
      if (data.email_draft && emailForm) {
        emailView.hidden = false;
        emailForm.elements.subject.value = data.email_draft.subject || '';
        emailForm.elements.body.value = data.email_draft.body || '';
        emailForm.elements.assistant_conversation_uuid.value = conversation || '';
      }
      syncPlan(data.options);
      if (data.message) addMessage('assistant', typeof data.message === 'string' ? data.message : data.message.content, data.message.id);
      if (data.clarification) say(typeof data.clarification === 'string' ? data.clarification : data.clarification.message || 'Please provide the missing schedule details.');
      renderProposal(data.proposal || null); renderLinks(data.action_links || []);
      const target = find('[data-ea-suggestions]'); target.replaceChildren();
      (data.suggestions || []).forEach(value => {
        const text = typeof value === 'string' ? value : value.label || value.text;
        if (!text) return; const button = node('button', text); button.type = 'button'; button.addEventListener('click', () => { input.value = typeof value === 'string' ? value : value.prompt || value.text || text; input.focus(); }); target.append(button);
      });
    }
    async function open() {
      returnFocus = document.activeElement; previousOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden'; panel.hidden = false; backdrop.hidden = false;
      openers.forEach(b => b.setAttribute('aria-expanded', 'true')); input.focus();
      if ((!context || context.available === false) && !busy) {
        setBusy(true); say('Loading event details…');
        try {
          populateContext(await request(panel.dataset.contextUrl, undefined, 'GET'));
          say(context.available === false ? 'The assistant database setup is not available yet. Close this panel and reopen it after local setup.'
            : context.capabilities?.voice_available === false ? 'Typed scheduling is ready. Voice and AI are unavailable in the current configuration.'
            : 'Ready. Speak or type a request, or open Schedule details.');
        }
        catch (error) { say(error.message, true); } finally { setBusy(false); }
      }
    }
    function encodeWav(chunks, sampleRate) {
      const inputLength = chunks.reduce((sum, chunk) => sum + chunk.length, 0);
      const samples = new Float32Array(inputLength); let position = 0;
      chunks.forEach(chunk => { samples.set(chunk, position); position += chunk.length; });
      const targetRate = 16000, ratio = sampleRate / targetRate, length = Math.floor(inputLength / ratio);
      const buffer = new ArrayBuffer(44 + length * 2), view = new DataView(buffer);
      function write(offset, text) { for (let i = 0; i < text.length; i++) view.setUint8(offset + i, text.charCodeAt(i)); }
      write(0, 'RIFF'); view.setUint32(4, 36 + length * 2, true); write(8, 'WAVE'); write(12, 'fmt ');
      view.setUint32(16, 16, true); view.setUint16(20, 1, true); view.setUint16(22, 1, true);
      view.setUint32(24, targetRate, true); view.setUint32(28, targetRate * 2, true);
      view.setUint16(32, 2, true); view.setUint16(34, 16, true); write(36, 'data'); view.setUint32(40, length * 2, true);
      let offset = 44;
      for (let i = 0; i < length; i++) {
        // Browsers may keep a native sample rate despite the requested 16 kHz context.
        // Average source samples across each output interval when downsampling.
        const start = i * ratio, end = Math.min(inputLength, (i + 1) * ratio);
        let sum = 0;
        for (let index = Math.floor(start); index < Math.ceil(end); index++) {
          sum += samples[index] * (Math.min(index + 1, end) - Math.max(index, start));
        }
        const value = Math.max(-1, Math.min(1, sum / (end - start)));
        view.setInt16(offset, value < 0 ? value * 32768 : value * 32767, true); offset += 2;
      }
      return new Blob([buffer], { type: 'audio/wav' });
    }
    function cleanAudio(session) {
      if (!session || session.cleaned) return;
      session.cleaned = true; root.clearTimeout(session.timer);
      if (session.processor) session.processor.onaudioprocess = null;
      [session.processor, session.source, session.silent].forEach(part => { try { part?.disconnect(); } catch (_) {} });
      session.stream?.getTracks().forEach(track => track.stop());
      if (session.audioContext?.state !== 'closed') session.audioContext?.close().catch(() => {});
    }
    async function stopVoice(discard) {
      const epoch = ++voiceEpoch, session = recorder; recorder = null; voiceStarting = false;
      cleanAudio(session); mic.textContent = 'Speak'; find('[data-ea-cancel-voice]').hidden = true;
      if (!session) { if (!requestController) setBusy(false); return; }
      if (discard) { session.chunks = []; setBusy(false); say('Recording discarded.'); return; }
      if (!session.heardSpeech) { session.chunks = []; setBusy(false); say('No speech was heard. Try again or type your request.', true); return; }
      const audio = encodeWav(session.chunks, session.audioContext.sampleRate); session.chunks = [];
      if (audio.size > 5 * 1024 * 1024) { setBusy(false); say('Recording is too large. Try a shorter request.', true); return; }
      const data = new FormData(); data.append('audio', audio, 'request.wav');
      data.append(context?.capabilities?.voice_local ? 'transcription_consent' : 'use_ai', '1');
      voiceTranscribing = true; setBusy(true); say('Transcribing your recording…');
      try {
        const result = await request(panel.dataset.transcribeUrl, data);
        if (epoch !== voiceEpoch || panel.hidden) return;
        const transcript = String(result.transcript || result.text || '').trim();
        if (!transcript) throw new Error('No speech could be transcribed. Try again or type your request.');
        input.value = transcript; say('Review the transcript, then Send.'); input.focus();
      } catch (error) { if (epoch === voiceEpoch && !panel.hidden) say(error.message, true); }
      finally { if (epoch === voiceEpoch) { voiceTranscribing = false; setBusy(false); if (!panel.hidden) input.focus(); } }
    }
    function close() {
      if (recorder || voiceStarting) stopVoice(true);
      else if (voiceTranscribing) { voiceEpoch++; voiceTranscribing = false; requestController?.abort(); setBusy(false); }
      panel.hidden = true; backdrop.hidden = true; document.body.style.overflow = previousOverflow;
      openers.forEach(b => b.setAttribute('aria-expanded', 'false')); returnFocus?.focus();
    }
    async function startVoice() {
      if (recorder && recorder.state === 'recording') { stopVoice(false); return; }
      if (busy || voiceStarting) return;
      const AudioContextClass = root.AudioContext || root.webkitAudioContext;
      if (!AudioContextClass || !root.navigator.mediaDevices?.getUserMedia) { say('Voice recording is unavailable in this browser. Use localhost or HTTPS, or type your request instead.', true); return; }
      const epoch = ++voiceEpoch; voiceStarting = true; setBusy(true);
      let session = null;
      try {
        const acquired = await root.navigator.mediaDevices.getUserMedia({ audio: true });
        if (epoch !== voiceEpoch || panel.hidden) { acquired.getTracks().forEach(track => track.stop()); return; }
        session = { stream: acquired, chunks: [], heardSpeech: false, speechStartedAt: null, lastVoiceAt: root.performance.now(), state: 'starting' };
        recorder = session;
        try { session.audioContext = new AudioContextClass({ sampleRate: 16000 }); }
        catch (error) { if (error.name !== 'NotSupportedError') throw error; session.audioContext = new AudioContextClass(); }
        if (session.audioContext.state === 'suspended') await session.audioContext.resume();
        if (epoch !== voiceEpoch || panel.hidden) { cleanAudio(session); return; }
        session.source = session.audioContext.createMediaStreamSource(acquired);
        session.processor = session.audioContext.createScriptProcessor(4096, 1, 1);
        session.silent = session.audioContext.createGain(); session.silent.gain.value = 0;
        session.processor.onaudioprocess = event => {
          if (recorder !== session || session.state !== 'recording') return;
          const samples = new Float32Array(event.inputBuffer.getChannelData(0)); session.chunks.push(samples);
          let power = 0; for (let i = 0; i < samples.length; i++) power += samples[i] * samples[i];
          const now = root.performance.now(), level = Math.sqrt(power / samples.length);
          if (level > 0.018) {
            session.heardSpeech = true; session.lastVoiceAt = now;
            if (session.speechStartedAt === null) session.speechStartedAt = now;
          }
          if (session.heardSpeech && now - session.lastVoiceAt > 1400 && now - session.speechStartedAt > 700) stopVoice(false);
        };
        session.source.connect(session.processor); session.processor.connect(session.silent); session.silent.connect(session.audioContext.destination);
        session.state = 'recording'; mic.textContent = 'Stop recording'; mic.disabled = false; find('[data-ea-cancel-voice]').hidden = false; find('[data-ea-cancel-voice]').disabled = false;
        input.disabled = true; find('[data-ea-send]').disabled = true;
        say('Recording. Stops after about 1.5 seconds of silence or 30 seconds. Review the transcript before sending.');
        session.timer = root.setTimeout(() => stopVoice(false), 30000);
      } catch (error) {
        cleanAudio(session);
        if (epoch === voiceEpoch) { recorder = null; say(error.name === 'NotAllowedError' ? 'Microphone access was denied. Allow it in your browser settings or type your request.' : 'Could not start recording. Check your microphone or type your request.', true); }
      }
      finally { if (epoch === voiceEpoch) voiceStarting = false; if (!recorder && !requestController && !voiceStarting) setBusy(false); }
    }
    openers.forEach(button => button.addEventListener('click', open)); find('[data-ea-close]').addEventListener('click', close); backdrop.addEventListener('click', close);
    panel.addEventListener('keydown', event => {
      if (event.key === 'Escape') { event.preventDefault(); close(); }
      if (event.key === 'Tab') {
        const controls = [...panel.querySelectorAll('button,input,textarea,select,a[href],summary')].filter(el => !el.disabled && el.getClientRects().length);
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
      }
    });
    messageForm.addEventListener('submit', async event => {
      event.preventDefault(); if (busy || recorder || !input.value.trim()) return;
      const draft = emailView && !emailView.hidden ? { subject: emailForm.elements.subject.value, body: emailForm.elements.body.value } : null;
      const text = input.value.trim(); setBusy(true); say('Preparing a response…'); addMessage('user', text); input.value = '';
      try { const data = await request(panel.dataset.messageUrl, { message: text, conversation_uuid: conversation, use_ai: ai.checked, ...(draft ? { email_draft: draft } : {}) }); say(''); accept(data); }
      catch (error) { input.value = text; say(error.message, true); } finally { setBusy(false); input.focus(); }
    });
    emailForm?.elements.scope.addEventListener('change', () => {
      const direct = emailForm.elements.scope.value === 'direct';
      find('[data-ea-email-direct]').hidden = !direct;
      emailForm.elements.direct_email.required = direct;
      if (!direct) emailForm.elements.direct_email.value = '';
    });
    emailForm?.addEventListener('submit', event => {
      if (busy || !conversation || !emailForm.reportValidity()) { event.preventDefault(); return; }
      emailForm.elements.assistant_conversation_uuid.value = conversation;
    });
    find('[data-ea-email-close]')?.addEventListener('click', () => { emailView.hidden = true; say('Email editor closed. No email was sent.'); });
    form.addEventListener('submit', async event => {
      event.preventDefault(); if (busy || recorder || voiceStarting || !form.reportValidity()) return;
      const options = planningOptions();
      const error = selectionError(options, true);
      if (error) { say(error, true); return; }
      setBusy(true); say('Calculating a schedule preview…');
      try { accept(await request(panel.dataset.previewUrl, { conversation_uuid: conversation, options })); say('Review the proposed assignments before saving.'); }
      catch (error) { say(error.message, true); } finally { setBusy(false); }
    });
    find('[data-ea-publication]').addEventListener('click', async () => {
      if (busy || recorder || voiceStarting || !form.reportValidity()) return;
      const options = planningOptions();
      const error = selectionError(options, false);
      if (error) { say(error, true); return; }
      setBusy(true); say('Preparing the saved order of play for review…');
      try { accept(await request(panel.dataset.publicationUrl, { conversation_uuid: conversation, options: { date: options.start.slice(0, 10), draw_ids: options.draw_ids } })); }
      catch (error) { say(error.message, true); } finally { setBusy(false); }
    });
    find('[data-ea-generation]').addEventListener('click', async () => {
      if (busy || recorder || voiceStarting) return;
      const ids = new FormData(form).getAll('draw_ids');
      if (ids.length !== 1) { say('Select exactly one prepared round-robin draw. Choose players, groups and seeds in its draw guide first.', true); return; }
      setBusy(true); say('Checking the prepared round-robin draw…');
      try { accept(await request(panel.dataset.generationUrl, { conversation_uuid: conversation, draw_id: Number(ids[0]) })); }
      catch (error) { say(error.message, true); } finally { setBusy(false); }
    });
    mic.addEventListener('click', startVoice); find('[data-ea-cancel-voice]').addEventListener('click', () => stopVoice(true));
    find('[data-ea-new]').addEventListener('click', () => { if (busy || recorder) return; conversation = null; currentProposal = null; if (emailView) { emailView.hidden = true; emailForm.reset(); } log.replaceChildren(); renderProposal(null); input.value = ''; say('New conversation. Existing saved schedules are unchanged.'); input.focus(); });
    panel.querySelectorAll('[data-ea-prompt]').forEach(button => button.addEventListener('click', () => { input.value = button.dataset.eaPrompt; input.focus(); if (button.dataset.eaPrompt.includes('schedule')) find('[data-ea-planner]').open = true; }));
    find('[data-ea-remember]').addEventListener('click', async () => {
      if (busy) return; setBusy(true);
      try { await request(panel.dataset.preferencesUrl, { confirmed: true, preferences: { duration: Number(form.elements.duration.value), court_gap: Number(form.elements.court_gap.value), player_rest: Number(form.elements.player_rest.value), start_time: form.elements.start_time.value, end_time: form.elements.end_time.value } }, 'PUT'); say('Timing preferences remembered for this event and your account.'); }
      catch (error) { say(error.message, true); } finally { setBusy(false); }
    });
    find('[data-ea-review]').addEventListener('click', async () => {
      if (busy) return; setBusy(true);
      try { const data = await request(panel.dataset.improvementsUrl, undefined, 'GET'); find('[data-ea-metrics]').textContent = data.summary || (data.feedback || []).map(row => row.rating + ' / ' + row.category.replaceAll('_', ' ') + ': ' + row.count).join(' · ') || 'No feedback recorded yet.'; }
      catch (error) { say(error.message, true); } finally { setBusy(false); }
    });
    find('[data-ea-duration]')?.addEventListener('click', async () => {
      if (busy) return;
      const ids = new FormData(form).getAll('draw_ids');
      if (ids.length !== 1) { say('Select exactly one draw in Schedule details for a duration suggestion.', true); return; }
      setBusy(true);
      try {
        const data = await request(panel.dataset.durationUrl + '?draw_id=' + encodeURIComponent(ids[0]), undefined, 'GET');
        find('[data-ea-duration-result]').textContent = data.minutes + ' minutes · ' + data.samples + ' comparable observations. ' + data.message;
      } catch (error) { say(error.message, true); } finally { setBusy(false); }
    });
    find('[data-ea-timing-form]')?.addEventListener('submit', async event => {
      event.preventDefault(); if (busy) return;
      const data = new FormData(event.currentTarget); setBusy(true);
      try { const result = await request(panel.dataset.timingUrl, { fixture_kind: data.get('fixture_kind'), fixture_id: Number(data.get('fixture_id')), actual_start: data.get('actual_start'), actual_finish: data.get('actual_finish'), verified: data.get('verified') === 'on', interrupted: data.get('interrupted') === 'on' }); say(result.message); }
      catch (error) { say(error.message, true); } finally { setBusy(false); }
    });
    find('[data-ea-load-matches]')?.addEventListener('click', async () => {
      if (busy) return;
      const ids = new FormData(form).getAll('draw_ids');
      if (ids.length !== 1) { say('Select exactly one draw in Schedule details.', true); return; }
      setBusy(true);
      try {
        const data = await request(panel.dataset.timingMatchesUrl + '?draw_id=' + encodeURIComponent(ids[0]), undefined, 'GET');
        const target = find('[data-ea-timing-form]'); target.elements.fixture_kind.value = data.fixture_kind;
        const choices = target.elements.fixture_id; choices.replaceChildren();
        const placeholder = node('option', data.matches.length ? 'Choose a completed match' : 'No completed matches'); placeholder.value = ''; choices.append(placeholder);
        data.matches.forEach(row => { const option = node('option', row.label); option.value = row.id; choices.append(option); });
      } catch (error) { say(error.message, true); } finally { setBusy(false); }
    });
    find('[data-ea-draws]').addEventListener('change', () => {
      const choices = find('[data-ea-timing-form]')?.elements.fixture_id;
      if (!choices) return;
      const placeholder = node('option', 'Load matches from one selected draw'); placeholder.value = ''; choices.replaceChildren(placeholder);
    });
    find('[data-ea-shortcut]')?.addEventListener('click', event => {
      const help = find('#ea-shortcut-help');
      help.hidden = !help.hidden;
      event.currentTarget.setAttribute('aria-expanded', String(!help.hidden));
    });
    find('[data-ea-shortcut-copy]')?.addEventListener('click', async () => {
      const address = find('[data-ea-shortcut-url]');
      const feedback = find('[data-ea-shortcut-status]');
      try {
        await root.navigator.clipboard.writeText(address.value);
        feedback.textContent = 'Address copied. Paste it into Chrome or Safari to add your shortcut.';
      } catch (_) {
        address.focus(); address.select();
        feedback.textContent = 'Press and hold the selected address, then choose Copy.';
      }
    });
    if (panel.dataset.autoOpen === 'true') {
      if (new URLSearchParams(root.location.search).get('home-screen') === '1') {
        const help = find('#ea-shortcut-help');
        if (help) { help.hidden = false; find('[data-ea-shortcut]').setAttribute('aria-expanded', 'true'); }
      }
      open();
    }
    root.addEventListener('pagehide', () => { if (recorder || voiceStarting) stopVoice(true); requestController?.abort(); });
    return { open, close, accept, planningOptions };
  }
  root.CapeEventAssistant = { mount };
  if (root.document) {
    const initialize = () => mount(root.document.getElementById('event-assistant-panel'), root.document, root.fetch.bind(root));
    if (root.document.readyState === 'loading') root.document.addEventListener('DOMContentLoaded', initialize); else initialize();
  }
})(typeof window !== 'undefined' ? window : globalThis);
