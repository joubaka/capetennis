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
    // DEBUG: detect bad keys
    Object.keys(RR_FIXTURES).forEach(k => {
      if (!k || k === "" || isNaN(parseInt(k))) {
        console.error("❌ BAD RR_FIXTURES KEY:", k, RR_FIXTURES[k]);
      }
    });
    Object.entries(RR_FIXTURES).forEach(([gid, list]) => {
      list.forEach((fx, i) => {
        console.log(`DEBUG FIXTURE gid=${gid} index=${i}`, fx.group_id);
      });
    });





    console.log('🟦 RR_GROUPS:', JSON.parse(JSON.stringify(window.RR_GROUPS)));

    const wrapper = $('#rr-matrix-wrapper');
    wrapper.empty();

    if (!window.RR_FIXTURES || !window.RR_GROUPS) {
      console.warn('⚠️ RR_FIXTURES or RR_GROUPS missing');
      return;
    }

    wrapper.addClass('rr-matrix-scroll');

    if (!RR_GROUPS.length) {
      wrapper.html('<div class="text-muted text-center py-4">No groups configured yet.</div>');
      return;
    }

    RR_GROUPS.forEach(group => {
      const groupId = group.id;
      const fixtures = (RR_FIXTURES && RR_FIXTURES[groupId]) ? RR_FIXTURES[groupId] : [];

      console.log(`🔷 GROUP ${groupId}`, group);
      console.log(`🔷 FIXTURES FOR GROUP ${groupId}`, fixtures);

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

    // Sort RR fixtures: round → group → match_nr; non-RR fixtures follow after
    const rrFx = RR_OOP.filter(f => f.stage === 'RR' || !f.stage)
      .slice()
      .sort((a, b) => {
        if (a.round !== b.round) return (a.round || 0) - (b.round || 0);
        if (a.group_id !== b.group_id) return (a.group_id || 0) - (b.group_id || 0);
        return (a.match_nr || 0) - (b.match_nr || 0);
      });
    const otherFx = RR_OOP.filter(f => f.stage && f.stage !== 'RR');
    const sorted = [...rrFx, ...otherFx];

    let html = '';

    sorted.forEach(fx => {

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
           <td class="text-center">${fx.group_name ? 'Box ' + fx.group_name : (fx.stage || '')}</td>
           <td class="text-center">${fx.time ?? ''}</td>
           <td class="text-center fw-bold">${fx.score || ''}</td>

           <td class="text-center">
               <button class="btn btn-sm btn-primary rr-open-score-modal"
                       data-fixture-id="${fx.id}"
                       data-home="${fx.home}"
                       data-away="${fx.away}">
                       <i class="ti ti-ball-tennis"></i>
               </button>
           </td>
       </tr>`;
    });

    tbody.html(html);
  }

  /* ===================================================
   * STANDINGS
   * ITF tiebreak cascade:
   *   1. Matches won
   *   2-way tie  : H2H → Sets % → Games % → =
   *   3+ way tie : Sets % → Games % → recurse sub-groups (→ H2H if 2-way) → =
   * =================================================== */
  function renderStandings() {
    const wrapper = $('#rr-standings-wrapper');
    if (!wrapper.length) return;

    wrapper.html('');

    const groups = window.RR_GROUPS || [];
    const standings = window.RR_STANDINGS || {};

    function sp(r) { const t = r.sets_won + r.sets_lost; return t > 0 ? r.sets_won / t : 0; }
    function gp(r) { const t = (r.games_won||0) + (r.games_lost||0); return t > 0 ? (r.games_won||0) / t : 0; }

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
          const subGroup = grp.slice(i, j);
          // Guard: if the sub-group is the same size as the input, no further
          // resolution is possible — return to avoid infinite recursion.
          if (subGroup.length === grp.length) {
            resolved.push(...subGroup);
          } else {
            resolved.push(...resolveGroup(subGroup));
          }
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
            <td>${r.player}</td>
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
      if (window.RR_DRAW_LOCKED) { toastr.warning('Draw is locked. Unlock the draw to make changes.'); return; }
      openScoreModal(
        $(this).data('fixture-id'),
        $(this).data('home'),
        $(this).data('away')
      );
    });

    $(document).on('click', '.rr-open-score-modal', function (e) {
      e.preventDefault();
      if (window.RR_DRAW_LOCKED) { toastr.warning('Draw is locked. Unlock the draw to make changes.'); return; }
      openScoreModal(
        $(this).data('fixture-id'),
        $(this).data('home'),
        $(this).data('away')
      );
    });

    // Bracket SVG click → open score modal
    $(document).on('click', '.bracket-score-btn', function () {
      if (window.RR_DRAW_LOCKED) { toastr.warning('Draw is locked. Unlock the draw to make changes.'); return; }
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
      if (window.RR_DRAW_LOCKED) { toastr.warning('Draw is locked. Unlock the draw to make changes.'); return; }

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

          /* ============================================================
             ROUND ROBIN RESULT
          ============================================================ */
          if (res.mode === 'RR') {

            const updated = res.fixture;

            // Update in-memory fixture
            // Update in-memory fixture (SAFE)
            for (let gid in RR_FIXTURES) {

              const idx = RR_FIXTURES[gid].findIndex(f => f && f.id == updated.id);

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


            // Update full data from server response
            if (res.rrFixtures) {
              window.RR_FIXTURES = res.rrFixtures;
            }
            if (res.standings) {
              window.RR_STANDINGS = res.standings;
            }
            if (res.oop) {
              window.RR_OOP = res.oop.map(fx => ({
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
                r2_id: fx.r2_id,
                playoff_type: fx.playoff_type ?? null,
                winner_feeders: fx.winner_feeders ?? [],
                loser_feeders: fx.loser_feeders ?? []
              }));
            }

            // Re-render all views with fresh data
            renderMatrixFallback();
            renderOrderOfPlay();
            renderStandings();
          }

          /* ============================================================
             BRACKET RESULT
          ============================================================ */
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

          // Clear all score inputs
          $('#set1-p1, #set1-p2, #set2-p1, #set2-p2, #set3-p1, #set3-p2').val('');
          $modalFixtureId.val('');
          $modalMatchLabel.html('');

        })
        .fail(err => {
          toastr.error(err.responseJSON?.message || 'Error saving score');
          console.error(err);
        });


    });

    // ============================================================
    // DELETE SCORE FROM MODAL
    // ============================================================
    $(document).on('click', '#rrm-delete-score', function() {
        if (window.RR_DRAW_LOCKED) { toastr.warning('Draw is locked. Unlock the draw to make changes.'); return; }
        var fixtureId = $('#rrm-fixture-id').val();
        if (!fixtureId) { toastr.warning('No fixture selected.'); return; }
        if (!confirm('Delete the score for this match?')) return;

        var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Deleting…');
        var url = window.RR_DELETE_SCORE_URL.replace('FIXTURE_ID', fixtureId);

        $.ajax({ url: url, method: 'DELETE' })
         .done(function(res) {
            toastr.success('Score deleted');
            bootstrap.Modal.getInstance(document.getElementById('rrScoreModal'))?.hide();
            $('#set1-p1, #set1-p2, #set2-p1, #set2-p2, #set3-p1, #set3-p2').val('');
            if (res.rrFixtures) {
                window.RR_FIXTURES = res.rrFixtures;
            }
            if (res.standings) {
                window.RR_STANDINGS = res.standings;
            }
            if (res.oop) {
                window.RR_OOP = res.oop.map(function(fx) {
                    return {
                        id: fx.id, stage: fx.stage ?? '', round: fx.round ?? '', match_nr: fx.match_nr ?? '',
                        time: fx.time ?? '', home: fx.home ?? '', away: fx.away ?? '',
                        score: fx.score ?? '', winner: fx.winner ?? null, r1_id: fx.r1_id, r2_id: fx.r2_id,
                        playoff_type: fx.playoff_type ?? null,
                        winner_feeders: fx.winner_feeders ?? [], loser_feeders: fx.loser_feeders ?? []
                    };
                });
            }
            renderMatrixFallback();
            renderOrderOfPlay();
            renderStandings();
            if (typeof loadMainBracket === 'function') loadMainBracket(true);
         })
         .fail(function(err) { toastr.error(err.responseJSON?.message || 'Error deleting score'); })
         .always(function() { $btn.prop('disabled', false).html('<i class="ti ti-trash me-1"></i> Delete Score'); });
    });

    // ============================================================
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

  const bracketSpinner = `<div class="text-center py-5">
      <div class="spinner-border text-primary" role="status" style="width:3rem;height:3rem;"></div>
      <div class="mt-3 fw-bold text-muted">Generating playoff brackets…</div>
      <small class="text-muted">This may take a few seconds</small>
    </div>`;

  const bracketLoadingSpinner = `<div class="text-center py-5">
      <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
      <div class="mt-2 text-muted">Loading bracket…</div>
    </div>`;

  $('#btn-generate-main-bracket').on('click', function () {
    const btn = $(this).prop('disabled', true);
    $('#main-bracket-wrapper').html(bracketSpinner);

    $.post(APP_URL + '/backend/draw/' + drawId + '/generate-main-bracket')
      .done(res => {
        if (res.success) {
          toastr.success(res.message);
          loadMainBracket();
        } else {
          toastr.error(res.message);
          $('#main-bracket-wrapper').html('<div class="alert alert-danger">Generation failed.</div>');
        }
      })
      .fail(() => {
        toastr.error('Error generating bracket');
        $('#main-bracket-wrapper').html('<div class="alert alert-danger">Error generating bracket.</div>');
      })
      .always(() => btn.prop('disabled', false));
  });

  function loadMainBracket(force = false) {
    $('#main-bracket-wrapper')
      .html(bracketLoadingSpinner)
      .load(`${APP_URL}/backend/draw/${drawId}/main-bracket?force=${force}`);
  }

  function loadPlateBracket(force = false) {
    $('#plate-bracket-wrapper')
      .html(bracketLoadingSpinner)
      .load(`${APP_URL}/backend/draw/${drawId}/plate-bracket?force=${force}`);
  }

  function loadConsBracket(force = false) {
    $('#cons-bracket-wrapper')
      .html(bracketLoadingSpinner)
      .load(`${APP_URL}/backend/draw/${drawId}/cons-bracket?force=${force}`);
  }

  /* ===================================================
   * STARTUP
   * =================================================== */
  $(document).ready(init);
  function updateMatrixCell(fx) {
    const gid = fx.draw_group_id;
    const group = RR_GROUPS.find(g => g.id == gid);
    if (!group) return;

    group.registrations.forEach(rowReg => {
      group.registrations.forEach(colReg => {

        if (rowReg.id === colReg.id) return;

        const match =
          (fx.r1_id == rowReg.id && fx.r2_id == colReg.id) ||
          (fx.r1_id == colReg.id && fx.r2_id == rowReg.id);

        if (!match) return;

        const selector = `.rr-score-cell[data-fixture-id="${fx.id}"]`;
        const $cell = $(selector);
        if (!$cell.length) return;

        let display = [];
        fx.all_sets.forEach(setStr => {
          let [s1, s2] = setStr.split('-').map(Number);
          if (fx.r2_id == rowReg.id) display.push(`${s2}-${s1}`);
          else display.push(`${s1}-${s2}`);
        });

        const last = display[display.length - 1] || '';
        const [a, b] = last.split('-').map(Number);
        let cls = '';
        if (a > b) cls = 'rr-win';
        else if (b > a) cls = 'rr-loss';

        $cell.html(`<span class="${cls}">${display.join(', ')}</span>`);
      });
    });
  }

  function updateOOPRow(fx) {
    const $row = $(`#rr-order-table tr[data-fixture-id="${fx.id}"]`);
    if (!$row.length) return;

    // Score column
    $row.find('td:eq(6)').text(fx.score || '');

    // Winner highlight
    const p1 = $row.find('td:eq(1)');
    const p2 = $row.find('td:eq(3)');
    p1.removeClass('bg-success bg-danger text-white');
    p2.removeClass('bg-success bg-danger text-white');

    if (fx.winner_registration) {
      if (fx.winner_registration == fx.r1_id) {
        p1.addClass('bg-success text-white');
        p2.addClass('bg-danger text-white');
      } else {
        p1.addClass('bg-danger text-white');
        p2.addClass('bg-success text-white');
      }
    }
  }


})(jQuery, window, document);
