(() => {
  const root = document.querySelector('[data-cavaliers-roster]');
  if (!root) return;

  const element = (selector) => root.querySelector(selector);
  const division = element('[data-division]');
  const editor = element('[data-editor]');
  const status = element('[data-status]');
  const save = element('[data-save]');
  const reload = element('[data-reload]');
  const search = element('[data-search]');
  const searchResults = element('[data-search-results]');
  const swapForm = element('[data-swap]');
  const swapTarget = element('[data-swap-target]');
  const limits = { selected: Number(root.dataset.selectedLimit), reserves: Number(root.dataset.reserveLimit) };
  const labels = { selected: 'Selected', reserves: 'Reserves' };
  let activeDivision = '';
  let draft = null;
  let dirty = false;
  let busy = false;
  let stale = false;
  let swap = null;
  let searchVersion = 0;
  let found = [];

  function setStatus(message, error = false) {
    status.textContent = message;
    status.classList.toggle('text-danger', error);
  }

  async function request(url, options = {}) {
    const response = await fetch(url, {
      ...options,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
      },
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) {
      const message = Object.values(data.errors ?? {}).flat()[0] ?? data.message;
      throw Object.assign(new Error(message || 'The request failed. Try again.'), { status: response.status });
    }
    return data;
  }

  function button(label, action, disabled = false) {
    const control = document.createElement('button');
    control.type = 'button';
    control.className = 'btn btn-sm btn-outline-secondary';
    control.textContent = label;
    control.disabled = disabled || busy || stale;
    control.addEventListener('click', action);
    return control;
  }

  function renderSearch() {
    const chosen = new Set([...draft.selected, ...draft.reserves].map((player) => player.id));
    searchResults.replaceChildren();
    searchResults.classList.toggle('d-none', found.length === 0);
    found.forEach((player) => {
      const row = document.createElement('li');
      row.className = 'list-group-item d-flex flex-wrap gap-2 justify-content-between align-items-center';
      const name = document.createElement('span');
      name.textContent = player.name;
      const actions = document.createElement('div');
      actions.className = 'd-flex flex-wrap gap-1';
      if (chosen.has(player.id)) {
        actions.textContent = 'Already on this roster';
      } else {
        for (const key of Object.keys(limits)) {
          const action = key === 'selected' ? 'Add selected' : 'Add reserve';
          actions.append(button(action, () => {
            draft[key].push(player);
            changed();
          }, draft[key].length >= limits[key]));
        }
      }
      row.append(name, actions);
      searchResults.append(row);
    });
  }

  function render() {
    division.disabled = busy;
    reload.disabled = busy;
    editor.hidden = !draft;
    if (!draft) return;
    search.querySelectorAll('input, button').forEach((control) => { control.disabled = busy || stale; });
    save.disabled = !dirty || busy || stale;
    for (const key of Object.keys(limits)) {
      const list = element(`[data-list="${key}"]`);
      list.replaceChildren();
      element(`[data-count="${key}"]`).textContent = `${draft[key].length} / ${limits[key]}`;
      element(`[data-empty="${key}"]`).hidden = draft[key].length !== 0;
      draft[key].forEach((player, index) => {
        const row = document.createElement('li');
        row.className = 'list-group-item d-flex flex-wrap gap-2 align-items-center';
        const name = document.createElement('span');
        name.className = 'flex-grow-1 text-break';
        name.textContent = player.name;
        const actions = document.createElement('div');
        actions.className = 'd-flex flex-wrap gap-1';
        const opposite = key === 'selected' ? 'reserves' : 'selected';
        const describe = (control) => {
          control.setAttribute('aria-label', `${control.textContent} — ${player.name}`);
          return control;
        };
        actions.append(
          describe(button('Up', () => moveWithin(key, index, -1), index === 0)),
          describe(button('Down', () => moveWithin(key, index, 1), index === draft[key].length - 1)),
          describe(button('Remove', () => {
            draft[key].splice(index, 1);
            changed();
          })),
          describe(button(`Move to ${labels[opposite].toLowerCase()}`, () => {
            draft[opposite].push(draft[key].splice(index, 1)[0]);
            changed();
          }, draft[opposite].length >= limits[opposite])),
          describe(button('Swap', () => showSwap(key, index, opposite), draft[opposite].length === 0))
        );
        row.append(name, actions);
        list.append(row);
      });
    }
    renderSearch();
  }

  function changed() {
    dirty = true;
    closeSwap();
    render();
    setStatus('Unsaved changes.');
  }

  function moveWithin(key, index, offset) {
    const list = draft[key];
    [list[index], list[index + offset]] = [list[index + offset], list[index]];
    changed();
    const row = element(`[data-list="${key}"]`).children[index + offset];
    const controls = [...row.querySelectorAll('button')];
    const direction = controls[offset < 0 ? 0 : 1];
    (direction.disabled ? controls.find((control) => !control.disabled) : direction)?.focus();
  }

  function showSwap(key, index, opposite) {
    swap = { key, index, opposite };
    element('[data-swap-label]').textContent = `Swap ${draft[key][index].name} with`;
    swapTarget.replaceChildren(new Option('Choose a player', '', true, true));
    draft[opposite].forEach((player, position) => {
      swapTarget.add(new Option(`${position + 1}. ${player.name}`, String(position)));
    });
    swapForm.hidden = false;
    swapTarget.focus();
  }

  function closeSwap() {
    swap = null;
    swapForm.hidden = true;
  }

  swapForm.addEventListener('submit', (event) => {
    event.preventDefault();
    if (!swap || busy || stale) return;
    const { key, index, opposite } = swap;
    const target = Number(swapTarget.value);
    [draft[key][index], draft[opposite][target]] = [draft[opposite][target], draft[key][index]];
    changed();
  });
  element('[data-cancel-swap]').addEventListener('click', closeSwap);

  async function load() {
    activeDivision = division.value;
    draft = null;
    dirty = false;
    stale = false;
    found = [];
    search.reset();
    searchVersion++;
    closeSwap();
    reload.classList.add('d-none');
    setStatus('');
    if (!activeDivision) {
      render();
      return;
    }
    busy = true;
    render();
    setStatus('Loading draft…');
    try {
      draft = await request(division.selectedOptions[0].dataset.draftUrl);
      setStatus(draft.revision ? 'Saved draft loaded.' : 'New draft.');
    } catch (error) {
      reload.classList.remove('d-none');
      setStatus(error.message, true);
    } finally {
      busy = false;
      render();
    }
  }

  division.addEventListener('change', () => {
    if (dirty && !window.confirm('Discard unsaved changes in this division?')) {
      division.value = activeDivision;
      return;
    }
    load();
  });
  reload.addEventListener('click', () => {
    if (!dirty || window.confirm('Discard unsaved changes and load the saved draft?')) load();
  });

  search.addEventListener('submit', async (event) => {
    event.preventDefault();
    const version = ++searchVersion;
    const url = new URL(division.selectedOptions[0].dataset.searchUrl, document.baseURI);
    url.searchParams.set('q', element('[data-search-input]').value.trim());
    found = [];
    renderSearch();
    setStatus('Searching…');
    try {
      const players = await request(url);
      if (version !== searchVersion) return;
      found = players;
      renderSearch();
      setStatus(players.length ? `${players.length} result(s). Refine the name if needed.` : 'No matching players.');
    } catch (error) {
      if (version === searchVersion) setStatus(error.message, true);
    }
  });

  save.addEventListener('click', async () => {
    busy = true;
    searchVersion++;
    closeSwap();
    render();
    setStatus('Saving draft…');
    try {
      const { revision } = await request(division.selectedOptions[0].dataset.draftUrl, {
        method: 'PUT',
        body: JSON.stringify({
          revision: draft.revision,
          selected: draft.selected.map((player) => player.id),
          reserves: draft.reserves.map((player) => player.id),
        }),
      });
      draft.revision = revision;
      dirty = false;
      setStatus('Draft saved.');
    } catch (error) {
      stale = error.status === 409;
      reload.classList.toggle('d-none', !stale);
      setStatus(error.message, true);
    } finally {
      busy = false;
      render();
    }
  });

  window.addEventListener('beforeunload', (event) => {
    if (!dirty) return;
    event.preventDefault();
    event.returnValue = true;
  });
})();
