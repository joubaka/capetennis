(function ($, window, document) {
    'use strict';

    // 1. Setup AJAX
    $.ajaxSetup({
        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' }
    });

    const $app = $('#round-robin-app');
    if (!$app.length) return;

    /* ===================================================
     * CORE INITIALIZATION
     * =================================================== */
    function init() {
        normalizeFixtures();
        renderMatrix();
        renderOrderOfPlay();
        renderStandings();
        // Sortable is now initialized in the Blade template's initGroupsSortable()
        refreshPlaceholders();
        bindEvents();
    }

    function normalizeFixtures() {
        for (let gid in window.RR_FIXTURES) {
            window.RR_FIXTURES[gid] = window.RR_FIXTURES[gid].map(f => {
                if (!f) return f;
                f.id = parseInt(f.id);
                f.r1_id = parseInt(f.r1_id);
                f.r2_id = parseInt(f.r2_id);
                if (!Array.isArray(f.all_sets)) {
                    f.all_sets = f.score ? f.score.split(' ').filter(s => s.includes('-')) : [];
                }
                return f;
            });
        }
    }

    /* ===================================================
     * DRAG & DROP LOGIC - Sortable initialized in Blade template
     * =================================================== */

    function refreshPlaceholders() {
        $('.rr-group').each(function() {
            if ($(this).children('li').length === 0) {
                if ($(this).find('.placeholder-text').length === 0) {
                    $(this).append('<div class="placeholder-text">Drop Players Here</div>');
                }
            } else {
                $(this).find('.placeholder-text').remove();
            }
        });
    }

    /* ===================================================
     * RENDERING LOGIC (MATRIX & STANDINGS)
     * =================================================== */
    function renderMatrix() {
        const $wrapper = $('#rr-matrix-wrapper');
        $wrapper.empty().addClass('rr-matrix-scroll');

        window.RR_GROUPS.forEach(group => {
            const fixtures = window.RR_FIXTURES[group.id] || [];
            let players = (group.registrations || []).map(r => ({
                id: r.id,
                name: r.display_name || 'N/A',
                seed: r.pivot ? (r.pivot.seed ?? 999) : 999
            })).sort((a, b) => a.seed - b.seed);

            let html = `<h6 class="fw-bold mt-4 mb-2">Box ${group.name}</h6>
                        <table class="table table-bordered rr-matrix-table mb-4">
                            <thead><tr><th class="bg-light"></th>${players.map(p => `<th>${p.name}</th>`).join('')}</tr></thead>
                            <tbody>`;

            players.forEach(rowP => {
                html += `<tr><th class="bg-light">${rowP.name}</th>`;
                players.forEach(colP => {
                    if (rowP.id === colP.id) {
                        html += `<td class="bg-diagonal"></td>`;
                    } else {
                        const fx = fixtures.find(f => (f.r1_id === rowP.id && f.r2_id === colP.id) || (f.r1_id === colP.id && f.r2_id === rowP.id));
                        html += `<td class="rr-score-cell" data-fixture-id="${fx ? fx.id : ''}" data-home="${rowP.name}" data-away="${colP.name}">
                                    ${fx ? formatMatrixScore(fx, rowP.id) : '–'}</td>`;
                    }
                });
                html += `</tr>`;
            });
            $wrapper.append(html + `</tbody></table>`);
        });
    }

    function formatMatrixScore(fx, rowPlayerId) {
        if (!fx.all_sets || fx.all_sets.length === 0) return fx.time || '–';
        const display = fx.all_sets.map(set => {
            const [s1, s2] = set.split('-').map(Number);
            return fx.r1_id === rowPlayerId ? `${s1}-${s2}` : `${s2}-${s1}`;
        });
        const last = display[display.length - 1].split('-').map(Number);
        const cls = last[0] > last[1] ? 'rr-win' : (last[1] > last[0] ? 'rr-loss' : '');
        return `<span class="${cls}">${display.join(', ')}</span>`;
    }

    function renderOrderOfPlay() {
        const $tbody = $('#rr-order-table tbody');
        $tbody.empty();
        if (!window.RR_OOP.length) { $tbody.html('<tr><td colspan="7" class="text-center py-3 text-muted">No matches generated yet</td></tr>'); return; }

        window.RR_OOP.forEach(fx => {
            $tbody.append(`
                <tr data-fixture-id="${fx.id}">
                    <td><small class="text-muted">#${fx.id}</small></td>
                    <td class="${fx.winner == fx.r1_id ? 'fw-bold text-success' : ''}">${fx.home}</td>
                    <td class="text-center text-muted"><small>vs</small></td>
                    <td class="${fx.winner == fx.r2_id ? 'fw-bold text-success' : ''}">${fx.away}</td>
                    <td class="text-center">${fx.round || 1}</td>
                    <td class="text-center fw-bold">${fx.score || '-'}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-icon btn-primary rr-open-score-modal" data-fixture-id="${fx.id}" data-home="${fx.home}" data-away="${fx.away}">
                            <i class="ti ti-ball-tennis"></i>
                        </button>
                    </td>
                </tr>`);
        });
    }

    function renderStandings() {
        const $wrapper = $('#rr-standings-wrapper');
        $wrapper.empty();
        const standings = window.RR_STANDINGS || {};

        window.RR_GROUPS.forEach(group => {
            if (!standings[group.id]) return;
            let rows = Object.values(standings[group.id]).sort((a,b) => (b.wins - a.wins) || ((b.sets_won-b.sets_lost) - (a.sets_won-a.sets_lost)));
            
            let html = `<h6 class="fw-bold mt-3">Box ${group.name} Standings</h6>
                        <table class="table table-sm mb-4">
                            <thead class="table-dark"><tr><th>#</th><th>Player</th><th>W</th><th>L</th><th>Sets Diff</th></tr></thead>
                            <tbody>`;
            rows.forEach((r, i) => {
                html += `<tr><td>${i+1}</td><td>${r.player}</td><td>${r.wins}</td><td>${r.losses}</td><td>${r.sets_won - r.sets_lost}</td></tr>`;
            });
            $wrapper.append(html + `</tbody></table>`);
        });
    }

    /* ===================================================
     * EVENT BINDING
     * =================================================== */
    function bindEvents() {
        // Save Group Assignments
        $('#btn-save-groups').on('click', function() {
            const groups = [];
            $('.rr-group').each(function() {
                const ids = [];
                $(this).find('li').each(function() { ids.push($(this).data('id')); });
                groups.push({ group_id: $(this).data('group-id'), registration_ids: ids });
            });

            $.post(`${APP_URL}/backend/draw/${DRAW_ID}/save-groups`, { groups }).done(() => toastr.success('Groups Saved'));
        });

        // Regenerate Fixtures
        $('#btn-regenerate-fixtures').on('click', function() {
            Swal.fire({ title: 'Regenerate?', text: 'Existing scores will be lost!', icon: 'warning', showCancelButton: true }).then(res => {
                if (res.isConfirmed) {
                    $.post(`${APP_URL}/backend/draw/${DRAW_ID}/regenerate-rr`).done(() => location.reload());
                }
            });
        });

        // Score Entry Modal
        $(document).on('click', '.rr-open-score-modal, .rr-score-cell', function() {
            const id = $(this).data('fixture-id');
            if (!id) return;
            $('#rrm-fixture-id').val(id);
            $('#rrm-match-label').text($(this).data('home') + ' vs ' + $(this).data('away'));
            $('.p1-name-label').text($(this).data('home'));
            $('.p2-name-label').text($(this).data('away'));
            new bootstrap.Modal(document.getElementById('rrScoreModal')).show();
        });

        $('#rr-score-modal-form').on('submit', function(e) {
            e.preventDefault();
            const sets = [
                [$('#set1-p1').val(), $('#set1-p2').val()],
                [$('#set2-p1').val(), $('#set2-p2').val()],
                [$('#set3-p1').val(), $('#set3-p2').val()]
            ].filter(s => s[0] !== '' && s[1] !== '').map(s => `${s[0]}-${s[1]}`);

            $.post(window.RR_SAVE_SCORE_URL.replace('FIXTURE_ID', $('#rrm-fixture-id').val()), { sets })
             .done(() => location.reload());
        });

        $(document).on('click', '.btn-remove-item', function() {
            const $li = $(this).closest('li');
            $('.rr-sortable[data-type="source"]').first().append($li);
            $(this).remove();
            refreshPlaceholders();
        });

        // Bracket tab - load main bracket when tab is shown
        $(document).on('shown.bs.tab', '#main-bracket-tab', function () {
            loadMainBracket();
        });

        // Generate main bracket button
        $('#btn-generate-main-bracket').on('click', function () {
            const btn = $(this).prop('disabled', true);

            $.post(APP_URL + '/backend/draw/' + DRAW_ID + '/generate-main-bracket')
                .done(res => {
                    if (res.success) {
                        toastr.success(res.message);
                        loadMainBracket(true);
                    } else {
                        toastr.error(res.message);
                    }
                })
                .fail(() => toastr.error('Error generating bracket'))
                .always(() => btn.prop('disabled', false));
        });
    }

    /* ===================================================
     * BRACKET LOADERS
     * =================================================== */
    function loadMainBracket(force = false) {
        $('#main-bracket-wrapper')
            .html('<div class="text-center text-muted py-5"><div class="spinner-border spinner-border-sm"></div><div class="mt-2">Loading bracket…</div></div>')
            .load(`${APP_URL}/backend/draw/${DRAW_ID}/main-bracket?force=${force}`);
    }

    function loadPlateBracket(force = false) {
        $('#plate-bracket-wrapper')
            .load(`${APP_URL}/backend/draw/${DRAW_ID}/plate-bracket?force=${force}`);
    }

    $(document).ready(init);

})(jQuery, window, document);
