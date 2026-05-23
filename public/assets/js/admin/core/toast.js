/**
 * AdminToast — Centralized toast notifications.
 *
 * Falls back through: toastr → Bootstrap Toast → console.
 *
 * Usage:
 *   AdminToast.success('Saved!')
 *   AdminToast.error('Something went wrong')
 *   AdminToast.warning('Check your input')
 *   AdminToast.info('FYI…')
 */

(function (root) {
  'use strict';

  function show(message, type) {
    type = type || 'success';

    // toastr (preferred — already loaded by layout)
    if (typeof toastr !== 'undefined') {
      var method = type === 'danger' ? 'error' : type;
      if (typeof toastr[method] === 'function') {
        toastr[method](message);
        return;
      }
    }

    // Bootstrap 5 Toast fallback
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
      var bg = type === 'danger' ? 'danger' : type;
      var $t = $([
        '<div class="toast align-items-center text-bg-' + bg + ' border-0 position-fixed bottom-0 end-0 m-3"',
        ' role="alert" style="z-index:9999">',
        '<div class="d-flex">',
        '<div class="toast-body">' + message + '</div>',
        '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>',
        '</div></div>'
      ].join(''));
      $('body').append($t);
      var t = new bootstrap.Toast($t[0], { delay: 3500 });
      t.show();
      $t[0].addEventListener('hidden.bs.toast', function () { $t.remove(); });
      return;
    }

    // Last resort
    console.log('[' + type.toUpperCase() + '] ' + message);
  }

  root.AdminToast = {
    success: function (msg) { show(msg, 'success'); },
    error:   function (msg) { show(msg, 'danger'); },
    warning: function (msg) { show(msg, 'warning'); },
    info:    function (msg) { show(msg, 'info'); },
    show:    show
  };

}(window));
