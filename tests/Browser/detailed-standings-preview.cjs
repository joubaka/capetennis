// Manual browser QA fixture: actual shipped renderers, fictional competition.
// node tests/Browser/detailed-standings-preview.cjs --serve
// Open http://127.0.0.1:8128/?view=public or ?view=admin.
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const publicRoot = path.join(root, 'public');
const names = ['Alex Example', 'Blair Example', 'Casey Example', 'Devon Example'];
const pairs = [[1, 2, 6, 2], [1, 3, 6, 4], [2, 4, 6, 1], [3, 4, 6, 3], [1, 4], [2, 3]];
const fixtures = pairs.map(([one, two, a, b], index) => ({
  id: index + 1, group_id: 1, r1_id: one, r2_id: two,
  name1: names[one - 1], name2: names[two - 1],
  all_sets: a === undefined ? [] : [`${a}-${b}`],
  winner: a === undefined ? null : a > b ? one : two,
  time: null, court: null, venue_name: null
}));
const rows = names.map((player, index) => {
  const id = index + 1;
  const matches = pairs.filter(pair => pair[0] === id || pair[1] === id);
  let wins = 0, losses = 0, gamesWon = 0, gamesLost = 0;
  const details = matches.map(pair => {
    const home = pair[0] === id;
    const opponent = home ? pair[1] : pair[0];
    const won = home ? pair[2] : pair[3], lost = home ? pair[3] : pair[2];
    const pending = won === undefined;
    if (!pending) { gamesWon += won; gamesLost += lost; won > lost ? wins++ : losses++; }
    return {fixture_id: pairs.indexOf(pair) + 1, opponent: names[opponent - 1],
      status: pending ? 'pending' : won > lost ? 'won' : 'lost',
      score: pending ? '' : `${won}–${lost}`, carried: false, ending: null};
  });
  return {reg_id: id, player, wins, losses, draws: 0, points: wins * 2,
    played: wins + losses, remaining: 3 - wins - losses,
    sets_won: wins, sets_lost: losses, games_won: gamesWon, games_lost: gamesLost,
    tiebreak: '', display_position: id, group_winner: false, incomplete_results: 0,
    group_result_status: 'incomplete', group_result_message: 'Provisional standings — two matches remain.',
    match_details: details};
});
const groups = [{id: 1, name: 'Example A', registrations: names.map((display_name, index) =>
  ({id: index + 1, display_name, pivot: {seed: index + 1}}))}];

function html(admin) {
  const data = JSON.stringify({groups, fixtures: {1: fixtures}, standings: {1: rows}}).replace(/</g, '\\u003c');
  return `<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Detailed standings · fictional ${admin ? 'admin' : 'public'} preview</title>
  <link rel="stylesheet" href="/assets/vendor/css/core.css"><link rel="stylesheet" href="/assets/vendor/css/theme-default.css">
  <link rel="stylesheet" href="/assets/css/draw-roundrobin.css"><link rel="stylesheet" href="/css/public-draw.css">
  <style>body{margin:0;background:#f5f7fa}main{max-width:1450px;margin:auto;padding:20px}.preview-note{color:#56657a;font-size:14px}.preview-card{background:white;padding:20px;border-radius:12px;min-width:0}#rr-standings-wrapper{min-width:0}</style>
  <main><h1>Round-robin standings</h1><p class="preview-note">Fictional ${admin ? 'admin' : 'public'} preview · One full set · <a href="/?view=${admin ? 'public' : 'admin'}">Switch view</a></p>
  <section id="round-robin-app" class="${admin ? '' : 'rr-public-view'}" data-draw-id="1"><div class="preview-card"><div id="rr-standings-wrapper"></div></div></section></main>
  <script src="/assets/vendor/libs/jquery/jquery.js"></script>
  <script>const previewData=${data};window.RR_GROUPS=previewData.groups;window.RR_FIXTURES=previewData.fixtures;window.RR_STANDINGS=previewData.standings;window.RR_OOP=[];window.RR_SCORE_FORMAT='one_full_set';window.AdminState={getGroups:()=>previewData.groups,getFixtures:()=>previewData.fixtures,getStandings:()=>previewData.standings,on:()=>{}};</script>
  <script src="/assets/js/roundrobin-standings.js"></script>
  <script src="${admin ? '/assets/js/admin/roundrobin/standings.js' : '/assets/js/roundrobin-public.js'}"></script>
  ${admin ? '<script>window.RRStandings.render();</script>' : ''}</html>`;
}
if (process.argv.includes('--serve')) {
  http.createServer((request, response) => {
    const url = new URL(request.url, 'http://127.0.0.1');
    if (url.pathname === '/') {
      response.writeHead(200, {'Content-Type': 'text/html; charset=utf-8'});
      return response.end(html(url.searchParams.get('view') === 'admin'));
    }
    const file = path.resolve(publicRoot, '.' + decodeURIComponent(url.pathname));
    if (!file.startsWith(publicRoot + path.sep) || !/\.(css|js|woff2?|ttf|png|svg)$/.test(file) || !fs.existsSync(file)) {
      response.writeHead(404); return response.end('Not found');
    }
    response.writeHead(200, {'Content-Type': file.endsWith('.js') ? 'text/javascript' : file.endsWith('.css') ? 'text/css' : 'application/octet-stream'});
    fs.createReadStream(file).pipe(response);
  }).listen(8128, '127.0.0.1', () => process.stdout.write('Fictional standings preview: http://127.0.0.1:8128/?view=public\n'));
} else {
  process.stdout.write('Use --serve for the loopback-only manual QA preview.\n');
}
