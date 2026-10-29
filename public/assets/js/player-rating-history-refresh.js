(function (root) {
  class PlayerRatingHistoryRefreshUI {
    constructor(panel, reload, options = {}) {
      this.panel = panel;
      this.reload = reload;
      this.fetch = options.fetch || root.fetch.bind(root);
      this.csrf = options.csrf || '';
      this.setTimer = options.setTimer || root.setTimeout.bind(root);
      this.clearTimer = options.clearTimer || root.clearTimeout.bind(root);
      this.waiting = false;
      this.checking = false;
      this.posting = false;
      this.timer = null;
      panel.addEventListener('click', event => {
        if (event.target.closest('[data-history-refresh]')) this.start();
      });
    }

    controls() { return this.panel.querySelector('[data-history-refresh-controls]'); }

    render(state, message) {
      const controls = this.controls();
      if (!controls) return;
      controls.querySelector('[data-history-refresh-status]').textContent = message;
      const button = controls.querySelector('[data-history-refresh]');
      button.disabled = ['pending', 'running'].includes(state) || this.posting;
      button.textContent = state === 'failed' ? 'Try update again' : (button.disabled ? 'Updating saved ratings…' : 'Update saved ratings');
    }

    async apply(status) {
      if (!['idle', 'pending', 'running', 'completed', 'failed'].includes(status.state)) throw new Error('Invalid update status');
      const active = ['pending', 'running'].includes(status.state);
      const completed = status.state === 'completed' && this.waiting;
      this.waiting = active;
      this.render(status.state, status.message);
      this.clearTimer(this.timer);
      if (active) this.timer = this.setTimer(() => this.sync(), 3000);
      if (completed) { await this.reload(); this.render('completed', status.message); }
    }

    async start() {
      const controls = this.controls();
      if (!controls || this.waiting || this.posting) return;
      this.posting = true;
      this.waiting = true;
      this.render('pending', 'Requesting a background update for all players…');
      try {
        const response = await this.fetch(controls.dataset.refreshUrl, { method: 'POST', credentials: 'same-origin', cache: 'no-store',
          headers: { Accept: 'application/json', 'X-CSRF-TOKEN': this.csrf } });
        const status = await response.json();
        if ((!response.ok && response.status !== 503) || response.redirected) throw new Error('Update request unavailable');
        this.posting = false;
        await this.apply(status);
      } catch (error) {
        this.posting = false;
        this.waiting = false;
        this.render('failed', 'The background update could not be requested. Please try again.');
      }
    }

    async sync() {
      const controls = this.controls();
      if (!controls || this.checking || this.posting) return;
      this.checking = true;
      try {
        const response = await this.fetch(controls.dataset.statusUrl, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
        if (!response.ok || response.redirected) throw new Error('Update status unavailable');
        await this.apply(await response.json());
      } catch (error) {
        this.waiting = false;
        this.render('failed', 'The update status could not be checked. The last saved history is retained. Please try again.');
      } finally { this.checking = false; }
    }
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = PlayerRatingHistoryRefreshUI;
  else root.PlayerRatingHistoryRefreshUI = PlayerRatingHistoryRefreshUI;
})(typeof window !== 'undefined' ? window : globalThis);
