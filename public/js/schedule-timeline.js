(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.CapeScheduleTimeline = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  const asTime = value => typeof value?.getTime === 'function' ? value.getTime() : new Date(String(value).replace(' ', 'T')).getTime();

  function createState() {
    const edits = new Map();
    const bases = new Map();
    const venueEdits = venue => {
      const key = String(venue);
      if (!edits.has(key)) edits.set(key, new Map());
      return edits.get(key);
    };
    return {
      reset() { edits.clear(); bases.clear(); },
      edit(venue, previous, next, start, end) {
        const from = asTime(previous), to = asTime(next);
        if (!Number.isFinite(from) || !Number.isFinite(to) || to < asTime(start) || to >= asTime(end)) {
          throw new Error('Choose a time within the current scheduling window.');
        }
        const changes = venueEdits(venue);
        const originals = [...changes].filter(([, target]) => target === from).map(([original]) => original);
        if (!originals.length || bases.get(String(venue))?.has(from)) originals.push(from);
        originals.forEach(original => {
          if (original === to) changes.delete(original);
          else changes.set(original, to);
        });
      },
      times(venue, base, booked, start, end) {
        const changes = venueEdits(venue);
        bases.set(String(venue), new Set(base.map(asTime)));
        const bookingTimes = new Set(booked.map(asTime));
        const times = new Set(base.map(asTime).filter(time => !changes.has(time) || bookingTimes.has(time)));
        changes.forEach(time => times.add(time));
        return [...times].filter(time => time >= asTime(start) && time < asTime(end)).sort((a, b) => a - b).map(time => new Date(time));
      },
    };
  }

  function mount({document, bootstrap, state, context, onChange}) {
    const modal = document.getElementById('editTimelineModal');
    const form = document.getElementById('edit-timeline-form');
    const input = document.getElementById('edit-timeline-time');
    const status = document.getElementById('edit-timeline-status');
    let editing = null;
    let focusTarget = null;
    document.getElementById('venue-slot-grids').addEventListener('click', event => {
      const button = event.target.closest('[data-edit-timeline]');
      if (!button) return;
      const current = context(button.dataset.venue);
      if (!current) return;
      editing = {venue:button.dataset.venue, previous:button.dataset.time, ...current};
      focusTarget = {venue:editing.venue, time:editing.previous};
      input.value = editing.previous.replace(' ', 'T').slice(0, 16);
      input.min = editing.start.replace(' ', 'T').slice(0, 16);
      input.max = new Date(asTime(editing.end) - 60000).toLocaleString('sv-SE').replace(' ', 'T').slice(0, 16);
      document.getElementById('edit-timeline-venue').textContent = editing.name;
      status.textContent = '';
      bootstrap.Modal.getOrCreateInstance(modal).show();
    });
    modal.addEventListener('shown.bs.modal', () => input.focus());
    modal.addEventListener('hidden.bs.modal', () => {
      editing = null;
      const grid = document.getElementById('venue-slot-grids');
      const target = [...grid.querySelectorAll('[data-edit-timeline]')].find(button => button.dataset.venue === focusTarget?.venue && asTime(button.dataset.time) === asTime(focusTarget?.time));
      (target || grid.querySelector('[tabindex="0"]'))?.focus();
      focusTarget = null;
    });
    form.addEventListener('submit', event => {
      event.preventDefault();
      if (!editing) return;
      const current = context(editing.venue);
      if (!current || current.start !== editing.start || current.end !== editing.end || current.version !== editing.version) {
        status.textContent = 'The scheduling window changed. Close this editor and generate a new preview.';
        return;
      }
      try {
        state.edit(editing.venue, editing.previous, input.value, editing.start, editing.end);
        focusTarget.time = input.value;
        onChange();
        bootstrap.Modal.getOrCreateInstance(modal).hide();
      } catch (error) { status.textContent = error.message; }
    });
  }
  return {createState, mount};
});
