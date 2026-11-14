/* Render canonical server standings with the shared public/admin presentation. */
(function ($, root) {
  'use strict';
  function render() {
    var wrapper = $('#rr-standings-wrapper');
    if (!wrapper.length) return;
    var groups = AdminState.getGroups() || [];
    var standings = AdminState.getStandings() || {};
    if (!groups.length) { wrapper.html('<p class="text-muted text-center py-4">No groups configured yet.</p>'); return; }
    wrapper.html(groups.map(function (group) {
      return root.RRStandingsTable.renderGroup(group, Object.values(standings[group.id] || {}));
    }).join(''));
  }
  AdminState.on('rr:standings:updated', render);
  AdminState.on('rr:fixtures:updated', render);
  root.renderStandings = render;
  root.RRStandings = {render: render};
  $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (event) {
    if ($(event.target).attr('id') === 'standings-tab') render();
  });
}(jQuery, window));