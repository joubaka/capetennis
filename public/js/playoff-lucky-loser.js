(function () {
  'use strict';
  const control = document.getElementById('playoff-ll-control');
  if (!control) return;
  const menu = document.getElementById('playoff-ll-menu');
  const modalElement = document.getElementById('playoff-ll-modal');
  const form = document.getElementById('playoff-ll-form');
  const search = document.getElementById('playoff-ll-search');
  const candidates = document.getElementById('playoff-ll-candidate');
  const more = document.getElementById('playoff-ll-more');
  const error = document.getElementById('playoff-ll-error');
  const submit = document.getElementById('playoff-ll-submit');
  let trigger, target, nextPage, timer, requestId = 0;
  function closeMenu() {
    menu.hidden = true;
    menu.classList.remove('show');
    trigger?.setAttribute('aria-expanded', 'false');
  }
  function openMenu(button) {
    closeMenu();
    trigger = button;
    target = button.dataset.llFixture + ':' + button.dataset.llSlot + ':' + button.dataset.llRegistration;
    button.setAttribute('aria-expanded', 'true');
    const box = button.getBoundingClientRect();
    menu.style.left = Math.max(8, Math.min(box.left, window.innerWidth - 250)) + 'px';
    menu.style.top = Math.min(box.bottom + 4, window.innerHeight - 70) + 'px';
    menu.hidden = false;
    menu.classList.add('show');
    document.getElementById('playoff-ll-action').focus();
  }
  async function load(url, append) {
    const id = ++requestId;
    if (!append) candidates.replaceChildren(new Option('Loading players…', ''));
    submit.disabled = true;
    try {
      const response = await fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'Could not load event entrants.');
      if (id !== requestId) return;
      if (!append) candidates.replaceChildren(new Option('Choose a player', ''));
      result.candidates.forEach(player => candidates.add(new Option(player.name + ' · ' + (player.division || 'Event entrant'), player.id)));
      nextPage = result.next_page_url;
      more.hidden = !nextPage;
      error.textContent = result.candidates.length ? '' : 'No active entrants match this search.';
    } catch (failure) {
      if (id === requestId) error.textContent = failure.message;
    } finally {
      if (id === requestId) submit.disabled = false;
    }
  }
  document.addEventListener('click', event => {
    const button = event.target.closest('[data-ll-fixture]');
    if (button) { event.preventDefault(); event.stopPropagation(); openMenu(button); }
    else if (!menu.contains(event.target)) closeMenu();
  }, true);
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') { closeMenu(); trigger?.focus(); }
    const button = event.target.closest('[data-ll-fixture]');
    if (button && ['Enter', ' '].includes(event.key)) { event.preventDefault(); openMenu(button); }
  });
  document.getElementById('playoff-ll-action').addEventListener('click', () => {
    closeMenu();
    document.getElementById('playoff-ll-outgoing').textContent = 'Withdraw ' + (trigger.dataset.llName || trigger.textContent) + ' from this playoff place.';
    form.reset(); error.textContent = ''; search.value = '';
    bootstrap.Modal.getOrCreateInstance(modalElement).show();
    load(control.dataset.candidatesUrl, false);
  });
  search.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => { const url = new URL(control.dataset.candidatesUrl, location.href); url.searchParams.set('q', search.value); load(url, false); }, 250);
  });
  more.addEventListener('click', () => nextPage && load(nextPage, true));
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (!form.reportValidity()) return;
    submit.disabled = true; error.textContent = '';
    try {
      const response = await fetch(control.dataset.storeUrl, {method: 'POST', credentials: 'same-origin',
        headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
        body: JSON.stringify({target, source_entry_id: Number(candidates.value), revision: control.dataset.revision, confirm_withdrawal: document.getElementById('playoff-ll-confirm').checked})});
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'The replacement could not be saved.');
      location.reload();
    } catch (failure) { error.textContent = failure.message; submit.disabled = false; }
  });
})();
