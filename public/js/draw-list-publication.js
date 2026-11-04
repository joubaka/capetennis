(function (window, document) {
  'use strict';
  var panel = document.querySelector('[data-event-draw-publication]');
  if (!panel) return;
  document.querySelectorAll('[data-draw-list-publish]').forEach(function (button) {
    button.addEventListener('click', function () {
      var selected = button.dataset.drawListPublish === 'selected';
      var target = panel.querySelector(selected
        ? '[data-bulk-draw-action="publish"][data-bulk-draw-scope="selected"]'
        : '[data-bulk-draw-action="publish"]:not([data-bulk-draw-scope])');
      if (!target || window.HeadOfficeDrawPublicationPending || window.HeadOfficeDrawPublicationUnconfirmed) return;
      if (selected) {
        var ids = new Set(Array.from(document.querySelectorAll('[data-delete-draw]:checked')).map(function (input) { return input.value; }));
        panel.querySelectorAll('[data-publication-draw-select]').forEach(function (input) {
          input.checked = ids.has(input.value);
          input.dispatchEvent(new Event('change', { bubbles: true }));
        });
      }
      if (target.disabled && !selected) return;
      // Empty selection still opens the panel so its selection guidance is visible.
      panel.open = true;
      if (!target.disabled) target.click();
      panel.scrollIntoView({ block: 'start', behavior: 'smooth' });
    });
  });
})(window, document);
