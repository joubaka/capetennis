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

  function indicator() {
    if (root.CapeLoading) return root.CapeLoading.spinner({small: true});
    var node = root.document.createElement('span');
    node.className = 'ct-loading-spinner ct-loading-spinner--small';
    node.setAttribute('aria-hidden', 'true');
    return node;
  }

  /**
   * Put a button into loading state. Returns a restore function.
   * @param {jQuery} $btn
   * @param {string} [label='Loading…']
   * @returns {function}
   */
  function button($btn, label) {
    var original = $btn.html();
    var disabled = $btn.prop('disabled');
    var busy = $btn.attr('aria-busy');
    $btn.prop('disabled', true).attr('aria-busy', 'true').empty()
        .append(indicator()).append(root.document.createTextNode(' ' + (label || 'Loading…')));
    return function () {
      $btn.prop('disabled', disabled).html(original);
      if (busy === undefined) $btn.removeAttr('aria-busy'); else $btn.attr('aria-busy', busy);
    };
  }

  /**
   * Replace container contents with a spinner.
   * @param {jQuery} $el
   * @param {string} [label='Loading…']
   */
  function spinner($el, label) {
    var status = root.document.createElement('div');
    status.className = 'ct-loading-inline';
    status.setAttribute('role', 'status');
    var text = root.document.createElement('p');
    text.textContent = label || 'Loading…';
    status.append(indicator(), text);
    $el.attr('aria-busy', 'true').empty().append(status);
  }

  /**
   * Clear spinner / restore empty state.
   * @param {jQuery} $el
   */
  function clear($el) {
    $el.empty().removeAttr('aria-busy');
  }

  root.AdminLoading = {
    button:  button,
    spinner: spinner,
    clear:   clear
  };

}(window));
