
@extends('layouts/layoutMaster')

@section('title', 'Admin - ' . $event->name)

@section('vendor-style')
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')}}">
<link rel="stylesheet" href="{{asset('assets/vendor/libs/animate-css/animate.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/select2/select2.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/formvalidation/dist/css/formValidation.min.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/quill/editor.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/toastr/toastr.css')}}" />
<link rel="stylesheet" href="{{asset('assets/vendor/libs/flatpickr/flatpickr.css')}}" />
@endsection




@section('page-style')
<link rel="stylesheet" href="{{asset('assets/vendor/css/pages/page-user-view.css')}}" />
@endsection

@section('vendor-script')


{{-- jQuery Repeater --}}


<script src="{{asset('assets/vendor/libs/moment/moment.js')}}"></script>
<script src="{{asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sweetalert2/sweetalert2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave.js')}}"></script>
<script src="{{asset('assets/vendor/libs/cleavejs/cleave-phone.js')}}"></script>
<script src="{{asset('assets/vendor/libs/select2/select2.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/FormValidation.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/Bootstrap5.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/formvalidation/dist/js/plugins/AutoFocus.min.js')}}"></script>
<script src="{{asset('assets/vendor/libs/quill/quill.js')}}"></script>
<script src="{{asset('assets/vendor/libs/toastr/toastr.js')}}"></script>
<script src="{{asset('assets/vendor/libs/sortablejs/sortable.js')}}"></script>
<script src="{{asset('assets/vendor/libs/flatpickr/flatpickr.js')}}"></script>
@endsection

@section('page-script')

<script>
    /* ---------------------------------------------
     * GLOBAL VENUES ARRAY (used for venue modal)
     * --------------------------------------------- */
    window.ALL_VENUES = @json(($venues ?? collect())->map(fn($v) => [
        'id'   => $v->id,
        'name' => $v->name
    ]));
</script>



