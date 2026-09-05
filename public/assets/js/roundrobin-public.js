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

  function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : String(value)).html();
  }

  /* ===============================
   * INIT
   * =============================== */
  function init() {
    console.log('[RR] Public init', drawId);

    normalizeFixtures();
    renderMatrix();
    renderOrderOfPlay();
    renderStandings();
    loadMainBracket();
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
  function formatScheduleParts(fx) {
    if (!fx.time) return { date: '', time: '' };

    const dt = new Date(fx.time.replace(' ', 'T'));

    if (Number.isNaN(dt.getTime())) return { date: '', time: '' };

    const date = dt.toLocaleDateString('en-GB', {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
    });
    const time = dt.toLocaleTimeString('en-GB', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    });

    return { date, time };
  }

  function formatDayTimeVenue(fx, includeCourt = false) {
    const schedule = formatScheduleParts(fx);
    if (!schedule.time) return '';

    const venue =
      fx.venue_name ||
      fx.venue_title ||
      fx.venue ||
      '';

    const parts = [`${schedule.date} ${schedule.time}`];
    if (venue) parts.push(venue);
    if (includeCourt && fx.court) parts.push(/^court\b/i.test(String(fx.court)) ? String(fx.court) : `Court ${fx.court}`);
    return parts.join(' · ');
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

    RR_GROUPS.forEach(group => {
      const groupId = group.id;
      const fixtures = (RR_FIXTURES && RR_FIXTURES[groupId]) ? RR_FIXTURES[groupId] : [];

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
              ${players.map(p => `<th class="text-center">${escapeHtml(p.name)}</th>`).join('')}
            </tr>
          </thead>
          <tbody>
      `;

      players.forEach(rowP => {
        html += `<tr><th class="bg-light small">${escapeHtml(rowP.name)}</th>`;

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
          const time = fx.time ? formatDayTimeVenue(fx, true) : '';

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
      tbody.html(`<tr><td colspan="10" class="text-muted text-center py-4">No matches are available for this draw.</td></tr>`);
      return;
    }

    // The backend already returns the organiser's canonical play_order.
    // Keep that sequence instead of regrouping matches in the browser.
    const sorted = RR_OOP.slice();

    let html = '';

    sorted.forEach(fx => {
      const schedule = formatScheduleParts(fx);
      const stage = fx.group_name ? 'Box ' + fx.group_name : (fx.stage || '');
      const court = fx.court
        ? (/^court\b/i.test(String(fx.court)) ? fx.court : 'Court ' + fx.court)
        : '';

      html += `
        <tr>
          <td data-label="Match" class="text-center fw-semibold">${escapeHtml(fx.match_nr || '')}</td>
          <td data-label="Player 1">${escapeHtml(fx.home)}</td>
          <td data-label="Player 2">${escapeHtml(fx.away)}</td>
          <td data-label="Round" class="text-center">${escapeHtml(fx.round)}</td>
          <td data-label="Stage" class="text-center">${escapeHtml(stage)}</td>
          <td data-label="Date" class="text-center">${escapeHtml(schedule.date || '—')}</td>
          <td data-label="Time" class="text-center">${escapeHtml(schedule.time || '—')}</td>
          <td data-label="Venue">${escapeHtml(fx.venue_name || fx.venue_title || fx.venue || '—')}</td>
          <td data-label="Court" class="text-center">${escapeHtml(court || '—')}</td>
          <td data-label="Score" class="text-center fw-bold">${escapeHtml(fx.score || '—')}</td>
        </tr>`;
    });

    tbody.html(html);
  }

  /* ===============================
   * STANDINGS
   * ITF tiebreak cascade:
   *   1. Matches won
   *   2-way tie  : H2H → Sets % → Games % → =
   *   3+ way tie : Sets % → Games % → recurse sub-groups (→ H2H if 2-way) → =
   * =============================== */
  function renderStandings() {
    const wrapper = $('#rr-standings-wrapper');
    wrapper.html('');

    function sp(r) { const t = r.sets_won + r.sets_lost; return t > 0 ? r.sets_won / t : 0; }
    function gp(r) { const t = (r.games_won||0) + (r.games_lost||0); return t > 0 ? (r.games_won||0) / t : 0; }

    RR_GROUPS.forEach(group => {
      const gid = group.id;
      if (!RR_STANDINGS[gid]) return;

      let rows = Object.values(RR_STANDINGS[gid]);

      function headToHead(a, b) {
        const fxList = RR_FIXTURES[gid] || [];
        const match = fxList.find(f =>
          (f.r1_id === a.reg_id && f.r2_id === b.reg_id) ||
          (f.r1_id === b.reg_id && f.r2_id === a.reg_id)
        );
        if (!match || !match.winner) return 0;
        return match.winner === a.reg_id ? 1 : -1;
      }

      function resolveGroup(grp) {
        if (grp.length <= 1) return grp;

        if (grp.length === 2) {
          const hh = headToHead(grp[0], grp[1]);
          if (hh !== 0) {
            grp[0].tiebreak = grp[0].tiebreak || 'H2H';
            grp[1].tiebreak = grp[1].tiebreak || 'H2H';
            return hh === 1 ? grp : [grp[1], grp[0]];
          }
          const dSets = sp(grp[1]) - sp(grp[0]);
          if (Math.abs(dSets) > 0.0001) {
            grp[0].tiebreak = grp[0].tiebreak || 'Sets %';
            grp[1].tiebreak = grp[1].tiebreak || 'Sets %';
            return dSets > 0 ? [grp[1], grp[0]] : grp;
          }
          const dGames = gp(grp[1]) - gp(grp[0]);
          if (Math.abs(dGames) > 0.0001) {
            grp[0].tiebreak = grp[0].tiebreak || 'Games %';
            grp[1].tiebreak = grp[1].tiebreak || 'Games %';
            return dGames > 0 ? [grp[1], grp[0]] : grp;
          }
          grp[0].tiebreak = grp[0].tiebreak || '=';
          grp[1].tiebreak = grp[1].tiebreak || '=';
          return grp;
        }

        grp.sort((a, b) => {
          const dSets = sp(b) - sp(a);
          if (Math.abs(dSets) > 0.0001) return dSets;
          return gp(b) - gp(a);
        });

        const resolved = [];
        let i = 0;
        while (i < grp.length) {
          let j = i + 1;
          while (j < grp.length &&
            Math.abs(sp(grp[j]) - sp(grp[i])) <= 0.0001 &&
            Math.abs(gp(grp[j]) - gp(grp[i])) <= 0.0001) j++;
          resolved.push(...resolveGroup(grp.slice(i, j)));
          i = j;
        }
        return resolved;
      }

      rows.sort((a, b) => b.wins - a.wins);
      const final = [];
      let i = 0;
      while (i < rows.length) {
        let j = i + 1;
        while (j < rows.length && rows[j].wins === rows[i].wins) j++;
        final.push(...resolveGroup(rows.slice(i, j)));
        i = j;
      }
      rows = final;

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
              <th class="text-center">Sets %</th>
              <th class="text-center">Games %</th>
              <th class="text-center">TB</th>
            </tr>
          </thead>
          <tbody>
      `;

      rows.forEach((r, i) => {
        const totalSets = r.sets_won + r.sets_lost;
        const setsPct = totalSets > 0 ? ((r.sets_won / totalSets) * 100).toFixed(0) : '-';
        const totalGames = (r.games_won || 0) + (r.games_lost || 0);
        const gamesPct = totalGames > 0 ? (((r.games_won || 0) / totalGames) * 100).toFixed(0) : '-';

        let rowClass = '';
        if (i === 0) rowClass = 'table-success fw-bold';
        else if (i === rows.length - 1) rowClass = 'table-danger';
        else rowClass = 'table-light';

        const tb = r.tiebreak || '';
        const tbBadge = tb ? `<span class="badge bg-warning text-dark" style="font-size:10px;">${tb}</span>` : '';

        html += `
          <tr class="${rowClass}">
            <td>${i + 1}</td>
            <td>${escapeHtml(r.player)}</td>
            <td class="text-center">${r.wins}</td>
            <td class="text-center">${r.losses}</td>
            <td class="text-center">${setsPct}%</td>
            <td class="text-center">${gamesPct}%</td>
            <td class="text-center">${tbBadge}</td>
          </tr>`;
      });

      html += `</tbody></table></div>`;
      wrapper.append(html);
    });
  }

  function loadMainBracket() {
    const wrapper = document.getElementById('main-bracket-wrapper');
    if (!wrapper || !window.RR_MAIN_BRACKET_URL) return;

    fetch(window.RR_MAIN_BRACKET_URL, { headers: { Accept: 'text/html' } })
      .then(response => {
        if (!response.ok) throw new Error('Unable to load bracket');
        return response.text();
      })
      .then(html => { wrapper.innerHTML = html; })
      .catch(() => {
        wrapper.innerHTML = '<div class="alert alert-warning m-3">The main bracket is not available yet.</div>';
      });
  }

  /* ===============================
   * STARTUP
   * =============================== */
  $(document).ready(init);

  $(document).ready(function () {
    if (['#schedule', '#oop', '#match-times'].includes(window.location.hash)) {
      document.getElementById('oop-tab')?.click();
    }
  });

})(jQuery, window, document);
