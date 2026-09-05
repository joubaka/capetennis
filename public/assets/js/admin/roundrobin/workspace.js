(function ($, root) {
  'use strict';
  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), Accept: 'application/json' }
  });
  const areas = {
    players: ['groups'],
    results: ['matrix', 'standings'],
    schedule: ['oop', 'schedule'],
    setup: ['settings', 'notes']
  };
  if (document.getElementById('main-bracket-tab')) areas.results.push('main-bracket');
  let active = 'players';
  function open(area, view, replace) {
    if (!areas[area]) return;
    active = area;
    document.querySelectorAll('[data-workspace]').forEach(button => {
      const selected = button.dataset.workspace === area;
      button.classList.toggle('active', selected);
      button.setAttribute('aria-pressed', String(selected));
    });
    document.querySelectorAll('[data-full-schedule-action]').forEach(action => {
      action.classList.toggle('d-none', area !== 'schedule');
    });
    $('#rrTabs > li').each(function () {
      const id = $(this).find('button').attr('id').replace(/-tab$/, '');
      $(this).toggleClass('rr-tab-hidden', !areas[area].includes(id) || areas[area].length === 1);
    });
    const target = areas[area].includes(view) ? view : areas[area][0];
    bootstrap.Tab.getOrCreateInstance(document.getElementById(target + '-tab')).show();
    try {
      sessionStorage.setItem('rr-view-' + DRAW_ID, target);
    } catch (e) {}
    if (replace) history.replaceState(null, '', '#' + target);
    else if (location.hash !== '#' + target) history.pushState(null, '', '#' + target);
  }
  function followHash(replace) {
    const value = location.hash.slice(1).replace(/-(tab|pane)$/, '');
    if (value === 'print') {
      print();
      return;
    }
    const area = Object.keys(areas).find(key => areas[key].includes(value));
    if (area) open(area, value, replace);
  }
  function print() {
    $('#rrTabs > li').addClass('rr-tab-hidden');
    bootstrap.Tab.getOrCreateInstance(document.getElementById('print-tab')).show();
    history.replaceState(null, '', '#print');
  }
  async function refreshHub() {
    const hub = await AdminApi.get(AdminRoutes.get('hub'));
    AdminState.setFixtures(hub.rrFixtures || {});
    AdminState.setOop(hub.oops || []);
    AdminState.setStandings(hub.standings || {});
    updateSummary();
    return hub;
  }
  function updateSummary() {
    const fixtures = new Map();
    Object.values(AdminState.getFixtures() || {}).flat().forEach(fixture => fixtures.set(String(fixture.id), fixture));
    (AdminState.getOop() || []).forEach(fixture => fixtures.set(String(fixture.id), fixture));
    const values = Array.from(fixtures.values());
    $('#rr-next-step').text(
      values.length ? 'Fixtures ready · review results and schedule' : 'Start with your players and groups'
    );
    $('#rr-fixture-summary').text(values.length + ' fixtures · ' + values.filter(fixture => fixture.score).length + ' scored');
  }
  $(function () {
    $('[data-workspace]').on('click', function () {
      open(this.dataset.workspace);
    });
    $('[data-open-workspace]').on('click', function () {
      open(this.dataset.openWorkspace);
    });
    $('#rr-open-print').on('click', print);
    $('#rrTabs button').on('shown.bs.tab', function () {
      const id = this.id.replace(/-tab$/, '');
      if (id !== 'print') {
        history.replaceState(null, '', '#' + id);
        try {
          sessionStorage.setItem('rr-view-' + DRAW_ID, id);
        } catch (e) {}
      }
    });
    $('#rr-refresh-ops-btn').on('click', function () {
      refreshHub().catch(error => AdminToast.error(error.message));
    });
    AdminState.on('rr:fixtures:updated', updateSummary);
    AdminState.on('rr:oop:updated', updateSummary);
    AdminState.on('score:saved', updateSummary);
    AdminState.on('score:deleted', updateSummary);
    $('#rr-publish-draw, #rr-publish-schedule').on('click', async function () {
      if (root.RRGroups && (RRGroups.isDirty() || RRGroups.isBusy())) {
        AdminToast.warning('Save your assignments before changing publication.');
        return;
      }
      const button = this;
      if (!root.confirm(button.textContent.trim() + '?')) return;
      button.disabled = true;
      try {
        await AdminApi.request({ url: button.dataset.url, method: 'POST', retries: 0 });
        root.location.reload();
      } catch (error) {
        AdminToast.error(error.message);
        button.disabled = false;
      }
    });
    root.addEventListener('popstate', () => followHash(true));
    root.addEventListener('hashchange', () => followHash(true));
    let stored;
    try {
      stored = sessionStorage.getItem('rr-view-' + DRAW_ID);
    } catch (e) {}
    const defaultView = (root.RR_OOP || []).length ? 'matrix' : 'groups';
    const view = location.hash ? location.hash.slice(1).replace(/-(tab|pane)$/, '') : stored || defaultView;
    const area = Object.keys(areas).find(key => areas[key].includes(view)) || 'players';
    if (view === 'print') print();
    else open(area, view, true);
  });
  root.RRWorkspace = { open: open, refreshHub: refreshHub };
})(jQuery, window);
