(function () {
  'use strict';
  const mounted = new WeakSet();
  function mount() {
    document.querySelectorAll('[data-pathway-journey]').forEach(function (journey) {
      if (mounted.has(journey)) return;
      mounted.add(journey);
      const destination = journey.querySelector('[data-pathway-exploration-panel]');
      const heading = destination.querySelector('[data-pathway-exploring-label]');
      const content = destination.querySelector('[data-pathway-exploring-content]');
      // Cached workspaces are cloned without listeners; restore their moved body first.
      const restoredBody = content.querySelector('.pathway-explore-body');
      if (restoredBody) journey.querySelector('.pathway-explore[open]')?.appendChild(restoredBody);
      destination.hidden = true;
      journey.querySelectorAll('.pathway-explore[open]').forEach(function (details) {
        details.open = false;
      });
      let active = null;
      let body = null;
      journey.querySelectorAll('.pathway-explore').forEach(function (details) {
        details.addEventListener('toggle', function () {
          if (details.open) {
            if (active === details) return;
            if (active && body) {
              active.appendChild(body);
              active.open = false;
            }
            active = details;
            body = details.querySelector('.pathway-explore-body');
            heading.textContent = 'Exploring ' + details.querySelector('summary strong').textContent;
            content.replaceChildren(body);
            destination.hidden = false;
          } else if (active === details) {
            details.appendChild(body);
            active = null;
            body = null;
            destination.hidden = true;
          }
        });
      });
    });
  }
  document.addEventListener('DOMContentLoaded', mount);
  document.addEventListener('my-tennis:mounted', mount);
  if (document.readyState !== 'loading') mount();
})();
