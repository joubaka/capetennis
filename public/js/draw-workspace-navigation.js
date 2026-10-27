(() => {
  'use strict';
  if (new URLSearchParams(location.search).get('review') === 'publication') {
    const toggle = document.querySelector('[data-publication-review-toggle]');
    if (toggle) {
      const tools = toggle.closest('details');
      if (tools) tools.open = true;
      window.bootstrap?.Dropdown.getOrCreateInstance(toggle).show();
      toggle.focus();
    }
  }
  const status = document.querySelector('[data-share-status]');
  const publicationStatus = (button, message, failed = false) => {
    const target = button.closest('[data-draw-publication]')?.querySelector('[data-publication-status]') || status;
    if (!target) return;
    target.textContent = message;
    target.classList.toggle('text-danger', failed);
    target.classList.toggle('text-muted', !failed);
  };
  const paintPublication = (button, published, kind) => {
    button.textContent = `${published ? 'Unpublish' : 'Publish'} ${kind}`;
    button.setAttribute('aria-pressed', String(published));
    button.classList.toggle('btn-success', published);
    button.classList.toggle('btn-outline-primary', !published);
  };
  document.querySelectorAll('[data-workspace-publish-draw-proxy]').forEach(button => button.addEventListener('click', () => {
    const editorButton = document.getElementById('fm-publish');
    if (!editorButton || editorButton.disabled || editorButton.hidden) return;
    editorButton.click();
  }));
  document.querySelectorAll('[data-workspace-publish-draw]').forEach(button => button.addEventListener('click', async () => {
    if (button.disabled || !window.confirm(button.textContent.trim() + '?')) return;
    button.disabled = true;
    try {
      const flexible = button.dataset.flexibleRevision !== undefined;
      const response = await fetch(button.dataset.workspacePublishDraw, {method:'POST', headers:{
        'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,
        Accept:'application/json', 'Content-Type':'application/json'
      }, body: JSON.stringify(flexible ? {revision:Number(button.dataset.flexibleRevision),published:button.dataset.published !== '1'} : {})});
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Publication failed. Reload this page and try again.');
      button.dataset.published = data.published ? '1' : '0';
      if (flexible) button.dataset.flexibleRevision = String(data.revision);
      paintPublication(button, Boolean(data.published), 'draw');
      publicationStatus(button, data.published ? 'Draw published. Schedule publication is unchanged.' : 'Draw unpublished. Schedule publication is unchanged.');
    } catch (error) { publicationStatus(button, error.message, true); }
    finally { button.disabled = false; }
  }));
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
    if (button.disabled || !window.confirm(button.textContent.trim() + '?')) return;
    const siblings = [...document.querySelectorAll('[data-workspace-publish-schedule]')].filter(item => item.dataset.workspacePublishSchedule === button.dataset.workspacePublishSchedule);
    siblings.forEach(item => { item.disabled = true; });
    try {
      const response = await fetch(button.dataset.workspacePublishSchedule, { method: 'POST', headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json'
      }});
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Publication failed.');
      siblings.forEach(item => paintPublication(item, Boolean(data.oop_published), 'schedule'));
      publicationStatus(button, data.oop_published
        ? (data.preview_only ? 'Times published for authorized front-page preview; the draw remains hidden from the public.' : 'Schedule published and visible with the public draw.')
        : 'Schedule unpublished.');
    } catch (error) { publicationStatus(button, error.message, true); }
    finally { siblings.forEach(item => { item.disabled = false; }); }
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
    const defaultTab = workspace.dataset.hasGeneratedDraw === '1' ? 'matrix' : 'groups';
    const tab = aliases[hash] || (['groups', 'matrix', 'schedule', 'settings', 'print'].includes(hash) ? hash : defaultTab);
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
    workspace.classList.remove('print-draw-only', 'print-timetable');
    window.print();
  });
  document.getElementById('fm-draw-only-print').addEventListener('click', () => {
    workspace.classList.remove('print-timetable');
    workspace.classList.add('print-draw-only');
    window.print();
  });
  document.getElementById('fm-timetable-print').addEventListener('click', () => {
    workspace.classList.remove('print-draw-only');
    workspace.classList.add('print-timetable');
    window.print();
  });
  window.addEventListener('afterprint', () => workspace.classList.remove('print-draw-only', 'print-timetable'));
  open();
})();
