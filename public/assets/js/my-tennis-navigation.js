(() => {
  'use strict';

  const workspaceSelector = '[data-my-tennis-workspace]';
  let pending = null;
  let renderedUrl = window.location.pathname + window.location.search;

  function showStatus(workspace, message) {
    let status = workspace.querySelector('[data-my-tennis-navigation-status]');
    if (!status) {
      status = document.createElement('p');
      status.dataset.myTennisNavigationStatus = '';
      status.className = 'small mt-2 mb-2';
      status.setAttribute('role', 'status');
      workspace.prepend(status);
    }
    status.textContent = message;
    return status;
  }

  async function open(url, {back = false, focus = false} = {}) {
    const workspace = document.querySelector(workspaceSelector);
    if (!workspace) return;

    pending?.abort();
    const request = new AbortController();
    pending = request;
    const timeout = window.setTimeout(() => request.abort(), 30000);
    workspace.setAttribute('aria-busy', 'true');
    const status = showStatus(workspace, 'Loading My Tennis…');

    try {
      const response = await fetch(url, {
        headers: {Accept: 'text/html', 'X-My-Tennis-Navigation': '1'},
        credentials: 'same-origin',
        cache: 'no-store',
        signal: request.signal,
      });
      if (pending !== request) return;
      if (response.redirected) {
        if (back) window.location.reload();
        else window.location.assign(url.href);
        return;
      }
      if (!response.ok) throw new Error('My Tennis is unavailable');

      const html = await response.text();
      if (pending !== request) return;
      if (request.signal.aborted) throw new Error('My Tennis request timed out');
      const page = new DOMParser().parseFromString(html, 'text/html');
      const next = page.querySelector(workspaceSelector);
      if (!next) throw new Error('My Tennis workspace missing');

      if (!back && url.href !== window.location.href) {
        window.history.pushState(null, '', url.href);
      }
      if (page.title) document.title = page.title;
      renderedUrl = url.pathname + url.search;
      workspace.dispatchEvent(new CustomEvent('my-tennis:unmounting', {bubbles: true}));
      workspace.replaceWith(next);
      next.dispatchEvent(new CustomEvent('my-tennis:mounted', {bubbles: true}));

      const active = next.querySelector('[data-my-tennis-link][aria-current="page"]');
      if (focus) active?.focus({preventScroll: true});
      if (url.hash) document.getElementById(url.hash.slice(1))?.scrollIntoView();
    } catch (error) {
      if (pending !== request) return;
      if (back) {
        window.location.reload();
        return;
      }
      status.textContent = 'This view could not be loaded. ';
      const retry = document.createElement('button');
      retry.type = 'button';
      retry.className = 'btn btn-sm btn-outline-secondary';
      retry.textContent = 'Try again';
      retry.addEventListener('click', () => open(url, {focus: true}), {once: true});
      status.append(retry);
      if (focus) retry.focus({preventScroll: true});
    } finally {
      window.clearTimeout(timeout);
      if (pending === request) {
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
    open(url, {focus: true});
  });

  window.addEventListener('popstate', () => {
    const url = new URL(window.location.href);
    if (renderedUrl !== url.pathname + url.search || pending) open(url, {back: true});
  });
})();
