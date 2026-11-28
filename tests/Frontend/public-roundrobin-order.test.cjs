const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const script = fs.readFileSync(path.join(__dirname, '../../public/assets/js/roundrobin-public.js'), 'utf8').replace(/\r\n/g, '\n');
const render = script.slice(script.indexOf('  function renderOrderOfPlay() {'), script.indexOf('  /* ===============================\n   * STANDINGS'));

test('public fixtures put unfinished playoffs ahead of completed RR results and retain round order within both groups', () => {
  const fixtures = [
    {id:1, match_nr:1, group_id:1, round:1, stage:'RR', completed:true, score:'6-2'},
    {id:2, match_nr:2, group_id:2, round:1, stage:'RR', completed:true, score:'3-3'},
    {id:3, match_nr:3, group_id:1, round:2, stage:'RR', completed:false, score:'2-1'},
    {id:4, match_nr:4, group_id:2, round:2, stage:'RR', completed:false},
    {id:5, match_nr:5, round:1, stage:'MAIN', completed:false},
    {id:6, match_nr:6, round:1, stage:'MAIN', completed:true, score:'W/O'},
    {id:7, match_nr:7, group_id:1, round:2, stage:'RR', completed:true, score:'RET'},
  ];
  const original = JSON.stringify(fixtures);
  let html;
  vm.runInNewContext(render + '\nrenderOrderOfPlay();', {
    RR_OOP: fixtures,
    RR_GROUPS: [{id:1,name:'A',registrations:[1,2,3,4]}, {id:2,name:'B',registrations:[5,6,7]}],
    $: () => ({html(value) {html=value;}}),
    window: {},
    escapeHtml: value => String(value ?? ''),
    formatScheduleParts: () => ({date:'',time:''}),
  });
  const order = [...html.matchAll(/data-label="Match"[^>]*>(\d+)</g)].map(match => Number(match[1]));
  assert.deepEqual(order, [3,4,5,1,2,7,6]);
  assert.equal(JSON.stringify(fixtures), original);
  assert.match(html, /3-3/);
  assert.match(html, /2-1/);
});
