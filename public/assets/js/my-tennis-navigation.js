(() => {
  'use strict';

  const workspaceSelector = '[data-my-tennis-workspace]';
  const cacheLifetime = 60000;
  const warmConcurrency = 5;
  let pending = null;
  let renderedUrl = pageKey(new URL(window.location.href));
  let scope = null;
  let warmDisabled = false;

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
      target.ready.set(key, {page, expires: Date.now() + cacheLifetime});
    }
  }

  function warmTabs(workspace, page) {
    if (warmDisabled) return;
    const tabs = Array.from(workspace.querySelectorAll('#my-tennis-tabs [data-my-tennis-link]'), link => new URL(link.href))
      .filter(url => url.origin === window.location.origin);
    const keys = new Set(tabs.map(pageKey));
    if (scope && keys.size === scope.keys.size && [...keys].every(key => scope.keys.has(key))) return;
    clearCache();
    if (!keys.size) return;

    const target = {keys, ready: new Map(), inFlight: new Map(), controller: new AbortController()};
    scope = target;
    if (page) remember(target, renderedUrl, page);
    const queue = tabs.filter(url => !target.ready.has(pageKey(url)));
    async function warmNext() {
      while (scope === target && queue.length) {
        const url = queue.shift();
        const key = pageKey(url);
        if (target.ready.has(key) || pending?.key === key) continue;
        const controller = new AbortController();
        const cancel = () => controller.abort();
        target.controller.signal.addEventListener('abort', cancel, {once: true});
        const signal = controller.signal;
        const timeout = window.setTimeout(() => controller.abort(), 15000);
        const task = fetchWorkspace(url, signal, true)
          .then(page => {
            if (!page.redirected && !signal.aborted) remember(target, key, page);
          })
          .catch(() => {})
          .finally(() => {
            window.clearTimeout(timeout);
            target.controller.signal.removeEventListener('abort', cancel);
            target.inFlight.delete(key);
          });
        target.inFlight.set(key, task);
        await task;
      }
    }
    // A normal workspace has six tabs. Start the other five together so a
    // slow Activity or Rating response cannot hold up Results or Opponents.
    const workers = Math.min(warmConcurrency, queue.length);
    for (let i = 0; i < workers; i++) warmNext();
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
    warmTabs(next, page);

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
      const warming = scope?.inFlight.get(key);
      if (warming) await warming;
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
      const retry = document.createElement('button');
      retry.type = 'button';
      retry.className = 'btn btn-sm btn-outline-secondary';
      retry.textContent = 'Try again';
      retry.addEventListener('click', () => open(url, {focus: true, tab}), {once: true});
      status.append(retry);
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
  const initialPage = initialWorkspace && document.readyState === 'interactive'
    ? {title: document.title, workspace: initialWorkspace.cloneNode(true)}
    : null;
  // Let the requested tab paint before the other authorized tabs start loading.
  window.requestAnimationFrame(() => window.setTimeout(() => {
    const workspace = document.querySelector(workspaceSelector);
    if (workspace) warmTabs(workspace, workspace === initialWorkspace ? initialPage : null);
  }, 0));
})();
