
@extends('layouts.backend')

@section('title', 'Admin - ' . $event->name)

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.css') }}">
@endsection
@section('page-style')
<link rel="stylesheet" href="{{ asset('css/head-office-draws.css') }}?v={{ filemtime(public_path('css/head-office-draws.css')) }}">
@endsection
@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/sweetalert2/sweetalert2.js') }}"></script>
@endsection

@section('page-script')

<script>
window.headOfficeDraws = {
    createUrl: @json(route('headoffice.createSingleDraw', $event->id)),
    bulkPublicationUrl: @json(route('backend.event-draws.bulk-publication', $event)),
    drawSettingsUrl: @json(route('backend.event-draws.bulk-publication', $event)),
    venueScheduleUrl: @json(route('backend.event-venue-schedule.index', $event)),
};
</script>
<script src="{{ asset('js/head-office-draws.js') }}?v={{ filemtime(public_path('js/head-office-draws.js')) }}"></script>
<script>
$(document).ready(function () {
    // Toggle select-all checkbox
    $('#chk-select-all-draws').on('change', function () {
        $('.print-draw-chk:not(:disabled)').prop('checked', $(this).is(':checked'));
    });
    $(document).on('change', '.print-draw-chk', function () {
        if ($('input[name="print_type"]:checked').val() === 'bracket' && $(this).is(':checked')) {
            $('.print-draw-chk').not(this).prop('checked', false);
        }
        var total = $('.print-draw-chk:not(:disabled)').length;
        var checked = $('.print-draw-chk:checked').length;
        $('#chk-select-all-draws').prop('checked', total === checked);
    });

    var defaultAccessibilityNote = $('#draw-pack-accessibility-note').text().trim();

    // Show/hide options that apply to the selected print type.
    $('input[name="print_type"]').on('change', function () {
        var val = $(this).val();
        $('#standings-option').toggle(val === 'pack' || val === 'matrix' || val === 'combined');
        var bracketOnly = val === 'bracket';
        if (bracketOnly) {
            $('.print-draw-chk').prop('checked', false).each(function () {
                $(this).prop('disabled', $(this).data('flexible-monrad') !== 1);
            });
        } else {
            $('.print-draw-chk').prop('disabled', false);
        }
        $('#chk-select-all-draws').prop({ checked: false, disabled: bracketOnly });
        $('#monrad-bracket-help').toggleClass('d-none', !bracketOnly);
        $('#btn-download-pdf').toggleClass('d-none', bracketOnly);
        $('#draw-pack-accessibility-note').text(bracketOnly
            ? 'The graphical Monrad board opens in a new tab and starts the print dialog. Choose Save as PDF there if you need a file.'
            : defaultAccessibilityNote);
        $('#btn-print-all-draws').html(bracketOnly
            ? '<i class="ti ti-printer me-1"></i> Print Monrad bracket'
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

    function openSelectedMonradBracket() {
        var selected = $('.print-draw-chk:checked');
        if (selected.length !== 1) {
            toastr.warning('Select one Flexible Monrad draw to print its graphical bracket.');
            return false;
        }
        var url = selected.first().data('monrad-print-url');
        if (!url) {
            toastr.warning('The selected draw is not a Flexible Monrad draw.');
            return false;
        }
        var printWindow = window.open(url + (url.includes('?') ? '&' : '?') + 'print=draw#matrix', '_blank');
        if (!printWindow) {
            toastr.error('Popup blocked - please allow popups for this site.');
            return false;
        }
        printWindow.opener = null;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('printAllDrawsModal')).hide();
        return true;
    }

    // ---- Sequential per-draw loader (browser print) ----
    $('#btn-print-all-draws').on('click', function () {
        var drawIds = getSelectedDrawIds();
        if (!drawIds.length) { toastr.warning('Please select at least one draw.'); return; }

        var printType = $('input[name="print_type"]:checked').val();
        var includeStandings = $('#chk-include-standings').is(':checked') ? 1 : 0;
        if (printType === 'bracket') {
            openSelectedMonradBracket();
            return;
        }
        if (printType === 'pack' || printType === 'venue') {
            var packParams = new URLSearchParams();
            drawIds.forEach(function (id) { packParams.append('draw_ids[]', id); });
            packParams.append('include_standings', includeStandings);
            packParams.append('print_type', printType);
            window.open(@json(route('headoffice.drawPack', $event)) + '?' + packParams.toString(), '_blank', 'noopener');
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

        var eventName = @json($event->name);
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

            $.get("{{ route('headoffice.printDrawsData', $event->id) }}", { draw_id: drawId })
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
        var includeStandings = $('#chk-include-standings').is(':checked') ? 1 : 0;

        if (printType === 'bracket') {
            openSelectedMonradBracket();
            return;
        }

        var params = new URLSearchParams();
        drawIds.forEach(function (id) { params.append('draw_ids[]', id); });
        params.append('print_type', printType);
        params.append('include_standings', includeStandings);

        if (printType === 'pack' || printType === 'venue') {
            params.append('download', 1);
            window.location.href = @json(route('headoffice.drawPack', $event)) + '?' + params.toString();
            return;
        }

        window.location.href = "{{ route('headoffice.printDrawsPdf', $event->id) }}?" + params.toString();
    });

});
</script>

@endsection

@section('content')

@include('backend.headOffice.partials.individual-draw-overview')

@if($event->draws->isNotEmpty())
@php
  $eventScheduleVisibilities = $event->draws
    ->map(fn ($draw) => $draw->settings?->showsFirstMatchOnly()
      ? \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH
      : \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FULL)
    ->unique();
  $eventScheduleVisibility = $eventScheduleVisibilities->count() === 1
    ? $eventScheduleVisibilities->first()
    : 'mixed';
  $eventSetFormats = $event->draws
    ->map(fn ($draw) => (int) ($draw->settings?->num_sets ?: 3))
    ->unique();
  $eventSetFormat = $eventSetFormats->count() === 1 ? $eventSetFormats->first() : 'mixed';
  $supportedEventSetFormats = [1, 2, 3, 5];
  $eventScoringSettingsLocked = $event->draws->contains(fn ($draw) => (bool) $draw->locked)
    || $event->hasRecordedResults();
@endphp
<div class="modal fade" id="drawSettingsModal" tabindex="-1" aria-labelledby="drawSettingsModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="drawSettingsForm">
        @csrf
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
            <p class="text-muted small">Apply the existing Sets per Match setting to every draw. Individual draw pages use this same setting.</p>
            @if($eventScoringSettingsLocked)
              <div class="alert alert-warning py-2 small" role="status">Match format is locked because a draw is locked or the tournament already has a recorded result.</div>
            @elseif($event->draws->contains(fn ($draw) => (bool) $draw->published))
              <div class="alert alert-info py-2 small" role="status">This tournament is published, but match format can still be changed until the first result is recorded.</div>
            @endif
            <label class="form-label fw-semibold" for="event-num-sets">Sets per match</label>
            <select class="form-select" id="event-num-sets" name="num_sets" @disabled($eventScoringSettingsLocked)>
              @if($eventSetFormat === 'mixed' || ! in_array($eventSetFormat, $supportedEventSetFormats, true))
                <option value="" selected>Keep current per-draw formats</option>
              @endif
              @foreach($supportedEventSetFormats as $sets)
                <option value="{{ $sets }}" @selected($eventSetFormat === $sets)>Best of {{ $sets }}</option>
              @endforeach
            </select>
            <div class="form-text">This changes scoring across all {{ $event->draws->count() }} {{ Str::plural('draw', $event->draws->count()) }} for the day.</div>
          </fieldset>

          <fieldset>
            <legend class="h6 mb-1">Public match time display</legend>
            <p class="text-muted small">Choose what players and parents see across every draw in this event.</p>
          @if($eventScheduleVisibility === 'mixed')
            <div class="alert alert-info py-2 small" role="status">Draws currently use different display settings. Saving will make them consistent.</div>
          @endif
          <div class="form-check border rounded p-3 ps-5 mb-3">
            <input class="form-check-input" type="radio" name="schedule_visibility"
                   id="event-schedule-first-match" value="{{ \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH }}"
                   @checked($eventScheduleVisibility === \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH) required>
            <label class="form-check-label fw-semibold" for="event-schedule-first-match">Show each player’s first match time only</label>
            <div class="form-text">Only the earliest upcoming assigned match is shown for each player. Their next time appears after that match is completed.</div>
          </div>
          <div class="form-check border rounded p-3 ps-5">
            <input class="form-check-input" type="radio" name="schedule_visibility"
                   id="event-schedule-full" value="{{ \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FULL }}"
                   @checked($eventScheduleVisibility === \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FULL) required>
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
@endif

<!-- Modal: Create New Draw -->
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

        {{-- Draw selection --}}
        <fieldset class="mb-3">
          <legend class="form-label fw-bold">Select draws</legend>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="chk-select-all-draws" checked>
            <label class="form-check-label fw-bold" for="chk-select-all-draws">Select All</label>
          </div>
          <div class="ps-3" id="print-draw-list">
            @foreach($event->draws as $draw)
              <div class="form-check">
                <input class="form-check-input print-draw-chk" type="checkbox"
                       value="{{ $draw->id }}" id="chk-draw-{{ $draw->id }}" checked
                       data-flexible-monrad="{{ $draw->is_flexible ? 1 : 0 }}"
                       data-monrad-print-url="{{ $draw->is_flexible ? route('backend.draw.roundrobin.show', $draw) : '' }}">
                <label class="form-check-label" for="chk-draw-{{ $draw->id }}">
                  {{ $draw->drawName ?? 'Draw #' . $draw->id }}
                </label>
              </div>
            @endforeach
          </div>
        </fieldset>

        <hr>

        {{-- Print type --}}
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

        <p class="small text-muted d-none" id="monrad-bracket-help">Select one Flexible Monrad draw above. Non-Monrad draws are disabled for this print type.</p>

        {{-- Include standings option (shown when matrix or combined selected) --}}
        <div class="form-check mb-3" id="standings-option">
          <input class="form-check-input" type="checkbox" id="chk-include-standings" checked>
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
@endif

@endsection
