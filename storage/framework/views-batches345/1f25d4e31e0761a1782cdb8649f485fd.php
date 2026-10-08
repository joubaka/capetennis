


<?php $__env->startSection('title', 'Admin - ' . $event->name); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.css')); ?>">
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/head-office-draws.css')); ?>?v=<?php echo e(filemtime(public_path('css/head-office-draws.css'))); ?>">
<?php $__env->stopSection(); ?>
<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>

<script>
window.headOfficeDraws = {
    createUrl: <?php echo json_encode(route('headoffice.createSingleDraw', $event->id), 512) ?>,
    bulkPublicationUrl: <?php echo json_encode(route('backend.event-draws.bulk-publication', $event), 512) ?>,
    drawSettingsUrl: <?php echo json_encode(route('backend.event-draws.bulk-publication', $event), 512) ?>,
    venueScheduleUrl: <?php echo json_encode(route('backend.event-venue-schedule.index', $event), 512) ?>,
};
</script>
<script src="<?php echo e(asset('js/head-office-draws.js')); ?>?v=<?php echo e(filemtime(public_path('js/head-office-draws.js'))); ?>"></script>
<script>
$(document).ready(function () {
    // Keep this draw-specific action with the server-rendered button so a
    // partially refreshed public asset cannot leave a visible dead control.
    function escapeProgressReviewHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function progressGroupHtml(group, checkboxId) {
        var rows = group.standings.map(function (row) {
            var tiebreak = row.tiebreak
                ? '<span class="badge bg-label-info">' + escapeProgressReviewHtml(row.tiebreak) + '</span>'
                : '—';

            return '<tr>'
                + '<td class="text-center fw-bold">' + row.position + '</td>'
                + '<td class="text-start fw-semibold">' + escapeProgressReviewHtml(row.player) + '</td>'
                + '<td class="text-center">' + row.played + '</td>'
                + '<td class="text-center">' + row.wins + '</td>'
                + '<td class="text-center">' + row.losses + '</td>'
                + '<td class="text-center text-nowrap">' + row.sets_won + '–' + row.sets_lost + '</td>'
                + '<td class="text-center text-nowrap">' + row.games_won + '–' + row.games_lost + '</td>'
                + '<td class="text-center">' + tiebreak + '</td>'
                + '</tr>';
        }).join('');

        return '<p class="text-muted mb-3">Check the final order carefully. These positions will be used to place players into the playoffs.</p>'
            + '<div class="table-responsive border rounded mb-3">'
            + '<table class="table table-sm table-striped align-middle mb-0">'
            + '<thead><tr><th class="text-center">Pos</th><th class="text-start">Player / team</th><th class="text-center">P</th>'
            + '<th class="text-center">W</th><th class="text-center">L</th><th class="text-center">Sets</th>'
            + '<th class="text-center">Games</th><th class="text-center">Tie-break</th></tr></thead>'
            + '<tbody>' + rows + '</tbody></table></div>'
            + '<div class="form-check text-start border rounded p-3 ps-5 bg-light">'
            + '<input class="form-check-input" type="checkbox" id="' + checkboxId + '">'
            + '<label class="form-check-label fw-semibold" for="' + checkboxId + '">I confirm that the standings and final order for '
            + escapeProgressReviewHtml(group.name) + ' are correct.</label></div>';
    }

    $(document).on('click', '.progress-draw', async function () {
        var $button = $(this);
        $button.prop('disabled', true).attr('aria-busy', 'true');

        Swal.fire({
            title: 'Loading final standings…',
            allowOutsideClick: false,
            allowEscapeKey: false,
            didOpen: function () { Swal.showLoading(); }
        });

        try {
            var response = await $.get($button.data('review-url'));
            var review = response.review;
            var groups = review.groups || [];

            if (!groups.length) {
                throw { responseJSON: { message: 'No round-robin groups are available to review.' } };
            }

            var confirmedGroupIds = [];
            var step = 0;
            while (step < groups.length) {
                var group = groups[step];
                var checkboxId = 'confirm-progress-group-' + group.id;
                var isLast = step === groups.length - 1;
                var result = await Swal.fire({
                    title: 'Confirm ' + group.name,
                    html: progressGroupHtml(group, checkboxId),
                    icon: 'question',
                    width: 960,
                    progressSteps: groups.map(function (_, index) { return String(index + 1); }),
                    currentProgressStep: step,
                    showCancelButton: true,
                    showDenyButton: step > 0,
                    denyButtonText: 'Back',
                    cancelButtonText: 'Cancel',
                    confirmButtonText: isLast ? 'Confirm & progress to playoffs' : 'Confirm & next group',
                    focusConfirm: false,
                    preConfirm: function () {
                        if (!document.getElementById(checkboxId).checked) {
                            Swal.showValidationMessage('Confirm that this group is correct before continuing.');
                            return false;
                        }
                        return true;
                    }
                });

                if (result.isDenied) {
                    step--;
                    confirmedGroupIds.pop();
                    continue;
                }
                if (!result.isConfirmed) {
                    return;
                }

                confirmedGroupIds.push(group.id);
                step++;
            }

            var progressResponse = await $.post($button.data('url'), {
                _token: $('meta[name="csrf-token"]').attr('content')
                , confirmed_group_ids: confirmedGroupIds
                , standings_snapshot: review.snapshot
            });

            await Swal.fire({
                title: 'Draw progressed',
                text: progressResponse.message,
                icon: 'success',
                confirmButtonText: 'Refresh draws'
            });
            window.location.reload();
        } catch (xhr) {
            Swal.close();
            toastr.error(xhr.responseJSON?.message || 'Could not progress ' + $button.data('draw-name') + '.');
        } finally {
            $button.prop('disabled', false).removeAttr('aria-busy');
        }
    });

    // Toggle select-all checkbox
    $('#chk-select-all-draws').on('change', function () {
        $('.print-draw-chk:not(:disabled)').prop('checked', $(this).is(':checked'));
    });
    $(document).on('change', '.print-draw-chk', function () {
        var total = $('.print-draw-chk:not(:disabled)').length;
        var checked = $('.print-draw-chk:checked').length;
        $('#chk-select-all-draws').prop('checked', total > 0 && total === checked);
    });

    var defaultAccessibilityNote = $('#draw-pack-accessibility-note').text().trim();

    // Show/hide options that apply to the selected print type.
    $('input[name="print_type"]').on('change', function () {
        var val = $(this).val();
        $('#standings-option').toggle(val === 'pack' || val === 'matrix' || val === 'combined');
        var bracketOnly = val === 'bracket';
        if (bracketOnly) {
            $('.print-draw-chk').each(function () {
                var isMonrad = $(this).data('flexible-monrad') === 1;
                $(this).prop('disabled', !isMonrad);
                if (!isMonrad) $(this).prop('checked', false);
            });
        } else {
            $('.print-draw-chk').prop('disabled', false);
        }
        var eligible = $('.print-draw-chk:not(:disabled)').length;
        var selected = $('.print-draw-chk:checked').length;
        $('#chk-select-all-draws').prop({ checked: eligible > 0 && eligible === selected, disabled: false });
        $('#monrad-bracket-help').toggleClass('d-none', !bracketOnly);
        $('#btn-download-pdf').toggleClass('d-none', bracketOnly);
        $('#draw-pack-accessibility-note').text(bracketOnly
            ? 'Downloads one PDF immediately, with one fitted landscape page for each selected Flexible Monrad draw.'
            : defaultAccessibilityNote);
        $('#btn-print-all-draws').html(bracketOnly
            ? '<i class="ti ti-file-type-pdf me-1"></i> Download bracket PDF'
            : '<i class="ti ti-printer me-1"></i> Print pack');
    });

    function escapePrintHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[character];
        });
    }

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
            html += '<h3 style="margin-top:18px;">' + escapePrintHtml(stageLabels[stage] || stage) + '</h3>';
            html += '<table><thead><tr><th>M#</th><th>Player 1</th><th class="text-center">vs</th><th>Player 2</th><th class="text-center">Rd</th><th class="text-center">Score</th></tr></thead><tbody>';
            grouped[stage].forEach(function (fx) {
                var w1 = fx.winner == fx.r1_id ? ' class="fw-bold text-success"' : '';
                var w2 = fx.winner == fx.r2_id ? ' class="fw-bold text-success"' : '';
                var typeLabel = fx.playoff_type ? ' <small style="color:#666;">(' + escapePrintHtml(fx.playoff_type) + ')</small>' : '';
                var home = escapePrintHtml(fx.home || '---');
                var away = escapePrintHtml(fx.away || '---');
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
                html += '<td class="text-center">' + escapePrintHtml(fx.score || '') + '</td>';
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
            html += '<h3>Box ' + escapePrintHtml(group.name) + '</h3>';
            html += '<table class="rr-matrix-table" style="width:' + tableW + 'px;"><thead><tr><th style="width:' + nameW + 'px;"></th>';
            players.forEach(function (p) { html += '<th style="width:' + cw + '">' + escapePrintHtml(p.name) + '</th>'; });
            html += '<th style="width:40px; background:#198754; color:#fff; font-weight:800;">W</th></tr></thead><tbody>';

            players.forEach(function (rowP) {
                html += '<tr><th>' + escapePrintHtml(rowP.name) + '</th>';
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
                html += '<h3>Box ' + escapePrintHtml(group.name) + ' — Standings</h3>';
                html += '<table class="standings-table"><thead><tr><th>#</th><th>Player</th><th>W</th><th>L</th><th>Sets +/-</th></tr></thead><tbody>';
                rows.forEach(function (r, i) { html += '<tr><td>'+(i+1)+'</td><td>'+escapePrintHtml(r.player)+'</td><td>'+r.wins+'</td><td>'+r.losses+'</td><td>'+(r.sets_won-r.sets_lost)+'</td></tr>'; });
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

    function downloadSelectedMonradBrackets() {
        var selected = $('.print-draw-chk:checked');
        if (!selected.length) {
            toastr.warning('Select at least one Flexible Monrad draw for the bracket PDF.');
            return false;
        }
        var params = new URLSearchParams({ print_type: 'bracket', download: '1' });
        selected.each(function () { params.append('draw_ids[]', $(this).val()); });
        window.location.href = <?php echo json_encode(route('headoffice.drawPack', $event), 512) ?> + '?' + params.toString();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('printAllDrawsModal')).hide();
        return true;
    }

    // ---- Sequential per-draw loader (browser print) ----
    $('#btn-print-all-draws').on('click', function () {
        var drawIds = getSelectedDrawIds();
        if (!drawIds.length) { toastr.warning('Please select at least one draw.'); return; }

        var printType = $('input[name="print_type"]:checked').val();
        if (printType === 'fixtures') printType = 'venue';
        var includeStandings = $('#chk-include-standings').is(':checked') ? 1 : 0;
        if (printType === 'bracket') {
            downloadSelectedMonradBrackets();
            return;
        }
        if (printType === 'pack' || printType === 'venue') {
            var packParams = new URLSearchParams();
            drawIds.forEach(function (id) { packParams.append('draw_ids[]', id); });
            packParams.append('include_standings', includeStandings);
            packParams.append('print_type', printType);
            if (printType === 'venue') appendVenuePrintFilters(packParams);
            window.open(<?php echo json_encode(route('headoffice.drawPack', $event), 512) ?> + '?' + packParams.toString(), '_blank', 'noopener');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('printAllDrawsModal')).hide();
            return;
        }

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

        var includeStandings = $('#chk-include-standings').is(':checked');
        var $btn = $(this).prop('disabled', true);
        var $progress = $('#print-progress');
        var $bar = $('#print-progress-bar');
        var $label = $('#print-progress-label');

        $progress.removeClass('d-none');
        $bar.css('width', '0%');

        var eventName = <?php echo json_encode($event->name, 15, 512) ?>;
        var fullHtml = '<h1>' + escapePrintHtml(eventName) + '</h1>';
        var loaded = 0;
        var total = drawIds.length;

        function loadNext() {
            if (loaded >= total) {
                // Reset modal UI
                $btn.prop('disabled', false).html('<i class="ti ti-printer me-1"></i> Print');
                $progress.addClass('d-none');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('printAllDrawsModal')).hide();

                // Write final content into the already-open window, then print
                var typeLabels = { fixtures: 'Fixtures', matrix: 'Matrix', combined: 'Combined' };
                var title = eventName + ' — ' + (typeLabels[printType] || 'Print');
                printWin.document.open();
                printWin.document.write('<!DOCTYPE html><html><head><title>' + escapePrintHtml(title) + '</title>' + printStyles + '</head><body>' + fullHtml + '</body></html>');
                printWin.document.close();
                printWin.focus();
                setTimeout(function () { printWin.print(); }, 300);
                return;
            }

            var drawId = drawIds[loaded];
            $label.text('Loading draw ' + (loaded + 1) + ' of ' + total + '…');

            $.get("<?php echo e(route('headoffice.printDrawsData', $event->id)); ?>", { draw_id: drawId })
              .done(function (resp) {
                  var drawData = resp.draw;
                  if (drawData) {
                      if (loaded > 0) fullHtml += '<div class="page-break"></div>';
                      fullHtml += '<h2>' + escapePrintHtml(drawData.name) + '</h2>';
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
        if (printType === 'fixtures') printType = 'venue';
        var includeStandings = $('#chk-include-standings').is(':checked') ? 1 : 0;

        if (printType === 'bracket') {
            downloadSelectedMonradBrackets();
            return;
        }

        var params = new URLSearchParams();
        drawIds.forEach(function (id) { params.append('draw_ids[]', id); });
        params.append('print_type', printType);
        params.append('include_standings', includeStandings);

        if (printType === 'pack' || printType === 'venue') {
            if (printType === 'venue') appendVenuePrintFilters(params);
            params.append('download', 1);
            window.location.href = <?php echo json_encode(route('headoffice.drawPack', $event), 512) ?> + '?' + params.toString();
            return;
        }

        window.location.href = "<?php echo e(route('headoffice.printDrawsPdf', $event->id)); ?>?" + params.toString();
    });

    function appendVenuePrintFilters(params) {
        params.append('schedule_source', $('#venue-print-source').val());
        if ($('#venue-print-date').val()) params.append('date', $('#venue-print-date').val());
        if ($('#venue-print-venue').val()) params.append('venue_id', $('#venue-print-venue').val());
    }
});
</script>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<?php echo $__env->make('backend.headOffice.partials.individual-draw-overview', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->draws->isNotEmpty()): ?>
<?php
  $eventScheduleVisibilities = $event->draws
    ->map(fn ($draw) => $draw->settings?->showsFirstMatchOnly()
      ? \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH
      : \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FULL)
    ->unique();
  $eventScheduleVisibility = $eventScheduleVisibilities->count() === 1
    ? $eventScheduleVisibilities->first()
    : 'mixed';
  $eventSetFormats = $event->draws
    ->map(fn ($draw) => $draw->settings?->score_format ?: 'legacy')
    ->unique();
  $eventSetFormat = $eventSetFormats->count() === 1 ? $eventSetFormats->first() : 'mixed';
  $supportedEventSetFormats = \App\Domain\Draws\Services\TennisScoreFormat::catalog();
  $eventScoringSettingsLocked = $event->draws->contains(fn ($draw) => (bool) $draw->locked)
    || $event->hasRecordedResults();
?>
<div class="modal fade" id="drawSettingsModal" tabindex="-1" aria-labelledby="drawSettingsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="drawSettingsForm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="operation" value="schedule_visibility">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="drawSettingsModalLabel">Draw settings</h5>
            <p class="text-muted small mb-0 mt-1">Set the shared match rules and public display for this tournament day.</p>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <fieldset class="mb-4">
            <legend class="h6 mb-1">Match scoring</legend>
            <p class="text-muted small">Choose one enforceable scoring preset for every draw. Score entry, validation and printed format guidance use the same selection.</p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventScoringSettingsLocked): ?>
              <div class="alert alert-warning py-2 small" role="status">Match format is locked because a draw is locked or the tournament already has a recorded result.</div>
            <?php elseif($event->draws->contains(fn ($draw) => (bool) $draw->published)): ?>
              <div class="alert alert-info py-2 small" role="status">This tournament is published, but match format can still be changed until the first result is recorded.</div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <label class="form-label fw-semibold" for="event-score-format">Match scoring format</label>
            <select class="form-select" id="event-score-format" name="score_format" <?php if($eventScoringSettingsLocked): echo 'disabled'; endif; ?>>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventSetFormat === 'mixed' || $eventSetFormat === 'legacy' || ! isset($supportedEventSetFormats[$eventSetFormat])): ?>
                <option value="" selected>Keep current per-draw formats</option>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Domain\Draws\Services\TennisScoreFormat::groupedCatalog(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupLabel => $formats): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <optgroup label="<?php echo e($groupLabel); ?>">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $formats; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $format): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($key); ?>" <?php if($eventSetFormat === $key): echo 'selected'; endif; ?>><?php echo e($format['label']); ?></option>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </optgroup>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </select>
            <div class="form-text">This changes scoring across all <?php echo e($event->draws->count()); ?> <?php echo e(Str::plural('draw', $event->draws->count())); ?> for the day.</div>
            <div class="form-text mt-2">Use a Custom scoring preset when non-standard completed set scores must be accepted.</div>
          </fieldset>

          <fieldset>
            <legend class="h6 mb-1">Public match time display</legend>
            <p class="text-muted small">Choose what players and parents see across every draw in this event.</p>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventScheduleVisibility === 'mixed'): ?>
            <div class="alert alert-info py-2 small" role="status">Draws currently use different display settings. Saving will make them consistent.</div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <div class="form-check border rounded p-3 ps-5 mb-3">
            <input class="form-check-input" type="radio" name="schedule_visibility"
                   id="event-schedule-first-match" value="<?php echo e(\App\Models\DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH); ?>"
                   <?php if($eventScheduleVisibility === \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH): echo 'checked'; endif; ?> required>
            <label class="form-check-label fw-semibold" for="event-schedule-first-match">Show each player’s first match time only</label>
            <div class="form-text">Only the earliest upcoming assigned match is shown for each player. Their next time appears after that match is completed.</div>
          </div>
          <div class="form-check border rounded p-3 ps-5">
            <input class="form-check-input" type="radio" name="schedule_visibility"
                   id="event-schedule-full" value="<?php echo e(\App\Models\DrawSetting::SCHEDULE_VISIBILITY_FULL); ?>"
                   <?php if($eventScheduleVisibility === \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FULL): echo 'checked'; endif; ?> required>
            <label class="form-check-label fw-semibold" for="event-schedule-full">Show all match times</label>
            <div class="form-text">Every published time, venue and court is shown on the public draw tables.</div>
          </div>
          </fieldset>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save draw settings</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<!-- Modal: Create New Draw -->
