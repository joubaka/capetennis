/**
 * AdminModal — Lightweight confirm/alert abstractions.
 *
 * Wraps SweetAlert2 (already loaded on RR pages).
 * Falls back to native browser confirm/alert if SweetAlert2 is absent.
 *
 * Usage:
 *   AdminModal.confirm({ title, html, confirmText, color })
 *     .then(confirmed => { if (confirmed) { ... } });
 *
 *   AdminModal.alert({ title, html, icon });
 *
 *   AdminModal.loading('Please wait…')   → returns { close() }
 */

(function (root) {
  'use strict';

  function hasSwal() {
    return typeof Swal !== 'undefined';
  }

  /**
   * Show a confirmation dialog.
   * @param {object} opts
   * @param {string} opts.title
   * @param {string} [opts.html]
   * @param {string} [opts.text]
   * @param {string} [opts.confirmText='Yes']
   * @param {string} [opts.cancelText='Cancel']
   * @param {string} [opts.confirmColor='#198754']
   * @param {string} [opts.icon='warning']
   * @returns {Promise<boolean>}
   */
  function confirm(opts) {
    opts = opts || {};
    if (hasSwal()) {
      return Swal.fire({
        title:             opts.title || 'Are you sure?',
        html:              opts.html,
        text:              opts.text,
        icon:              opts.icon || 'warning',
        showCancelButton:  true,
        confirmButtonText: opts.confirmText || 'Yes',
        cancelButtonText:  opts.cancelText || 'Cancel',
        confirmButtonColor: opts.confirmColor || '#198754'
      }).then(function (result) { return result.isConfirmed; });
    }
    // Fallback
    var ok = window.confirm((opts.title || '') + (opts.text ? '\n' + opts.text : ''));
    return Promise.resolve(ok);
  }

  /**
   * Show an informational alert.
   * @param {object} opts
   * @returns {Promise<void>}
   */
  function alert(opts) {
    opts = opts || {};
    if (hasSwal()) {
      return Swal.fire({
        title:             opts.title || 'Notice',
        html:              opts.html,
        text:              opts.text,
        icon:              opts.icon || 'info',
        confirmButtonText: opts.confirmText || 'OK'
      }).then(function () {});
    }
    window.alert((opts.title || '') + (opts.text ? '\n' + opts.text : ''));
    return Promise.resolve();
  }

  /**
   * Show a loading overlay. Returns an object with a close() method.
   * @param {string} [title]
   * @param {string} [text]
   * @returns {{ close: function }}
   */
  function loading(title, text) {
    if (hasSwal()) {
      Swal.fire({
        title:            title || 'Please wait…',
        html:             text || '',
        allowOutsideClick: false,
        didOpen: function () { Swal.showLoading(); }
      });
      return { close: function () { Swal.close(); } };
    }
    // No-op fallback
    return { close: function () {} };
  }

  root.AdminModal = {
    confirm: confirm,
    alert:   alert,
    loading: loading
  };

}(window));
