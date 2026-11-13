// Fictional browser fixture only. All fetches stay in memory; no app endpoint is contacted.
function installFixture() {
  const player = id => ({id, name: `Fictional Player ${String(id).padStart(2, '0')}`});
  let revision = 1;
  const nextRevision = () => `00000000-0000-4000-8000-${String(revision++).padStart(12, '0')}`;
  const candidates = Array.from({length: 50}, (_, i) => ({...player(i + 1), source: 'Inter-Districts', region: 'Fictional region', wins: 1, losses: 1, matches: []}));
  const publication = {published: true, fingerprint: 'a'.repeat(64), players: [player(70)]};
  const fixture = window.rosterTransferFixture = {mode: 'success', delay: 0, puts: [], publicationCalls: 0,
    draft: {revision: nextRevision(), selected: [player(1), player(2)], reserves: [player(3)], declined: [player(4)], publication}};
  const response = (body, status = 200) => new Response(JSON.stringify(body), {status, headers: {'Content-Type': 'application/json'}});
  const save = body => { fixture.draft = {...fixture.draft, revision: nextRevision(), ...Object.fromEntries(['selected', 'reserves', 'declined'].map(key => [key, body[key].map(player)]))}; };
  window.fetch = async (input, options = {}) => {
    const url = new URL(input, location.href);
    if (options.method === 'PUT') {
      const body = JSON.parse(options.body); fixture.puts.push(body);
      const mode = fixture.mode;
      fixture.mode = 'success'; document.querySelector('[data-fixture-mode]').value = 'success';
      await new Promise(resolve => setTimeout(resolve, fixture.delay));
      if (mode === 'reject422') return response({message: 'Fictional eligibility rejection.'}, 422);
      if (mode === 'conflict409') { fixture.draft.revision = nextRevision(); return response({message: 'Fictional draft changed in another session.'}, 409); }
      if (body.revision !== fixture.draft.revision) return response({message: 'Stale fixture revision.'}, 409);
      const all = [...body.selected, ...body.reserves, ...body.declined];
      if (body.selected.length > 32 || body.reserves.length > 5 || new Set(all).size !== all.length) return response({message: 'Fixture roster limits or duplicate violation.'}, 422);
      save(body);
      if (mode === 'commit503') return response({message: 'Fictional server response failed after saving.'}, 503);
      if (mode === 'network') throw new TypeError('Fictional connection lost after saving.');
      if (mode === 'invalid200') return response({});
      return response({revision: fixture.draft.revision});
    }
    if (options.method && options.method !== 'GET') { fixture.publicationCalls++; return response({message: 'No publication is allowed in this fixture.'}, 403); }
    if (url.pathname.endsWith('/candidates')) return response({candidates});
    if (url.pathname.endsWith('/players')) return response(candidates.filter(item => item.name.toLowerCase().includes((url.searchParams.get('q') || '').toLowerCase())));
    if (url.pathname.endsWith('/setup')) return response({regions: [], region_ids: [], excluded_result_region_ids: []});
    return response(fixture.draft);
  };
  fixture.full = () => {
    fixture.draft = {...fixture.draft, revision: nextRevision(), selected: candidates.slice(0, 32).map(({id}) => player(id)), reserves: candidates.slice(32, 37).map(({id}) => player(id)), declined: [player(38)]};
  };
  document.querySelector('[data-fixture-mode]').addEventListener('change', event => { fixture.mode = event.target.value; });
  document.querySelector('[data-fixture-delay]').addEventListener('input', event => { fixture.delay = Number(event.target.value); });
  document.querySelector('[data-fixture-full]').addEventListener('click', () => { fixture.full(); document.querySelector('[data-reload]').click(); });
  document.querySelector('[data-fixture-reload]').addEventListener('click', () => document.querySelector('[data-reload]').click());
}

function buildFixture(fragment) {
  return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="fictional-fixture"><title>Fictional roster transfer QA</title><link rel="stylesheet" href="/public/assets/vendor/css/core.css"><link rel="stylesheet" href="/public/assets/vendor/css/theme-default.css"></head><body style="padding:1rem"><aside class="border rounded p-2 mb-3"><strong>Fictional local QA only</strong><label class="mx-2">Next PUT <select data-fixture-mode><option value="success">Success</option><option value="reject422">Reject 422</option><option value="conflict409">Stale 409</option><option value="commit503">Save then 503</option><option value="network">Save then connection loss</option><option value="invalid200">Save then invalid response</option></select></label><label>Delay ms <input data-fixture-delay type="number" value="0" min="0" max="5000" style="width:6rem"></label><button data-fixture-full>Load full32/5</button><button data-fixture-reload>Reload saved</button></aside>${fragment}<script>(${installFixture.toString()})()</script><script src="/public/assets/vendor/js/bootstrap.js"></script><script src="/public/assets/js/cavaliers-trial-roster.js"></script></body></html>`;
}

module.exports = {buildFixture};

if (require.main === module) {
  const fs = require('node:fs');
  const path = require('node:path');
  const directory = process.env.CT_ROSTER_TRANSFER_QA_OUTPUT || 'storage/app/testing/cavaliers-transfer-qa';
  fs.writeFileSync(path.join(directory, 'index.html'), buildFixture(fs.readFileSync(path.join(directory, 'roster.html'), 'utf8')));
}
