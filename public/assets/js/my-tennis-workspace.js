(function (root) {
  'use strict';
  function integrateRating(document, panel) {
    const host = document.querySelector('[data-rating-header-snapshot-host]');
    const hero = panel.querySelector('[data-rating-hero]');
    const snapshot = hero?.querySelector('[data-rating-snapshot]');
    if (!host || !hero || !snapshot) return false;
    host.replaceChildren(snapshot);
    hero.dataset.embeddedRatingHero = '';
    hero.hidden = true;
    return true;
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = integrateRating;
  if (root.document) {
    root.MyTennisWorkspace = {integrateRating: panel => integrateRating(root.document, panel)};
    root.document.addEventListener('DOMContentLoaded', function () {
      root.document.addEventListener('click', function (event) {
        const link = event.target.closest('[data-plus-preview-link]');
        if (!link || event.defaultPrevented || (event.button !== undefined && event.button !== 0)
            || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        const tab = root.document.getElementById('my-tennis-rating-tab');
        if (!tab || !root.bootstrap?.Tab) return;
        event.preventDefault();
        root.bootstrap.Tab.getOrCreateInstance(tab).show();
      });
    });
  }
})(typeof window === 'undefined' ? globalThis : window);
