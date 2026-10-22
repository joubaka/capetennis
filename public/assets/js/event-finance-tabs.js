/* Event finance sections share the authorized report route. Keep visited DOM
 * alive so switching tabs preserves open details and unfinished form input. */
(function () {
  'use strict';

  const root = document.querySelector('.finance-workspace');
  const nav = root?.querySelector('[data-finance-tabs]');
  if (!nav) return;
  const tabs = [...nav.querySelectorAll('[data-finance-tab]')];
  const feedback = root.querySelector('[data-finance-tab-feedback]');
  let active = tabs.find(tab => tab.getAttribute('aria-selected') === 'true').dataset.financeTab;
  const loaded = new Set([active]);
  let pending;
  let generation = 0;

  function clearLoading() {
    root.querySelectorAll('[aria-busy="true"]').forEach(element => element.removeAttribute('aria-busy'));
    nav.querySelectorAll('[data-finance-loading-icon]').forEach(icon => icon.remove());
  }
  function showLoading(tab) {
    const name = tab.textContent.trim();
    tab.setAttribute('aria-busy', 'true');
    const tabSpinner = document.createElement('span');
    tabSpinner.className = 'spinner-border spinner-border-sm finance-loading-spinner';
    tabSpinner.dataset.financeLoadingIcon = '';
    tabSpinner.setAttribute('aria-hidden', 'true');
    tab.append(tabSpinner);

    const status = document.createElement('div');
    status.className = 'finance-loading-status d-flex align-items-center gap-3 mb-3';
    const spinner = document.createElement('span');
    spinner.className = 'spinner-border finance-loading-spinner flex-shrink-0';
    spinner.setAttribute('aria-hidden', 'true');
    const copy = document.createElement('div');
    const title = document.createElement('strong');
    title.className = 'd-block';
    title.textContent = 'Loading ' + name.toLowerCase() + '…';
    const helper = document.createElement('small');
    helper.className = 'd-block text-muted mt-1';
    helper.textContent = 'Your current section stays available while we load this one.';
    copy.append(title, helper);
    status.append(spinner, copy);
    feedback.replaceChildren(status);
  }
  function panel(key) { return document.getElementById('finance-panel-' + key); }
  function tabFor(key) { return tabs.find(tab => tab.dataset.financeTab === key); }
  function select(key) {
    active = key;
    tabs.forEach(tab => {
      const selected = tab.dataset.financeTab === key;
      tab.setAttribute('aria-selected', String(selected));
      tab.tabIndex = selected ? 0 : -1;
      panel(tab.dataset.financeTab).hidden = !selected;
    });
  }
  function address(key, push) {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', key);
    if (push) url.hash = '';
    if (url.href !== window.location.href) history[push ? 'pushState' : 'replaceState']({}, '', url);
  }
  function showHashTarget() {
    let id;
    try { id = decodeURIComponent(window.location.hash.slice(1)); } catch (_) { return; }
    const target = document.getElementById(id);
    if (!target || target.closest('.finance-tab-panel')?.hidden) return;
    if (target.matches('details')) target.open = true;
    target.scrollIntoView({block: 'start'});
  }
  async function openTab(key, push = true, fromHash = false) {
    const tab = tabFor(key);
    if (!tab) return;
    const current = ++generation;
    pending?.abort();
    clearLoading();
    feedback.replaceChildren();
    if (loaded.has(key)) {
      select(key);
      address(key, push);
      if (fromHash) showHashTarget();
      return;
    }
    const controller = new AbortController();
    pending = controller;
    panel(active).setAttribute('aria-busy', 'true');
    showLoading(tab);
    try {
      const response = await fetch(tab.href, {
        headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html'},
        credentials: 'same-origin', cache: 'no-store', signal: controller.signal
      });
      if (!response.ok || response.redirected) throw new Error('Could not load this section.');
      const documentFragment = new DOMParser().parseFromString(await response.text(), 'text/html');
      const replacement = documentFragment.getElementById('finance-panel-' + key);
      if (!replacement || replacement.getAttribute('role') !== 'tabpanel') throw new Error('Could not load this section.');
      if (current !== generation) return;
      const previous = panel(key);
      if (window.jQuery?.fn.select2) {
        jQuery(previous).find('.select2-hidden-accessible').select2('destroy');
      }
      previous.replaceWith(replacement);
      document.dispatchEvent(new CustomEvent('finance:panel-loaded', {detail: {panel: replacement}}));
      loaded.add(key);
      select(key);
      address(key, push);
      feedback.textContent = '';
      if (fromHash) showHashTarget();
    } catch (error) {
      if (error.name === 'AbortError' || current !== generation) return;
      const message = document.createElement('div');
      message.className = 'alert alert-danger d-flex align-items-center flex-wrap gap-2';
      message.append('Could not load ' + tab.textContent.trim().toLowerCase() + '. Your current section is still here.');
      const retry = document.createElement('button');
      retry.type = 'button';
      retry.className = 'btn btn-sm btn-outline-danger';
      retry.textContent = 'Try again';
      retry.addEventListener('click', () => openTab(key, push, fromHash));
      message.append(retry);
      feedback.replaceChildren(message);
    } finally {
      if (current === generation) {
        clearLoading();
        pending = undefined;
      }
    }
  }
  nav.addEventListener('click', event => {
    const tab = event.target.closest('[data-finance-tab]');
    if (!tab || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    openTab(tab.dataset.financeTab);
  });
  nav.addEventListener('keydown', event => {
    const index = tabs.indexOf(event.target.closest('[data-finance-tab]'));
    if (index < 0) return;
    if (event.key === ' ') {
      event.preventDefault();
      openTab(tabs[index].dataset.financeTab);
      return;
    }
    let next;
    if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
    if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
    if (event.key === 'Home') next = 0;
    if (event.key === 'End') next = tabs.length - 1;
    if (next !== undefined) { event.preventDefault(); tabs[next].focus(); }
  });
  function restoreAddress() {
    let target;
    try { target = document.getElementById(decodeURIComponent(location.hash.slice(1))); } catch (_) { /* Invalid hash. */ }
    const hashPanel = target?.closest('.finance-tab-panel');
    const key = hashPanel?.id.replace('finance-panel-', '') || new URL(location.href).searchParams.get('tab') || 'breakdown';
    openTab(key, false, !!hashPanel);
  }
  window.addEventListener('popstate', restoreAddress);
  window.addEventListener('hashchange', restoreAddress);
  restoreAddress();

  let printState;
  window.addEventListener('beforeprint', () => {
    if (printState) return;
    printState = [...root.querySelectorAll('details, .collapse')].map(element => ({element, open: element.open, shown: element.classList.contains('show')}));
    printState.forEach(({element}) => { if (element.matches('details')) element.open = true; else element.classList.add('show'); });
  });
  window.addEventListener('afterprint', () => {
    printState?.forEach(({element, open, shown}) => { if (element.matches('details')) element.open = open; else element.classList.toggle('show', shown); });
    printState = undefined;
  });
})();
