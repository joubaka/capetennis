(function () {
  'use strict';
  const modal = document.getElementById('match-reminder');
  if (!modal || !window.bootstrap?.Modal) return;
  const key = `ct.match-reminder.${modal.dataset.account}`;
  const dismissed = () => { try { return localStorage.getItem(key); } catch (_) { return null; } };
  if (dismissed() === modal.dataset.day) return;
  function element(tag, text, className) {
    const node = document.createElement(tag);
    node.textContent = text;
    if (className) node.className = className;
    return node;
  }
  fetch(modal.dataset.endpoint, {credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}})
    .then(response => response.ok ? response.json() : null)
    .then(data => {
      if (!data?.players?.length || dismissed() === data.day) return;
      const body = modal.querySelector('[data-reminder-players]');
      for (const player of data.players) {
        const section = element('section', '', 'mb-4');
        section.append(element('h3', player.name, 'fs-5'));
        for (const match of player.matches) {
          const card = element('article', '', 'border rounded p-3 mb-2');
          card.append(element('div', `${match.day} · ${match.date} · ${match.time}`, 'fw-bold reminder-time mb-2'));
          card.append(element('div', `${match.label}: ${match.participants.filter(Boolean).join(' vs ')}`, 'fw-semibold'));
          card.append(element('div', `${match.event} · ${match.draw}`, 'small mt-1'));
          card.append(element('div', `${match.venue}${match.court ? ` · Court ${match.court}` : ''}`, 'mt-2'));
          const link = element('a', 'View fixtures', 'btn btn-outline-primary mt-3');
          link.href = match.url;
          link.style.minHeight = '44px';
          card.append(link);
          section.append(card);
        }
        body.append(section);
      }
      const markDismissed = () => { try { localStorage.setItem(key, data.day); } catch (_) {} };
      modal.addEventListener('hidden.bs.modal', markDismissed, {once: true});
      modal.querySelectorAll('a').forEach(link => link.addEventListener('click', markDismissed));
      new bootstrap.Modal(modal).show();
    }).catch(() => {}); // Optional reminder failure must not interrupt the page.
}());
