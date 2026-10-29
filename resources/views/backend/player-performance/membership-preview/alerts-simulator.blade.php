<style>.mp-alert-options{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,180px),1fr));gap:.8rem}.mp-alert-options fieldset{border:1px solid #dbe4df;border-radius:12px;padding:.8rem;min-width:0}.mp-alert-options legend{font-size:.9rem;font-weight:700;float:none;width:auto;padding:0 .25rem}.mp-alert-options label,.mp-alert-toggle{display:flex;align-items:center;gap:.6rem;min-height:44px;font-size:.85rem}.mp-alert-options input,.mp-alert-toggle input{width:19px;height:19px;flex:none;accent-color:#146452}.mp-alert-controls{display:flex;gap:.5rem;flex-wrap:wrap}.mp-alert-controls button{flex:1;min-width:145px}.mp-alert-message{white-space:pre-line}.mp-alert-preferences select{width:100%}.mp-alert-preferences .mp-filters{display:block}.mp-alert-preferences .mp-filters>div{margin-bottom:.75rem}.mp-alert-controls .mp-secondary{background:white;color:#146452}</style>
<div class="mp-note mb-3"><strong>Fictional simulation · all delivery off</strong><br>No real players, events, draws, contacts or account preferences are connected. Choices are kept only in this browser tab using session storage. This is not an email subscription.</div>
<noscript><div class="alert alert-warning">Enable JavaScript to try the browser-only simulation. No preferences or messages are submitted to the server.</div></noscript>
<div id="mp-alert-simulator" hidden>
<section class="mp-card mb-3"><div class="mp-topline"><h2 class="mb-0">1. Choose what to follow</h2><span class="mp-chip">Opt-in only</span></div><p class="mp-subtle mt-2">All examples below are fictional. Follow a player, a group or an event to allow its on-screen examples.</p>
<div class="mp-alert-options">
@foreach(['Players' => ['alex' => 'Demo Alex · U12 singles', 'sam' => 'Demo Sam · U11 singles'], 'Age groups' => ['u12' => 'Demo U12 singles', 'u11' => 'Demo U11 singles'], 'Events' => ['coastal' => 'Demo Coastal Cup', 'riverside' => 'Demo Riverside Festival']] as $heading => $choices)
<fieldset><legend>{{ $heading }}</legend>@foreach($choices as $key => $label)<label><input type="checkbox" data-alert-watch="{{ $key }}">{{ $label }}</label>@endforeach</fieldset>
@endforeach
</div></section>
<div class="mp-grid">
<section class="mp-card mp-alert-preferences"><h2>2. Set your preferences</h2><div class="mp-filters"><div><label for="mp-alert-frequency">Frequency</label><select id="mp-alert-frequency"><option value="prompt">Prompt queueing · example only</option><option value="digest">Daily digest · example only</option><option value="off">Off</option></select></div></div><p class="mp-subtle">Prompt describes queueing, not a delivery-time guarantee. No delivery or actual queue runs in this preview.</p>
<fieldset><legend class="h6">Alert types</legend><label class="mp-alert-toggle"><input type="checkbox" data-alert-type="published">First draw publication</label><label class="mp-alert-toggle"><input type="checkbox" data-alert-type="updated">Published draw updated</label><label class="mp-alert-toggle"><input type="checkbox" data-alert-type="schedule">Schedule changed</label></fieldset>
<div class="mp-alert-controls mt-3"><button type="button" class="mp-btn mp-secondary" id="mp-alert-pause" aria-pressed="false">Pause examples</button><button type="button" class="mp-btn mp-secondary" id="mp-alert-unsubscribe">Unsubscribe all examples</button><button type="button" class="mp-btn mp-secondary" id="mp-alert-reset">Reset this preview</button></div><p class="mp-subtle mt-2 mb-0" id="mp-alert-storage">Preferences stay in this browser tab only; they do not change your account.</p>
</section>
<section class="mp-card mp-alert-preferences"><h2>3. Try an example</h2><div class="mp-filters"><div><label for="mp-alert-event">Fictional event</label><select id="mp-alert-event"><option value="coastal">Demo Coastal Cup · Alex · U12 singles</option><option value="riverside">Demo Riverside Festival · Sam · U11 singles</option></select></div><div><label for="mp-alert-scenario">Example change</label><select id="mp-alert-scenario"><option value="published">First draw publication</option><option value="updated">Published draw updated</option><option value="schedule">Schedule changed</option></select></div></div><button type="button" class="mp-btn" id="mp-alert-simulate">Preview this alert</button><p id="mp-alert-status" class="mp-note mt-3" role="status" aria-live="polite">Choose something to follow, then preview an example.</p>
<div class="mp-alert-example mt-3" role="region" aria-label="Sample notification"><div class="mp-eyebrow">On-screen sample · delivery off</div><h3 id="mp-alert-title" class="mt-2">Your sample will appear here</h3><p class="mp-subtle mp-alert-message mb-0" id="mp-alert-message">No real message is created, queued or sent.</p></div>
</section>
</div>
</div>
<p class="mp-footer">Essential participation, cancellation and required tournament information would remain free. The proposed paid benefit is convenient optional alerts. Real delivery, contact verification and account preferences are not implemented here.</p>
<script>
(() => {
    'use strict';
    const root = document.getElementById('mp-alert-simulator');
    if (!root) return;
    const storageKey = 'ct.player-plus.alerts-preview.v1';
    const watchKeys = ['alex', 'sam', 'u12', 'u11', 'coastal', 'riverside'];
    const types = ['published', 'updated', 'schedule'];
    const defaults = () => ({watches: Object.fromEntries(watchKeys.map(key => [key, false])), types: {published: true, updated: true, schedule: true}, frequency: 'prompt', paused: false});
    const clean = value => {
        const result = defaults();
        if (!value || typeof value !== 'object') return result;
        watchKeys.forEach(key => { result.watches[key] = value.watches?.[key] === true; });
        types.forEach(key => { result.types[key] = value.types?.[key] === true; });
        result.frequency = ['prompt', 'digest', 'off'].includes(value.frequency) ? value.frequency : 'prompt';
        result.paused = value.paused === true;
        return result;
    };
    let preferences = defaults();
    let storageAvailable = true;
    try { const saved = sessionStorage.getItem(storageKey); if (saved) preferences = clean(JSON.parse(saved)); } catch { storageAvailable = false; }
    const element = id => document.getElementById(id);
    const status = message => { element('mp-alert-status').textContent = message; };
    const seenFirst = new Set();
    const events = {coastal: {name: 'Demo Coastal Cup', player: 'alex', group: 'u12', label: 'Demo U12 singles'}, riverside: {name: 'Demo Riverside Festival', player: 'sam', group: 'u11', label: 'Demo U11 singles'}};
    const update = () => {
        root.querySelectorAll('[data-alert-watch]').forEach(input => { input.checked = preferences.watches[input.dataset.alertWatch]; });
        root.querySelectorAll('[data-alert-type]').forEach(input => { input.checked = preferences.types[input.dataset.alertType]; });
        element('mp-alert-frequency').value = preferences.frequency;
        element('mp-alert-pause').textContent = preferences.paused ? 'Resume examples' : 'Pause examples';
        element('mp-alert-pause').setAttribute('aria-pressed', String(preferences.paused));
        if (!storageAvailable) element('mp-alert-storage').textContent = 'Browser storage is unavailable. Choices last only while this page stays open; nothing is submitted.';
    };
    const save = () => { try { sessionStorage.setItem(storageKey, JSON.stringify(preferences)); } catch { storageAvailable = false; } update(); };
    const clearSample = () => { element('mp-alert-title').textContent = 'Your sample will appear here'; element('mp-alert-message').textContent = 'No real message is created, queued or sent.'; };
    root.querySelectorAll('[data-alert-watch]').forEach(input => input.addEventListener('change', () => { preferences.watches[input.dataset.alertWatch] = input.checked; clearSample(); save(); status('Following choices updated in this browser tab. All delivery stays off.'); }));
    root.querySelectorAll('[data-alert-type]').forEach(input => input.addEventListener('change', () => { preferences.types[input.dataset.alertType] = input.checked; clearSample(); save(); status('Alert-type preferences updated locally. All delivery stays off.'); }));
    element('mp-alert-frequency').addEventListener('change', event => { preferences.frequency = event.target.value; clearSample(); save(); status('Frequency updated locally. No real queue or delivery is enabled.'); });
    element('mp-alert-pause').addEventListener('click', () => { preferences.paused = !preferences.paused; clearSample(); save(); status(preferences.paused ? 'Examples paused. No alert will be previewed until resumed.' : 'Examples resumed. Real delivery remains off.'); });
    element('mp-alert-unsubscribe').addEventListener('click', () => { preferences = defaults(); preferences.frequency = 'off'; seenFirst.clear(); clearSample(); save(); status('All fictional follows cleared and examples switched off. No real subscription existed.'); });
    element('mp-alert-reset').addEventListener('click', () => { preferences = defaults(); seenFirst.clear(); clearSample(); try { sessionStorage.removeItem(storageKey); } catch { storageAvailable = false; } update(); status('Preview reset. Nothing is followed and real delivery remains off.'); });
    element('mp-alert-simulate').addEventListener('click', () => {
        const eventKey = element('mp-alert-event').value;
        const event = events[eventKey];
        const type = element('mp-alert-scenario').value;
        if (!event || !types.includes(type)) return;
        if (preferences.paused || preferences.frequency === 'off') { status('Suppressed: examples are paused or frequency is off. Nothing is sent.'); return; }
        if (!preferences.watches[eventKey] && !preferences.watches[event.player] && !preferences.watches[event.group]) { status('Suppressed: follow this fictional player, group or event first. Nothing is sent.'); return; }
        if (!preferences.types[type]) { status('Suppressed: this alert type is switched off. Nothing is sent.'); return; }
        if (type === 'published' && seenFirst.has(eventKey)) { status('First publication already previewed for this event on this page. No duplicate example was added.'); return; }
        if (type === 'published') seenFirst.add(eventKey);
        const subjects = {published: 'Your draw has been published', updated: 'Your published draw has changed', schedule: 'Your match schedule has changed'};
        const bodies = {published: 'The fictional draw is ready to review.', updated: 'A fictional published draw has a new version. Review it before making plans.', schedule: 'A fictional match now has a different time or court. Recheck the latest schedule.'};
        element('mp-alert-title').textContent = preferences.frequency === 'digest' ? 'Your daily draw summary · sample' : subjects[type] + ' · sample';
        element('mp-alert-message').textContent = event.name + ' / ' + event.label + '\n' + subjects[type] + '. ' + bodies[type] + '\nSimulation only. No real draw, match, contact or delivery is connected.';
        status(preferences.frequency === 'digest' ? 'This change is shown inside a fictional daily digest. No digest is scheduled or sent.' : 'Prompt-queue example shown on screen. No queue runs and no delivery time is promised.');
    });
    update();
    root.hidden = false;
})();
</script>
