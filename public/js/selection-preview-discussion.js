(function () {
  'use strict';
  document.addEventListener('submit', event => {
    if (event.target.matches('[data-finalize-teams]') && !window.confirm(event.target.dataset.confirm)) event.preventDefault();
  });
  const modal = document.getElementById('selection-note-modal');
  if (!modal) return;
  const status = modal.querySelector('[data-preview-note-status]');
  const thread = modal.querySelector('[data-preview-discussion]');
  const form = modal.querySelector('[data-preview-comment-form]');
  const more = modal.querySelector('[data-preview-more]');
  let target, version, sequence = 0, nextPage, busy = false;
  async function request(url, options) {
    const response = await fetch(url, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', ...options?.headers } });
    const data = await response.json();
    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'The discussion could not be loaded.');
    return data;
  }
  async function load(append = false) {
    const current = sequence;
    const url = new URL(modal.dataset.noteUrl, window.location.href);
    url.searchParams.set('group_key', target.group); url.searchParams.set('player_key', target.player);
    status.textContent = 'Loading discussion…';
    try {
      const data = await request(append ? nextPage : url.href);
      if (current !== sequence) return;
      version = data.draft_version;
      modal.querySelector('[data-preview-original-note]').textContent = data.original_note || 'No note recorded in the saved draft.';
      if (!append) thread.replaceChildren();
      data.comments.forEach(comment => {
        const article = document.createElement('article'); article.className = 'border-bottom py-2';
        const heading = document.createElement('strong'); heading.textContent = comment.author;
        const context = document.createElement('small'); context.className = 'text-muted d-block'; context.textContent = new Date(comment.created_at).toLocaleString() + ' · Draft version ' + comment.draft_version;
        const body = document.createElement('p'); body.style.whiteSpace = 'pre-wrap'; body.textContent = comment.body;
        article.append(heading, context, body); thread.append(article);
      });
      nextPage = data.next_page_url; more.hidden = !nextPage;
      status.textContent = 'Comments discuss the saved draft and do not change selections.';
      form.querySelector('button').disabled = false;
    } catch (error) { if (current === sequence) { status.textContent = error.message; form.querySelector('button').disabled = true; } }
  }
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-preview-note]');
    if (!button) return;
    sequence++; target = { group: button.dataset.groupKey, player: button.dataset.playerKey }; version = null;
    modal.querySelector('#selection-note-title').textContent = button.dataset.playerName + ' · Note and discussion';
    modal.querySelector('[data-preview-original-note]').textContent = ''; thread.replaceChildren(); form.reset(); more.hidden = true;
    form.querySelector('button').disabled = true;
    bootstrap.Modal.getOrCreateInstance(modal).show(); load();
  });
  more.addEventListener('click', () => { if (nextPage) load(true); });
  form.addEventListener('submit', async event => {
    event.preventDefault(); if (busy || !target || !version) return;
    const current = sequence; busy = true; form.querySelector('button').disabled = true; status.textContent = 'Saving comment…';
    try {
      await request(modal.dataset.noteUrl, { method: 'POST', body: JSON.stringify({ group_key: target.group, player_key: target.player, draft_version: version, body: form.elements.body.value }) });
      if (current === sequence) { form.elements.body.value = ''; await load(); }
    } catch (error) { if (current === sequence) status.textContent = error.message; }
    finally { busy = false; if (current === sequence) form.querySelector('button').disabled = false; }
  });
})();
