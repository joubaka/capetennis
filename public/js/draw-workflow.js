(() => {
  'use strict';
  const journey = () => document.querySelector('[data-draw-journey]');
  const active = () => document.querySelectorAll('[data-draw-journey]').forEach(rail => {
    const value = location.hash.replace(/^#/, '').replace(/-(pane|tab)$/, '');
    const matrix = !value || value === 'matrix';
    const key = rail.dataset.workflowContext || (matrix && new URLSearchParams(location.search).get('review') === 'publication' ? 'review'
      : matrix && new URLSearchParams(location.search).get('view') === 'results' ? 'results'
      : ({settings:'setup', notes:'setup', groups:'players', matrix:'matches', standings:'results', 'main-bracket':'results', schedule:'schedule', oop:'schedule'})[value]
        || (document.getElementById('draw-format-form') ? 'setup' : rail.dataset.defaultStep || 'players'));
    rail.querySelectorAll('[data-journey-step]').forEach(link => {
      if (link.dataset.journeyStep === key) link.setAttribute('aria-current', 'step');
      else link.removeAttribute('aria-current');
    });
  });
  let refreshing = false, pending = false;
  async function refresh() {
    const current = journey();
    if (!current) return;
    if (refreshing) { pending = true; return; }
    refreshing = true;
    try {
      const response = await fetch(current.dataset.workflowUrl, {headers:{Accept:'application/json'}, cache:'no-store'});
      if (!response.ok) throw Error('Progress could not be refreshed. Reload to review saved progress.');
      const data = await response.json();
      if (data.workflow_html) {
        const container = document.createElement('div');
        container.innerHTML = data.workflow_html;
        const replacement = container.querySelector('[data-draw-journey]');
        if (replacement) document.querySelectorAll('[data-draw-journey]').forEach(rail => {
          if (rail.dataset.workflowUrl !== current.dataset.workflowUrl) return;
          const updated = replacement.cloneNode(true);
          updated.dataset.workflowContext = rail.dataset.workflowContext || '';
          rail.replaceWith(updated);
        });
      }
      active();
    } catch (error) {
      const next = journey()?.querySelector('[data-journey-next]');
      if (next) { next.textContent = 'Reload to review saved progress'; next.href = location.href; }
    } finally {
      refreshing = false;
      if (pending) { pending = false; refresh(); }
    }
  }
  window.addEventListener('hashchange', active);
  window.addEventListener('popstate', active);
  window.addEventListener('draw-workflow-saved', refresh);
  window.addEventListener('draw-workflow-mounted', active);
  document.addEventListener('click', event => {
    if (event.target.closest('[data-journey-create-matches]')) {
      document.getElementById('btn-regenerate-fixtures')?.click();
      return;
    }
    if (!event.target.closest('[data-draw-journey] a')) return;
    if (document.getElementById('fm-app')?.dataset.dirty === 'true' || window.RRGroups?.isDirty() || window.RRGroups?.isBusy()) {
      event.preventDefault();
      window.alert('Save or discard your player edits before leaving this step.');
    }
  });
  if (window.jQuery) window.jQuery(document).ajaxSuccess((event, xhr, settings) => {
    if ((settings.type || 'GET').toUpperCase() !== 'GET') refresh();
  });
  active();
})();
