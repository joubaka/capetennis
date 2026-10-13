(function () {
  'use strict';
  const root = document.querySelector('[data-preview-share]'); if (!root) return;
  const selected = new Map(), results = root.querySelector('[data-share-results]'), selectedList = root.querySelector('[data-share-selected-list]');
  const hidden = root.querySelector('[data-share-hidden-inputs]'), status = root.querySelector('[data-share-search-status]'), search = root.querySelector('[data-share-search]');
  let timer, requestSequence = 0, abort, grantBusy = false, whatsappSelection, grantSequence = 0;
  const grants = root.querySelector('[data-share-grants]'), grantStatus = root.querySelector('[data-share-grant-status]');
  const whatsapp = root.querySelector('[data-share-whatsapp]'), whatsappOpen = root.querySelector('[data-share-whatsapp-open]'), whatsappStatus = root.querySelector('[data-share-whatsapp-status]');
  whatsapp.hidden = false;
  let initialLimited = false;
  JSON.parse(root.querySelector('[data-share-initial]').textContent || '[]').slice(0, 30).forEach(user => selected.set(String(user.id), user));
  try {
    if (root.dataset.sendComplete === '1') sessionStorage.removeItem(root.dataset.storageKey);
    else JSON.parse(sessionStorage.getItem(root.dataset.storageKey) || '[]').forEach(user => {
      if (!user.id || !user.name || selected.has(String(user.id))) return;
      if (selected.size >= 30) { initialLimited = true; return; }
      selected.set(String(user.id), user);
    });
  } catch (_) {}
  function element(tag, text, className) { const node = document.createElement(tag); if (text !== undefined) node.textContent = text; if (className) node.className = className; return node; }
  function sync() {
    selectedList.replaceChildren(); hidden.replaceChildren();
    if (!selected.size) selectedList.append(element('p', 'Choose an account to start your invitation list.', 'share-help'));
    selected.forEach(user => {
      const row = element('div', undefined, 'share-selected-row'), identity = element('span'); identity.append(element('strong', user.name), element('small', user.email));
      const remove = element('button', '×', 'share-remove-recipient'); remove.type = 'button'; remove.disabled = grantBusy; remove.setAttribute('aria-label', 'Remove ' + user.name); remove.addEventListener('click', () => { if (!grantBusy) { selected.delete(String(user.id)); sync(); } });
      row.append(identity, remove); selectedList.append(row);
      const input = element('input'); input.type = 'hidden'; input.name = 'recipient_ids[]'; input.value = user.id; hidden.append(input);
    });
    results.querySelectorAll('[data-share-user]').forEach(input => { input.removeAttribute('name'); input.checked = selected.has(input.value); input.disabled = grantBusy || input.dataset.selectable === '0'; });
    root.querySelector('[data-share-count]').textContent = selected.size + ' / 30'; root.querySelector('[data-share-send-button]').disabled = !selected.size || grantBusy;
    whatsapp.disabled = !selected.size || grantBusy;
    if (whatsappSelection && whatsappSelection !== JSON.stringify([...selected.keys()].sort())) { whatsappOpen.hidden = true; whatsappStatus.textContent = 'Recipients changed. Create a new WhatsApp link for the current list.'; }
    root.querySelector('[data-share-clear]').disabled = !selected.size || grantBusy;
    try { sessionStorage.setItem(root.dataset.storageKey, JSON.stringify([...selected.values()])); } catch (_) {}
  }
  root.addEventListener('change', event => {
    const input = event.target.closest('[data-share-user]'); if (!input) return;
    if (grantBusy) { sync(); return; }
    if (input.checked) { if (selected.size >= 30 && !selected.has(input.value)) { input.checked = false; status.textContent = 'Choose no more than 30 accounts per send.'; return; } selected.set(input.value, { id: Number(input.value), name: input.dataset.name, email: input.dataset.email }); }
    else selected.delete(input.value); sync();
  });
  function render(data) {
    results.replaceChildren(); const list = element('div', undefined, 'share-user-list');
    if (!data.users.length) { const empty = element('div', undefined, 'share-empty'); empty.append(element('strong', 'No matching accounts'), element('p', 'Try part of a name, surname or email address.')); list.append(empty); }
    data.users.forEach(user => {
      const label = element('label', undefined, 'share-user-card'), input = element('input'); input.type = 'checkbox'; input.className = 'form-check-input'; input.value = String(user.id); input.dataset.shareUser = ''; input.dataset.name = user.name; input.dataset.email = user.email; input.dataset.selectable = user.selectable ? '1' : '0'; input.disabled = !user.selectable;
      const identity = element('span'); identity.append(element('strong', user.name), element('small', 'Account #' + user.id + ' · ' + user.email));
      if (user.shared) identity.append(element('small', 'Already has preview access', 'share-access-label'));
      if (!user.selectable) identity.append(element('small', 'Valid account email needed', 'text-danger'));
      label.append(input, identity); list.append(label);
    }); results.append(list);
    const page = data.pagination, nav = element('nav', undefined, 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-3'); nav.setAttribute('aria-label', 'Account search pages');
    nav.append(element('span', 'Page ' + page.current_page + ' of ' + page.last_page + ' · ' + page.total + ' accounts', 'text-muted small'));
    const links = element('div', undefined, 'd-flex gap-2'); [['Previous', page.previous_page_url], ['Next', page.next_page_url]].forEach(([label, url]) => { if (url) { const link = element('a', label, 'btn btn-outline-secondary btn-sm'); link.href = url; link.dataset.sharePage = ''; links.append(link); } }); nav.append(links); results.append(nav);
    root.querySelector('[data-share-result-count]').textContent = page.total + ' found'; sync();
  }
  async function load(url) {
    const sequence = ++requestSequence; abort?.abort(); abort = new AbortController(); results.setAttribute('aria-busy', 'true'); status.textContent = 'Searching accounts…';
    try {
      const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: abort.signal }); const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Search could not be loaded.');
      if (sequence !== requestSequence) return; render(data); status.textContent = data.pagination.total ? 'Choose accounts to add to your selected list.' : 'No matching accounts. Your selected recipients are kept.';
      const nextUrl = new URL(url, window.location.href); window.history.replaceState({}, '', nextUrl.href);
    } catch (error) { if (sequence === requestSequence && error.name !== 'AbortError') status.textContent = error.message + ' Your selected recipients are kept. Try Search again.'; }
    finally { if (sequence === requestSequence) results.removeAttribute('aria-busy'); }
  }
  function searchUrl() { const url = new URL(root.dataset.searchUrl, window.location.href); url.searchParams.set('q', search.elements.q.value.trim()); return url.href; }
  search.elements.q.addEventListener('input', () => { window.clearTimeout(timer); timer = window.setTimeout(() => load(searchUrl()), 350); });
  search.addEventListener('submit', event => { event.preventDefault(); window.clearTimeout(timer); load(searchUrl()); });
  root.addEventListener('click', event => { const link = event.target.closest('[data-share-page]'); if (!link) return; event.preventDefault(); window.clearTimeout(timer); load(link.href); });
  root.querySelector('[data-share-clear]').addEventListener('click', () => { if (!grantBusy) { selected.clear(); sync(); } });
  async function jsonRequest(url, options = {}) {
    const response = await fetch(url, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': root.querySelector('[data-share-send]').elements._token.value, ...options.headers } });
    const data = await response.json(); if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'The action could not be completed.');
    return data;
  }
  async function loadGrants(url) {
    const sequence = ++grantSequence, endpoint = new URL(url || root.dataset.searchUrl, window.location.href); endpoint.searchParams.set('section', 'grants');
    grants.setAttribute('aria-busy', 'true');
    try {
      const data = await jsonRequest(endpoint.href); if (sequence !== grantSequence) return;
      grants.replaceChildren();
      if (!data.grants.length) grants.append(element('p', 'No shared accounts yet.', 'share-help'));
      data.grants.forEach(user => {
        const row = element('div', undefined, 'share-existing-row'), identity = element('span'); identity.append(element('strong', user.name), element('small', user.email));
        const form = element('form'); form.method = 'POST'; form.action = user.revoke_url; form.dataset.shareRevoke = '';
        [['_token', root.querySelector('[data-share-send]').elements._token.value], ['_method', 'DELETE']].forEach(([name, value]) => { const input = element('input'); input.type = 'hidden'; input.name = name; input.value = value; form.append(input); });
        const button = element('button', 'Remove access', 'btn btn-outline-danger btn-sm'); button.type = 'submit'; form.append(button); row.append(identity, form); grants.append(row);
      });
      const page = data.pagination, nav = element('nav', undefined, 'd-flex flex-wrap align-items-center justify-content-between gap-2 mt-3'); nav.setAttribute('aria-label', 'Shared access pages');
      nav.append(element('span', 'Page ' + page.current_page + ' of ' + page.last_page + ' · ' + page.total + ' accounts', 'text-muted small'));
      const links = element('div', undefined, 'd-flex gap-2'); [['Previous', page.previous_page_url], ['Next', page.next_page_url]].forEach(([label, url]) => { if (url) { const link = element('a', label, 'btn btn-outline-secondary btn-sm'); link.href = url; link.dataset.shareGrantPage = ''; links.append(link); } }); nav.append(links); grants.append(nav);
      root.querySelector('[data-share-grant-count]').textContent = page.total + ' shared accounts'; grantStatus.textContent = '';
    } catch (error) { if (sequence === grantSequence) grantStatus.textContent = error.message + ' Refresh shared accounts to try again.'; }
    finally { if (sequence === grantSequence) grants.removeAttribute('aria-busy'); }
  }
  root.addEventListener('click', event => { const link = event.target.closest('[data-share-grants] nav a, [data-share-grant-page]'); if (link) { event.preventDefault(); loadGrants(link.href); } });
  root.addEventListener('submit', async event => {
    const form = event.target.closest('[data-share-revoke]'); if (!form) return; event.preventDefault(); if (grantBusy) return;
    grantBusy = true; sync(); form.querySelector('button').disabled = true; grantStatus.textContent = 'Removing preview access…';
    try { await jsonRequest(form.action, { method: 'DELETE' }); await loadGrants(); }
    catch (error) { grantStatus.textContent = error.message; }
    finally { grantBusy = false; form.querySelector('button').disabled = false; sync(); }
  });
  async function share(channel) {
    if (grantBusy || !selected.size) return;
    grantBusy = true; sync(); whatsappOpen.hidden = true; whatsappStatus.textContent = 'Preparing access for the selected accounts…';
    const form = root.querySelector('[data-share-send]'), ids = [...selected.keys()].map(Number);
    try {
      const data = await jsonRequest(form.action, { method: 'POST', body: JSON.stringify({ request_key: form.elements.request_key.value, recipient_ids: ids, age: form.elements.age?.value || null, channel }) });
      let link;
      if (channel === 'whatsapp') { link = new URL(data.whatsapp_url); if (link.protocol !== 'https:' || link.hostname !== 'wa.me') throw new Error('The shared link could not be verified.'); }
      form.elements.request_key.value = data.next_request_key; selected.clear();
      if (channel === 'whatsapp') { whatsappSelection = '[]'; whatsappOpen.href = link.href; whatsappOpen.hidden = false; whatsappStatus.textContent = 'Access prepared for ' + data.recipients + ' accounts. Open WhatsApp and choose these people. No email was sent.'; }
      else { whatsappSelection = null; whatsappStatus.textContent = 'Access shared with ' + data.recipients + ' accounts. Email: ' + data.queued + ' queued, ' + data.prepared + ' prepared, ' + data.sending + ' processing, ' + data.accepted + ' accepted, ' + data.failed + ' failed, ' + data.skipped + ' skipped, ' + data.unknown + ' uncertain. These outcomes do not confirm inbox delivery.'; }
      await loadGrants();
    } catch (error) { whatsappStatus.textContent = error.message + ' Your selected recipients are kept; retry the same action.'; }
    finally { grantBusy = false; sync(); }
  }
  whatsapp.addEventListener('click', () => share('whatsapp'));
  root.querySelector('[data-share-send]').addEventListener('submit', event => { event.preventDefault(); if (grantBusy || !selected.size) status.textContent = grantBusy ? 'Wait for access preparation to finish.' : 'Choose at least one recipient.'; else return share('email'); });
  sync();
  if (initialLimited) status.textContent = 'Your submitted selections were kept first. The invitation list is limited to 30 accounts; review it before sending.';
})();