<div class="modal fade" id="createDrawModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <form id="createDrawForm">
        <?php echo csrf_field(); ?>

        <div class="modal-header">
          <h5 class="modal-title">Create New Draw</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">

          <ol class="small text-muted ps-3 mb-4" aria-label="Draw setup steps">
            <li><strong>Name and category</strong></li>
            <li>Choose the draw format</li>
            <li>Place players and generate fixtures</li>
          </ol>

          <div class="mb-3">
            <label for="drawName" class="form-label fw-bold">Draw Name</label>
            <input type="text" id="drawName" name="drawName" class="form-control"
                   placeholder="e.g. Boys U14 Main Draw" required>
            <div class="form-text">Use a name parents and players will recognise when the draw is published.</div>
          </div>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->categoryEvents->isNotEmpty()): ?>
            <div class="mb-3">
              <label for="drawCategory" class="form-label fw-bold">Player category</label>
              <select id="drawCategory" name="category_event_id" class="form-select" required>
                <option value="">Choose a category</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->categoryEvents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <?php ($categoryName = $categoryEvent->category?->name ?? 'Category #'.$categoryEvent->id); ?>
                  <option value="<?php echo e($categoryEvent->id); ?>" data-suggested-name="<?php echo e($categoryName); ?> Draw">
                    <?php echo e($categoryName); ?> · <?php echo e($categoryEvent->eligible_draw_entries_count); ?> paid active <?php echo e(Str::plural('player', $categoryEvent->eligible_draw_entries_count)); ?>

                  </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </select>
              <div class="form-text">Player placement starts with paid, active entries in this category.</div>
            </div>
            <div class="form-check border rounded p-3 ps-5 mb-0">
              <input class="form-check-input" type="checkbox" id="eventWideDraw">
              <label class="form-check-label" for="eventWideDraw">
                <strong>Combine players from different categories</strong>
                <span class="d-block small text-muted">Advanced: use this only when the competition intentionally mixes categories.</span>
              </label>
            </div>
          <?php else: ?>
            <div class="alert alert-warning mb-0" role="alert">
              No player categories are configured yet. You can create the draw now, but add event categories and entries before placing players.
            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create &amp; choose format →</button>
        </div>

      </form>
    </div>
  </div>
