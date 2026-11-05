(function (root) {
  'use strict';
  if (root.document) {
    let navigationLoading, navigationTimeout;
    const closeNavigationLoading = () => {
      navigationLoading?.close();
      navigationLoading = null;
      root.clearTimeout(navigationTimeout);
    };
    root.document.addEventListener('click', event => {
      const link = event.target.closest('[data-pathway-navigation]');
      if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target === '_blank') return;
      closeNavigationLoading();
      navigationLoading = root.CapeLoading?.show({title: 'Loading your tennis pathway', message: 'Please wait while we load your latest saved badge and pathway.'});
      // Let normal browser navigation retain its usual errors and retry controls.
      navigationTimeout = root.setTimeout(closeNavigationLoading, 30000);
    });
    root.addEventListener('pageshow', closeNavigationLoading);
  }
  class PlayerPathwayUI {
    constructor(panel, reload, options = {}) {
      this.panel = panel;
      this.reload = reload;
      this.csrf = options.csrf || '';
      this.signal = options.signal;
      this.pending = false;
      // Delegate from the persistent panel: its contents are replaced after month/filter changes.
      panel.addEventListener('submit', event => {
        const form = event.target.closest('[data-pathway-form]');
        if (!form) return;
        event.preventDefault();
        this.save(form);
      });
      panel.addEventListener('click', event => {
        const button = event.target.closest('[data-pathway-info]');
        if (button) { event.preventDefault(); this.info(button); }
      });
    }

    async info(button) {
      const card = button.closest('li');
      const content = card?.querySelector('[data-pathway-info-content]');
      if (!content || button.disabled) return;
      if (button.getAttribute('aria-expanded') === 'true' && content.dataset.loaded === 'true') {
        content.hidden = true; button.setAttribute('aria-expanded', 'false'); return;
      }
      content.hidden = false;
      button.setAttribute('aria-expanded', 'true');
      if (content.dataset.loaded === 'true') return;
      button.disabled = true;
      content.setAttribute('aria-busy', 'true');
      content.textContent = 'Loading stage details…';
      const loading = root.CapeLoading?.show({ title: 'Loading pathway details', message: 'Please wait while we load this stage’s guidance.' });
      const controller = new AbortController();
      const abort = () => controller.abort();
      this.signal?.addEventListener('abort', abort, { once: true });
      const timeout = root.setTimeout(() => controller.abort(), 30000);
      try {
        const response = await root.fetch(button.dataset.pathwayInfo, {
          credentials: 'same-origin', cache: 'no-store', signal: controller.signal, headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok || response.redirected) throw new Error('Stage details are unavailable. Select More info to try again.');
        const html = await response.text();
        if (!html.includes('data-pathway-stage-details=')) throw new Error('Stage details are unavailable. Select More info to try again.');
        content.innerHTML = html;
        content.dataset.loaded = 'true';
      } catch (error) {
        content.textContent = 'Stage details are unavailable. Select More info to try again.';
      } finally {
        root.clearTimeout(timeout);
        this.signal?.removeEventListener('abort', abort);
        content.removeAttribute('aria-busy');
        button.disabled = false;
        loading?.close();
      }
    }

    message(text) {
      const status = this.panel.querySelector('[data-pathway-status]');
      if (status) status.textContent = text;
    }

    async save(form) {
      if (this.pending) return;
      this.pending = true;
      const buttons = form.querySelectorAll('button[type="submit"]');
      buttons.forEach(button => { button.disabled = true; });
      this.message('Saving pathway…');
      try {
        const response = await root.fetch(form.action, {
          method: 'POST', credentials: 'same-origin', cache: 'no-store', body: new root.FormData(form),
          headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf }
        });
        const data = await response.json();
        if (!response.ok || response.redirected) {
          const errors = Object.values(data.errors || {}).flat().join('\n');
          throw new Error(errors || data.message || 'The pathway could not be saved.');
        }
        await this.reload();
        const tools = this.panel.querySelector('[data-pathway-admin]');
        if (tools) tools.open = true;
        this.message(data.message || 'Pathway saved.');
      } catch (error) {
        this.message(error.message || 'The pathway could not be saved. Try again.');
      } finally {
        this.pending = false;
        buttons.forEach(button => { button.disabled = false; });
      }
    }
  }
  root.PlayerPathwayUI = PlayerPathwayUI;
  if (typeof module !== 'undefined') module.exports = PlayerPathwayUI;
}(typeof window === 'undefined' ? globalThis : window));
