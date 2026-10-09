/* Loaded only by the private Super Admin view. The endpoint enforces that role. */
(function (root) {
  'use strict';
  const config = root.CTPlayerRatingConfig;
  if (!config) return;
  const cache = new Map();
  let scheduled = false;
  let running = false;
  let pending = false;
  let version = null;
  let polling = false;
  const escape = value => String(value).replace(/[&<>"']/g, char => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'}[char]));

  root.CTPlayerRatings = {
    marker: function (identity, context = {}) {
      const id = Number(identity.playerId || identity.registrationId || identity.fixtureId);
      if (!Number.isSafeInteger(id) || id < 1) return '';
      const kind = identity.playerId ? 'player' : (identity.fixtureId ? 'fixture' : 'registration');
      const draw = Number(context.drawId || config.drawId || 0);
      const field = Number(context.categoryEventId || 0);
      return '<span data-rating-' + kind + '="' + id + '" data-rating-side="' + Number(identity.side || 1) + '" data-rating-draw="' + draw + '" data-rating-field="' + field + '"></span>';
    },
    refresh: schedule
  };

  function schedule() {
    if (scheduled) return;
    scheduled = true;
    root.setTimeout(() => { scheduled = false; scan(); }, 40);
  }
  function render(node, ratings) {
    if (node.dataset.ratingSvg) {
      node.innerHTML = ratings.map(rating => '<tspan class="player-rating-badge" dx="4" style="font-size:10px;fill:#075985;font-weight:700"><title>' + escape(rating.title) + '</title>[' + escape(rating.display_label || rating.label) + ']</tspan>').join('');
      node.dataset.ratingDone = '1';
      return;
    }
    node.innerHTML = ratings.map(rating => '<span class="badge player-rating-badge ms-1" style="font-size:.68rem;vertical-align:middle;background:#e0f2fe;color:#075985;padding:.2em .4em;border:1px solid #7dd3fc;white-space:nowrap" title="' + escape(rating.title) + '" aria-label="' + escape(rating.title) + '">' + escape(rating.display_label || rating.label) + '</span>').join('');
    node.dataset.ratingDone = '1';
  }
  function status(data) {
    if (!data) return;
    document.querySelectorAll('[data-rating-status]').forEach(node => {
      node.textContent = 'Last updated: ' + (data.last_updated || 'Not yet updated')
        + (data.failed ? ' · Update failed; retry pending.' : (data.pending ? ' · Update pending.' : ' · Up to date.'));
    });
    if (version !== null && version !== data.version) {
      cache.clear();
      document.querySelectorAll('[data-rating-player], [data-rating-registration], [data-rating-fixture]').forEach(node => {
        delete node.dataset.ratingDone;
        node.innerHTML = '';
      });
      schedule();
    }
    version = data.version;
  }
  async function poll() {
    if (document.hidden || polling || running) return;
    polling = true;
    try {
      const response = await fetch(config.endpoint, {credentials: 'same-origin', headers: {'Accept':'application/json'}});
      if (response.ok) status((await response.json()).status);
    } catch (_) { /* Retain the last successful badges during network failures. */ }
    finally { polling = false; }
  }
  async function scan() {
    if (running) { pending = true; return; }
    const groups = new Map();
    document.querySelectorAll('[data-rating-player]:not([data-rating-done]), [data-rating-registration]:not([data-rating-done]), [data-rating-fixture]:not([data-rating-done])').forEach(node => {
      const kind = node.dataset.ratingPlayer ? 'p' : (node.dataset.ratingFixture ? 'f' : 'r');
      const id = Number(node.dataset.ratingPlayer || node.dataset.ratingRegistration || node.dataset.ratingFixture);
      const side = Number(node.dataset.ratingSide || 1);
      if (kind === 'f' && side !== 1 && side !== 2) { node.dataset.ratingDone = '1'; return; }
      if (!Number.isSafeInteger(id) || id < 1) { node.dataset.ratingDone = '1'; return; }
      const draw = Number(node.dataset.ratingDraw === undefined ? (config.drawId || 0) : node.dataset.ratingDraw);
      const field = Number(node.dataset.ratingField || 0);
      const category = Number(node.dataset.ratingCategory || 0);
      const context = category ? 'c:' + category : (field ? 'f:' + field : 'd:' + draw);
      const identityKey = kind + ':' + id + (kind === 'f' ? ':' + side : '');
      const key = context + ':' + identityKey;
      if (cache.has(key)) { render(node, cache.get(key)); return; }
      if (!groups.has(context)) groups.set(context, {draw, field, category, entries: new Map()});
      const entries = groups.get(context).entries;
      if (!entries.has(identityKey)) entries.set(identityKey, {key, identityKey, kind, id, nodes: []});
      entries.get(identityKey).nodes.push(node);
    });
    if (!groups.size) return;
    running = true;
    try {
      for (const group of groups.values()) {
        const entries = Array.from(group.entries.values());
        for (let index = 0; index < entries.length; index += 100) {
          const batch = entries.slice(index, index + 100);
          const params = new URLSearchParams();
          if (group.category) params.set('category_id', group.category);
          else if (group.field) params.set('category_event_id', group.field);
          else if (group.draw) params.set('draw_id', group.draw);
          batch.forEach(entry => params.append(entry.kind === 'p' ? 'players[]' : (entry.kind === 'f' ? 'fixtures[]' : 'registrations[]'), entry.id));
          const response = await fetch(config.endpoint + '?' + params, {credentials: 'same-origin', headers: {'Accept':'application/json'}});
          if (!response.ok) { batch.forEach(entry => entry.nodes.forEach(node => { node.dataset.ratingDone = '1'; })); continue; }
          const data = await response.json();
          status(data.status);
          batch.forEach(entry => {
            const ratings = data.ratings[entry.identityKey] || [];
            cache.set(entry.key, ratings);
            entry.nodes.forEach(node => render(node, ratings));
          });
        }
      }
    } catch (_) { /* A failed private lookup leaves the original name unchanged. */ }
    finally { running = false; if (pending) { pending = false; schedule(); } }
  }
  const observer = new MutationObserver(schedule);
  function boot() {
    observer.observe(document.body, {childList:true, subtree:true});
    schedule(); poll();
    root.setInterval(poll, 60000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
}(window));
