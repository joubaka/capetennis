const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function matrix(source, groups, fixtures) {
  const output = new Map();
  const window = {};
  function $(selector) {
    return {length:1,
      empty() { output.set(selector,''); return this; }, addClass() { return this; },
      append(value) { output.set(selector,(output.get(selector)||'')+value); return this; },
      html(value) { if (value === undefined) return output.get(selector); output.set(selector,value); return this; },
      text(value) { output.set(selector,value); return this; }, on() { return this; }
    };
  }
  vm.runInNewContext(fs.readFileSync(source,'utf8'), {jQuery:$,window,
    AdminState:{getGroups:()=>groups,getFixtures:()=>fixtures,on(){}}, document:{}});
  window.RRMatrix.render();
  return output;
}

test('saved lower-stage source fixtures render readable noninteractive desktop cards in both matrix assets', () => {
  for (const source of ['resources/js/admin/roundrobin/matrix.js','public/assets/js/admin/roundrobin/matrix.js']) {
    const fixtures = Array.from({length:6},(_,index)=>({id:index+100,match_nr:index+17,r1_id:null,r2_id:null,
      name1:'Group A #'+(3+index%2),name2:'Group B #'+(3+index%3),all_sets:[]}));
    const output = matrix(source,[{id:7,name:'Stage 2 · Positions 5–9',registrations:[]}],{7:fixtures});
    const html = output.get('#rr-matrix-wrapper');
    assert.equal((html.match(/data-pending-fixture-id=/g)||[]).length,6);
    assert.match(html,/Match #17/); assert.match(html,/Match #22/);
    assert.match(html,/Group A #3/); assert.match(html,/Group B #5/);
    assert.match(html,/first-stage finishing places, not seeds/);
    assert.equal((html.match(/first-stage finishing places, not seeds/g)||[]).length,1);
    assert.doesNotMatch(html,/<button|<table|class="rr-mobile-match-list"/);
    assert.match(html,/rr-pending-match-list/);
    assert.equal(output.get('#rr-matrix-status'),'0 of 6 matches completed');
  }
});

test('unresolved fixtures cannot become mobile score buttons even when other group players exist', () => {
  for (const source of ['resources/js/admin/roundrobin/matrix.js','public/assets/js/admin/roundrobin/matrix.js']) {
    const output = matrix(source,[{id:7,name:'A',registrations:[{id:1,display_name:'Example One'},{id:2,display_name:'Example Two'}]}],
      {7:[{id:100,r1_id:null,r2_id:null,name1:'Group A #3',name2:'Group B #3',all_sets:[]}]});
    const html = output.get('#rr-matrix-wrapper');
    assert.match(html,/data-pending-fixture-id="100"/);
    assert.doesNotMatch(html,/data-fixture-id="100"/);
    assert.match(html,/cannot be scored yet/);
  }
});

test('pending labels are escaped and qualified source-stage fixtures retain normal score controls', () => {
  const source = 'public/assets/js/admin/roundrobin/matrix.js';
  const pending = matrix(source,[{id:7,name:'A',registrations:[]}],
    {7:[{id:100,r1_id:null,r2_id:null,name1:'<script>bad</script>',name2:'Group B #3',all_sets:[]}]});
  assert.match(pending.get('#rr-matrix-wrapper'),/&lt;script&gt;/);
  assert.doesNotMatch(pending.get('#rr-matrix-wrapper'),/<script>/);
  const ready = matrix(source,[{id:7,name:'A',registrations:[{id:1,display_name:'Example One'},{id:2,display_name:'Example Two'}]}],
    {7:[{id:100,r1_id:1,r2_id:2,all_sets:[]}]});
  assert.match(ready.get('#rr-matrix-wrapper'),/<button[^>]+data-fixture-id="100"/);
});
