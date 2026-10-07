(function () {
  'use strict';
  const modal = document.getElementById('match-reminder');
  if (!modal || !window.bootstrap?.Modal) return;
  const key = `ct.match-reminder.${modal.dataset.account}.${modal.dataset.login}`;
  const dismissed = () => { try { return sessionStorage.getItem(key); } catch (_) { return null; } };
  const buttons = document.querySelectorAll('[data-open-match-reminder]');
  let loading = false;
  let reminderModal;
  function element(tag, text, className) {
    const node = document.createElement(tag);
    node.textContent = text;
    if (className) node.className = className;
    return node;
  }
  function openReminder(manual = false) {
    if (loading) return;
    loading = true;
    buttons.forEach(button => { button.disabled = true; button.setAttribute('aria-busy', 'true'); });
    const body = modal.querySelector('[data-reminder-players]');
    const show = () => { reminderModal ||= new bootstrap.Modal(modal); reminderModal.show(); };
    return fetch(modal.dataset.endpoint, {credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}})
    .then(response => response.ok ? response.json() : null)
    .then(data => {
      if (!data) throw new Error('Reminder unavailable');
      if (!manual && (!data.players?.length || dismissed() === data.day)) return;
      body.replaceChildren();
      if (!data.players?.length) {
        body.append(element('p', 'No published, unfinished matches were found for your linked players in the next seven days.', 'mb-0'));
        show();
        return;
      }
      for (const player of data.players) {
        const section = element('section', '', 'mb-4');
        section.append(element('h3', player.name, 'fs-5'));
        for (const match of player.matches) {
          const card = element('article', '', 'border rounded p-3 mb-2');
          card.append(element('div', `${match.day} · ${match.date} · ${match.time}`, 'fw-bold reminder-time mb-2'));
          card.append(element('div', `${match.label}: ${match.participants.filter(Boolean).join(' vs ')}`, 'fw-semibold'));
          card.append(element('div', `${match.event} · ${match.draw}`, 'small mt-1'));
          card.append(element('div', match.venue, 'mt-2'));
          const link = element('a', 'View fixtures', 'btn btn-outline-primary mt-3');
          link.href = match.url;
          link.style.minHeight = '44px';
          card.append(link);
          section.append(card);
        }
        body.append(section);
      }
      const markDismissed = () => { try { sessionStorage.setItem(key, data.day); } catch (_) {} };
      modal.addEventListener('hidden.bs.modal', markDismissed, {once: true});
      modal.querySelectorAll('a').forEach(link => link.addEventListener('click', markDismissed));
      show();
    }).catch(() => {
      if (!manual) return; // Optional automatic reminder failure must not interrupt the page.
      body.replaceChildren(element('p', 'Match reminders could not be loaded. Please try again.', 'text-danger mb-0'));
      show();
    }).finally(() => {
      loading = false;
      buttons.forEach(button => { button.disabled = false; button.removeAttribute('aria-busy'); });
    });
  }
  buttons.forEach(button => button.addEventListener('click', () => openReminder(true)));
  if (dismissed() !== modal.dataset.day) openReminder();
}());