</div>

<!-- Modal: Print All Draws -->
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->draws->count()): ?>
<div class="modal fade" id="printAllDrawsModal" tabindex="-1" aria-labelledby="draw-pack-modal-title" aria-hidden="true">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title" id="draw-pack-modal-title"><i class="ti ti-printer me-1"></i> Draw Pack</h5>
          <p class="small text-muted mb-0 mt-1">One paper pack for draws, fixtures and the master schedule.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">

        
        <fieldset class="mb-3">
          <legend class="form-label fw-bold">Select draws</legend>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="chk-select-all-draws" checked>
            <label class="form-check-label fw-bold" for="chk-select-all-draws">Select All</label>
          </div>
          <div class="ps-3" id="print-draw-list">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <div class="form-check">
                <input class="form-check-input print-draw-chk" type="checkbox"
                       value="<?php echo e($draw->id); ?>" id="chk-draw-<?php echo e($draw->id); ?>" checked
                       data-flexible-monrad="<?php echo e($draw->is_flexible ? 1 : 0); ?>">
                <label class="form-check-label" for="chk-draw-<?php echo e($draw->id); ?>">
                  <?php echo e($draw->drawName ?? 'Draw #' . $draw->id); ?>

                </label>
              </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
        </fieldset>

        <hr>

        
        <fieldset class="mb-3">
          <legend class="form-label fw-bold">Print type</legend>
          <div class="d-flex flex-column gap-2">
            <div class="form-check">
              <input class="form-check-input" type="radio" name="print_type" value="pack" id="pt-pack" checked>
              <label class="form-check-label" for="pt-pack">
                <i class="ti ti-files me-1 text-primary"></i> <strong>Complete Draw Pack</strong>
                <span class="d-block small text-muted">Cover, publication checks, master order of play, rules, matrices, pathway boards, fixtures, courts and result space</span>
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="print_type" value="fixtures" id="pt-fixtures">
              <label class="form-check-label" for="pt-fixtures">
                <i class="ti ti-list-details me-1 text-primary"></i> Order of Play / Fixtures
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="print_type" value="venue" id="pt-venue">
              <label class="form-check-label" for="pt-venue">
                <i class="ti ti-building-stadium me-1 text-primary"></i> <strong>Per-Venue Order of Play</strong>
                <span class="d-block small text-muted">A separate operational schedule for each venue across all selected draws</span>
              </label>
            </div>
            <div class="form-check">
              <input class="form-check-input" type="radio" name="print_type" value="bracket" id="pt-bracket">
              <label class="form-check-label" for="pt-bracket">
                <i class="ti ti-tournament me-1 text-primary"></i> <strong>Flexible Monrad Bracket Only</strong>
                <span class="d-block small text-muted">Print the graphical bracket exactly as shown in the Monrad workspace</span>
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
        </fieldset>

        <p class="small text-muted d-none" id="monrad-bracket-help">Select one or more Flexible Monrad draws above. Non-Monrad draws are disabled. Each selected draw is fitted to one PDF page.</p>
        <fieldset class="mb-3">
          <legend class="form-label fw-bold">Venue order of play filters</legend>
          <label class="form-label" for="venue-print-date">Day (blank for all days)</label>
          <input type="date" class="form-control mb-2" id="venue-print-date">
          <label class="form-label" for="venue-print-venue">Venue</label>
          <select class="form-select mb-2" id="venue-print-venue">
            <option value="">All venues</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $printVenue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($printVenue->id); ?>"><?php echo e($printVenue->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
          <label class="form-label" for="venue-print-source">Schedule</label>
          <select class="form-select" id="venue-print-source">
            <option value="published">Published — same as public order of play</option>
            <option value="working">Working preview — includes unpublished changes</option>
          </select>
          <p class="small text-muted mt-2">Select draws above to print one age group or category. These filters apply to Per-Venue Order of Play.</p>
        </fieldset>

        
        <div class="form-check mb-3" id="standings-option">
          <input class="form-check-input" type="checkbox" id="chk-include-standings" checked>
          <label class="form-check-label" for="chk-include-standings">Include Standings</label>
        </div>

        
        <div id="print-progress" class="d-none mb-3">
          <small id="print-progress-label" class="text-muted d-block mb-1">Loading…</small>
          <div class="progress" style="height: 20px;">
            <div id="print-progress-bar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                 role="progressbar" style="width: 0%;">0%</div>
          </div>
        </div>

        <p class="small text-muted mb-0" id="draw-pack-accessibility-note">
          For an assistive-technology-friendly version, choose Print pack. It opens the same content as semantic HTML with labelled tables; the downloaded PDF is optimised for paper.
        </p>

      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-outline-secondary" id="btn-download-pdf" aria-describedby="draw-pack-accessibility-note">
          <i class="ti ti-file-type-pdf me-1"></i> Download pack
        </button>
        <button type="button" class="btn btn-primary" id="btn-print-all-draws" aria-describedby="draw-pack-accessibility-note">
          <i class="ti ti-printer me-1"></i> Print pack
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\headOffice\individual-event-show.blade.php ENDPATH**/ ?>