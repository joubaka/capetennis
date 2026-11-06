(function (root) {
  'use strict';
  function launch(form) {
    if (!form || form.dataset.plusSubmitted) return;
    try {
      form.dataset.plusSubmitted = '1';
      form.submit();
    } catch (_) {
      delete form.dataset.plusSubmitted;
      // The visible submit button remains available when automatic navigation fails.
    }
  }

  class PaymentReturn {
    constructor(panel, options = {}) {
      this.panel = panel;
      this.fetch = options.fetch || root.fetch.bind(root);
      this.setTimer = options.setTimer || root.setTimeout.bind(root);
      this.setRequestTimer = options.setRequestTimer || root.setTimeout.bind(root);
      this.clearRequestTimer = options.clearRequestTimer || root.clearTimeout.bind(root);
      this.navigate = options.navigate || (url => root.location.replace(url));
      this.origin = options.origin || root.location.origin;
      this.attempts = 0;
      this.checking = false;
      this.stopped = false;
    }
    stop() {
      this.stopped = true;
      this.panel.querySelector('[data-plus-return-message]').textContent =
        'Automatic checks have stopped. Use Check payment status to check again. Access starts only after verified payment.';
    }
    async check() {
      if (this.stopped || this.checking) return;
      this.checking = true;
      this.attempts++;
      const controller = new AbortController();
      const timeout = this.setRequestTimer(() => controller.abort(), 10000);
      try {
        const response = await this.fetch(this.panel.dataset.statusUrl, {
          credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' }, signal: controller.signal,
        });
        if (!response.ok || response.redirected) throw new Error('Status unavailable');
        const status = await response.json();
        if (status.state === 'pending' && status.destination === null) {
          if (this.attempts >= 12) this.stop();
          else this.setTimer(() => this.check(), 5000);
        } else if (['verified', 'receipt'].includes(status.state) && typeof status.destination === 'string') {
          const destination = new URL(status.destination, this.origin);
          if (destination.origin !== this.origin) throw new Error('Unexpected destination');
          this.stopped = true;
          this.navigate(destination.href);
        } else throw new Error('Unexpected status');
      } catch (_) { this.stop(); }
      finally { this.clearRequestTimer(timeout); this.checking = false; }
    }
  }
  function initialize() {
    if (root.document.querySelector('[data-plus-payfast-launch]')) launch(root.document.getElementById('payfastForm'));
    const panel = root.document.querySelector('[data-plus-payment-return]');
    if (panel) new PaymentReturn(panel).check();
    const retry = root.document.querySelector('[data-plus-retry-refresh-ms]');
    if (retry) root.setTimeout(() => root.location.replace(retry.href), Number(retry.dataset.plusRetryRefreshMs));
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = { launch, PaymentReturn };
  else if (root.document.readyState === 'loading') root.document.addEventListener('DOMContentLoaded', initialize, { once: true });
  else initialize();
})(typeof window !== 'undefined' ? window : globalThis);
