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
    wrapper.empty();

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
        <table class="table table-bordered table-sm rr-matrix-table" style="--rr-matrix-width: ${220 + players.length * 190}px">
          <caption class="visually-hidden">Round robin results for Box ${escapeHtml(group.name)}</caption>
          <colgroup><col class="rr-matrix-player-column"><col span="${players.length}"></colgroup>
          <thead>
            <tr>
              <th scope="col" aria-label="Player"></th>
              ${players.map(p => `<th scope="col" class="text-center" title="${escapeHtml(p.name)}">${escapeHtml(p.name)}${window.CTPlayerRatings?.marker({registrationId:p.id}) || ''}</th>`).join('')}
            </tr>
          </thead>
          <tbody>
      `;

      players.forEach(rowP => {
        html += `<tr><th scope="row" class="small" title="${escapeHtml(rowP.name)}">${escapeHtml(rowP.name)}${window.CTPlayerRatings?.marker({registrationId:rowP.id}) || ''}</th>`;

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

          const winner = Number(fx.winner || 0);
          const knownWinner = winner > 0 && (winner === Number(fx.r1_id) || winner === Number(fx.r2_id));
          const outcome = knownWinner ? (winner === Number(rowP.id) ? 'winner-home' : 'loser-home') : '';
          const score = formatScoreCell(fx, rowP.id);
          const time = fx.schedule_hidden === true
            ? 'Followed by'
            : (fx.time ? formatDayTimeVenue(fx) : '');

          const schedule = formatScheduleParts(fx);
          const venue = fx.venue_name || fx.venue_title || fx.venue || '';
          const cellContent = score
            ? escapeHtml(score)
            : (time && fx.schedule_hidden !== true
              ? `<span class="rr-public-match-time">${escapeHtml(schedule.date)} <strong>${escapeHtml(schedule.time)}</strong></span>${venue ? `<span class="rr-public-match-venue">${escapeHtml(venue)}</span>` : ''}`
              : escapeHtml(time || '—'));
          html += `<td class="text-center rr-match-cell ${outcome}">${cellContent}</td>`;
        });

        html += `</tr>`;
      });

      html += `</tbody></table></div><div class="rr-mobile-match-list">`;

      fixtures.forEach(fx => {
        if (!fx) return;
        const home = players.find(player => player.id === fx.r1_id)?.name || fx.name1 || 'Player';
        const away = players.find(player => player.id === fx.r2_id)?.name || fx.name2 || 'Player';
        const winner = Number(fx.winner || 0);
        const knownWinner = winner > 0 && (winner === Number(fx.r1_id) || winner === Number(fx.r2_id));
        const homeClass = knownWinner ? (winner === Number(fx.r1_id) ? 'winner-home' : 'loser-home') : '';
        const awayClass = knownWinner ? (winner === Number(fx.r2_id) ? 'winner-home' : 'loser-home') : '';
        const outcomeLabel = cls => cls ? `<small class="result-label">${cls === 'winner-home' ? 'Won' : 'Lost'}</small>` : '';
        const score = formatScoreCell(fx, fx.r1_id);
        const status = score || (fx.schedule_hidden === true
          ? 'Followed by'
          : (fx.time ? formatDayTimeVenue(fx) : 'Awaiting schedule'));

        html += `<div class="rr-mobile-match" role="group" aria-label="${escapeHtml(home)} versus ${escapeHtml(away)}">
          <span class="rr-mobile-players"><span class="${homeClass}">${escapeHtml(home)}${window.CTPlayerRatings?.marker({registrationId:fx.r1_id}) || ''}${outcomeLabel(homeClass)}</span><span class="rr-mobile-versus">vs</span><span class="${awayClass}">${escapeHtml(away)}${window.CTPlayerRatings?.marker({registrationId:fx.r2_id}) || ''}${outcomeLabel(awayClass)}</span></span>
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

    // The public schedule reads chronologically across group matches and playoffs.
    // Hidden/unreleased times must not affect the visible ordering.
    const timestamp = fx => {
      if (!fx.time || fx.schedule_hidden === true) return Number.POSITIVE_INFINITY;
      const value = new Date(String(fx.time).replace(' ', 'T')).getTime();
      return Number.isNaN(value) ? Number.POSITIVE_INFINITY : value;
    };
    const sorted = RR_OOP.slice().sort((a, b) => {
      const first = timestamp(a), second = timestamp(b);
      if (first !== second) return first < second ? -1 : 1;
      return (Number(a.match_nr) || 0) - (Number(b.match_nr) || 0)
        || (Number(a.id) || 0) - (Number(b.id) || 0);
    });

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
    const tabs = {'oop-tab': 'schedule', 'matrix-tab': 'draw', 'standings-tab': 'standings', 'main-bracket-tab': 'playoffs'};
    function openHash() {
      const hash = window.location.hash;
      const id = ['#draw', '#matrix'].includes(hash) ? 'matrix-tab'
        : hash === '#standings' ? 'standings-tab'
        : ['#playoffs', '#main-bracket'].includes(hash) ? 'main-bracket-tab' : 'oop-tab';
      document.getElementById(id)?.click();
    }
    Object.entries(tabs).forEach(([id, name]) => {
      document.getElementById(id)?.addEventListener('shown.bs.tab', () => {
        history.replaceState(null, '', '#' + name);
      });
    });
    window.addEventListener('hashchange', openHash);
    openHash();
  });

})(jQuery, window, document);
