(() => {
  'use strict';
  const status = document.querySelector('[data-share-status]');
  document.querySelectorAll('[data-share-draw]').forEach(button => button.addEventListener('click', async () => {
    if (button.disabled) return;
    try {
      if (navigator.share) await navigator.share({ title: document.title, url: button.dataset.shareDraw });
      else if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(button.dataset.shareDraw);
        if (status) status.textContent = 'Public draw link copied.';
      } else window.prompt('Copy the public draw link:', button.dataset.shareDraw);
    } catch (error) {
      if (error.name !== 'AbortError' && status) status.textContent = 'Could not share. Open Public view and copy its address.';
    }
  }));
  document.querySelectorAll('[data-workspace-switch]').forEach(link => link.addEventListener('click', () => {
    const allowed = ['groups', 'matrix', 'standings', 'main-bracket', 'schedule', 'oop', 'settings', 'notes', 'print'];
    const context = location.hash.slice(1) || document.querySelector('[data-workspace-context]')?.dataset.workspaceContext;
    if (allowed.includes(context)) link.hash = context;
  }));
  document.querySelectorAll('[data-workspace-publish-schedule]').forEach(button => button.addEventListener('click', async () => {
    if (!window.confirm(button.textContent.trim() + '?')) return;
    button.disabled = true;
    try {
      const response = await fetch(button.dataset.workspacePublishSchedule, { method: 'POST', headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json'
      }});
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Publication failed.');
      button.textContent = data.oop_published ? 'Unpublish schedule' : 'Publish schedule';
      if (status) status.textContent = data.oop_published ? 'Schedule published; it is visible when the draw is public.' : 'Schedule unpublished.';
    } catch (error) { if (status) status.textContent = error.message; }
    finally { button.disabled = false; }
  }));
  const workspace = document.getElementById('flexible-draw-workspace');
  document.querySelector('[data-workspace-notes]')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    try {
      const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Could not save notes.');
      const notes = {};
      form.querySelectorAll('textarea').forEach(field => { notes[field.name.slice(6, -1)] = field.value; });
      window.dispatchEvent(new CustomEvent('draw-workspace-notes-saved', { detail: notes }));
      if (status) status.textContent = 'Rules and notes saved.';
    } catch (error) { if (status) status.textContent = error.message; }
    finally { button.disabled = false; }
  });
  document.querySelectorAll('[data-workspace-lock]').forEach(button => button.addEventListener('click', async () => {
    if (document.getElementById('fm-app')?.dataset.dirty === 'true') {
      if (status) status.textContent = 'Save your starting-position edits before locking the draw.';
      return;
    }
    if (!window.confirm(button.textContent.trim() + '? Save any starting-position edits first.')) return;
    button.disabled = true;
    try {
      const response = await fetch(button.dataset.workspaceLock, { method: 'POST', headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json'
      }});
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Lock update failed.');
      location.reload();
    } catch (error) { if (status) status.textContent = error.message; button.disabled = false; }
  }));
  if (!workspace) return;
  const aliases = { standings: 'matrix', 'main-bracket': 'matrix', oop: 'schedule', notes: 'settings' };
  function open() {
    const hash = location.hash.slice(1).replace(/-(tab|pane)$/, '');
    const tab = aliases[hash] || (['groups', 'matrix', 'schedule', 'settings', 'print'].includes(hash) ? hash : 'matrix');
    const panel = ['groups', 'matrix'].includes(tab) ? 'editor' : tab;
    workspace.querySelectorAll('[data-flexible-panel]').forEach(element => { element.hidden = element.dataset.flexiblePanel !== panel; });
    workspace.querySelectorAll('[data-flexible-tab]').forEach(button => {
      const active = button.dataset.flexibleTab === tab;
      button.classList.toggle('active', active);
      button.setAttribute('aria-pressed', String(active));
    });
    window.dispatchEvent(new Event('resize'));
  }
  workspace.querySelectorAll('[data-flexible-tab]').forEach(button => button.addEventListener('click', () => { location.hash = button.dataset.flexibleTab; }));
  window.addEventListener('hashchange', open);
  document.getElementById('fm-workspace-print').addEventListener('click', () => {
    workspace.classList.remove('print-timetable');
    window.print();
  });
  document.getElementById('fm-timetable-print').addEventListener('click', () => {
    workspace.classList.add('print-timetable');
    window.print();
  });
  window.addEventListener('afterprint', () => workspace.classList.remove('print-timetable'));
  open();
})();
