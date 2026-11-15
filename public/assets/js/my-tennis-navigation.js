(() => {
  'use strict';

  const workspaceSelector = '[data-my-tennis-workspace]';
  const cacheLifetime = 60000;
  let pending = null;
  let renderedUrl = pageKey(new URL(window.location.href));
  let scope = null;
  let warmDisabled = false;
  const liveResultsSelector = '[data-my-tennis-live-results]';
  const liveResultsInterval = 15000;
  let stopLiveResults = null;

  function mountLiveResults(workspace, immediately = false) {
    stopLiveResults?.();
    let panel = workspace.querySelector(liveResultsSelector);
    if (!panel) return;

    const url = new URL(panel.dataset.myTennisLiveResults, window.location.href);
    if (url.origin !== window.location.origin) return;
    let timer;
    let request;
    let stopped = false;

    function schedule(delay = liveResultsInterval) {
      window.clearTimeout(timer);
      if (!stopped && !document.hidden && !request) timer = window.setTimeout(refresh, delay);
    }

    async function refresh() {
      if (stopped || document.hidden || !panel.isConnected) return;
      request = new AbortController();
      const {signal} = request;
      const timeout = window.setTimeout(() => request.abort(), 10000);
      try {
        const response = await fetch(url, {
          headers: {Accept: 'text/html'}, credentials: 'same-origin', cache: 'no-store', signal,
        });
        if (stopped || signal.aborted) return;
        if (response.redirected || [401, 403, 404, 419].includes(response.status)) {
          panel.remove();
          disableCache();
          stopLiveResults?.();
          return;
        }
        if (!response.ok) return;
        const page = new DOMParser().parseFromString(await response.text(), 'text/html');
        const next = page.querySelector(liveResultsSelector);
        if (!stopped && !signal.aborted && next && !panel.isEqualNode(next)) {
          const focused = panel.contains(document.activeElement);
          panel.replaceWith(next);
          panel = next;
          if (focused) panel.querySelector('h2, h3')?.focus({preventScroll: true});
        }
      } catch {
        // Keep the last successful response during a transient network failure.
      } finally {
        window.clearTimeout(timeout);
        request = null;
        schedule();
      }
    }

    function visibilityChanged() {
      if (document.hidden) request?.abort();
      schedule(0);
    }
    document.addEventListener('visibilitychange', visibilityChanged);
    stopLiveResults = () => {
      stopped = true;
      window.clearTimeout(timer);
      request?.abort();
      document.removeEventListener('visibilitychange', visibilityChanged);
      stopLiveResults = null;
    };
    schedule(immediately ? 0 : liveResultsInterval);
  }

  function pageKey(url) {
    return url.pathname + url.search;
  }

  function clearCache() {
    scope?.controller.abort();
    scope = null;
  }

  async function fetchWorkspace(url, signal, prefetch = false) {
    const headers = {Accept: 'text/html', 'X-My-Tennis-Navigation': '1'};
    if (prefetch) headers.Purpose = 'prefetch';
    const response = await fetch(url, {
      headers, credentials: 'same-origin', cache: 'no-store', signal,
      redirect: prefetch ? 'manual' : 'follow',
    });
    if (response.redirected || response.type === 'opaqueredirect') {
      disableCache();
      return {redirected: true};
    }
    if ([401, 403, 419].includes(response.status)) disableCache();
    if (!response.ok) throw new Error('My Tennis is unavailable');

    const page = new DOMParser().parseFromString(await response.text(), 'text/html');
    const workspace = page.querySelector(workspaceSelector);
    if (!workspace || signal.aborted) throw new Error('My Tennis workspace unavailable');
    return {title: page.title, workspace};
  }

  function remember(target, key, page) {
    if (scope === target && target.keys.has(key)) {
      // Warm the Results tab without retaining scores whose visibility may change.
      if (page.workspace.querySelector(liveResultsSelector)) {
        const workspace = page.workspace.cloneNode(true);
        const panel = workspace.querySelector(liveResultsSelector);
        panel.replaceChildren();
        panel.hidden = true;
        page = {...page, workspace};
      }
      target.ready.set(key, {page, expires: Date.now() + cacheLifetime});
    }
  }

  function cacheTabs(workspace, page) {
    if (warmDisabled) return;
    const tabs = Array.from(workspace.querySelectorAll('#my-tennis-tabs [data-my-tennis-link]'), link => new URL(link.href))
      .filter(url => url.origin === window.location.origin);
    const keys = new Set(tabs.map(pageKey));
    if (scope && keys.size === scope.keys.size && [...keys].every(key => scope.keys.has(key))) return;
    clearCache();
    if (!keys.size) return;

    const target = {keys, ready: new Map(), controller: new AbortController()};
    scope = target;
    if (page) remember(target, renderedUrl, page);
    // Cache visited tabs only: speculative reads compete with the selected tab.
  }

  function cachedPage(key) {
    const item = scope?.ready.get(key);
    if (item?.expires > Date.now()) return item.page;
    scope?.ready.delete(key);
    return null;
  }

  function render(page, workspace, url, {back, focus}) {
    const next = page.workspace.cloneNode(true);
    if (!back && url.href !== window.location.href) window.history.pushState(null, '', url.href);
    if (page.title) document.title = page.title;
    renderedUrl = pageKey(url);
    workspace.dispatchEvent(new CustomEvent('my-tennis:unmounting', {bubbles: true}));
    workspace.replaceWith(next);
    next.dispatchEvent(new CustomEvent('my-tennis:mounted', {bubbles: true}));
    mountLiveResults(next, true);
    cacheTabs(next, page);

    const active = next.querySelector('[data-my-tennis-link][aria-current="page"]');
    if (focus) active?.focus({preventScroll: true});
    if (url.hash) document.getElementById(url.hash.slice(1))?.scrollIntoView();
  }

  function showStatus(workspace, message) {
    let status = workspace.querySelector('[data-my-tennis-navigation-status]');
    if (!status) {
      status = document.createElement('p');
      status.dataset.myTennisNavigationStatus = '';
      status.className = 'small mt-2 mb-2';
      status.setAttribute('role', 'status');
      const tabs = workspace.querySelector('#my-tennis-tabs');
      if (tabs) tabs.after(status);
      else workspace.prepend(status);
    }
    status.textContent = message;
    return status;
  }

  async function open(url, {back = false, focus = false, tab = null} = {}) {
    const workspace = document.querySelector(workspaceSelector);
    if (!workspace) return;

    pending?.abort();
    pending = null;
    const key = pageKey(url);
    const ready = cachedPage(key);
    if (ready) {
      render(ready, workspace, url, {back, focus});
      return;
    }

    const request = new AbortController();
    request.key = key;
    pending = request;
    const timeout = window.setTimeout(() => request.abort(), 30000);
    workspace.setAttribute('aria-busy', 'true');
    const status = showStatus(workspace, 'Loading My Tennis…');
    workspace.querySelector('[data-my-tennis-tab-spinner]')?.remove();
    workspace.querySelector('[data-my-tennis-tab-pending]')?.removeAttribute('data-my-tennis-tab-pending');
    tab?.setAttribute('data-my-tennis-tab-pending', '');
    status.classList.add('visually-hidden');
    const spinner = document.createElement('span');
    spinner.className = 'spinner-border spinner-border-sm flex-shrink-0 me-1';
    spinner.dataset.myTennisTabSpinner = '';
    spinner.setAttribute('aria-hidden', 'true');
    const spinnerDelay = window.setTimeout(() => {
      if (pending !== request) return;
      (tab || status).prepend(spinner);
      if (!tab) status.classList.remove('visually-hidden');
    }, 180);

    try {
      if (pending !== request) return;
      const page = cachedPage(key) ?? await fetchWorkspace(url, request.signal);
      if (pending !== request) return;
      if (page.redirected) {
        if (back) window.location.reload();
        else window.location.assign(url.href);
        return;
      }
      if (request.signal.aborted) throw new Error('My Tennis request timed out');
      if (scope) remember(scope, key, page);
      render(page, workspace, url, {back, focus});
    } catch (error) {
      if (pending !== request) return;
      if (back) {
        window.location.reload();
        return;
      }
      status.classList.remove('visually-hidden');
      status.textContent = tab ? `${tab.textContent.trim()} could not be loaded. ` : 'This view could not be loaded. ';
      if (request.signal.aborted) status.textContent += 'The request took too long. ';
      const retry = document.createElement('button');
      retry.type = 'button';
      retry.className = 'btn btn-sm btn-outline-secondary';
      retry.textContent = 'Try again';
      retry.addEventListener('click', () => open(url, {focus: true, tab}), {once: true});
      status.append(retry);
      const fullPage = document.createElement('a');
      fullPage.href = url.href;
      fullPage.className = 'ms-2';
      fullPage.textContent = 'Open full page';
      status.append(fullPage);
      if (focus) retry.focus({preventScroll: true});
    } finally {
      spinner.remove();
      window.clearTimeout(spinnerDelay);
      window.clearTimeout(timeout);
      if (pending === request) {
        tab?.removeAttribute('data-my-tennis-tab-pending');
        pending = null;
        workspace.removeAttribute('aria-busy');
      }
    }
  }

  document.addEventListener('click', event => {
    const link = event.target.closest('[data-my-tennis-link]');
    if (!link?.closest(workspaceSelector) || event.defaultPrevented || event.button !== 0
      || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
      || (link.target && link.target !== '_self') || link.hasAttribute('download')) return;
    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin) return;

    event.preventDefault();
    const tab = link.closest('#my-tennis-tabs') ? link : null;
    open(url, {focus: true, tab});
  });

  window.addEventListener('popstate', () => {
    const url = new URL(window.location.href);
    if (renderedUrl !== pageKey(url) || pending) open(url, {back: true});
  });

  // A rating fragment changes the current workspace URL without replacing it.
  // Keep Back/Forward and the warmed tab cache aligned with that rendered state.
  document.addEventListener('my-tennis:rating-navigated', () => {
    renderedUrl = pageKey(new URL(window.location.href));
    clearCache();
  });

  function disableCache() {
    warmDisabled = true;
    clearCache();
  }

  document.addEventListener('submit', event => {
    if (event.target.closest(workspaceSelector) && event.target.method !== 'get') disableCache();
  }, true);
  document.addEventListener('click', event => {
    if (event.target.closest(`${workspaceSelector} [data-history-refresh]`)) disableCache();
  }, true);
  document.addEventListener('my-tennis:invalidate', disableCache);
  window.addEventListener('pagehide', () => {
    stopLiveResults?.();
    pending?.abort();
    pending = null;
    disableCache();
  });
  window.addEventListener('pageshow', event => {
    if (event.persisted) window.location.reload();
  });

  // Defer runs before the controls mount on DOMContentLoaded. Keep a pristine
  // copy of the initial tab so returning to it does not clone enhanced widgets.
  const initialWorkspace = document.querySelector(workspaceSelector);
  if (initialWorkspace) mountLiveResults(initialWorkspace);
  const initialPage = initialWorkspace && document.readyState === 'interactive'
    ? {title: document.title, workspace: initialWorkspace.cloneNode(true)}
    : null;
  // Register the initial workspace for reuse without speculative server requests.
  window.requestAnimationFrame(() => window.setTimeout(() => {
    const workspace = document.querySelector(workspaceSelector);
    if (workspace) cacheTabs(workspace, workspace === initialWorkspace ? initialPage : null);
  }, 0));
})();
