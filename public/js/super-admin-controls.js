(function () {
  'use strict';
  const source = document.getElementById('ct-super-admin-routes');
  if (!source) return;
  const routes = JSON.parse(source.textContent).map(([pattern, methods, exclusive]) => {
    try { return {pattern: new RegExp(pattern), methods, exclusive}; }
    catch (_) { return null; }
  }).filter(Boolean);
  const cache = new Map();
  const selector = 'a[href], button, input:not([type="hidden"]), select, textarea, summary, [role="button"], [role="tab"]';
  const current = new URL(window.location.href);
  const basePath = (source.dataset.basePath || '').replace(/\/$/, '');
  function restricted(value, method = 'GET') {
    if (!value || /^(?:#|javascript:|mailto:|tel:)/i.test(value)) return false;
    let url;
    try { url = new URL(value, document.baseURI); } catch (_) { return false; }
    if (url.origin !== current.origin) return false;
    if (basePath && url.pathname !== basePath && !url.pathname.startsWith(basePath + '/')) return false;
    const path = url.pathname.slice(basePath.length) || '/';
    const key = method + ' ' + path;
    if (!cache.has(key)) {
      const route = routes.find(route => route.methods.includes(method) && route.pattern.test(path));
      cache.set(key, route?.exclusive === true);
    }
    return cache.get(key);
  }
  function formRestricted(element) {
    if (element.matches('a[href], summary, [role="tab"]')) return false;
    const form = element.form || element.closest('form');
    if (!form) return false;
    const action = element.getAttribute('formaction') || form.getAttribute('action') || current.href;
    const method = (element.getAttribute('formmethod') || form.querySelector('input[name="_method"]')?.value || form.method || 'GET').toUpperCase();
    return restricted(action, method);
  }
  function refresh() {
    document.querySelectorAll(selector).forEach(element => {
      const explicit = element.closest('[data-super-admin-only]');
      const pageOnly = element.closest('[data-super-admin-page]');
      const sharedChrome = element.closest('[data-super-admin-shared]');
      // Navigation to shared pages stays shared, even from an exclusive workspace.
      const sharedLink = element.matches('a[href]') && !/^(?:#|javascript:)/i.test(element.getAttribute('href'));
      const marked = Boolean(explicit) || restricted(element.getAttribute('href')) || formRestricted(element)
        || (Boolean(pageOnly) && !sharedChrome && !sharedLink);
      element.classList.toggle('ct-super-admin-control', marked);
    });
  }
  let pending = false;
  const observer = new MutationObserver(() => {
    if (pending) return;
    pending = true;
    window.queueMicrotask(() => { pending = false; refresh(); });
  });
  refresh();
  observer.observe(document.body, {subtree: true, childList: true, attributes: true,
    attributeFilter: ['href', 'action', 'formaction', 'method', 'formmethod', 'data-super-admin-only', 'data-super-admin-page', 'data-super-admin-shared']});
}());
