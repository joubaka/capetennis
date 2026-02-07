/*
 * Round Robin Hub JS
 * - Loads fixtures
 * - Renders matrix
 * - Renders order of play
 * - Score entry via 3-set modal
 * - AJAX update of matrix + standings without page reload
 */

(function ($, window, document) {
  'use strict';

  console.log('🟦 Round Robin Hub JS loaded.....');
  // NEW: Use the Blade-injected global variable
  const RR_GROUPS = window.RR_GROUPS || [];


  const CSRF = $('meta[name="csrf-token"]').attr('content');
  $.ajaxSetup({
    headers: {
      'X-CSRF-TOKEN': CSRF,
      'Accept': 'application/json'
    }
  });

  const $app = $('#round-robin-app');
  if (!$app.length) return;

  const drawId = $app.data('draw-id');

  const $matrixWrapper = $('#rr-matrix-wrapper');
  const $matrixLoading = $('#rr-matrix-loading');
  const $orderTableBody = $('#rr-order-table tbody');

  // Modal elements
  const $scoreModalForm = $('#rr-score-modal-form');
  const $modalFixtureId = $('#rrm-fixture-id');
  const $modalMatchLabel = $('#rrm-match-label');

  // Init sortable group lists
  document.querySelectorAll('.rr-sortable').forEach(el => {
    new Sortable(el, {
      group: 'rr-groups',
      animation: 150
    });
  });
  function formatDayTimeVenue(fx) {
    if (!fx.time) return '';

    const dt = new Date(fx.time.replace(' ', 'T'));

    const day = dt.toLocaleDateString('en-GB', { weekday: 'short' }); // Mon, Tue, Wed…
    const time = dt.toLocaleTimeString('en-GB', {
      hour: '2-digit',
      minute: '2-digit'
    });
    console.log('fx:', fx); 
    // Try all possible venue fields
    const venue =
      fx.venue_name ||
      fx.venue_title ||
      fx.venue ||
      '';

    return venue ? `${day} ${time} (${venue})` : `${day} ${time}`;
  }

  /* ===================================================
   * INIT
   * =================================================== */
  function init() {
    console.log('[RR] Init draw', drawId);

    if (window.RR_FIXTURES) normalizeAllFixtures();

    if ($matrixLoading.length) $matrixLoading.remove();

    renderMatrixFallback();
    renderOrderOfPlay();
    renderStandings();
    bindEvents();
  }

  /* ===================================================
   * NORMALISE FIXTURES
   * =================================================== */
  function normalizeAllFixtures() {
    for (let gid in RR_FIXTURES) {
      RR_FIXTURES[gid] = RR_FIXTURES[gid].map(f => normalizeFixture(f));
    }
  }

  function normalizeFixture(f) {
    if (!f) return f;

    // FIX: Normalize IDs to integers
    f.id = parseInt(f.id ?? 0, 10);
    f.r1_id = parseInt(f.r1_id ?? 0, 10);
    f.r2_id = parseInt(f.r2_id ?? 0, 10);

    // Keep the time untouched
    f.time = f.time ?? null;

    // Parse last-set score
    if ((f.home_score == null || f.away_score == null) && f.score) {
      const parts = String(f.score).trim().split(' ');
      const last = parts[parts.length - 1];
      if (last && last.includes('-')) {
        const [s1, s2] = last.split('-').map(n => parseInt(n, 10));
        if (!isNaN(s1) && !isNaN(s2)) {
          f.home_score = s1;
          f.away_score = s2;
        }
      }
    }

    // Ensure all_sets exists
    if (!f.all_sets || !Array.isArray(f.all_sets)) {
      if (f.score) {
        f.all_sets = String(f.score).trim().split(' ').filter(s => s.includes('-'));
      } else {
        f.all_sets = [];
      }
    }

    return f;
  }

  function formatFixtureTime(dtString) {
    if (!dtString) return '';
    const dt = new Date(dtString.replace(' ', 'T'));

    return dt.toLocaleString('en-GB', {
      weekday: 'short',
      hour: '2-digit',
      minute: '2-digit'
    }).replace(',', '');
  }

  /* ===================================================
   * MATRIX (HTML TABLE)
   * =================================================== */
  function renderMatrixFallback() {
    console.log('🟦 [RR] renderMatrixFallback() called');
    console.log('🟦 RR_FIXTURES:', JSON.parse(JSON.stringify(window.RR_FIXTURES)));
    console.log('🟦 RR_GROUPS:', JSON.parse(JSON.stringify(window.RR_GROUPS)));

    const wrapper = $('#rr-matrix-wrapper');
    wrapper.empty();

    if (!window.RR_FIXTURES || !window.RR_GROUPS) {
      console.warn('⚠️ RR_FIXTURES or RR_GROUPS missing');
      return;
    }

    wrapper.addClass('rr-matrix-scroll');

    Object.keys(RR_FIXTURES).forEach(groupId => {

      const group = RR_GROUPS.find(g => g.id == groupId);
      const fixtures = RR_FIXTURES[groupId];

      console.log(`🔷 GROUP ${groupId}`, group);
      console.log(`🔷 FIXTURES FOR GROUP ${groupId}`, fixtures);

      if (!group) return;

      let players = group.registrations.map(r => ({
        id: r.id,
        name: r.display_name,
        seed: r.pivot ? (r.pivot.seed ?? 9999) : 9999
      }));

      players = players.sort((a, b) => a.seed - b.seed);

      let html = `
      <h6 class="fw-bold mt-3 mb-2">Box ${group.name}</h6>
      <div class="table-responsive mb-4">
      <table class="table table-bordered table-sm rr-matrix-table rr-matrix-dark">

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

          console.log(`🟩 Checking fixture: ${rowP.name} vs ${colP.name}`, fx);

          if (!fx) {
            console.log(`   ➜ No fixture found.`);
            html += `<td class="text-center text-muted">–</td>`;
            return;
          }

          // Log the final output choice
          const debugOutput = (() => {
            const scoreHtml = formatScoreCell(fx, rowP.id);

            if (scoreHtml && scoreHtml.trim() !== '') {
              console.log(`   ➜ SCORE shown:`, scoreHtml);
              return scoreHtml;
            }

            if (fx.time) {
              const formatted = formatFixtureTime(fx.time);
              console.log(`   ➜ TIME shown:`, formatted);
              return formatted;
            }

            console.log(`   ➜ Showing dash.`);
            return '–';
          })();

          html += `
          <td class="text-center rr-score-cell"
              data-fixture-id="${fx.id}"
              data-home="${rowP.name}"
              data-away="${colP.name}">
              ${debugOutput}
          </td>`;
        });

        html += `</tr>`;
      });

      html += `</tbody></table></div>`;
      wrapper.append(html);
    });
  }

  /* ===================================================
   * ORDER OF PLAY
   * =================================================== */
function renderOrderOfPlay() {
  const tbody = $('#rr-order-table tbody');

  if (!window.RR_OOP || !window.RR_OOP.length) {
    tbody.html(`<tr><td colspan="8" class="text-muted text-center">No fixtures…</td></tr>`);
    return;
  }

  let html = '';

  RR_OOP.forEach(fx => {
    let p1Class = '';
    let p2Class = '';

    if (fx.winner) {
      if (fx.winner == fx.r1_id) {
        p1Class = 'bg-success text-white';
        p2Class = 'bg-danger text-white';
      } else {
        p1Class = 'bg-danger text-white';
        p2Class = 'bg-success text-white';
      }
    }

    html += `
      <tr data-fixture-id="${fx.id}">
        <td>${fx.id}</td>
        <td class="${p1Class}">${fx.home}</td>
        <td class="text-center">vs</td>
        <td class="${p2Class}">${fx.away}</td>
        <td class="text-center">${fx.round}</td>
        <td class="text-center">${formatDayTimeVenue(fx)}</td>
        <td class="text-center fw-bold">${fx.score || ''}</td>
       
      </tr>`;
  });

  tbody.html(html);
}

  /* ===================================================
   * STANDINGS
   * =================================================== */
  function renderStandings() {
    const wrapper = $('#rr-standings-wrapper');
    if (!wrapper.length) return;

    wrapper.html('');

    const groups = window.RR_GROUPS || [];
    const standings = window.RR_STANDINGS || {};

    groups.forEach(group => {
      const gid = group.id;

      if (!standings[gid]) return;

      let rows = Object.values(standings[gid]);

      function headToHead(a, b) {
        const fxList = RR_FIXTURES[gid] || [];

        const match = fxList.find(f =>
          (f.r1_id === a.reg_id && f.r2_id === b.reg_id) ||
          (f.r1_id === b.reg_id && f.r2_id === a.reg_id)
        );

        if (!match || !match.winner) return 0;
        return match.winner === a.reg_id ? 1 : -1;
      }

      rows.sort((a, b) => {
        if (a.wins !== b.wins) return b.wins - a.wins;

        const diffA = a.sets_won - a.sets_lost;
        const diffB = b.sets_won - b.sets_lost;

        if (diffA !== diffB) return diffB - diffA;

        return headToHead(a, b);
      });

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
        const diffColor =
          diff > 0 ? 'text-success' :
            diff < 0 ? 'text-danger' : 'text-muted';

        let rowClass = '';
        if (i === 0) rowClass = 'table-success fw-bold';
        else if (i === rows.length - 1) rowClass = 'table-danger';
        else rowClass = 'table-light';

        html += `
          <tr class="${rowClass}">
            <td>${i + 1}</td>
            <td>${r.player}</td>
            <td class="text-center">${r.wins}</td>
            <td class="text-center">${r.losses}</td>
            <td class="text-center">${r.sets_won}</td>
            <td class="text-center">${r.sets_lost}</td>
            <td class="text-center ${diffColor}">${diff}</td>
          </tr>`;
      });

      html += `</tbody></table></div>`;
      wrapper.append(html);
    });
  }

  /* ===================================================
   * OPEN SCORE MODAL
   * =================================================== */
  function openScoreModal(id, home, away) {
    $modalFixtureId.val(id);
    $modalMatchLabel.html(`<b>${home}</b> vs <b>${away}</b>`);

    $('#set1-p1-label, #set2-p1-label, #set3-p1-label').text(home);
    $('#set1-p2-label, #set2-p2-label, #set3-p2-label').text(away);

    $('#set1-p1, #set1-p2, #set2-p1, #set2-p2, #set3-p1, #set3-p2').val('');

    new bootstrap.Modal(document.getElementById('rrScoreModal')).show();
  }

  /* ===================================================
   * BIND EVENTS
   * =================================================== */
  function bindEvents() {

    $(document).on('click', '.rr-score-cell', function () {
      openScoreModal(
        $(this).data('fixture-id'),
        $(this).data('home'),
        $(this).data('away')
      );
    });

    $(document).on('click', '.rr-open-score-modal', function (e) {
      e.preventDefault();
      openScoreModal(
        $(this).data('fixture-id'),
        $(this).data('home'),
        $(this).data('away')
      );
    });

    // ============================================================
    // SAVE SCORE (RR + BRACKET)
    // ============================================================
    $scoreModalForm.on('submit', function (e) {
      e.preventDefault();

      const fixtureId = $modalFixtureId.val();

      function readSet(p1, p2) {
        const v1 = $(p1).val().trim();
        const v2 = $(p2).val().trim();

        if (v1 === '' && v2 === '') return null;
        if (v1 === '' || v2 === '') {
          toastr.error('Please complete both values for a set.');
          throw new Error();
        }
        return `${v1}-${v2}`;
      }

      let sets;
      try {
        sets = [
          readSet('#set1-p1', '#set1-p2'),
          readSet('#set2-p1', '#set2-p2'),
          readSet('#set3-p1', '#set3-p2')
        ].filter(Boolean);
      } catch (e) {
        return;
      }

      if (!sets.length) {
        toastr.error('Please enter at least one valid set.');
        return;
      }

      const url = window.RR_SAVE_SCORE_URL.replace('FIXTURE_ID', fixtureId);

      $.post(url, { sets })
        .done(res => {
          toastr.success('Score saved');

          /* ROUND ROBIN RESULT */
          if (res.mode === 'RR') {

            const updated = res.fixture;

            for (let gid in RR_FIXTURES) {
              const idx = RR_FIXTURES[gid].findIndex(f => f.id == updated.id);
              if (idx !== -1) {
                RR_FIXTURES[gid][idx] = {
                  ...RR_FIXTURES[gid][idx],
                  score: updated.score,
                  all_sets: updated.all_sets,
                  winner: updated.winner_registration,
                  home_score: updated.home_score,
                  away_score: updated.away_score
                };
              }
            }

            if (res.standings)
              RR_STANDINGS[updated.draw_group_id] = res.standings;

            if (res.oop) {
              RR_OOP = res.oop.map(fx => ({
                id: fx.id,
                round: fx.round ?? fx.round_nr ?? '',
                time: fx.time ?? '',
                home: fx.home ?? fx.home_name ?? fx.name1 ?? '',
                away: fx.away ?? fx.away_name ?? fx.name2 ?? '',
                score: fx.score ?? '',
                winner: fx.winner_registration ?? fx.winner ?? null,
                r1_id: fx.r1_id,
                r2_id: fx.r2_id
              }));

              renderOrderOfPlay();
            }

            renderMatrixFallback();
            renderStandings();
          }

          /* BRACKET RESULT */
          else if (res.mode === 'BRACKET') {

            if (res.oop) {
              RR_OOP = res.oop.map(fx => ({
                id: fx.id,
                stage: fx.stage ?? '',
                round: fx.round ?? fx.round_nr ?? '',
                match_nr: fx.match_nr ?? '',
                time: fx.time ?? '',
                home: fx.home ?? fx.home_name ?? fx.name1 ?? '',
                away: fx.away ?? fx.away_name ?? fx.name2 ?? '',
                score: fx.score ?? '',
                winner: fx.winner_registration ?? fx.winner ?? null,
                r1_id: fx.r1_id,
                r2_id: fx.r2_id
              }));

              renderOrderOfPlay();
            }

            loadMainBracket(true);
            loadPlateBracket(true);
            loadConsBracket(true);
          }

          const modal = bootstrap.Modal.getInstance(document.getElementById('rrScoreModal'));
          if (modal) modal.hide();

        })
        .fail(err => {
          toastr.error(err.responseJSON?.message || 'Error saving score');
          console.error(err);
        });
    });

    // ============================================================
    // SAVE GROUPS
    // ============================================================
    $('#btn-save-groups').on('click', function () {
      let payload = [];

      $('.rr-group').each(function () {
        const groupId = $(this).data('group-id');

        const registrationIds = $(this)
          .find('li')
          .map(function () { return $(this).data('id'); })
          .get();

        payload.push({
          group_id: groupId,
          registration_ids: registrationIds
        });
      });

      $.post(`${APP_URL}/backend/draw/${drawId}/save-groups`, { groups: payload })
        .done(() => toastr.success('Groups saved successfully'))
        .fail(() => toastr.error('Failed to save groups'));
    });
  }

  /* ===================================================
   * SCORE ORIENTATION
   * =================================================== */
  function getOrientedScore(fx, rowPlayerId) {
    if (!fx) return { s1: null, s2: null, isRowReg1: null };

    const isRowReg1 = fx.r1_id === rowPlayerId;
    const s1 = isRowReg1 ? fx.home_score : fx.away_score;
    const s2 = isRowReg1 ? fx.away_score : fx.home_score;

    return { s1, s2, isRowReg1 };
  }

  function formatScoreCell(fx, rowPlayerId) {
    if (!fx || !fx.all_sets || fx.all_sets.length === 0) return '';

    let display = [];

    fx.all_sets.forEach(setStr => {
      let [s1, s2] = setStr.split('-').map(Number);
      if (fx.r2_id === rowPlayerId) display.push(`${s2}-${s1}`);
      else display.push(`${s1}-${s2}`);
    });

    const last = display[display.length - 1];
    const [a, b] = last.split('-').map(Number);

    let cls = '';
    if (a > b) cls = 'rr-win';
    else if (b > a) cls = 'rr-loss';

    return `<span class="${cls}">${display.join(', ')}</span>`;
  }

  /* ===================================================
   * BRACKET LOADERS
   * =================================================== */
  $(document).on('shown.bs.tab', '#main-bracket-tab', function () {
    loadMainBracket();
  });

  $('#btn-generate-main-bracket').on('click', function () {
    const btn = $(this).prop('disabled', true);

    $.post(APP_URL + '/backend/draw/' + drawId + '/generate-main-bracket')
      .done(res => {
        if (res.success) {
          toastr.success(res.message);
          loadMainBracket();
        } else toastr.error(res.message);
      })
      .fail(() => toastr.error('Error generating bracket'))
      .always(() => btn.prop('disabled', false));
  });

  function loadMainBracket(force = false) {
    $('#main-bracket-wrapper')
      .load(`${APP_URL}/backend/draw/${drawId}/main-bracket?force=${force}`);
  }

  function loadPlateBracket(force = false) {
    $('#plate-bracket-wrapper')
      .load(`${APP_URL}/backend/draw/${drawId}/plate-bracket?force=${force}`);
  }

  function loadConsBracket(force = false) {
    $('#cons-bracket-wrapper')
      .load(`${APP_URL}/backend/draw/${drawId}/cons-bracket?force=${force}`);
  }

  /* ===================================================
   * STARTUP
   * =================================================== */
  $(document).ready(init);

})(jQuery, window, document);
