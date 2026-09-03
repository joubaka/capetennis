<script>
// ============================================================
// PRINT TAB HANDLERS
// ============================================================
(function($) {
  const drawName = @json($draw->drawName ?? 'Draw');
  const printStyles = `
    <style>
      * { margin: 0; padding: 0; box-sizing: border-box; }
      body { font-family: Arial, sans-serif; padding: 15px; color: #000; font-size: 18px; }
      h1 { font-size: 30px; margin-bottom: 8px; }
      h2 { font-size: 22px; color: #333; margin-bottom: 18px; }
      table { width: 100%; border-collapse: collapse; margin-bottom: 24px; font-size: 16px; }
      th, td { border: 1px solid #999; padding: 10px 8px; text-align: left; }
      th { background: #333; color: #fff; font-weight: 700; font-size: 15px; }
      .text-center { text-align: center; }
      .fw-bold { font-weight: bold; }
      .text-success { color: #198754; }
      .text-muted { color: #888; }
      .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 13px; font-weight: 700; }
      .bg-dark { background: #000; color: #fff; }
      .bg-primary { background: #0d6efd; color: #fff; }
      .bg-secondary { background: #6c757d; color: #fff; }
      svg { max-width: 100%; }
      .page-break { page-break-before: always; }
      .rr-matrix-table { border-collapse: collapse; table-layout: fixed; }
      .rr-matrix-table td, .rr-matrix-table th { border: 1px solid #999; padding: 10px 6px; text-align: center; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
      .rr-matrix-table thead th { background: #fff; color: #0a3566; border: 2px solid #0a3566; font-weight: 700; padding: 10px 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 14px; }
      .rr-matrix-table tbody th { background: #fff; color: #0b722e; border: 2px solid #0b722e; font-weight: 700; white-space: nowrap; text-align: left; padding: 10px 8px; overflow: hidden; text-overflow: ellipsis; font-size: 14px; }
      .rr-matrix-table .rr-win { color: #00a859; font-weight: bold; }
      .rr-matrix-table .rr-loss { color: #d32f2f; font-weight: bold; }
      .rr-matrix-table td.bg-diagonal, .rr-matrix-table td.bg-light { background: #000 !important; border-color: #333; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; color-adjust: exact !important; }
      .standings-table { width: auto; margin-top: 12px; }
      .standings-table th { border: 2px solid #222; color: #222; font-weight: 700; font-size: 15px; }
      .standings-table td { font-size: 15px; }
      .bracket-print-wrap svg { width: 100% !important; }
      @media print {
        body { padding: 5px; }
        @page { margin: 8mm; }
        *:last-child { margin-bottom: 0 !important; padding-bottom: 0 !important; }
      }
    </style>`;

  const landscapeStyles = `
    <style>
      @page { size: landscape; margin: 5mm; }
      html, body { margin: 0 !important; padding: 0 !important; width: 100%; height: 100%; overflow: visible; }
      .bracket-header { display: flex; gap: 12px; align-items: baseline; margin-bottom: 2px; }
      .bracket-header h1 { font-size: 13px; margin: 0; }
      .bracket-header h2 { font-size: 10px; margin: 0; color: #555; }
      .bracket-print-wrap { width: 100%; height: calc(100vh - 20px); overflow: visible; }
      .bracket-print-wrap svg {
        display: block;
        width: 100% !important;
        height: 100% !important;
        object-fit: contain;
      }
      @media print {
        html, body { height: 100%; overflow: visible !important; }
        .bracket-print-wrap { width: 100%; height: calc(100vh - 15px); margin: 0; padding: 0; page-break-inside: avoid; break-inside: avoid; }
        .bracket-print-wrap svg {
          width: 100% !important;
          height: 100% !important;
          max-width: 100%;
          max-height: 100%;
          page-break-inside: avoid;
        }
        *:last-child { margin-bottom: 0 !important; }
      }
    </style>`;

  function openPrintWindow(title, bodyHtml, landscape) {
    var styles = printStyles + (landscape ? landscapeStyles : '');
    const w = window.open('', '_blank');
    w.document.write('<!DOCTYPE html><html><head><title>' + title + '</title>' + styles + '</head><body>' + bodyHtml + '</body></html>');
    w.document.close();
    // Remove fixed width/height from SVGs so viewBox controls full-page scaling
    if (landscape) {
      var svgs = w.document.querySelectorAll('.bracket-print-wrap svg');
      svgs.forEach(function(svg) {
        svg.removeAttribute('width');
        svg.removeAttribute('height');
        svg.style.width = '100%';
        svg.style.height = '100%';
      });
    }
    w.onload = function() { w.print(); };
  }

  // ---- FEEDER LABEL HELPER ----
  function feederLabel(fx, slot) {
    // slot: 'home' or 'away'
    if (fx.stage === 'RR') return '';
    var wf = fx.winner_feeders || [];
    var lf = fx.loser_feeders || [];
    var idx = (slot === 'home') ? 0 : 1;
    var playerName = (slot === 'home') ? fx.home : fx.away;

    // If player is already known, no feeder label needed
    if (playerName && playerName !== 'TBD' && playerName !== '---') return '';

    // Two winner feeders (normal bracket progression)
    if (wf.length >= 2) return '<small style="color:#0d6efd;">W' + wf[idx] + '</small>';
    // One winner + one loser feeder (e.g. position playoff fed by winners + losers)
    if (wf.length === 1 && lf.length >= 1) {
      return idx === 0
        ? '<small style="color:#0d6efd;">W' + wf[0] + '</small>'
        : '<small style="color:#e65100;">L' + lf[0] + '</small>';
    }
    // Two loser feeders (e.g. consolation bracket)
    if (lf.length >= 2) return '<small style="color:#e65100;">L' + lf[idx] + '</small>';
    if (lf.length === 1 && idx === 0) return '<small style="color:#e65100;">L' + lf[0] + '</small>';
    return '';
  }

  // ---- PRINT FIXTURES ----
  $('#btn-print-fixtures').on('click', function() {
    const oop = window.RR_OOP || [];
    if (!oop.length) { toastr.warning('No fixtures to print.'); return; }

    const stageLabels = { RR: 'Round Robin', MAIN: 'Main Draw', PLATE: 'Plate', CONS: 'Consolation', BOWL: 'Bowl', SHIELD: 'Shield', SPOON: 'Spoon' };
    let html = '<h1>' + drawName + '</h1><h2>Order of Play / Fixtures</h2>';
    html += '<table><thead><tr><th>M#</th><th>Stage</th><th>Player 1</th><th class="text-center">vs</th><th>Player 2</th><th class="text-center">Rd</th><th class="text-center">Score</th></tr></thead><tbody>';
    oop.forEach(function(fx) {
      var w1 = fx.winner == fx.r1_id ? ' class="fw-bold text-success"' : '';
      var w2 = fx.winner == fx.r2_id ? ' class="fw-bold text-success"' : '';
      var stage = fx.stage || 'RR';
      var stageLabel = stageLabels[stage] || stage;
      var score = fx.score ? fx.score : '';
      var home = (fx.home || '---');
      var away = (fx.away || '---');
      var homeFeed = feederLabel(fx, 'home');
      var awayFeed = feederLabel(fx, 'away');
      if (homeFeed) home = homeFeed;
      if (awayFeed) away = awayFeed;
      var typeLabel = fx.playoff_type ? '<br><small style="color:#666;">' + fx.playoff_type + '</small>' : '';
      html += '<tr>';
      html += '<td>' + (fx.match_nr || fx.id) + '</td>';
      html += '<td><span class="badge ' + (stage === 'RR' ? 'bg-secondary' : 'bg-primary') + '">' + stageLabel + '</span>' + typeLabel + '</td>';
      html += '<td' + w1 + '>' + home + '</td>';
      html += '<td class="text-center">vs</td>';
      html += '<td' + w2 + '>' + away + '</td>';
      html += '<td class="text-center">' + (fx.round || '') + '</td>';
      html += '<td class="text-center">' + score + '</td>';
      html += '</tr>';
    });
    html += '</tbody></table>';
    openPrintWindow(drawName + ' — Fixtures', html);
  });

  // ---- PRINT MATRIX ----
  $('#btn-print-matrix').on('click', function() {
    var includeStandings = $('#chk-print-standings').is(':checked');

    // Build matrix from JS data (same as renderMatrix)
    var groups = window.RR_GROUPS || [];
    var fixtures = window.RR_FIXTURES || {};
    if (!groups.length) { toastr.warning('No groups/matrix data available.'); return; }

    // Sort groups alphabetically (A, B, C, D …)
    var sortedGroups = groups.slice().sort(function(a, b) { return (a.name || '').localeCompare(b.name || ''); });

    // Global pass: find longest name and most columns across ALL groups
    var globalMaxLen = 6;
    var globalMaxCols = 0;
    sortedGroups.forEach(function(g) {
      var regs = g.registrations || [];
      regs.forEach(function(r) {
        var len = (r.display_name || 'N/A').length;
        if (len > globalMaxLen) globalMaxLen = len;
      });
      if (regs.length + 1 > globalMaxCols) globalMaxCols = regs.length + 1;
    });
    var colW = Math.max(130, globalMaxLen * 7 + 20);
    var tableW = globalMaxCols * colW;
    var cw = colW + 'px';

    var html = '<h1>' + drawName + '</h1><h2>Round Robin Matrix</h2>';

    sortedGroups.forEach(function(group) {
      var gFixtures = fixtures[group.id] || [];
      var players = (group.registrations || []).map(function(r) {
        return { id: r.id, name: r.display_name || 'N/A', seed: r.pivot ? (r.pivot.seed || 999) : 999 };
      }).sort(function(a, b) { return a.seed - b.seed; });

      html += '<h3 style="font-size:14px; margin:16px 0 6px;">Box ' + group.name + '</h3>';
      html += '<table class="rr-matrix-table" style="width:' + (tableW + 60) + 'px;"><thead><tr><th style="width:' + cw + '"></th>';
      players.forEach(function(p) { html += '<th style="width:' + cw + '">' + p.name + '</th>'; });
      html += '<th style="width:50px; background:#198754; color:#fff; font-weight:800;">W</th>';
      html += '</tr></thead><tbody>';

      players.forEach(function(rowP) {
        html += '<tr><th>' + rowP.name + '</th>';
        players.forEach(function(colP) {
          if (rowP.id === colP.id) {
            html += '<td class="bg-diagonal"></td>';
          } else {
            var fx = gFixtures.find(function(f) {
              return (f.r1_id === rowP.id && f.r2_id === colP.id) || (f.r1_id === colP.id && f.r2_id === rowP.id);
            });
            if (fx && fx.all_sets && fx.all_sets.length > 0) {
              var display = fx.all_sets.map(function(set) {
                var parts = set.split('-').map(Number);
                return fx.r1_id === rowP.id ? parts[0] + '-' + parts[1] : parts[1] + '-' + parts[0];
              });
              var last = display[display.length - 1].split('-').map(Number);
              var cls = last[0] > last[1] ? 'rr-win' : (last[1] > last[0] ? 'rr-loss' : '');
              html += '<td class="' + cls + '">' + display.join(', ') + '</td>';
            } else {
              html += '<td></td>';
            }
          }
        });
        // Count matches won for this row player using canonical winner field
        var rowWins = 0;
        gFixtures.forEach(function(f) {
          if (f.winner && f.winner === rowP.id) rowWins++;
        });
        html += '<td style="font-weight:800; font-size:13px; background:#f0fdf4; color:#198754;">' + rowWins + '</td>';
        html += '</tr>';
      });
      html += '</tbody></table>';
    });

    // Standings — use server-provided order (already sorted by canonical StandingsService)
    if (includeStandings) {
      var standings = window.RR_STANDINGS || {};
      sortedGroups.forEach(function(group) {
        if (!standings[group.id]) return;
        var rows = Object.values(standings[group.id]);
        html += '<div class="page-break"></div>';
        html += '<h3 style="font-size:14px; margin:16px 0 6px;">Box ' + group.name + ' — Standings</h3>';
        html += '<table class="standings-table"><thead><tr><th>#</th><th>Player</th><th>W</th><th>L</th><th>Sets %</th><th>Games %</th><th>TB</th></tr></thead><tbody>';
        rows.forEach(function(r, i) {
          var totalSets = r.sets_won + r.sets_lost;
          var setsPct = totalSets > 0 ? ((r.sets_won / totalSets) * 100).toFixed(0) + '%' : '-';
          var totalGames = (r.games_won || 0) + (r.games_lost || 0);
          var gamesPct = totalGames > 0 ? (((r.games_won || 0) / totalGames) * 100).toFixed(0) + '%' : '-';
          var tb = r.tiebreak || '';
          html += '<tr><td>' + (i + 1) + '</td><td>' + r.player + '</td><td>' + r.wins + '</td><td>' + r.losses + '</td><td>' + setsPct + '</td><td>' + gamesPct + '</td><td>' + tb + '</td></tr>';
        });
        html += '</tbody></table>';
      });
    }

    openPrintWindow(drawName + ' — Matrix', html);
  });

  // ---- PRINT COMBINED (MATRIX + FIXTURES ON 1 PAGE) ----
  $('#btn-print-combined').on('click', function() {
    var groups = window.RR_GROUPS || [];
    var fixtures = window.RR_FIXTURES || {};
    var oop = window.RR_OOP || [];
    if (!groups.length && !oop.length) { toastr.warning('No data to print.'); return; }

    var html = '<h1>' + drawName + '</h1>';

    // ---- MATRIX SECTION ----
    var sortedGroups = groups.slice().sort(function(a, b) { return (a.name || '').localeCompare(b.name || ''); });

    // Use same column sizing logic as standalone print matrix
    var globalMaxLen = 6;
    var globalMaxCols = 0;
    sortedGroups.forEach(function(g) {
      var regs = g.registrations || [];
      regs.forEach(function(r) {
        var len = (r.display_name || 'N/A').length;
        if (len > globalMaxLen) globalMaxLen = len;
      });
      if (regs.length + 1 > globalMaxCols) globalMaxCols = regs.length + 1;
    });
    var colW = Math.max(130, globalMaxLen * 7 + 20);
    var tableW = (globalMaxCols + 1) * colW; // +1 for W column
    var cw = colW + 'px';

    if (sortedGroups.length) {
      html += '<h2>Round Robin Matrix</h2>';

      sortedGroups.forEach(function(group) {
        var gFixtures = fixtures[group.id] || [];
        var players = (group.registrations || []).map(function(r) {
          return { id: r.id, name: r.display_name || 'N/A', seed: r.pivot ? (r.pivot.seed || 999) : 999 };
        }).sort(function(a, b) { return a.seed - b.seed; });

        html += '<h3 style="font-size:14px; margin:16px 0 6px;">Box ' + group.name + '</h3>';
        html += '<table class="rr-matrix-table" style="width:' + tableW + 'px;"><thead><tr><th style="width:' + cw + '"></th>';
        players.forEach(function(p) { html += '<th style="width:' + cw + '">' + p.name + '</th>'; });
        html += '<th style="width:50px; background:#198754; color:#fff; font-weight:800;">W</th>';
        html += '</tr></thead><tbody>';

        players.forEach(function(rowP) {
          html += '<tr><th>' + rowP.name + '</th>';
          players.forEach(function(colP) {
            if (rowP.id === colP.id) {
              html += '<td class="bg-diagonal"></td>';
            } else {
              var fx = gFixtures.find(function(f) {
                return (f.r1_id === rowP.id && f.r2_id === colP.id) || (f.r1_id === colP.id && f.r2_id === rowP.id);
              });
              if (fx && fx.all_sets && fx.all_sets.length > 0) {
                var display = fx.all_sets.map(function(set) {
                  var parts = set.split('-').map(Number);
                  return fx.r1_id === rowP.id ? parts[0] + '-' + parts[1] : parts[1] + '-' + parts[0];
                });
                var last = display[display.length - 1].split('-').map(Number);
                var cls = last[0] > last[1] ? 'rr-win' : (last[1] > last[0] ? 'rr-loss' : '');
                html += '<td class="' + cls + '">' + display.join(', ') + '</td>';
              } else {
                html += '<td></td>';
              }
            }
          });
          var rowWins = 0;
          gFixtures.forEach(function(f) {
            if (!f.all_sets || !f.all_sets.length) return;
            var lastSet = f.all_sets[f.all_sets.length - 1].split('-').map(Number);
            if (f.r1_id === rowP.id && lastSet[0] > lastSet[1]) rowWins++;
            if (f.r2_id === rowP.id && lastSet[1] > lastSet[0]) rowWins++;
          });
          html += '<td style="font-weight:800; font-size:13px; background:#f0fdf4; color:#198754;">' + rowWins + '</td>';
          html += '</tr>';
        });
        html += '</tbody></table>';
      });
    }

    // ---- FIXTURES SECTION ----
    if (oop.length) {
      var stageLabels = { RR: 'Round Robin', MAIN: 'Main Draw', PLATE: 'Plate', CONS: 'Consolation', BOWL: 'Bowl', SHIELD: 'Shield', SPOON: 'Spoon' };
      html += '<h2 style="margin-top:20px;">Order of Play / Fixtures</h2>';
      html += '<table><thead><tr><th>M#</th><th>Stage</th><th>Player 1</th><th class="text-center">vs</th><th>Player 2</th><th class="text-center">Rd</th><th class="text-center">Score</th></tr></thead><tbody>';
      oop.forEach(function(fx) {
        var w1 = fx.winner == fx.r1_id ? ' class="fw-bold text-success"' : '';
        var w2 = fx.winner == fx.r2_id ? ' class="fw-bold text-success"' : '';
        var stage = fx.stage || 'RR';
        var stageLabel = stageLabels[stage] || stage;
        var score = fx.score ? fx.score : '';
        var home = (fx.home || '---');
        var away = (fx.away || '---');
        var homeFeed = feederLabel(fx, 'home');
        var awayFeed = feederLabel(fx, 'away');
        if (homeFeed) home = homeFeed;
        if (awayFeed) away = awayFeed;
        var typeLabel = fx.playoff_type ? '<br><small style="color:#666;">' + fx.playoff_type + '</small>' : '';
        html += '<tr>';
        html += '<td>' + (fx.match_nr || fx.id) + '</td>';
        html += '<td><span class="badge ' + (stage === 'RR' ? 'bg-secondary' : 'bg-primary') + '">' + stageLabel + '</span>' + typeLabel + '</td>';
        html += '<td' + w1 + '>' + home + '</td>';
        html += '<td class="text-center">vs</td>';
        html += '<td' + w2 + '>' + away + '</td>';
        html += '<td class="text-center">' + (fx.round || '') + '</td>';
        html += '<td class="text-center">' + score + '</td>';
        html += '</tr>';
      });
      html += '</tbody></table>';
    }

    openPrintWindow(drawName + ' — Combined', html);
  });

  // ---- BUILD BRACKET HTML FROM CONFIG (fallback when no fixtures exist) ----
  function buildBracketFromConfig(isEmpty) {
    var config = (typeof playoffConfig !== 'undefined') ? playoffConfig : [];
    var groups = (typeof numGroups !== 'undefined') ? numGroups : 4;
    var groupNames = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').slice(0, groups);
    var html = '';

    var enabledPlayoffs = config.filter(function(p) { return p.enabled; });
    if (!enabledPlayoffs.length) return '<p style="color:#888;">No playoff brackets configured. Go to Settings tab to configure.</p>';

    enabledPlayoffs.forEach(function(playoff) {
      var positions = playoff.positions || [];
      var size = playoff.size || 4;
      var seeds = buildSnakeSeeds(positions, groupNames, playoff.group_order || null);

      var matchups = generateBracketMatchups(size);
      var numRounds = Math.ceil(Math.log2(size));

      html += '<div style="margin-bottom:30px;">';
      html += '<h3 style="font-size:15px; margin:10px 0 6px;">' + playoff.name + ' (' + size + '-draw)</h3>';

      // Build rounds
      for (var rd = 1; rd <= numRounds; rd++) {
        var rdLabel = rd === numRounds ? 'Final' : rd === numRounds - 1 ? 'SF' : rd === numRounds - 2 ? 'QF' : 'R' + rd;
        html += '<div style="margin-bottom:8px;"><strong style="font-size:11px; color:#666;">' + rdLabel + '</strong></div>';

        if (rd === 1) {
          // Show seeded matchups
          html += '<table style="border-collapse:collapse; margin-bottom:14px; font-size:11px; width:auto;">';
          matchups.forEach(function(m, idx) {
            var s1 = seeds[m.seed1 - 1];
            var s2 = seeds[m.seed2 - 1];
            var label1 = s1 ? (isEmpty ? s1.group + s1.position : '#' + m.seed1 + ' (' + s1.group + s1.position + ')') : 'BYE';
            var label2 = s2 ? (isEmpty ? s2.group + s2.position : '#' + m.seed2 + ' (' + s2.group + s2.position + ')') : 'BYE';
            html += '<tr>';
            html += '<td style="border:1px solid #999; padding:3px 12px; min-width:160px; background:' + (s1 ? '#fff' : '#f0f0f0') + ';">' + label1 + '</td>';
            html += '<td style="padding:0 6px; font-size:10px; color:#888;">vs</td>';
            html += '<td style="border:1px solid #999; padding:3px 12px; min-width:160px; background:' + (s2 ? '#fff' : '#f0f0f0') + ';">' + label2 + '</td>';
            html += '<td style="padding:0 8px;">→</td>';
            html += '<td style="border:1px solid #ccc; padding:3px 12px; min-width:140px; background:#fafafa;"></td>';
            html += '</tr>';
            if (idx % 2 === 1 && idx < matchups.length - 1) {
              html += '<tr><td colspan="5" style="height:6px;"></td></tr>';
            }
          });
          html += '</table>';
        } else {
          // Show empty slots for later rounds
          var matchesInRound = Math.pow(2, numRounds - rd);
          html += '<table style="border-collapse:collapse; margin-bottom:14px; font-size:11px; width:auto;">';
          for (var mi = 0; mi < matchesInRound; mi++) {
            html += '<tr>';
            html += '<td style="border:1px solid #ccc; padding:3px 12px; min-width:160px; background:#fafafa;">Winner M' + (mi*2+1) + '</td>';
            html += '<td style="padding:0 6px; font-size:10px; color:#888;">vs</td>';
            html += '<td style="border:1px solid #ccc; padding:3px 12px; min-width:160px; background:#fafafa;">Winner M' + (mi*2+2) + '</td>';
            html += '<td style="padding:0 8px;">→</td>';
            html += '<td style="border:1px solid #ccc; padding:3px 12px; min-width:140px; background:#fafafa;"></td>';
            html += '</tr>';
          }
          html += '</table>';
        }
      }

      // 3rd/4th playoff
      if (size >= 4) {
        html += '<div style="margin-top:6px;"><strong style="font-size:11px; color:#666;">3rd/4th Place</strong></div>';
        html += '<table style="border-collapse:collapse; margin-bottom:14px; font-size:11px; width:auto;">';
        html += '<tr><td style="border:1px solid #ccc; padding:3px 12px; min-width:160px; background:#fafafa;">SF Loser 1</td>';
        html += '<td style="padding:0 6px; font-size:10px; color:#888;">vs</td>';
        html += '<td style="border:1px solid #ccc; padding:3px 12px; min-width:160px; background:#fafafa;">SF Loser 2</td></tr>';
        html += '</table>';
      }

      html += '</div>';
    });

    return html;
  }

  // Helper: check if SVG has actual bracket content (not just wrapper + style)
  function svgHasBracketContent(svgHtml) {
    return svgHtml && (svgHtml.indexOf('<line') !== -1 || svgHtml.indexOf('<text x=') !== -1);
  }

  // Helper: build printable notes HTML from the notes textarea fields
  function buildNotesHtml() {
    var sections = [];
    $('#notes-pane .notes-field').each(function() {
      var val = $(this).val().trim();
      if (!val) return;
      var $card = $(this).closest('.card');
      var isEnabled = $card.find('.notes-enabled').prop('checked');
      if (!isEnabled) return;
      var label = $card.find('.card-header h6').text().trim();
      sections.push({ label: label, text: val });
    });
    if (!sections.length) return '';
    var html = '';
    for (var i = 0; i < sections.length; i++) {
      html += '<div style="margin-bottom:18px;">';
      html += '<h3 style="font-size:18px; font-weight:700; margin:0 0 8px; color:#1e293b; border-bottom:1px solid #ddd; padding-bottom:4px;">' + sections[i].label + '</h3>';
      html += '<div style="font-size:15px; white-space:pre-wrap; color:#333; line-height:1.7;">' + sections[i].text.replace(/</g, '&lt;').replace(/>/g, '&gt;') + '</div>';
      html += '</div>';
    }
    return html;
  }

  // ---- PRINT EMPTY BRACKET ----
  $('#btn-print-empty-bracket').on('click', function() {
    var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Loading…');
    console.log('🖨️ [PrintEmptyBracket] Button clicked');
    console.log('🖨️ [PrintEmptyBracket] playoffConfig:', (typeof playoffConfig !== 'undefined') ? playoffConfig : 'UNDEFINED');
    console.log('🖨️ [PrintEmptyBracket] numGroups:', (typeof numGroups !== 'undefined') ? numGroups : 'UNDEFINED');

    $.get(APP_URL + '/backend/draw/' + DRAW_ID + '/main-bracket?empty=1')
      .done(function(svgHtml) {
        var hasContent = svgHasBracketContent(svgHtml);
        console.log('🖨️ [PrintEmptyBracket] AJAX done, length:', (svgHtml || '').length, 'hasContent:', hasContent);
        var html = '<div class="bracket-header"><h1>' + drawName + '</h1><h2>Blank Bracket</h2></div>';
        if (hasContent) {
          console.log('🖨️ [PrintEmptyBracket] Using SVG from server');
          html += '<div class="bracket-print-wrap">' + svgHtml + '</div>';
        } else {
          console.log('🖨️ [PrintEmptyBracket] SVG empty, using config fallback');
          html += buildBracketFromConfig(true);
        }
        openPrintWindow(drawName + ' — Empty Bracket', html, true);
      })
      .fail(function(xhr, status, err) {
        console.error('🖨️ [PrintEmptyBracket] AJAX FAILED:', status, err);
        var html = '<div class="bracket-header"><h1>' + drawName + '</h1><h2>Blank Bracket</h2></div>';
        html += buildBracketFromConfig(true);
        openPrintWindow(drawName + ' — Empty Bracket', html, true);
      })
      .always(function() { $btn.prop('disabled', false).html('<i class="ti ti-printer me-1"></i> Print Empty Bracket'); });
  });

  // ---- PRINT BRACKET (WITH NAMES) ----
  $('#btn-print-bracket').on('click', function() {
    var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Loading…');
    console.log('🖨️ [PrintBracket] Button clicked');

    $.get(APP_URL + '/backend/draw/' + DRAW_ID + '/main-bracket')
      .done(function(svgHtml) {
        var hasContent = svgHasBracketContent(svgHtml);
        console.log('🖨️ [PrintBracket] AJAX done, length:', (svgHtml || '').length, 'hasContent:', hasContent);
        var html = '<div class="bracket-header"><h1>' + drawName + '</h1><h2>Playoff Brackets</h2></div>';
        if (hasContent) {
          html += '<div class="bracket-print-wrap">' + svgHtml + '</div>';
        } else {
          html += buildBracketFromConfig(false);
        }
        openPrintWindow(drawName + ' — Brackets', html, true);
      })
      .fail(function() {
        var html = '<div class="bracket-header"><h1>' + drawName + '</h1><h2>Playoff Brackets</h2></div>';
        html += buildBracketFromConfig(false);
        openPrintWindow(drawName + ' — Brackets', html, true);
      })
      .always(function() { $btn.prop('disabled', false).html('<i class="ti ti-printer me-1"></i> Print Bracket'); });
  });

  // ---- SHARED: build matrix HTML ----
  function buildMatrixHtml() {
    var groups = window.RR_GROUPS || [];
    var fixtures = window.RR_FIXTURES || {};
    if (!groups.length) return '';

    var sortedGroups = groups.slice().sort(function(a, b) { return (a.name || '').localeCompare(b.name || ''); });
    var globalMaxLen = 6;
    var globalMaxCols = 0;
    sortedGroups.forEach(function(g) {
      var regs = g.registrations || [];
      regs.forEach(function(r) {
        var len = (r.display_name || 'N/A').length;
        if (len > globalMaxLen) globalMaxLen = len;
      });
      if (regs.length + 1 > globalMaxCols) globalMaxCols = regs.length + 1;
    });
    var colW = Math.max(130, globalMaxLen * 7 + 20);
    var tableW = (globalMaxCols + 1) * colW;
    var cw = colW + 'px';
    var html = '';

    sortedGroups.forEach(function(group) {
      var gFixtures = fixtures[group.id] || [];
      var players = (group.registrations || []).map(function(r) {
        return { id: r.id, name: r.display_name || 'N/A', seed: r.pivot ? (r.pivot.seed || 999) : 999 };
      }).sort(function(a, b) { return a.seed - b.seed; });

      html += '<h3 style="font-size:14px; margin:16px 0 6px;">Box ' + group.name + '</h3>';
      html += '<table class="rr-matrix-table" style="width:' + tableW + 'px;"><thead><tr><th style="width:' + cw + '"></th>';
      players.forEach(function(p) { html += '<th style="width:' + cw + '">' + p.name + '</th>'; });
      html += '<th style="width:50px; background:#198754; color:#fff; font-weight:800;">W</th>';
      html += '</tr></thead><tbody>';

      players.forEach(function(rowP) {
        html += '<tr><th>' + rowP.name + '</th>';
        players.forEach(function(colP) {
          if (rowP.id === colP.id) {
            html += '<td class="bg-diagonal"></td>';
          } else {
            var fx = gFixtures.find(function(f) {
              return (f.r1_id === rowP.id && f.r2_id === colP.id) || (f.r1_id === colP.id && f.r2_id === rowP.id);
            });
            if (fx && fx.all_sets && fx.all_sets.length > 0) {
              var display = fx.all_sets.map(function(set) {
                var parts = set.split('-').map(Number);
                return fx.r1_id === rowP.id ? parts[0] + '-' + parts[1] : parts[1] + '-' + parts[0];
              });
              var last = display[display.length - 1].split('-').map(Number);
              var cls = last[0] > last[1] ? 'rr-win' : (last[1] > last[0] ? 'rr-loss' : '');
              html += '<td class="' + cls + '">' + display.join(', ') + '</td>';
            } else {
              html += '<td></td>';
            }
          }
        });
        var rowWins = 0;
        gFixtures.forEach(function(f) {
          if (!f.all_sets || !f.all_sets.length) return;
          var lastSet = f.all_sets[f.all_sets.length - 1].split('-').map(Number);
          if (f.r1_id === rowP.id && lastSet[0] > lastSet[1]) rowWins++;
          if (f.r2_id === rowP.id && lastSet[1] > lastSet[0]) rowWins++;
        });
        html += '<td style="font-weight:800; font-size:13px; background:#f0fdf4; color:#198754;">' + rowWins + '</td>';
        html += '</tr>';
      });
      html += '</tbody></table>';
    });

    return html;
  }

  // ---- SHARED: build standings HTML ----
  function buildStandingsHtml() {
    var groups = window.RR_GROUPS || [];
    var standings = window.RR_STANDINGS || {};
    if (!groups.length) return '';

    var sortedGroups = groups.slice().sort(function(a, b) { return (a.name || '').localeCompare(b.name || ''); });
    var html = '';

    sortedGroups.forEach(function(group) {
      if (!standings[group.id]) return;
      var rows = Object.values(standings[group.id]).sort(function(a, b) {
        if (a.wins !== b.wins) return b.wins - a.wins;
        var aTotalSets = a.sets_won + a.sets_lost;
        var bTotalSets = b.sets_won + b.sets_lost;
        var aSetsPct = aTotalSets > 0 ? a.sets_won / aTotalSets : 0;
        var bSetsPct = bTotalSets > 0 ? b.sets_won / bTotalSets : 0;
        if (Math.abs(aSetsPct - bSetsPct) > 0.0001) return bSetsPct - aSetsPct;
        var aTotalGames = (a.games_won || 0) + (a.games_lost || 0);
        var bTotalGames = (b.games_won || 0) + (b.games_lost || 0);
        var aGamesPct = aTotalGames > 0 ? (a.games_won || 0) / aTotalGames : 0;
        var bGamesPct = bTotalGames > 0 ? (b.games_won || 0) / bTotalGames : 0;
        if (Math.abs(aGamesPct - bGamesPct) > 0.0001) return bGamesPct - aGamesPct;
        return 0;
      });
      html += '<h3 style="font-size:14px; margin:16px 0 6px;">Box ' + group.name + ' — Standings</h3>';
      html += '<table class="standings-table"><thead><tr><th>#</th><th>Player</th><th>W</th><th>L</th><th>Sets %</th><th>Games %</th><th>TB</th></tr></thead><tbody>';
      rows.forEach(function(r, i) {
        var totalSets = r.sets_won + r.sets_lost;
        var setsPct = totalSets > 0 ? ((r.sets_won / totalSets) * 100).toFixed(0) + '%' : '-';
        var totalGames = (r.games_won || 0) + (r.games_lost || 0);
        var gamesPct = totalGames > 0 ? (((r.games_won || 0) / totalGames) * 100).toFixed(0) + '%' : '-';
        var tb = r.tiebreak || '';
        html += '<tr><td>' + (i + 1) + '</td><td>' + r.player + '</td><td>' + r.wins + '</td><td>' + r.losses + '</td><td>' + setsPct + '</td><td>' + gamesPct + '</td><td>' + tb + '</td></tr>';
      });
      html += '</tbody></table>';
    });

    return html;
  }

  // ---- SHARED: build fixtures table HTML ----
  function buildFixturesHtml(filterStage) {
    var oop = window.RR_OOP || [];
    if (!oop.length) return '';
    var stageLabels = { RR: 'Round Robin', MAIN: 'Main Draw', PLATE: 'Plate', CONS: 'Consolation', BOWL: 'Bowl', SHIELD: 'Shield', SPOON: 'Spoon' };
    var list = filterStage ? oop.filter(function(fx) { return fx.stage === filterStage; }) : oop;
    if (!list.length) return '';

    var html = '<table><thead><tr><th>M#</th><th>Stage</th><th>Player 1</th><th class="text-center">vs</th><th>Player 2</th><th class="text-center">Rd</th><th class="text-center">Score</th></tr></thead><tbody>';
    list.forEach(function(fx) {
      var w1 = fx.winner == fx.r1_id ? ' class="fw-bold text-success"' : '';
      var w2 = fx.winner == fx.r2_id ? ' class="fw-bold text-success"' : '';
      var stage = fx.stage || 'RR';
      var stageLabel = stageLabels[stage] || stage;
      var score = fx.score ? fx.score : '';
      var home = (fx.home || '---');
      var away = (fx.away || '---');
      var homeFeed = feederLabel(fx, 'home');
      var awayFeed = feederLabel(fx, 'away');
      if (homeFeed) home = homeFeed;
      if (awayFeed) away = awayFeed;
      var typeLabel = fx.playoff_type ? '<br><small style="color:#666;">' + fx.playoff_type + '</small>' : '';
      html += '<tr>';
      html += '<td>' + (fx.match_nr || fx.id) + '</td>';
      html += '<td><span class="badge ' + (stage === 'RR' ? 'bg-secondary' : 'bg-primary') + '">' + stageLabel + '</span>' + typeLabel + '</td>';
      html += '<td' + w1 + '>' + home + '</td>';
      html += '<td class="text-center">vs</td>';
      html += '<td' + w2 + '>' + away + '</td>';
      html += '<td class="text-center">' + (fx.round || '') + '</td>';
      html += '<td class="text-center">' + score + '</td>';
      html += '</tr>';
    });
    html += '</tbody></table>';
    return html;
  }

  // ---- BUILD BRACKET FIXTURE TABLE FROM CONFIG ----
  // Generates a fixture list for each enabled playoff bracket from playoffConfig,
  // with match numbers, round labels, seed sources, and W/L feeder indicators.
  function buildBracketFixtureTableFromConfig() {
    var config = (typeof playoffConfig !== 'undefined') ? playoffConfig : [];
    var nGroups = (typeof numGroups !== 'undefined') ? numGroups : 4;
    var groupNames = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('').slice(0, nGroups);

    var enabledPlayoffs = config.filter(function(p) { return p.enabled; });
    if (!enabledPlayoffs.length) return '';

    var html = '';

    enabledPlayoffs.forEach(function(playoff) {
      var positions = playoff.positions || [];
      var size = playoff.size || 4;
      var seeds = buildSnakeSeeds(positions, groupNames, playoff.group_order || null);

      var matchups = generateBracketMatchups(size);
      var numRounds = Math.ceil(Math.log2(size));
      var matchNr = 1;
      // Store match numbers per round so later rounds can reference them
      var roundMatches = {}; // roundMatches[round] = [{nr, idx}]

      html += '<h3 style="font-size:14px; margin:18px 0 6px;">' + playoff.name + ' (' + size + '-draw)</h3>';
      html += '<table><thead><tr>';
      html += '<th>M#</th><th>Round</th><th>Player 1</th><th class="text-center">vs</th><th>Player 2</th><th>Position</th>';
      html += '</tr></thead><tbody>';

      // Round 1 — seeded matchups
      roundMatches[1] = [];
      matchups.forEach(function(m) {
        var s1 = seeds[m.seed1 - 1];
        var s2 = seeds[m.seed2 - 1];
        var label1 = s1 ? s1.group + '#' + s1.position : '<span style="color:#999;">BYE</span>';
        var label2 = s2 ? s2.group + '#' + s2.position : '<span style="color:#999;">BYE</span>';

        var rdLabel = numRounds === 1 ? 'Final' : numRounds === 2 ? 'SF' : numRounds === 3 ? 'QF' : 'R1';

        html += '<tr>';
        html += '<td>' + matchNr + '</td>';
        html += '<td>' + rdLabel + '</td>';
        html += '<td>' + label1 + '</td>';
        html += '<td class="text-center">vs</td>';
        html += '<td>' + label2 + '</td>';
        html += '<td></td>';
        html += '</tr>';

        roundMatches[1].push(matchNr);
        matchNr++;
      });

      // Subsequent rounds
      for (var rd = 2; rd <= numRounds; rd++) {
        var matchesInRound = Math.pow(2, numRounds - rd);
        var prevMatchList = roundMatches[rd - 1] || [];
        var isFinalRound = (rd === numRounds);
        var isSF = (rd === numRounds - 1) && numRounds >= 3;
        var isQF = (rd === numRounds - 2) && numRounds >= 4;
        var rdLabel = isFinalRound ? 'Final' : isSF ? 'SF' : isQF ? 'QF' : 'R' + rd;

        roundMatches[rd] = [];

        for (var mi = 0; mi < matchesInRound; mi++) {
          var feeder1 = prevMatchList[mi * 2];
          var feeder2 = prevMatchList[mi * 2 + 1];
          var p1Label = feeder1 ? '<span style="color:#0d6efd; font-weight:bold;">W' + feeder1 + '</span>' : '---';
          var p2Label = feeder2 ? '<span style="color:#0d6efd; font-weight:bold;">W' + feeder2 + '</span>' : '---';
          var posLabel = isFinalRound ? '1st/2nd' : '';

          html += '<tr>';
          html += '<td>' + matchNr + '</td>';
          html += '<td>' + rdLabel + '</td>';
          html += '<td>' + p1Label + '</td>';
          html += '<td class="text-center">vs</td>';
          html += '<td>' + p2Label + '</td>';
          html += '<td>' + posLabel + '</td>';
          html += '</tr>';

          roundMatches[rd].push(matchNr);
          matchNr++;
        }

        // 3rd/4th playoff from SF losers
        if (isFinalRound && numRounds >= 2) {
          var sfMatches = roundMatches[rd - 1] || [];
          var sf1 = sfMatches[0];
          var sf2 = sfMatches[1];
          if (sf1 && sf2) {
            html += '<tr style="border-top:2px solid #999;">';
            html += '<td>' + matchNr + '</td>';
            html += '<td>3rd/4th</td>';
            html += '<td><span style="color:#e65100; font-weight:bold;">L' + sf1 + '</span></td>';
            html += '<td class="text-center">vs</td>';
            html += '<td><span style="color:#e65100; font-weight:bold;">L' + sf2 + '</span></td>';
            html += '<td>3rd/4th</td>';
            html += '</tr>';
            matchNr++;
          }
        }

        // 5th–8th from QF losers
        if (isSF && matchesInRound === 2) {
          var qfMatches = roundMatches[rd - 1] || [];
          if (qfMatches.length >= 4) {
            // Cons SF 1: L(QF1) vs L(QF2)
            var cSF1Nr = matchNr;
            html += '<tr style="border-top:2px solid #ccc;">';
            html += '<td>' + matchNr + '</td>';
            html += '<td>Cons SF</td>';
            html += '<td><span style="color:#e65100; font-weight:bold;">L' + qfMatches[0] + '</span></td>';
            html += '<td class="text-center">vs</td>';
            html += '<td><span style="color:#e65100; font-weight:bold;">L' + qfMatches[1] + '</span></td>';
            html += '<td></td>';
            html += '</tr>';
            matchNr++;

            // Cons SF 2: L(QF3) vs L(QF4)
            var cSF2Nr = matchNr;
            html += '<tr>';
            html += '<td>' + matchNr + '</td>';
            html += '<td>Cons SF</td>';
            html += '<td><span style="color:#e65100; font-weight:bold;">L' + qfMatches[2] + '</span></td>';
            html += '<td class="text-center">vs</td>';
            html += '<td><span style="color:#e65100; font-weight:bold;">L' + qfMatches[3] + '</span></td>';
            html += '<td></td>';
            html += '</tr>';
            matchNr++;

            // 5th/6th: W(consSF1) vs W(consSF2)
            html += '<tr>';
            html += '<td>' + matchNr + '</td>';
            html += '<td>5th/6th</td>';
            html += '<td><span style="color:#0d6efd; font-weight:bold;">W' + cSF1Nr + '</span></td>';
            html += '<td class="text-center">vs</td>';
            html += '<td><span style="color:#0d6efd; font-weight:bold;">W' + cSF2Nr + '</span></td>';
            html += '<td>5th/6th</td>';
            html += '</tr>';
            matchNr++;

            // 7th/8th: L(consSF1) vs L(consSF2)
            html += '<tr>';
            html += '<td>' + matchNr + '</td>';
            html += '<td>7th/8th</td>';
            html += '<td><span style="color:#e65100; font-weight:bold;">L' + cSF1Nr + '</span></td>';
            html += '<td class="text-center">vs</td>';
            html += '<td><span style="color:#e65100; font-weight:bold;">L' + cSF2Nr + '</span></td>';
            html += '<td>7th/8th</td>';
            html += '</tr>';
            matchNr++;
          }
        }
      }

      html += '</tbody></table>';
    });

    return html;
  }

  // ---- PRINT DRAW PACK ----
  $('#btn-print-draw-pack').on('click', function() {
    var $btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Generating…');

    // Fetch empty bracket SVG first, then assemble everything
    $.get(APP_URL + '/backend/draw/' + DRAW_ID + '/main-bracket?empty=1')
      .done(function(svgHtml) {
        var bracketHtml = '';
        if (svgHasBracketContent(svgHtml)) {
          bracketHtml = '<div class="bracket-print-wrap">' + svgHtml + '</div>';
        } else {
          bracketHtml = buildBracketFromConfig(true);
        }
        assemblePack(bracketHtml);
      })
      .fail(function() {
        assemblePack(buildBracketFromConfig(true));
      })
      .always(function() {
        $btn.prop('disabled', false).html('<i class="ti ti-printer me-1"></i> Print Draw Pack');
      });

    function assemblePack(bracketHtml) {
      var html = '';
      var hasContent = false;

      var incNotes    = $('#pack-notes').is(':checked');
      var incMatrix   = $('#pack-matrix').is(':checked');
      var incRRFx     = $('#pack-rr-fixtures').is(':checked');
      var incPlayFx   = $('#pack-playoff-fixtures').is(':checked');
      var incBrackets = $('#pack-brackets').is(':checked');

      // --- PAGE 1: Rules & Notes (cover page) ---
      if (incNotes) {
        var rulesHtml = buildNotesHtml();
        if (rulesHtml) {
          html += '<h1>' + drawName + '</h1>';
          html += '<h2>Rules &amp; Notes</h2>';
          html += rulesHtml;
          hasContent = true;
        }
      }

      // --- PAGE 2: Matrix ---
      if (incMatrix) {
        if (hasContent) html += '<div class="page-break"></div>';
        html += '<h1>' + drawName + '</h1>';
        html += '<h2>Round Robin Matrix</h2>';
        html += buildMatrixHtml();
        hasContent = true;
      }

      // --- PAGE 3: RR Fixtures ---
      if (incRRFx) {
        var rrFx = buildFixturesHtml('RR');
        if (rrFx) {
          if (hasContent) html += '<div class="page-break"></div>';
          html += '<h1>' + drawName + '</h1>';
          html += '<h2>Round Robin Fixtures</h2>';
          html += rrFx;
          hasContent = true;
        }
      }

      // --- PAGE 4: Bracket Fixtures from config (with W/L feeders) ---
      if (incPlayFx) {
        var bracketFx = buildBracketFixtureTableFromConfig();
        if (bracketFx) {
          if (hasContent) html += '<div class="page-break"></div>';
          html += '<h1>' + drawName + '</h1>';
          html += '<h2>Playoff Fixtures</h2>';
          html += '<p style="font-size:15px; color:#444; margin-bottom:12px;">';
          html += '<span style="color:#0d6efd; font-weight:bold;">W3</span> = Winner of match 3 &nbsp; ';
          html += '<span style="color:#e65100; font-weight:bold;">L3</span> = Loser of match 3 &nbsp; ';
          html += '<span style="font-weight:bold;">A#1</span> = Group A position 1';
          html += '</p>';
          html += bracketFx;
          hasContent = true;
        }
      }

      // --- PAGE 5: Empty Brackets ---
      if (incBrackets && bracketHtml && bracketHtml.indexOf('No playoff') === -1) {
        if (hasContent) html += '<div class="page-break"></div>';
        html += '<h1>' + drawName + '</h1>';
        html += '<h2>Blank Brackets</h2>';
        html += bracketHtml;
        hasContent = true;
      }

      if (!hasContent) {
        toastr.warning('No sections selected. Please check at least one option.');
        return;
      }

      openPrintWindow(drawName + ' — Draw Pack', html);
    }
  });

})(jQuery);
</script>
