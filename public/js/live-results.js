(() => {
  'use strict';
  const regions = [...document.querySelectorAll('[data-live-results]')];
  if (!regions.length) return;
  const status = document.querySelector('[data-live-results-status]');
  let stopped = status?.dataset.autoRefresh === '0';
  let busy = false;
  const editing = () => document.activeElement?.matches('input, select, textarea, [contenteditable="true"]')
    || document.querySelector('.modal.show, [data-live-results] form');
  const refresh = async () => {
    if (stopped || busy || document.hidden || editing()) return;
    busy = true;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetch(window.location.href, { cache: 'no-store', credentials: 'same-origin', signal: controller.signal });
      if ([401, 403, 404].includes(response.status)) {
        stopped = true;
        regions.forEach(region => region.replaceChildren());
        if (status) status.textContent = 'These results are no longer available. Return to the tournament page.';
        return;
      }
      if (!response.ok || response.redirected) throw new Error('Results unavailable');
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      const refreshedStatus = page.querySelector('[data-live-results-status]');
      for (const region of regions) {
        const replacement = [...page.querySelectorAll('[data-live-results]')]
          .find(candidate => candidate.dataset.liveResults === region.dataset.liveResults)
          || page.querySelector('[data-live-results-empty]');
        if (!replacement) throw new Error('Results unavailable');
        replacement.querySelectorAll('script').forEach(node => node.remove());
        // A reader may start interacting while the request is in flight.
        if (document.hidden || editing()) return;
        if (region.innerHTML === replacement.innerHTML) continue;
        const disclosure = [...region.querySelectorAll('details')].map((node, index) => ({
          key: node.dataset.liveKey || node.id || String(index), open: node.open,
        }));
        const scroll = [...region.querySelectorAll('.table-responsive')].map(node => node.scrollLeft);
        const expandedRows = [...region.querySelectorAll('tr[id^="details-"]:not(.d-none)')].map(node => node.id);
        const position = { x: window.scrollX, y: window.scrollY };
        const focusables = 'a, button, summary, [tabindex]';
        const focusedIndex = [...region.querySelectorAll(focusables)].indexOf(document.activeElement);
        // Preserve only the trusted rendered region; fetched scripts never execute.
        region.replaceChildren(...replacement.childNodes);
        region.querySelectorAll('details').forEach((node, index) => {
          const previous = disclosure.find(item => item.key === (node.dataset.liveKey || node.id || String(index)));
          if (previous) {
            if (node.open !== previous.open) node.dataset.liveRestored = '1';
            node.open = previous.open;
          }
        });
        expandedRows.forEach(id => {
          const row = document.getElementById(id);
          if (!row || !region.contains(row)) return;
          row.classList.remove('d-none');
          region.querySelectorAll('.toggle-details').forEach(button => {
            if (button.dataset.target !== `#${id}`) return;
            button.setAttribute('aria-expanded', 'true');
            button.setAttribute('aria-label', 'Hide match details');
            button.querySelector('i')?.classList.replace('ti-plus', 'ti-minus');
          });
        });
        region.querySelectorAll('.table-responsive').forEach((node, index) => { node.scrollLeft = scroll[index] || 0; });
        if (focusedIndex >= 0) region.querySelectorAll(focusables)[focusedIndex]?.focus({ preventScroll: true });
        window.scrollTo(position.x, position.y);
      }
      if (refreshedStatus?.dataset.autoRefresh === '0') {
        stopped = true;
        if (status) { status.dataset.autoRefresh = '0'; status.textContent = refreshedStatus.textContent; }
      } else if (status) status.textContent = `Results checked at ${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}. Updates every 30 seconds.`;
    } catch {
      if (status) status.textContent = 'Updates are temporarily unavailable. Showing the last loaded results; retrying automatically.';
    } finally {
      clearTimeout(timeout);
      busy = false;
    }
  };
  setInterval(refresh, 30000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  // Delegation survives replacing fixture disclosures during refresh.
  document.addEventListener('toggle', event => {
    const round = event.target;
    if (round.dataset?.liveRestored) {
      delete round.dataset.liveRestored;
      return;
    }
    if (round.matches?.('.fixture-round') && round.open) {
      round.querySelectorAll('.fixture-tie').forEach(tie => { tie.open = true; });
    }
  }, true);
})();
