(function (root) {
  'use strict';
  function createLoading(document) {
    const requests = new Map();
    let dialog, title, message, cancel, previousFocus;
    function ensureDialog() {
      if (dialog) return;
      dialog = document.createElement('dialog');
      dialog.id = 'ct-loading-dialog';
      dialog.setAttribute('aria-labelledby', 'ct-loading-title');
      dialog.setAttribute('aria-describedby', 'ct-loading-message');
      dialog.setAttribute('aria-busy', 'true');
      const spinner = document.createElement('span');
      spinner.className = 'ct-loading-spinner';
      spinner.setAttribute('aria-hidden', 'true');
      title = document.createElement('h2'); title.id = 'ct-loading-title';
      title.tabIndex = -1;
      message = document.createElement('p'); message.id = 'ct-loading-message';
      message.setAttribute('role', 'status');
      cancel = document.createElement('button'); cancel.type = 'button';
      cancel.textContent = 'Cancel loading';
      function cancelLatest(event) {
        event.preventDefault();
        const latest = [...requests.values()].at(-1);
        if (typeof latest?.onCancel === 'function') latest.onCancel();
      }
      cancel.addEventListener('click', cancelLatest);
      dialog.addEventListener('cancel', cancelLatest);
      dialog.append(spinner, title, message, cancel);
      document.body.append(dialog);
    }
    function render() {
      const latest = [...requests.values()].at(-1);
      if (!latest) {
        if (dialog?.open) dialog.close();
        if (previousFocus?.isConnected) previousFocus.focus({preventScroll: true});
        previousFocus = null;
        return;
      }
      ensureDialog();
      title.textContent = latest.title || 'Loading…';
      message.textContent = latest.message || 'Please wait while we load your information.';
      cancel.hidden = typeof latest.onCancel !== 'function';
      if (!dialog.open) {
        previousFocus = document.activeElement;
        dialog.showModal();
        (cancel.hidden ? title : cancel).focus({preventScroll: true});
      } else if (document.activeElement === cancel && cancel.hidden) {
        title.focus({preventScroll: true});
      }
    }
    return { show(options = {}) {
      const key = Symbol('loading');
      requests.set(key, options); render();
      return { close() { if (requests.delete(key)) render(); } };
    } };
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = createLoading;
  if (root.document) root.CapeLoading = createLoading(root.document);
})(typeof window === 'undefined' ? globalThis : window);
