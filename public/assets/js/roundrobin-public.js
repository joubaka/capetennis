/*
 * PUBLIC Round Robin Viewer
 * - Displays matrix
 * - Displays order of play
 * - Displays standings
 * - NO score entry
 * - NO admin features
 */

(function ($, window, document) {
  'use strict';

  console.log('🟦 Public RR Viewer JS loaded');

  const $app = $('#round-robin-app');
  if (!$app.length) return;

  const drawId = $app.data('draw-id');

  /* ===============================
   * GLOBALS from Blade
   * =============================== */

  const RR_GROUPS = window.RR_GROUPS || [];
  const RR_FIXTURES = window.RR_FIXTURES || {};
  const RR_OOP = window.RR_OOP || [];
  const RR_STANDINGS = window.RR_STANDINGS || {};

  /* ===============================
   * INIT
   * =============================== */
  function init() {
    console.log('[RR] Public init', drawId);

    normalizeFixtures();
    renderMatrix();
    renderOrderOfPlay();
    renderStandings();
  }

  /* ===============================
   * NORMALIZE FIXTURES
   * =============================== */
  function normalizeFixtures() {
    for (let gid in RR_FIXTURES) {
      RR_FIXTURES[gid] = RR_FIXTURES[gid].map(f => {
        if (!f) return f;

        f.id = parseInt(f.id ?? 0, 10);
        f.r1_id = parseInt(f.r1_id ?? 0, 10);
        f.r2_id = parseInt(f.r2_id ?? 0, 10);
        f.time = f.time ?? null;

        if (!f.all_sets && f.score) {
          f.all_sets = String(f.score).split(' ').filter(s => s.includes('-'));
        }

        return f;
      });
    }
  }

  /* ===============================
   * FORMAT TIME
   * =============================== */
  function formatDayTimeVenue(fx) {
    if (!fx.time) return '';

    const dt = new Date(fx.time.replace(' ', 'T'));

    const day = dt.toLocaleDateString('en-GB', { weekday: 'short' });
    const time = dt.toLocaleTimeString('en-GB', {
      hour: '2-digit',
      minute: '2-digit',
    });

    const venue =
      fx.venue_name ||
      fx.venue_title ||
      fx.venue ||
      '';

    return venue ? `${day} ${time} (${venue})` : `${day} ${time}`;
  }

  /* ===============================
   * MATRIX
   * =============================== */
  function formatScoreCell(fx, rowPlayerId) {
    if (!fx || !fx.all_sets || fx.all_sets.length === 0) return '';

    let display = [];

    fx.all_sets.forEach(s => {
      const [a, b] = s.split('-').map(Number);

      if (fx.r1_id === rowPlayerId) {
        display.push(`${a}-${b}`);
      } else {
        display.push(`${b}-${a}`);
      }
    });

    return display.join(', ');
  }

  function renderMatrix() {
    console.log('🔹 Rendering public matrix');

    const wrapper = $('#rr-matrix-wrapper');
    wrapper.empty();

    Object.keys(RR_FIXTURES).forEach(groupId => {
      const group = RR_GROUPS.find(g => g.id == groupId);
      const fixtures = RR_FIXTURES[groupId];

      if (!group) return;

      let players = group.registrations.map(r => ({
        id: r.id,
        name: r.display_name,
        seed: r.pivot?.seed ?? 999,
      })).sort((a, b) => a.seed - b.seed);

      let html = `
      <h6 class="fw-bold mt-3 mb-2">Box ${group.name}</h6>
      <div class="table-responsive mb-4">
        <table class="table table-bordered table-sm rr-matrix-table">
          <thead>
            <tr>
              <th class="bg-light"></th>
              ${players.map(p => `<th class="text-center">${p.name}</th>`).join('')}
            </tr>
          </thead>
          <tbody>
      `;

      players.forEach(rowP => {
        html += `<tr><th class="bg-light small">${rowP.name}</th>`;

        players.forEach(colP => {
          if (rowP.id === colP.id) {
            html += `<td class="bg-light"></td>`;
            return;
          }

          const fx = fixtures.find(f =>
            (f.r1_id === rowP.id && f.r2_id === colP.id) ||
            (f.r1_id === colP.id && f.r2_id === rowP.id)
          );

          if (!fx) {
            html += `<td class="text-center text-muted">–</td>`;
            return;
          }

          const score = formatScoreCell(fx, rowP.id);
          const time = fx.time ? formatDayTimeVenue(fx) : '';

          html += `<td class="text-center">${score || time || '–'}</td>`;
        });

        html += `</tr>`;
      });

      html += `</tbody></table></div>`;
      wrapper.append(html);
    });
  }

  /* ===============================
   * ORDER OF PLAY
   * =============================== */
  function renderOrderOfPlay() {
    const tbody = $('#rr-order-table tbody');

    if (!RR_OOP.length) {
      tbody.html(`<tr><td colspan="8" class="text-muted text-center">No fixtures…</td></tr>`);
      return;
    }

    let html = '';

    RR_OOP.forEach(fx => {
      html += `
        <tr>
          <td>${fx.id}</td>
          <td>${fx.home}</td>
          <td class="text-center">vs</td>
          <td>${fx.away}</td>
          <td class="text-center">${fx.round}</td>
          <td class="text-center">${formatDayTimeVenue(fx)}</td>
          <td class="text-center fw-bold">${fx.score || ''}</td>
        </tr>`;
    });

    tbody.html(html);
  }

  /* ===============================
   * STANDINGS
   * =============================== */
  function renderStandings() {
    const wrapper = $('#rr-standings-wrapper');
    wrapper.html('');

    RR_GROUPS.forEach(group => {
      const gid = group.id;
      if (!RR_STANDINGS[gid]) return;

      let rows = Object.values(RR_STANDINGS[gid]);

      rows.sort((a, b) => b.wins - a.wins);

      let html = `
        <h6 class="fw-bold mt-4">Box ${group.name}</h6>
        <div class="table-responsive mb-2">
        <table class="table table-sm table-striped">
          <thead>
            <tr>
              <th>#</th>
              <th>Player</th>
              <th class="text-center">W</th>
              <th class="text-center">L</th>
              <th class="text-center">Sets +</th>
              <th class="text-center">Sets −</th>
              <th class="text-center">Diff</th>
            </tr>
          </thead>
          <tbody>
      `;

      rows.forEach((r, i) => {
        const diff = r.sets_won - r.sets_lost;

        html += `
          <tr>
            <td>${i + 1}</td>
            <td>${r.player}</td>
            <td class="text-center">${r.wins}</td>
            <td class="text-center">${r.losses}</td>
            <td class="text-center">${r.sets_won}</td>
            <td class="text-center">${r.sets_lost}</td>
            <td class="text-center">${diff}</td>
          </tr>`;
      });

      html += `</tbody></table></div>`;
      wrapper.append(html);
    });
  }

  /* ===============================
   * STARTUP
   * =============================== */
  $(document).ready(init);

})(jQuery, window, document);
