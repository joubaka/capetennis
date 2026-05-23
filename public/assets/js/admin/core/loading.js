/**
 * AdminLoading — Button and overlay loading state helpers.
 *
 * Usage:
 *   var restore = AdminLoading.button($btn, 'Saving…');
 *   // ... async work ...
 *   restore();
 *
 *   AdminLoading.spinner($container, 'Loading data…');
 *   AdminLoading.clear($container);
 */

(function (root) {
  'use strict';

  /**
   * Put a button into loading state. Returns a restore function.
   * @param {jQuery} $btn
   * @param {string} [label='Loading…']
   * @returns {function}
   */
  function button($btn, label) {
    var original = $btn.html();
    $btn.prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm me-1"></span>' + (label || 'Loading…'));
    return function () {
      $btn.prop('disabled', false).html(original);
    };
  }

  /**
   * Replace container contents with a spinner.
   * @param {jQuery} $el
   * @param {string} [label='Loading…']
   */
  function spinner($el, label) {
    $el.html(
      '<div class="text-center text-muted py-4">' +
      '<div class="spinner-border spinner-border-sm"></div>' +
      '<div class="mt-2">' + (label || 'Loading…') + '</div>' +
      '</div>'
    );
  }

  /**
   * Clear spinner / restore empty state.
   * @param {jQuery} $el
   */
  function clear($el) {
    $el.empty();
  }

  root.AdminLoading = {
    button:  button,
    spinner: spinner,
    clear:   clear
  };

}(window));
