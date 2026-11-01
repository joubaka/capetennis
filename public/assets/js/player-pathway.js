(function (root) {
  'use strict';
  class PlayerPathwayUI {
    constructor(panel, reload, options = {}) {
      this.panel = panel;
      this.reload = reload;
      this.csrf = options.csrf || '';
      this.pending = false;
      panel.addEventListener('change', event => {
        if (!event.target.matches('[data-pathway-stage]')) return;
        const division = event.target.closest('form').querySelector('[data-pathway-division]');
        if (!division) return;
        division.disabled = event.target.value !== 'standard';
        if (division.disabled) division.value = '';
      });
      // Delegate from the persistent panel: its contents are replaced after month/filter changes.
      panel.addEventListener('submit', event => {
        const form = event.target.closest('[data-pathway-form]');
        if (!form) return;
        event.preventDefault();
        this.save(form);
      });
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
