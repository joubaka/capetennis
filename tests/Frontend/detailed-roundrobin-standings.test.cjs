const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const renderer = require('../../public/assets/js/roundrobin-standings.js');

const row = (name, extra = {}) => ({player:name, reg_id:1, played:2, remaining:1, wins:1, losses:1, draws:0,
  points:2, sets_won:1, sets_lost:1, games_won:9, games_lost:7, match_details:[], ...extra});

test('finished group matches get factual completion text without inferring final standings or overriding explicit status', () => {
  const complete = [row('Alpha', {played:3,remaining:0,incomplete_results:0}), row('Bravo', {played:3,remaining:0,incomplete_results:0})];
  const html = renderer.renderGroup({name:'A'}, complete);
  assert.ok(html.includes('Group matches complete.'));
  assert.equal(html.includes('Provisional standings'), false);
  assert.equal(html.includes('Final group standings.'), false);
  assert.equal(html.includes('Group winner</span>'), false);
  for (const incomplete of [{remaining:undefined},{remaining:null},{remaining:'0'},{played:0},{played:'3'},
    {incomplete_results:undefined},{incomplete_results:1},{match_details:[{fixture_id:7,status:'incomplete'}]}]) {
    assert.equal(renderer.renderGroup({name:'A'}, [complete[0], {...complete[1],...incomplete}]).includes('Group matches complete.'), false);
  }
  assert.ok(renderer.renderGroup({name:'A'}, [{...complete[0],group_result_status:'tied'},complete[1]]).includes('leading tie unresolved'));
  assert.ok(renderer.renderGroup({name:'A'}, [{...complete[0],group_result_message:'Await confirmed qualification.'},complete[1]]).includes('Await confirmed qualification.'));
});

test('shared standings preserve server order and expose complete totals with precise percentages', () => {
  const html = renderer.renderGroup({name:'A'}, [row('Server first', {games_won:10,games_lost:8}), row('Server second')]);
  assert.ok(html.indexOf('Server first') < html.indexOf('Server second'), 'presentation must never re-sort canonical order');
  for (const heading of ['Played','Won','Drawn','Lost','Remaining','Points','Sets W–L','Games W–L']) assert.ok(html.includes(`<th scope="col">${heading}</th>`));
  assert.ok(html.includes('55.56%'));
  assert.ok(html.includes('56.25%'));
  assert.ok(html.includes('10–8'));
  assert.ok(html.includes('tabindex="0"'));
  assert.ok(html.includes('table-responsive'));
});

test('all dynamic labels and score evidence are escaped in summary and expandable results', () => {
  const attack = '<img src=x onerror=alert(1)>';
  const html = renderer.renderGroup({name:attack}, [row(attack, {tiebreak:attack, group_result_message:attack,
    match_details:[{opponent:attack,status:'won',score:attack,ending:attack,carried:true}]})]);
  assert.equal(html.includes(attack), false);
  assert.ok(html.includes('&lt;img'));
  assert.ok(html.includes('<details'));
  assert.ok(html.includes('Carried result'));
  assert.ok(html.includes('Won vs'));
});

test('partial scores are explicitly incomplete, unplayed opponents remain pending, zero totals stay unavailable', () => {
  const html = renderer.renderGroup({name:'A'}, [row('Alpha', {played:0,remaining:3,wins:0,losses:0,points:0,
    sets_won:0,sets_lost:0,games_won:0,games_lost:0,match_details:[
      {fixture_id:7,opponent:'Bravo',status:'incomplete',score:'6–4'},
      {fixture_id:8,opponent:'Charlie',status:'pending',score:''}]}),
    row('Bravo', {played:0,remaining:3,match_details:[{fixture_id:7,opponent:'Alpha',status:'incomplete',score:'4–6'}]})]);
  assert.ok(html.includes('1 match has recorded scores'));
  assert.ok(html.includes('Incomplete match vs'));
  assert.ok(html.includes('Still to play vs'));
  assert.equal(html.includes('Won vs'), false);
  assert.ok(html.includes('0 played, 3 remaining'));
  assert.equal(renderer.percent(0,0), '—');
});

test('admin adapter delegates canonical rows on initial and refreshed standings events', () => {
  const handlers = {};
  let rendered = '';
  const values = [row('Alpha')];
  const window = {RRStandingsTable:renderer};
  function $(value) { return {length:1,html(html){rendered=html;return this;},on(){return this;},attr(){return value?.id;}}; }
  vm.runInNewContext(fs.readFileSync('public/assets/js/admin/roundrobin/standings.js','utf8'), {
    jQuery:$,window,AdminState:{getGroups:()=>[{id:5,name:'A'}],getStandings:()=>({5:values}),on:(name,callback)=>handlers[name]=callback}
  });
  window.renderStandings();
  assert.ok(rendered.includes('Alpha'));
  values[0].player = 'Updated';
  handlers['rr:standings:updated']();
  assert.ok(rendered.includes('Updated'));
});

test('source and served shared/admin scripts match and both public scripts delegate identically', () => {
  for (const [source,served] of [['resources/js/roundrobin-standings.js','public/assets/js/roundrobin-standings.js'],
    ['resources/js/admin/roundrobin/standings.js','public/assets/js/admin/roundrobin/standings.js']]) {
    assert.equal(fs.readFileSync(source,'utf8'),fs.readFileSync(served,'utf8'));
  }
  const fragment = path => fs.readFileSync(path,'utf8').split('  function renderStandings() {')[1].split('  function loadMainBracket()')[0];
  assert.equal(fragment('resources/assets/js/roundrobin-public.js'),fragment('public/assets/js/roundrobin-public.js'));
});