<script>
$(document).ready(function () {

    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    /* ============================================================
     * SECTION 1 — DRAW NAME AUTO-UPDATE LOGIC
     * ============================================================ */
    function updateDrawName() {
        let selectedType = $('input[name="draw_type_id"]:checked');
        let typeText = selectedType.closest('.switch').find('.switch-label').text().trim();

        let selectedCat = $('input[name="category_choice"]:checked');
        let catText = selectedCat.data('age')
            || selectedCat.closest('.switch').find('.switch-label').text().trim();

        let name = '';

        if (selectedCat.length) name += catText;

        if (selectedType.val() == "3" && selectedCat.length) {
            name += ' – ' + typeText + ' (Boys & Girls)';
        } else if (typeText) {
            name += ' – ' + typeText;
        }

        $('#drawName').val(name);
    }

    $(document).on('change', 'input[name="draw_type_id"]', function () {
        let selectedVal = $(this).val();
        let isMixed = $(this).data('mixed') == 1;

        if (selectedVal == "3") {
            $('#categorySection').addClass('d-none');
            $('#mixedPlaceholder').addClass('d-none');
            $('#type3Categories').removeClass('d-none');
            $('input[name="category_choice"]').prop('checked', false);

        } else if (isMixed) {
            $('#type3Categories').addClass('d-none');
            $('#categorySection').addClass('d-none');
            $('#mixedPlaceholder').removeClass('d-none');
            $('input[name="category_choice"]').prop('checked', false);

        } else {
            $('#type3Categories').addClass('d-none');
            $('#mixedPlaceholder').addClass('d-none');
            $('#categorySection').removeClass('d-none');
        }

        updateDrawName();
    });

    $(document).on('change', 'input[name="category_choice"]', updateDrawName);


    /* ============================================================
     * SECTION 2 — CREATE DRAW FORM SUBMIT
     * ============================================================ */
    $('#createDrawForm').on('submit', function (e) {
        e.preventDefault();

        const drawName = $('#drawName').val().trim();
        if (!drawName) { toastr.error('Please enter a draw name'); return; }

        $.post("{{ route('headoffice.createSingleDraw', $event->id) }}", $(this).serialize())
        .done(function (data) {
            toastr.success(data.message);
            $('#createDrawModal').modal('hide');
            location.reload();
        })
        .fail(function () {
            toastr.error('Error creating draw');
        });
    });


    /* ============================================================
     * SECTION 3 — RECREATE FIXTURES
     * ============================================================ */
    $(document).on('click', '.btn-recreate-fixtures', function () {
        const $btn = $(this);

        Swal.fire({
            title: 'Recreate Fixtures?',
            html: `This will <strong>delete and rebuild</strong> all fixtures for <b>${$btn.data('draw-name')}</b>.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, recreate',
            confirmButtonColor: '#28a745'
        }).then((result) => {
            if (!result.isConfirmed) return;

            showLoading();

            $.post($btn.data('url'), {_token: csrfToken})
            .done(function (response) {
                if (response.success) {
                    toastr.success(response.message);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    toastr.error(response.message);
                }
            })
            .fail(function () {
                toastr.error('Failed to recreate fixtures.');
            })
            .always(hideLoading);
        });
    });


    /* ============================================================
     * SECTION 4 — GENERATE FIXTURES (NEW DRAW)
     * ============================================================ */
    $('#generate-fixtures-btn').on('click', function (e) {
        e.preventDefault();

        let url = $(this).data('url');
        if (!url) { toastr.error('No fixture generation URL'); return; }

        showLoading();

        $.post(url, {_token: csrfToken})
        .done(function (data) {
            toastr.success(data.message || 'Fixtures generated');
            location.reload();
        })
        .fail(function () {
            toastr.error('Error generating fixtures');
        })
        .always(hideLoading);
    });


    /* ============================================================
     * SECTION 5 — VENUE MODAL LOGIC
     * ============================================================ */

    const $venuesModal     = $('#venuesModal');
    const $venuesForm      = $('#venuesForm');
    const $venuesContainer = $('#venues-container');

    function venueRowTemplate(selectedId = "", numCourts = 1) {
        let options = `<option value="">-- Select Venue --</option>`;
        window.ALL_VENUES.forEach(v => {
            const sel = (String(selectedId) === String(v.id)) ? 'selected' : '';
            options += `<option value="${v.id}" ${sel}>${v.name}</option>`;
        });

        return `
          <div class="venue-row d-flex gap-2 mb-2">
            <select name="venue_id[]" class="form-select venue-select">${options}</select>
            <input type="number" name="num_courts[]" class="form-control" min="1" value="${numCourts}">
            <button type="button" class="btn btn-danger btn-remove-row">&times;</button>
          </div>`;
    }

    function initSelect2($row) {
        let $select = $row.find('.venue-select');
        if ($select.hasClass("select2-hidden-accessible")) $select.select2('destroy');

        $select.select2({
            dropdownParent: $venuesModal,
            width: '100%'
        });
    }

    $(document).on('click', '.btn-add-venues', function () {
        let drawId   = $(this).data('draw-id');
        let drawName = $(this).data('draw-name');

        let url = @json(route('backend.draw.venues.store', ['draw' => '__ID__'])).replace('__ID__', drawId);
        $venuesForm.attr('action', url).data('draw-id', drawId);

        $venuesModal.find('.modal-title').text('Assign Venues to ' + drawName);
        $venuesContainer.empty();

        let jsonUrl = @json(route('backend.draw.venues.json', ['draw' => '__ID__'])).replace('__ID__', drawId);
        
        $.get(jsonUrl).done(function (venues) {
            if (venues.length > 0) {
                venues.forEach(v => {
                    let $row = $(venueRowTemplate(v.id, v.num_courts));
                    $venuesContainer.append($row);
                    initSelect2($row);
                });
            } else {
                let $row = $(venueRowTemplate());
                $venuesContainer.append($row);
                initSelect2($row);
            }

            $venuesModal.modal('show');
        });
    });

    $('#addVenueRow').on('click', function () {
        let $row = $(venueRowTemplate());
        $venuesContainer.append($row);
        initSelect2($row);
    });

    $(document).on('click', '.btn-remove-row', function () {
        $(this).closest('.venue-row').remove();
    });

    $venuesForm.on('submit', function (e) {
        e.preventDefault();

        let url    = $(this).attr('action');
        let data   = $(this).serialize();
        let drawId = $(this).data('draw-id');

        $.post(url, data + '&_token=' + csrfToken)
        .done(function (response) {
            if (!response.success) {
                toastr.error("Could not save venues.");
                return;
            }

            toastr.success("Venues updated successfully.");
            $venuesModal.modal('hide');

            let $vc = $('.draw-venues[data-draw-id="' + drawId + '"]');

            if (response.venues.length > 0) {
                let html = '<small class="text-muted">Venues:</small> ';
                response.venues.forEach(v => {
                    html += `
                        <span class="badge bg-label-primary me-1">
                          ${v.name} <span class="text-muted">(${v.pivot.num_courts})</span>
                        </span>`;
                });
                $vc.html(html);
            } else {
                $vc.empty();
            }
        })
        .fail(() => toastr.error("Error while saving venues."));
    });


    /* ============================================================
     * SECTION 6 — PUBLISH / DELETE DRAW
     * ============================================================ */
    $(document).on('click', '.toggle-publish', function () {
        let $btn = $(this);

        $.post($btn.data('url'), {_token: csrfToken, status: $btn.data('status')})
        .done(function (resp) {
            if (!resp.success) {
                toastr.error("Could not update publish status.");
                return;
            }

            let newStatus = resp.published ? 1 : 0;
            $btn.data('status', newStatus);

            if (newStatus) {
                $btn.removeClass('btn-danger').addClass('btn-success')
                    .html('<i class="ti ti-eye-off me-1"></i>Unpublish');
                toastr.success("Draw published.");
            } else {
                $btn.removeClass('btn-success').addClass('btn-danger')
                    .html('<i class="ti ti-eye me-1"></i>Publish');
                toastr.info("Draw unpublished.");
            }
        });
    });

    $(document).on('click', '.btn-delete-draw', function () {
        let $btn = $(this);

        Swal.fire({
            title: 'Delete Draw?',
            html: `Are you sure you want to delete <strong>${$btn.data('draw-name')}</strong>?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it',
        }).then((res) => {
            if (!res.isConfirmed) return;

            $.ajax({
                url: $btn.data('url'),
                type: 'DELETE',
                data: { _token: csrfToken },
                success: function (resp) {
                    if (resp.success) {
                        toastr.success(resp.message);
                        $btn.closest('.list-group-item').fadeOut(300, function () { $(this).remove(); });
                    } else {
                        toastr.error(resp.message);
                    }
                }
            });
        });
    });


    /* ============================================================
     * SECTION 7 — LOADING OVERLAY HELPERS
     * ============================================================ */
    function showLoading() {
        $('#loading-overlay').removeClass('d-none').addClass('d-flex');
    }
    function hideLoading() {
        $('#loading-overlay').removeClass('d-flex').addClass('d-none');
    }


    /* ============================================================
     * SECTION 8 — PRINT ALL DRAWS
     * ============================================================ */

    // Toggle select-all checkbox
    $('#chk-select-all-draws').on('change', function () {
        $('.print-draw-chk').prop('checked', $(this).is(':checked'));
    });
    $(document).on('change', '.print-draw-chk', function () {
        var total = $('.print-draw-chk').length;
        var checked = $('.print-draw-chk:checked').length;
        $('#chk-select-all-draws').prop('checked', total === checked);
    });

    // Show/hide standings option
    $('input[name="print_type"]').on('change', function () {
        var val = $(this).val();
        $('#standings-option').toggle(val === 'matrix' || val === 'combined');
    });

    // Print styles for browser print window
    var printStyles = `<style>
      * { margin: 0; padding: 0; box-sizing: border-box; }
      body { font-family: Arial, sans-serif; padding: 15px; color: #000; }
      h1 { font-size: 18px; margin-bottom: 4px; }
      h2 { font-size: 14px; color: #555; margin-bottom: 16px; }
      h3 { font-size: 14px; margin: 16px 0 6px; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 12px; }
      th, td { border: 1px solid #999; padding: 8px 6px; text-align: left; }
      th { background: #333; color: #fff; font-weight: 600; }
      .text-center { text-align: center; }
      .fw-bold { font-weight: bold; }
      .text-success { color: #198754; }
      .page-break { page-break-before: always; }
      .matrix-group { page-break-inside: avoid; }
      .rr-matrix-table { border-collapse: collapse; table-layout: fixed; page-break-inside: avoid; }
      .rr-matrix-table td, .rr-matrix-table th { border: 1px solid #999; padding: 6px 4px; text-align: center; font-size: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .rr-matrix-table thead th { background: #fff; color: #0a3566; border: 2px solid #0a3566; font-weight: 700; padding: 6px 4px; }
      .rr-matrix-table tbody th { background: #fff; color: #0b722e; border: 2px solid #0b722e; font-weight: 700; text-align: left; padding: 6px 5px; }
      .rr-matrix-table .rr-win { color: #00a859; font-weight: bold; }
      .rr-matrix-table .rr-loss { color: #d32f2f; font-weight: bold; }
      .rr-matrix-table td.bg-diagonal { background: #000 !important; border-color: #333; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
      .standings-table { width: auto; margin-top: 10px; page-break-inside: avoid; }
      .standings-table th { border: 2px solid #222; color: #222; font-weight: 700; }
      @media print { body { padding: 5px; } @page { margin: 8mm; } }
    </style>`;

    function openPrintWindow(title, bodyHtml) {
        var w = window.open('', '_blank');
        if (!w) { toastr.error('Popup blocked — please allow popups for this site.'); return null; }
        w.document.write('<!DOCTYPE html><html><head><title>' + title + '</title>' + printStyles + '</head><body>' + bodyHtml + '</body></html>');
        w.document.close();
        w.focus();
        w.print();
        return w;
    }

    function writeToPrintWindow(w, title, bodyHtml) {
        w.document.open();
        w.document.write('<!DOCTYPE html><html><head><title>' + title + '</title>' + printStyles + '</head><body>' + bodyHtml + '</body></html>');
        w.document.close();
        w.focus();
        w.print();
    }

    function buildFixturesHtml(drawData) {
        var oop = drawData.oops || [];
        if (!oop.length) return '';

        var stageLabels = { RR: 'Round Robin', MAIN: 'Main Draw', PLATE: 'Plate', CONS: 'Consolation', BOWL: 'Bowl', SHIELD: 'Shield', SPOON: 'Spoon' };
        var grouped = {};
        var stageOrder = [];
        oop.forEach(function (fx) {
            var stage = fx.stage || 'RR';
            if (!grouped[stage]) { grouped[stage] = []; stageOrder.push(stage); }
            grouped[stage].push(fx);
        });

        function feederLabel(fx, slot) {
            if (fx.stage === 'RR') return '';
            var wf = fx.winner_feeders || [];
            var lf = fx.loser_feeders || [];
            var idx = (slot === 'home') ? 0 : 1;
            var playerName = (slot === 'home') ? fx.home : fx.away;
            if (playerName && playerName !== 'TBD' && playerName !== '---') return '';
            if (wf.length >= 2) return '<small style="color:#0d6efd;">W' + wf[idx] + '</small>';
            if (wf.length === 1 && lf.length >= 1) {
                return idx === 0
                    ? '<small style="color:#0d6efd;">W' + wf[0] + '</small>'
                    : '<small style="color:#e65100;">L' + lf[0] + '</small>';
            }
            if (lf.length >= 2) return '<small style="color:#e65100;">L' + lf[idx] + '</small>';
            if (lf.length === 1 && idx === 0) return '<small style="color:#e65100;">L' + lf[0] + '</small>';
            return '';
        }

        var html = '';
        stageOrder.forEach(function (stage) {
            html += '<h3 style="margin-top:18px;">' + (stageLabels[stage] || stage) + '</h3>';
            html += '<table><thead><tr><th>M#</th><th>Player 1</th><th class="text-center">vs</th><th>Player 2</th><th class="text-center">Rd</th><th class="text-center">Score</th></tr></thead><tbody>';
            grouped[stage].forEach(function (fx) {
                var w1 = fx.winner == fx.r1_id ? ' class="fw-bold text-success"' : '';
                var w2 = fx.winner == fx.r2_id ? ' class="fw-bold text-success"' : '';
                var typeLabel = fx.playoff_type ? ' <small style="color:#666;">(' + fx.playoff_type + ')</small>' : '';
                var home = fx.home || '---';
                var away = fx.away || '---';
                var homeFeed = feederLabel(fx, 'home');
                var awayFeed = feederLabel(fx, 'away');
                if (homeFeed) home = homeFeed;
                if (awayFeed) away = awayFeed;
                html += '<tr>';
                html += '<td>' + (fx.match_nr || fx.id) + '</td>';
                html += '<td' + w1 + '>' + home + typeLabel + '</td>';
                html += '<td class="text-center">vs</td>';
                html += '<td' + w2 + '>' + away + '</td>';
                html += '<td class="text-center">' + (fx.round || '') + '</td>';
                html += '<td class="text-center">' + (fx.score || '') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
        });
        return html;
    }

    function buildMatrixHtml(drawData, includeStandings) {
        var groups = drawData.groups || [];
        var fixtures = drawData.rrFixtures || {};
        if (!groups.length) return '';

        var sortedGroups = groups.slice().sort(function (a, b) { return (a.name || '').localeCompare(b.name || ''); });

        var html = '';
        sortedGroups.forEach(function (group) {
            var gFixtures = fixtures[group.id] || [];
            var players = (group.registrations || []).map(function (r) {
                return { id: r.id, name: r.display_name || 'N/A', seed: r.pivot ? (r.pivot.seed || 999) : 999 };
            }).sort(function (a, b) { return a.seed - b.seed; });

            // Auto-scale: fit within ~700px page width
            var numCols = players.length + 2;
            var colW = Math.min(130, Math.floor(700 / numCols));
            var nameW = Math.max(colW, 90);
            var tableW = nameW + (players.length * colW) + 40;
            var cw = colW + 'px';

            html += '<div class="matrix-group">';
            html += '<h3>Box ' + group.name + '</h3>';
            html += '<table class="rr-matrix-table" style="width:' + tableW + 'px;"><thead><tr><th style="width:' + nameW + 'px;"></th>';
            players.forEach(function (p) { html += '<th style="width:' + cw + '">' + p.name + '</th>'; });
            html += '<th style="width:40px; background:#198754; color:#fff; font-weight:800;">W</th></tr></thead><tbody>';

            players.forEach(function (rowP) {
                html += '<tr><th>' + rowP.name + '</th>';
                players.forEach(function (colP) {
                    if (rowP.id === colP.id) { html += '<td class="bg-diagonal"></td>'; return; }
                    var fx = gFixtures.find(function (f) { return (f.r1_id === rowP.id && f.r2_id === colP.id) || (f.r1_id === colP.id && f.r2_id === rowP.id); });
                    if (fx && fx.all_sets && fx.all_sets.length > 0) {
                        var display = fx.all_sets.map(function (set) { var p = set.split('-').map(Number); return fx.r1_id === rowP.id ? p[0]+'-'+p[1] : p[1]+'-'+p[0]; });
                        var last = display[display.length-1].split('-').map(Number);
                        html += '<td class="' + (last[0]>last[1]?'rr-win':last[1]>last[0]?'rr-loss':'') + '">' + display.join(', ') + '</td>';
                    } else { html += '<td></td>'; }
                });
                var rowWins = 0;
                gFixtures.forEach(function (f) { if (!f.all_sets||!f.all_sets.length) return; var ls=f.all_sets[f.all_sets.length-1].split('-').map(Number); if(f.r1_id===rowP.id&&ls[0]>ls[1]) rowWins++; if(f.r2_id===rowP.id&&ls[1]>ls[0]) rowWins++; });
                html += '<td style="font-weight:800;font-size:13px;background:#f0fdf4;color:#198754;">' + rowWins + '</td></tr>';
            });
            html += '</tbody></table>';
            html += '</div>';
        });

        if (includeStandings) {
            var standings = drawData.standings || {};
            sortedGroups.forEach(function (group) {
                if (!standings[group.id]) return;
                var rows = Object.values(standings[group.id]).sort(function (a,b) { return (b.wins-a.wins)||((b.sets_won-b.sets_lost)-(a.sets_won-a.sets_lost)); });
                html += '<h3>Box ' + group.name + ' — Standings</h3>';
                html += '<table class="standings-table"><thead><tr><th>#</th><th>Player</th><th>W</th><th>L</th><th>Sets +/-</th></tr></thead><tbody>';
                rows.forEach(function (r, i) { html += '<tr><td>'+(i+1)+'</td><td>'+r.player+'</td><td>'+r.wins+'</td><td>'+r.losses+'</td><td>'+(r.sets_won-r.sets_lost)+'</td></tr>'; });
                html += '</tbody></table>';
            });
        }
        return html;
    }

    function getSelectedDrawIds() {
        var ids = [];
        $('.print-draw-chk:checked').each(function () { ids.push($(this).val()); });
        return ids;
    }

    // ---- Sequential per-draw loader (browser print) ----
    $('#btn-print-all-draws').on('click', function () {
        var drawIds = getSelectedDrawIds();
        if (!drawIds.length) { toastr.warning('Please select at least one draw.'); return; }

        // Open window NOW (synchronous, on user click) so popup blocker won't block it
        var printWin = window.open('', '_blank');
        if (!printWin) {
            toastr.error('Popup blocked — please allow popups for this site.');
            return;
        }
        printWin.document.write('<!DOCTYPE html><html><head><title>Loading…</title></head><body style="font-family:Arial,sans-serif;padding:40px;text-align:center;"><h2>Loading draws…</h2><p>Please wait.</p></body></html>');
        printWin.document.close();

        // Keep focus on the modal so user sees the progress bar
        window.focus();

        var printType = $('input[name="print_type"]:checked').val();
        var includeStandings = $('#chk-include-standings').is(':checked');
        var $btn = $(this).prop('disabled', true);
        var $progress = $('#print-progress');
        var $bar = $('#print-progress-bar');
        var $label = $('#print-progress-label');

        $progress.removeClass('d-none');
        $bar.css('width', '0%');

        var eventName = @json($event->name);
        var fullHtml = '<h1>' + eventName + '</h1>';
        var loaded = 0;
        var total = drawIds.length;

        function loadNext() {
            if (loaded >= total) {
                // Reset modal UI
                $btn.prop('disabled', false).html('<i class="ti ti-printer me-1"></i> Print');
                $progress.addClass('d-none');
                $('#printAllDrawsModal').modal('hide');

                // Write final content into the already-open window, then print
                var typeLabels = { fixtures: 'Fixtures', matrix: 'Matrix', combined: 'Combined' };
                var title = eventName + ' — ' + (typeLabels[printType] || 'Print');
                printWin.document.open();
                printWin.document.write('<!DOCTYPE html><html><head><title>' + title + '</title>' + printStyles + '</head><body>' + fullHtml + '</body></html>');
                printWin.document.close();
                printWin.focus();
                setTimeout(function () { printWin.print(); }, 300);
                return;
            }

            var drawId = drawIds[loaded];
            $label.text('Loading draw ' + (loaded + 1) + ' of ' + total + '…');

            $.get("{{ route('headoffice.printDrawsData', $event->id) }}", { draw_id: drawId })
              .done(function (resp) {
                  var drawData = resp.draw;
                  if (drawData) {
                      if (loaded > 0) fullHtml += '<div class="page-break"></div>';
                      fullHtml += '<h2>' + drawData.name + '</h2>';
                      if (printType === 'fixtures')  fullHtml += buildFixturesHtml(drawData);
                      if (printType === 'matrix')    fullHtml += buildMatrixHtml(drawData, includeStandings);
                      if (printType === 'combined') { fullHtml += buildMatrixHtml(drawData, includeStandings); fullHtml += buildFixturesHtml(drawData); }
                  }
              })
              .fail(function () { toastr.error('Failed to load draw data.'); })
              .always(function () {
                  loaded++;
                  var pct = Math.round((loaded / total) * 100);
                  $bar.css('width', pct + '%').text(pct + '%');
                  loadNext();
              });
        }

        $btn.html('<span class="spinner-border spinner-border-sm"></span> Loading…');
        loadNext();
    });

    // ---- PDF download ----
    $('#btn-download-pdf').on('click', function () {
        var drawIds = getSelectedDrawIds();
        if (!drawIds.length) { toastr.warning('Please select at least one draw.'); return; }

        var printType = $('input[name="print_type"]:checked').val();
        var includeStandings = $('#chk-include-standings').is(':checked') ? 1 : 0;

        var params = new URLSearchParams();
        drawIds.forEach(function (id) { params.append('draw_ids[]', id); });
        params.append('print_type', printType);
        params.append('include_standings', includeStandings);

        window.location.href = "{{ route('headoffice.printDrawsPdf', $event->id) }}?" + params.toString();
    });

});
</script>

@endsection

@section('content')

<!-- Loading Overlay -->
<div id="loading-overlay"
     class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 justify-content-center align-items-center d-none"
     style="z-index: 2000;">
  <div class="text-center">
    <div class="spinner-border text-light mb-3" role="status" style="width: 4rem; height: 4rem;">
      <span class="visually-hidden">Generating fixtures...</span>
    </div>
    <div class="text-white fw-bold fs-5">Generating fixtures… Please wait</div>
  </div>
</div>

<!-- Event Header -->
<div class="card mb-4">
  <div class="card-body py-3">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
      <div>
        <h4 class="mb-1">
          <i class="ti ti-tournament me-1 text-primary"></i>
          {{ $event->name }}
        </h4>
        <span class="text-muted">Individual Event — {{ $event->draws->count() }} {{ Str::plural('draw', $event->draws->count()) }}</span>
      </div>
      <div class="d-flex gap-2 flex-shrink-0">
        @if($event->draws->count())
          <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#printAllDrawsModal">
            <i class="ti ti-printer me-1"></i> Print Draws
          </button>
        @endif
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDrawModal">
          <i class="ti ti-plus me-1"></i> New Draw
        </button>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Sidebar -->
  <div class="col-12 col-lg-3">
    <div class="card sticky-top" style="top: 80px;">
      <div class="card-body p-2">
        @include('backend.adminPage.admin_show.navbar.navbar')
      </div>
    </div>
  </div>

  <!-- Main Content -->
  <div class="col-12 col-lg-9">
    @if(session('success'))
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="ti ti-check me-1"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    @endif

    @forelse($event->draws as $draw)
      @include('backend.draw._includes.draw_tab_interpro')
    @empty
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="ti ti-mood-empty ti-lg text-muted mb-3 d-block" style="font-size: 3rem;"></i>
          <h5 class="text-muted mb-2">No draws created yet</h5>
          <p class="text-muted mb-3">Get started by creating your first draw for this event.</p>
          <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createDrawModal">
            <i class="ti ti-plus me-1"></i> Create First Draw
          </button>
        </div>
      </div>
    @endforelse
  </div>
</div>

<!-- Modal: Create New Draw -->
<!-- Modal: Create New Draw (Individual Event) -->
<div class="modal fade" id="createDrawModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <form id="createDrawForm">
        @csrf

        <div class="modal-header">
          <h5 class="modal-title">Create New Draw</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          {{-- Only draw name required --}}
          <div class="mb-3">
            <label for="drawName" class="form-label fw-bold">Draw Name</label>
            <input type="text" id="drawName" name="drawName" class="form-control"
                   placeholder="e.g. Boys U14 Main Draw" required>
          </div>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>

      </form>
    </div>
  </div>
</div>

<!-- Modal: Print All Draws -->
@if($event->draws->count())
<div class="modal fade" id="printAllDrawsModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-printer me-1"></i> Print Draws</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">

        {{-- Draw selection --}}
        <div class="mb-3">
          <label class="form-label fw-bold">Select Draws</label>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="chk-select-all-draws" checked>
            <label class="form-check-label fw-bold" for="chk-select-all-draws">Select All</label>
          </div>
          <div class="ps-3" id="print-draw-list">
            @foreach($event->draws as $draw)
              <div class="form-check">
                <input class="form-check-input print-draw-chk" type="checkbox"
                       value="{{ $draw->id }}" id="chk-draw-{{ $draw->id }}" checked>
                <label class="form-check-label" for="chk-draw-{{ $draw->id }}">
                  {{ $draw->drawName ?? 'Draw #' . $draw->id }}
                </label>
              </div>
            @endforeach
          </div>
        </div>

        <hr>

        {{-- Print type --}}
        <div class="mb-3">
          <label class="form-label fw-bold">Print Type</label>
          <div class="d-flex flex-column gap-2">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="print_type" value="fixtures" id="pt-fixtures" checked>
              <label class="form-check-label" for="pt-fixtures">
                <i class="ti ti-list-details me-1 text-primary"></i> Order of Play / Fixtures
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="print_type" value="matrix" id="pt-matrix">
              <label class="form-check-label" for="pt-matrix">
                <i class="ti ti-grid-dots me-1 text-success"></i> Round Robin Matrix
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="print_type" value="combined" id="pt-combined">
              <label class="form-check-label" for="pt-combined">
                <i class="ti ti-layout-rows me-1 text-warning"></i> Combined (Matrix + Fixtures)
              </label>
            </div>
          </div>
        </div>

        {{-- Include standings option (shown when matrix or combined selected) --}}
        <div class="form-check mb-3" id="standings-option" style="display:none;">
          <input class="form-check-input" type="checkbox" id="chk-include-standings">
          <label class="form-check-label" for="chk-include-standings">Include Standings</label>
        </div>

        {{-- Progress bar (hidden by default) --}}
        <div id="print-progress" class="d-none mb-3">
          <small id="print-progress-label" class="text-muted d-block mb-1">Loading…</small>
          <div class="progress" style="height: 20px;">
            <div id="print-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                 role="progressbar" style="width: 0%;">0%</div>
          </div>
        </div>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-outline-danger" id="btn-download-pdf">
          <i class="ti ti-file-type-pdf me-1"></i> Download PDF
        </button>
        <button type="button" class="btn btn-primary" id="btn-print-all-draws">
          <i class="ti ti-printer me-1"></i> Print
        </button>
      </div>
    </div>
  </div>
</div>
@endif

@endsection
