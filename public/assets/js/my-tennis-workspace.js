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
  function showFeature(feature) {
    const keys = ['record', 'opponents', 'pathway', 'events', 'rating'];
    const selected = keys.includes(feature) ? feature : 'rating';
    const panel = root.document.querySelector('[data-plus-feature-panel="' + selected + '"]');
    if (!panel) return false;
    root.document.querySelectorAll('[data-plus-feature-panel]').forEach(item => { item.hidden = item !== panel; });
    panel.focus({preventScroll: true});
    // Theme smooth scrolling and the tab's layout change can otherwise leave
    // focus on an explanation that is still below the viewport on mobile.
    const reveal = () => {
      if (!panel.hidden) panel.scrollIntoView({block: 'start', behavior: 'instant'});
    };
    reveal();
    if (root.requestAnimationFrame) root.requestAnimationFrame(() => root.requestAnimationFrame(reveal));
    return true;
  }
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
        const feature = link.dataset.plusFeature || 'rating';
        if (!tab.classList.contains('active')) tab.addEventListener('shown.bs.tab', () => showFeature(feature), {once: true});
        root.bootstrap.Tab.getOrCreateInstance(tab).show();
        showFeature(feature);
      });
      const feature = new URL(root.location.href).searchParams.get('feature');
      if (feature) {
        const tab = root.document.getElementById('my-tennis-rating-tab');
        if (tab && root.bootstrap?.Tab) {
          tab.addEventListener('shown.bs.tab', () => showFeature(feature), {once: true});
          root.bootstrap.Tab.getOrCreateInstance(tab).show();
          showFeature(feature);
        }
      }
    });
  }
})(typeof window === 'undefined' ? globalThis : window);
