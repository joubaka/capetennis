(function (root) {
  'use strict';

  function bindPlayerSwitch(window, select) {
    if (!select || !select.form) return null;
    if (select.capePlayerSwitch) return select.capePlayerSwitch;
    const form = select.form;
    const previousChange = select.onchange;
    const previousBusy = form.getAttribute('aria-busy');
    let handle = null, timer = null;

    function close() {
      if (timer !== null) window.clearTimeout(timer);
      timer = null;
      if (handle) handle.close();
      handle = null;
      if (previousBusy === null) form.removeAttribute('aria-busy');
      else form.setAttribute('aria-busy', previousBusy);
    }

    function show() {
      if (handle) return;
      form.setAttribute('aria-busy', 'true');
      handle = window.CapeLoading?.show({title: 'Changing player', message: 'Please wait while we load the selected player’s dashboard.'}) || null;
      if (timer !== null) window.clearTimeout(timer);
      timer = window.setTimeout(close, 30000);
    }

    function change() {
      show();
      try {
        // Keep the selector enabled: disabled fields are omitted from the GET.
        form.submit();
      } catch (error) {
        close();
        throw error;
      }
    }

    // Select2 triggers the onchange property as well as native select changes.
    select.onchange = change;
    form.addEventListener('submit', show);
    window.addEventListener('pageshow', close);
    select.capePlayerSwitch = {close, destroy() {
      close();
      if (select.onchange === change) select.onchange = previousChange;
      form.removeEventListener('submit', show);
      window.removeEventListener('pageshow', close);
      delete select.capePlayerSwitch;
    }};
    return select.capePlayerSwitch;
  }

  if (typeof module !== 'undefined' && module.exports) module.exports = bindPlayerSwitch;
  if (root.document) root.document.addEventListener('DOMContentLoaded', function () {
    bindPlayerSwitch(root, root.document.getElementById('my-tennis-player'));
  });
})(typeof window === 'undefined' ? globalThis : window);
