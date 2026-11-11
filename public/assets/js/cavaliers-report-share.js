(() => {
  const root = document.querySelector('[data-roster-report]');
  if (!root) return;
  const el = (s) => root.querySelector(s);
  const chosen = new Map();
  let snapshot = null;
  let busy = false;
  const status = (text) => { el('[data-status]').textContent = text; };
  const invalidate = () => { snapshot = null; el('[data-review]').hidden = true; };
  async function request(url, data) {
    const response = await fetch(url, {method: data ? 'POST' : 'GET', headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, ...(data ? {body: JSON.stringify(data)} : {})});
    const result = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Request failed. Please try again.');
    return result;
  }
  async function run(action) {
    if (busy) return;
    busy = true;
    root.querySelectorAll('button').forEach((b) => { b.disabled = true; });
    status('Loading…');
    try { await action(); } catch (error) { status(error.message); }
    finally { busy = false; root.querySelectorAll('button').forEach((b) => { b.disabled = false; }); }
  }
  function control(text, action) {
    const b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-sm btn-outline-primary'; b.textContent = text; b.addEventListener('click', action); return b;
  }
  function renderChosen() {
    el('[data-chosen]').replaceChildren();
    chosen.forEach((user) => {
      const row = document.createElement('li'); row.className = 'list-group-item d-flex flex-wrap justify-content-between gap-2';
      const name = document.createElement('span'); name.textContent = `${user.name} <${user.email}>`; row.append(name, control('Remove', () => { chosen.delete(user.id); invalidate(); renderChosen(); })); el('[data-chosen]').append(row);
    });
  }
  el('[data-find]').addEventListener('submit', (event) => {
    event.preventDefault();
    run(async () => {
      const data = await request(`${root.dataset.url}?q=${encodeURIComponent(el('input').value)}`);
      el('[data-results]').replaceChildren();
      data.users.forEach((user) => {
        const row = document.createElement('div'); row.className = 'list-group-item d-flex flex-wrap justify-content-between gap-2';
        const name = document.createElement('span'); name.textContent = `${user.name} <${user.email}>`;
        row.append(name, control('Add recipient', () => { if (chosen.size >= 30 && !chosen.has(user.id)) { status('Choose at most 30 recipients.'); return; } chosen.set(user.id, user); invalidate(); renderChosen(); })); el('[data-results]').append(row);
      }); status(data.users.length === 20 ? 'Showing the first 20 matches. Refine your search to find other accounts.' : data.users.length ? 'Choose recipients below. Search again to add more.' : 'No matching accounts found.');
    });
  });
  el('[data-preview]').addEventListener('click', () => run(async () => {
    invalidate(); snapshot = await request(root.dataset.previewUrl, {recipient_ids: Array.from(chosen.keys())});
    el('[data-reviewed-recipients]').textContent = `To: ${snapshot.recipients.map((u) => `${u.name} <${u.email}>`).join(', ')}`;
    el('[data-email]').srcdoc = snapshot.html; el('[data-pdf]').src = snapshot.pdf_url; el('[data-pdf-link]').href = snapshot.pdf_url;
    el('[data-review]').hidden = false; status('Review both previews. Sending shares a private copy with these recipients.');
  }));
  el('[data-send]').addEventListener('click', () => run(async () => {
    if (!snapshot) throw new Error('Prepare the preview first.');
    const data = await request(snapshot.send_url, {}); invalidate(); status(`${data.queued} email(s) queued. Queueing does not confirm delivery.`);
  }));
})();
