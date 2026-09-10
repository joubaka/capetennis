/** Draw assignments: one state model and one handler per action. */
(function ($, root) {
  'use strict';
  let drawId,
    groups = [],
    roster = [],
    baseline = '',
    revision = '',
    busy = false,
    instances = [];
  const selected = new Set();
  const esc = value =>
    $('<div>')
      .text(value == null ? '' : String(value))
      .html();
  const snapshot = () =>
    JSON.stringify(groups.map(g => ({ group_id: g.id, registration_ids: g.players.map(p => Number(p.id)) })));
  const dirty = () => snapshot() !== baseline;
  const editable = () => !!root.RR_CAN_ASSIGN && !root.RR_DRAW_LOCKED && !root.RR_DRAW_PUBLISHED;
  const normalise = raw =>
    (raw || []).map(g => ({
      id: Number(g.id),
      name: g.name,
      players: (g.players || g.registrations || []).map(p => ({
        id: Number(p.id),
        name: p.name || p.display_name || 'Unknown player'
      }))
    }));
  function status(message, error) {
    $('#rr-save-status').text(message).toggleClass('text-danger', !!error);
  }
  function announce(message) {
    $('#rr-assignment-message').text(message);
  }
  function guard() {
    if (busy) return false;
    return !dirty() || root.confirm('You have unsaved assignments. Discard these changes and reload?');
  }
  function options(value) {
    return groups
      .map(
        g => '<option value="' + g.id + '"' + (g.id == value ? ' selected' : '') + '>Group ' + esc(g.name) + '</option>'
      )
      .join('');
  }
  function visiblePlayers() {
    const assigned = new Set(groups.flatMap(g => g.players.map(p => p.id)));
    const query = String($('#rr-player-search').val() || '')
      .toLowerCase()
      .trim();
    const category = $('#rr-player-category').val();
    return roster.filter(
      p =>
        !assigned.has(p.id) &&
        (!category || String(p.category_id) === category) &&
        (!query || p.name.toLowerCase().includes(query))
    );
  }
  function update() {
    const count = groups.reduce((total, g) => total + g.players.length, 0);
    $('#rr-assigned-count').text(count + ' assigned · ' + groups.length + ' groups');
    const assigned = new Set(groups.flatMap(g => g.players.map(p => p.id)));
    $('#rr-available-count').text(roster.filter(p => !assigned.has(p.id)).length);
    if (!busy) status(dirty() ? 'Unsaved changes' : 'Saved');
    $('#btn-save-groups, #rr-discard-groups').prop('disabled', busy || !editable() || !dirty());
    $('#btn-regenerate-fixtures').prop(
      'disabled',
      busy || !root.RR_CAN_GENERATE || root.RR_DRAW_LOCKED || root.RR_DRAW_PUBLISHED || !groups.length
    );
    $('#groups-pane .rr-player-actions button').prop('disabled', busy || !editable());
    $('#groups-pane .rr-group').each(function () {
      $(this).children().first().find('.rr-player-up').prop('disabled', true);
      $(this).children().last().find('.rr-player-down').prop('disabled', true);
    });
    $(
      '#rr-apply-group-count, #groups-tab-boxes, #btn-import-teams, #rr-assign-selected, #rr-select-visible, #rr-assign-target'
    ).prop('disabled', busy || !editable());
    $('.rr-locked-overlay').toggleClass('d-none', editable());
  }
  function renderAvailable() {
    const visible = visiblePlayers();
    let html = '<ul class="rr-sortable" data-type="source">';
    visible.forEach(p => {
      html +=
        '<li class="rr-player-row" data-id="' +
        p.id +
        '"><input class="form-check-input rr-player-select" type="checkbox" aria-label="Select ' +
        esc(p.name) +
        '"' +
        (selected.has(p.id) ? ' checked' : '') +
        (!editable() || busy ? ' disabled' : '') +
        '><span class="rr-player-name">' +
        esc(p.name) +
        '<small>' +
        esc(p.category || '') +
        '</small></span><button type="button" class="btn btn-sm btn-outline-primary rr-add-player" aria-label="Add ' +
        esc(p.name) +
        ' to selected group"' +
        (!editable() || busy ? ' disabled' : '') +
        '>Add</button></li>';
    });
    html += '</ul>';
    if (!visible.length)
      html +=
        '<p class="p-3 text-muted small">' +
        (roster.length
          ? 'No available players match this view. Clear the search or remove a player from a group.'
          : 'No eligible entries yet. Add or complete entries through the event, then refresh players.') +
        '</p>';
    $('#available-players-list').html(html);
    $('#rr-select-visible').prop('checked', visible.length > 0 && visible.every(p => selected.has(p.id)));
  }
  function render() {
    instances.forEach(instance => instance.destroy());
    instances = [];
    const target = $('#rr-assign-target').val();
    $('#rr-assign-target').html(options(target));
    let html = '';
    groups.forEach(g => {
      html +=
        '<section class="rr-group-card"><header><h6>Group ' +
        esc(g.name) +
        '</h6><span class="badge bg-label-primary">' +
        g.players.length +
        ' players</span></header><ul class="rr-sortable rr-group" data-group-id="' +
        g.id +
        '">';
      g.players.forEach((p, index) => {
        html +=
          '<li class="rr-player-row" data-id="' +
          p.id +
          '"><span class="rr-drag-handle" aria-hidden="true">⠿</span><span class="rr-seed">' +
          (index + 1) +
          '</span><span class="rr-player-name">' +
          esc(p.name) +
          '</span>';
        if (editable())
          html +=
            '<div class="rr-player-actions"><button type="button" class="rr-player-up" aria-label="Move ' +
            esc(p.name) +
            ' up"' +
            (!index ? ' disabled' : '') +
            '>↑</button><button type="button" class="rr-player-down" aria-label="Move ' +
            esc(p.name) +
            ' down"' +
            (index === g.players.length - 1 ? ' disabled' : '') +
            '>↓</button><button type="button" class="rr-player-move" aria-label="Move ' +
            esc(p.name) +
            ' to another group">⇄</button><button type="button" class="btn-remove-from-group" aria-label="Remove ' +
            esc(p.name) +
            ' from group">×</button></div>';
        html += '</li>';
      });
      html +=
        '</ul>' +
        (!g.players.length
          ? '<p class="small text-muted p-3 mb-0">Assign players here or drag them into this group.</p>'
          : '') +
        '</section>';
    });
    $('#rr-groups-row').html(
      html || '<div class="alert alert-info">Choose the number of groups above and select Apply to begin.</div>'
    );
    renderAvailable();
    update();
    initSortable();
  }
  function initSortable() {
    instances.forEach(instance => instance.destroy());
    instances = [];
    if (!root.Sortable || !editable() || busy) return;
    document.querySelectorAll('#groups-pane .rr-sortable').forEach(el => {
      instances.push(
        new Sortable(el, {
          group: 'draw-players',
          animation: 150,
          filter: 'input,button',
          preventOnFilter: false,
          onEnd: function () {
            groups.forEach(g => {
              g.players = Array.from(document.querySelectorAll('.rr-group[data-group-id="' + g.id + '"] > li')).map(
                li => player(Number(li.dataset.id))
              );
            });
            selected.clear();
            render();
          }
        })
      );
    });
  }
  function player(id) {
    return (
      roster.find(p => p.id === id) ||
      groups.flatMap(g => g.players).find(p => p.id === id) || { id: id, name: 'Unknown player' }
    );
  }
  function assign(ids, target) {
    if (!editable() || busy) return;
    const group = groups.find(g => g.id === Number(target));
    if (!group) {
      AdminToast.warning('Create or select a destination group first.');
      return;
    }
    const players = ids.map(player);
    groups.forEach(g => {
      g.players = g.players.filter(p => !ids.includes(p.id));
    });
    group.players.push(...players);
    selected.clear();
    render();
  }
  function syncState() {
    AdminState.setGroups(
      groups.map(g => ({
        id: g.id,
        name: g.name,
        registrations: g.players.map((p, index) => ({ id: p.id, display_name: p.name, seed: index + 1 }))
      }))
    );
  }
  async function save() {
    if (busy || !editable()) return false;
    if (!dirty()) return true;
    busy = true;
    render();
    status('Saving…');
    try {
      const result = await AdminApi.request({
        url: AdminRoutes.drawUrl(drawId, '/save-groups'),
        method: 'POST',
        json: true,
        retries: 0,
        data: { groups: JSON.parse(snapshot()), revision: revision }
      });
      revision = result.revision;
      baseline = snapshot();
      syncState();
      announce(
        result.fixtures_need_regeneration
          ? 'Assignments saved. Preview and regenerate fixtures before using the changed groups.'
          : 'Assignments saved. Event registrations are unchanged.'
      );
      return true;
    } catch (error) {
      announce(error.message);
      return false;
    } finally {
      busy = false;
      render();
    }
  }
  async function refresh(force) {
    if (!force && !guard()) return;
    busy = true;
    update();
    try {
      const results = await Promise.all([
        AdminApi.get(AdminRoutes.drawUrl(drawId, '/groups-data')),
        AdminApi.get(AdminRoutes.drawUrl(drawId, '/available-players'))
      ]);
      const next = normalise(results[0].groups);
      const nextRoster = results[1].categories.flatMap(c =>
        c.players.map(p => ({ id: Number(p.id), name: p.name, category_id: c.id, category: c.category }))
      );
      // Keep names/category identity for assigned players, including now-ineligible historical entries.
      next
        .flatMap(g => g.players)
        .forEach(p => {
          if (!nextRoster.some(r => r.id === p.id)) nextRoster.push(roster.find(r => r.id === p.id) || p);
        });
      groups = next;
      roster = nextRoster;
      revision = results[0].revision;
      baseline = snapshot();
      selected.clear();
      categories();
      syncState();
    } catch (error) {
      AdminToast.error(error.message);
    } finally {
      busy = false;
      render();
    }
  }
  function categories() {
    const value = $('#rr-player-category').val();
    const categories = new Map(roster.filter(p => p.category_id).map(p => [String(p.category_id), p.category]));
    $('#rr-player-category')
      .html(
        '<option value="">All categories</option>' +
          Array.from(categories, ([id, name]) => '<option value="' + id + '">' + esc(name) + '</option>').join('')
      )
      .val(value || '');
    $('#rr-player-category').toggle(categories.size > 1);
  }
  function discard() {
    groups = normalise(
      JSON.parse(baseline).map(g => ({
        id: g.group_id,
        name: groups.find(x => x.id === g.group_id).name,
        players: g.registration_ids.map(player)
      }))
    );
    selected.clear();
    render();
  }
  async function resize() {
    if (!editable() || !guard()) return;
    if (dirty()) discard();
    const count = Number($('#groups-tab-boxes').val());
    if (count === groups.length) return;
    const removed = groups.slice(count),
      moved = removed.reduce((n, g) => n + g.players.length, 0);
    const result = await Swal.fire({
      title: groups.length ? 'Change groups?' : 'Create groups?',
      html:
        '<p>Keep the existing players and seed order in the remaining groups.</p>' +
        (moved
          ? '<p>' +
            moved +
            ' players need a new group. Move them to:</p><select id="rr-resize-target" class="form-select">' +
            groups
              .slice(0, count)
              .map(g => '<option value="' + g.id + '">Group ' + esc(g.name) + '</option>')
              .join('') +
            '</select>'
          : ''),
      showCancelButton: true,
      confirmButtonText: 'Apply groups',
      preConfirm: () => $('#rr-resize-target').val() || null
    });
    if (!result.isConfirmed) return;
    busy = true;
    update();
    try {
      await AdminApi.request({
        url: AdminRoutes.drawUrl(drawId, '/settings'),
        method: 'POST',
        retries: 0,
        data: { boxes: count, move_to_group_id: result.value }
      });
      await refresh(true);
      document.dispatchEvent(new CustomEvent('rr:groups:count:changed', { detail: { count: count } }));
      // Preset choices and per-stage notes depend on the number of groups.
      // Reload the saved workspace so these server-rendered options also agree.
      if (!dirty()) {
        busy = false;
        root.location.reload();
      }
    } catch (error) {
      AdminToast.error(error.message);
      $('#groups-tab-boxes').val(groups.length || 4);
    } finally {
      busy = false;
      update();
    }
  }
  async function generate() {
    if (busy || !root.RR_CAN_GENERATE) return;
    if (!(await save())) return;
    const matches = groups.reduce((total, g) => total + (g.players.length * (g.players.length - 1)) / 2, 0);
    if (!matches) {
      AdminToast.warning('Assign at least two players to a group first.');
      return;
    }
    const existing = AdminState.getOop().length;
    const result = await Swal.fire({
      title: 'Preview round-robin fixtures',
      html:
        '<p>' +
        groups.map(g => 'Group ' + esc(g.name) + ': ' + g.players.length + ' players').join('<br>') +
        '</p><p><strong>' +
        matches +
        ' playable matches</strong>, plus any bye slots.</p>' +
        (existing
          ? '<p class="text-danger">This replaces ' +
            existing +
            ' existing fixtures, including playoff fixtures. Their schedule will need to be reviewed. Scored fixtures cannot be replaced.</p>'
          : ''),
      showCancelButton: true,
      confirmButtonText: existing ? 'Regenerate fixtures' : 'Generate fixtures'
    });
    if (!result.isConfirmed) return;
    busy = true;
    update();
    status('Generating fixtures…');
    try {
      await AdminApi.request({
        url: AdminRoutes.drawUrl(drawId, '/regenerate-rr'),
        method: 'POST',
        json: true,
        retries: 0,
        data: { revision: revision }
      });
      await root.RRWorkspace.refreshHub();
      announce('Fixtures generated. Review the matrix, then schedule your matches.');
      root.RRWorkspace.open('results');
    } catch (error) {
      AdminToast.error(error.message);
      announce(error.message);
    } finally {
      busy = false;
      update();
    }
  }
  function init(id) {
    drawId = id;
    groups = normalise(root.RR_GROUPS);
    roster = (root.RR_ROSTER || []).map(p => Object.assign({}, p, { id: Number(p.id) }));
    revision = root.RR_ASSIGNMENT_REVISION;
    baseline = snapshot();
    categories();
    render();
    $(document).on('input', '#rr-player-search', function () {
      renderAvailable();
      initSortable();
    });
    $(document).on('change', '#rr-player-category', function () {
      renderAvailable();
      initSortable();
    });
    $(document).on('change', '.rr-player-select', function () {
      const id = Number($(this).closest('li').data('id'));
      this.checked ? selected.add(id) : selected.delete(id);
    });
    $('#rr-select-visible').on('change', function () {
      visiblePlayers().forEach(p => (this.checked ? selected.add(p.id) : selected.delete(p.id)));
      renderAvailable();
      initSortable();
    });
    $('#rr-assign-selected').on('click', () => assign(Array.from(selected), $('#rr-assign-target').val()));
    $(document).on('click', '.rr-add-player', function () {
      assign([Number($(this).closest('li').data('id'))], $('#rr-assign-target').val());
    });
    $(document).on('click', '#groups-pane .btn-remove-from-group', function () {
      if (!editable() || busy) return;
      const id = Number($(this).closest('li').data('id'));
      const p = player(id);
      if (!roster.some(r => r.id === id)) roster.push(p);
      groups.forEach(g => (g.players = g.players.filter(p => p.id !== id)));
      render();
    });
    $(document).on('click', '.rr-player-up,.rr-player-down', function () {
      if (!editable() || busy) return;
      const id = Number($(this).closest('li').data('id'));
      const g = groups.find(g => g.players.some(p => p.id === id));
      const index = g.players.findIndex(p => p.id === id),
        next = index + ($(this).hasClass('rr-player-up') ? -1 : 1);
      const direction = $(this).hasClass('rr-player-up') ? 'rr-player-up' : 'rr-player-down';
      if (next >= 0 && next < g.players.length)
        [g.players[index], g.players[next]] = [g.players[next], g.players[index]];
      render();
      const button = document.querySelector('.rr-group li[data-id="' + id + '"] .' + direction);
      if (button) button.focus();
    });
    $(document).on('click', '.rr-player-move', async function () {
      const id = Number($(this).closest('li').data('id'));
      const result = await Swal.fire({
        title: 'Move ' + player(id).name,
        input: 'select',
        inputOptions: Object.fromEntries(groups.map(g => [g.id, 'Group ' + g.name])),
        showCancelButton: true,
        confirmButtonText: 'Move player'
      });
      if (result.isConfirmed) assign([id], result.value);
    });
    $('#btn-save-groups').on('click', save);
    $('#btn-regenerate-fixtures').on('click', generate);
    $('#rr-apply-group-count').on('click', resize);
    $('#rr-discard-groups').on('click', function () {
      if (!guard()) return;
      discard();
    });
    $('#rr-refresh-players').on('click', () => refresh(false));
    $('#btn-import-teams').on('click', async function () {
      if (
        !editable() ||
        !guard() ||
        !root.confirm('Import teams for the whole event? This creates categories and registrations.')
      )
        return;
      try {
        await AdminApi.request({
          url: AdminRoutes.appUrl() + '/backend/event/' + root.EVENT_ID + '/import-teams',
          method: 'POST',
          retries: 0
        });
        await refresh(true);
      } catch (error) {
        AdminToast.error(error.message);
      }
    });
    root.addEventListener('beforeunload', function (event) {
      if (dirty() || busy) {
        event.preventDefault();
        event.returnValue = '';
      }
    });
    AdminState.on('rr:draw:locked', render);
    AdminState.on('rr:draw:published', render);
  }
  root.RRGroups = {
    init: init,
    refresh: refresh,
    refreshGroupsAndPlayers: refresh,
    initSortable: initSortable,
    applyLockUI: render,
    isDirty: dirty,
    isBusy: () => busy,
    save: save
  };
})(jQuery, window);
