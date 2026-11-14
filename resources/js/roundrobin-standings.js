/* Shared read-only standings presentation. Server ordering and outcomes are authoritative. */
(function (root) {
  'use strict';
  function escape(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (character) {
      return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[character];
    });
  }
  function number(value) { return Number.isFinite(Number(value)) ? Number(value) : 0; }
  function percent(won, lost) {
    var total = number(won) + number(lost);
    return total > 0 ? (number(won) / total * 100).toFixed(2) + '%' : '—';
  }
  function renderGroup(group, rows) {
    if (!rows.length) return '';
    var html = '<section class="rr-detailed-standings mb-4" style="min-width:0;max-width:100%">';
    html += '<h6 class="fw-bold mt-4">Box ' + escape(group.name) + '</h6>';
    var first = rows[0];
    var message = first.group_result_message || (first.group_result_status === 'complete' ? 'Final group standings.'
      : first.group_result_status === 'tied' ? 'Group winner pending: leading tie unresolved.'
      : first.group_result_status === 'incomplete' ? 'Group winner pending: complete every group match.'
      : 'Provisional standings — remaining results may change the order.');
    html += '<p class="small text-muted">' + escape(message) + '</p>';
    var incomplete = new Set();
    rows.forEach(function (row) { (row.match_details || []).forEach(function (match) { if (match.status === 'incomplete') incomplete.add(match.fixture_id); }); });
    if (incomplete.size) html += '<p class="alert alert-warning py-2 small" role="status">' + incomplete.size + ' match' + (incomplete.size === 1 ? ' has' : 'es have') + ' recorded scores without a completed match outcome. Check the scoring format or finish the remaining sets. These scores do not count as match wins or losses yet.</p>';
    html += '<div class="table-responsive" tabindex="0" role="region" aria-label="' + escape('Standings for Box ' + group.name) + '"><table class="table table-sm table-striped text-nowrap"><caption class="visually-hidden">Match standings for Box ' + escape(group.name) + '</caption><thead><tr>';
    ['Position', 'Player', 'Played', 'Won', 'Drawn', 'Lost', 'Remaining', 'Points', 'Sets W–L', 'Sets %', 'Games W–L', 'Games %', 'Tiebreak'].forEach(function (title) { html += '<th scope="col">' + title + '</th>'; });
    html += '</tr></thead><tbody>';
    rows.forEach(function (row, index) {
      var played = row.played == null ? number(row.wins) + number(row.losses) + number(row.draws) : number(row.played);
      var values = [escape(row.display_position == null ? index + 1 : row.display_position),
        escape(row.player) + (row.group_winner ? ' <span class="badge bg-success">Group winner</span>' : ''), played,
        number(row.wins), number(row.draws), number(row.losses), row.remaining == null ? '—' : number(row.remaining),
        number(row.points), number(row.sets_won) + '–' + number(row.sets_lost), percent(row.sets_won, row.sets_lost),
        number(row.games_won) + '–' + number(row.games_lost), percent(row.games_won, row.games_lost), escape(row.tiebreak || '—')];
      html += '<tr' + (row.group_winner ? ' class="table-success"' : '') + '>';
      values.forEach(function (value, column) { html += '<' + (column === 1 ? 'th scope="row"' : 'td') + '>' + value + '</' + (column === 1 ? 'th' : 'td') + '>'; });
      html += '</tr>';
    });
    html += '</tbody></table></div><p class="small text-muted mt-2">Played counts completed match outcomes. Points: win 2, draw 1, loss 0. W–L means won–lost. Percentages use the exact game and set totals. Scroll the table horizontally on smaller screens.</p>';
    rows.forEach(function (row) {
      var matches = row.match_details || [];
      if (!matches.length) return;
      html += '<details class="border rounded p-2 mb-2"><summary style="cursor:pointer;overflow-wrap:anywhere"><strong>' + escape(row.player) + '</strong> — match results (' + number(row.played) + ' played, ' + number(row.remaining) + ' remaining)</summary><ul class="list-unstyled mb-0 mt-2">';
      matches.forEach(function (match) {
        var labels = {won: 'Won', lost: 'Lost', drawn: 'Draw', incomplete: 'Incomplete match', pending: 'Still to play'};
        html += '<li class="border-top py-2" style="overflow-wrap:anywhere"><span>' + escape(labels[match.status] || 'Still to play') + ' vs <strong>' + escape(match.opponent) + '</strong></span>';
        if (match.score) html += ' · <span>' + escape(match.score) + '</span>';
        if (match.ending) html += ' · ' + escape(match.ending);
        if (match.carried) html += ' <span class="badge bg-secondary">Carried result</span>';
        html += '</li>';
      });
      html += '</ul></details>';
    });
    return html + '</section>';
  }
  var api = {renderGroup: renderGroup, escape: escape, percent: percent};
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  root.RRStandingsTable = api;
}(typeof window === 'undefined' ? globalThis : window));
