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
    const dateSource = fx.time || (fx.scheduled_date ? `${fx.scheduled_date} 00:00:00` : '');
    if (!dateSource) return { date: '', time: '' };

    const dt = new Date(dateSource.replace(' ', 'T'));

    if (Number.isNaN(dt.getTime())) return { date: '', time: '' };

    const date = dt.toLocaleDateString('en-GB', {
      weekday: 'short',
      day: 'numeric',
      month: 'short',
    });
    const time = fx.time
      ? dt.toLocaleTimeString('en-GB', {
          hour: '2-digit',
          minute: '2-digit',
          hour12: false,
        })
      : '';

    return { date, time };
  }

  function formatDayTimeVenue(fx) {
    const schedule = formatScheduleParts(fx);
    if (!schedule.time) return '';

    const venue =
      fx.venue_name ||
      fx.venue_title ||
      fx.venue ||
      '';

    const parts = [`${schedule.date} ${schedule.time}`];
    if (venue) parts.push(venue);
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
    wrapper.empty().addClass('rr-matrix-scroll');

    if (!RR_GROUPS.length) {
      wrapper.html('<div class="text-muted text-center py-4">No groups are available for this draw.</div>');
      return;
    }

    RR_GROUPS.forEach(group => {
      const groupId = group.id;
      const fixtures = (RR_FIXTURES && RR_FIXTURES[groupId]) ? RR_FIXTURES[groupId] : [];

      let players = group.registrations.map(r => ({
        id: r.id,
        name: r.display_name,
        seed: r.pivot?.seed ?? 999,
      })).sort((a, b) => a.seed - b.seed);

      const completed = fixtures.filter(fx => fx && fx.all_sets && fx.all_sets.length).length;

      let html = `
      <section class="rr-group-section" aria-labelledby="rr-public-group-${groupId}">
      <div class="rr-group-heading">
        <h6 id="rr-public-group-${groupId}" class="fw-bold">Box ${escapeHtml(group.name)}</h6>
        <span class="rr-group-meta">${players.length} players · ${fixtures.length} matches · ${completed} completed</span>
      </div>
      <div class="rr-matrix-scroll rr-matrix-table-shell">
        <table class="table table-bordered table-sm rr-matrix-table">
          <caption class="visually-hidden">Round robin results for Box ${escapeHtml(group.name)}</caption>
          <thead>
            <tr>
              <th scope="col" aria-label="Player"></th>
              ${players.map(p => `<th scope="col" class="text-center" title="${escapeHtml(p.name)}">${escapeHtml(p.name)}</th>`).join('')}
            </tr>
          </thead>
          <tbody>
      `;

      players.forEach(rowP => {
        html += `<tr><th scope="row" class="small" title="${escapeHtml(rowP.name)}">${escapeHtml(rowP.name)}</th>`;

        players.forEach(colP => {
          if (rowP.id === colP.id) {
            html += `<td class="bg-diagonal" aria-label="Same player; no match"></td>`;
            return;
          }

          const fx = fixtures.find(f =>
            (f.r1_id === rowP.id && f.r2_id === colP.id) ||
            (f.r1_id === colP.id && f.r2_id === rowP.id)
          );

          if (!fx) {
            html += `<td class="text-center text-muted" aria-label="Match not generated">Not generated</td>`;
            return;
          }

          const score = formatScoreCell(fx, rowP.id);
          const time = fx.schedule_hidden === true
            ? 'Followed by'
            : (fx.time ? formatDayTimeVenue(fx) : '');

          html += `<td class="text-center rr-match-cell">${escapeHtml(score || time || '—')}</td>`;
        });

        html += `</tr>`;
      });

      html += `</tbody></table></div><div class="rr-mobile-match-list">`;

      fixtures.forEach(fx => {
        if (!fx) return;
        const home = players.find(player => player.id === fx.r1_id)?.name || fx.name1 || 'Player';
        const away = players.find(player => player.id === fx.r2_id)?.name || fx.name2 || 'Player';
        const score = formatScoreCell(fx, fx.r1_id);
        const status = score || (fx.schedule_hidden === true
          ? 'Followed by'
          : (fx.time ? formatDayTimeVenue(fx) : 'Awaiting schedule'));

        html += `<div class="rr-mobile-match" role="group" aria-label="${escapeHtml(home)} versus ${escapeHtml(away)}">
          <span class="rr-mobile-players"><span>${escapeHtml(home)}</span><span class="rr-mobile-versus">vs</span><span>${escapeHtml(away)}</span></span>
          <span class="rr-mobile-status">${escapeHtml(status)}</span>
        </div>`;
      });

      if (!fixtures.length) {
        html += '<p class="text-muted small mb-0">No matches have been generated for this box.</p>';
      }

      html += `</div></section>`;
      wrapper.append(html);
    });
  }

  /* ===============================
   * ORDER OF PLAY
   * =============================== */
  function renderOrderOfPlay() {
    const tbody = $('#rr-order-table tbody');

    if (!RR_OOP.length) {
      tbody.html(`<tr><td colspan="9" class="text-muted text-center py-4">No matches are available for this draw.</td></tr>`);
      return;
    }

    // The backend already returns the organiser's canonical play_order.
    // Keep that sequence instead of regrouping matches in the browser.
    const sorted = RR_OOP.slice();

    let html = '';

    sorted.forEach(fx => {
      const schedule = formatScheduleParts(fx);
      const scheduleHidden = fx.schedule_hidden === true;
      const followedBy = 'Followed by';
      const stage = fx.group_name ? 'Box ' + fx.group_name : (fx.stage || '');

      html += `
        <tr>
          <td data-label="Match" class="text-center fw-semibold">${escapeHtml(fx.match_nr || '')}</td>
          <td data-label="Player 1">${escapeHtml(fx.home)}</td>
          <td data-label="Player 2">${escapeHtml(fx.away)}</td>
          <td data-label="Round" class="text-center">${escapeHtml(fx.round)}</td>
          <td data-label="Stage" class="text-center">${escapeHtml(stage)}</td>
          <td data-label="Date" class="text-center">${escapeHtml(schedule.date || '—')}</td>
          <td data-label="Time" class="text-center">${escapeHtml(scheduleHidden ? followedBy : schedule.time || '—')}</td>
          <td data-label="Venue">${escapeHtml(fx.venue_name || fx.venue_title || fx.venue || '—')}</td>
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
    wrapper.html(RR_GROUPS.map(group => window.RRStandingsTable.renderGroup(group, Object.values(RR_STANDINGS[group.id] || {}))).join(''));
  }
  function loadMainBracket() {
    const wrapper = document.getElementById('main-bracket-wrapper');
    if (!wrapper || !window.RR_MAIN_BRACKET_URL) return;

    const url = new URL(window.RR_MAIN_BRACKET_URL, window.location.href);
    url.searchParams.set('_fresh', Date.now().toString());

    fetch(url.toString(), {
      cache: 'no-store',
      headers: {
        Accept: 'text/html',
        'Cache-Control': 'no-cache',
      },
    })
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
    } else if (['', '#draw', '#matrix'].includes(window.location.hash)) {
      document.getElementById('matrix-tab')?.click();
    }
  });

})(jQuery, window, document);
