(function () {
  'use strict';
  const modal = document.getElementById('match-reminder');
  if (!modal || !window.bootstrap?.Modal) return;
  const key = `ct.match-reminder.${modal.dataset.account}.${modal.dataset.login}`;
  const dismissed = () => { try { return sessionStorage.getItem(key); } catch (_) { return null; } };
  const snoozeKey = `ct.match-reminder.snooze.${modal.dataset.account}`;
  const snoozeUntil = () => { try { return Number(localStorage.getItem(snoozeKey)) || 0; } catch (_) { return 0; } };
  const snoozed = () => snoozeUntil() > Date.now();
  const preference = modal.querySelector('[data-reminder-preference]');
  const applyPreference = modal.querySelector('[data-reminder-apply]');
  const nextDayOption = modal.querySelector('[data-reminder-next-day]');
  let nextMatchAt = null;
  const buttons = document.querySelectorAll('[data-open-match-reminder]');
  let loading = false;
  let reminderModal;
  async function enhancePreference() {
    if (!preference || !window.jQuery) return;
    const $ = window.jQuery;
    if (!$.fn.select2) {
      const styleUrl = preference.dataset.select2Style;
      if (styleUrl && !Array.from(document.querySelectorAll('link[rel="stylesheet"]')).some(link => link.href === styleUrl)) {
        const link = document.createElement('link');
        link.rel = 'stylesheet'; link.href = styleUrl; document.head.append(link);
      }
      try {
        await new Promise((resolve, reject) => {
          const script = document.createElement('script');
          script.src = preference.dataset.select2Script;
          script.onload = resolve; script.onerror = reject; document.head.append(script);
        });
      } catch (_) { return; }
    }
    if (!$.fn.select2) return;
    if ($(preference).hasClass('select2-hidden-accessible')) $(preference).select2('destroy');
    $(preference).select2({dropdownParent: $(modal), minimumResultsForSearch: Infinity, width: '100%'});
    const selection = $(preference).next('.select2-container').find('.select2-selection');
    selection.css({'min-height': '44px', display: 'flex', 'align-items': 'center'});
    selection.attr('aria-describedby', 'match-reminder-timing-help');
  }
  modal.addEventListener('shown.bs.modal', enhancePreference);
  const markDismissed = () => {
    if (snoozed()) return;
    try { sessionStorage.setItem(key, 'closed'); } catch (_) {}
  };
  modal.addEventListener('hidden.bs.modal', markDismissed);
  applyPreference?.addEventListener('click', () => {
    const choice = preference.value;
    const until = choice === 'hour' ? Date.now() + 3600000 : choice === 'week' ? Date.now() + 7 * 86400000 : choice === 'match-day' ? nextMatchAt : null;
    if (choice === 'match-day' && !until) return;
    if (until) {
      try { localStorage.setItem(snoozeKey, String(until)); sessionStorage.removeItem(key); } catch (_) {}
    } else {
      try { localStorage.removeItem(snoozeKey); } catch (_) {}
      markDismissed();
    }
    reminderModal?.hide();
  });
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
      if (!manual && (!data.players?.length || dismissed() || snoozed())) return;
      // Calendar days use South African time even when the browser is abroad.
      const starts = (data.players || []).flatMap(player => player.matches || []).map(match => {
        const value = match.scheduled_at;
        if (!value) return null;
        const zoned = /(?:Z|[+-]\d{2}:?\d{2})$/.test(value) ? value : `${value.replace(' ', 'T')}+02:00`;
        const start = Date.parse(zoned);
        if (!Number.isFinite(start) || start <= Date.now()) return null;
        const parts = new Intl.DateTimeFormat('en', {timeZone: 'Africa/Johannesburg', year: 'numeric', month: '2-digit', day: '2-digit'}).formatToParts(new Date(start));
        const part = type => parts.find(item => item.type === type).value;
        const day = `${part('year')}-${part('month')}-${part('day')}`;
        return {start, day};
      }).filter(Boolean).sort((a, b) => a.start - b.start);
      const futureDay = starts.find(match => match.day > data.day);
      nextMatchAt = futureDay ? Date.parse(`${futureDay.day}T00:00:00+02:00`) : starts[0]?.start || null;
      if (nextDayOption) nextDayOption.disabled = !nextMatchAt;
      if (preference && preference.value === 'match-day' && !nextMatchAt) preference.value = 'login';
      body.replaceChildren();
      if (!data.players?.length) {
        body.append(element('p', 'No published, unfinished matches were found for your linked players in the next seven days.', 'mb-0'));
        show();
        return;
      }
      for (const player of data.players) {
        const section = element('section', '', 'reminder-player');
        section.append(element('h3', player.name, 'reminder-player-name'));
        for (const match of player.matches) {
          const card = element('article', '', 'reminder-match');
          card.append(element('div', `${match.date} · ${match.time}`, 'reminder-time'));
          card.append(element('div', `${match.label}: ${match.participants.filter(Boolean).join(' vs ')}`, 'reminder-participants'));
          card.append(element('div', `${match.event} · ${match.draw}`, 'reminder-event'));
          card.append(element('div', match.venue, 'reminder-venue'));
          const link = element('a', 'View fixtures', 'reminder-fixtures');
          link.href = match.url;
          link.style.minHeight = '44px';
          card.append(link);
          section.append(card);
        }
        body.append(section);
      }
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
  if (modal.dataset.autoOpen === '1' && !dismissed() && !snoozed()) openReminder();
}());
