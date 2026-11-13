const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
function harness(hash = '#groups', search = '', context = '') {
  const events = {}, clicks = {}, requests = [], alerts = [];
  const links = ['setup','players','matches','schedule','review','results'].map(key => ({dataset:{journeyStep:key}, attrs:{}, setAttribute(k,v){this.attrs[k]=v;}, removeAttribute(k){delete this.attrs[k];}}));
  const next = {textContent:'Next required step: players', href:'/players'};
  const rail = {dataset:{workflowContext:context,defaultStep:'players',workflowUrl:'/guide?workflow_only=1'}, querySelectorAll(){return links;}, querySelector(){return next;}};
  const dirty = {dataset:{dirty:'false'}};
  const generate = {count:0, click(){this.count++;}};
  const document = {
    querySelector(){return rail;}, querySelectorAll(){return [rail];},
    getElementById(id){return id === 'fm-app' ? dirty : (id === 'btn-regenerate-fixtures' ? generate : null);},
    addEventListener(name,fn){clicks[name]=fn;},
  };
  const window = {addEventListener(name,fn){events[name]=fn;},alert(text){alerts.push(text);}};
  const location = {hash,search,href:'/draw'+search+hash};
  const fetch = async (url,options) => {requests.push({url,options}); return {ok:false};};
  vm.runInNewContext(fs.readFileSync('public/js/draw-workflow.js','utf8'), {window,document,location,URLSearchParams,fetch});
  return {links,events,clicks,requests,alerts,dirty,generate,location,next,selected:()=>links.find(link=>link.attrs['aria-current']==='step')?.dataset.journeyStep};
}
test('six destinations and old deep links highlight the current step, not stale publication queries', () => {
  for (const [hash,search,selected] of [['#groups','?review=publication','players'],['#settings','?view=results','setup'],['#matrix','?review=publication','review'],['#matrix','?view=results','results'],['#notes-pane','','setup'],['#schedule','','schedule']]) {
    assert.equal(harness(hash,search).selected(),selected);
  }
  const ui=harness('#matrix','?review=publication'); ui.location.hash='#groups'; ui.events.hashchange(); assert.equal(ui.selected(),'players');
  assert.equal(harness('#round-days','','schedule').selected(),'schedule');
});
test('unsaved player edits block workflow navigation without discarding or saving anything', () => {
  const ui=harness(); ui.dirty.dataset.dirty='true'; let blocked=false;
  ui.clicks.click({target:{closest:selector=>selector === '[data-draw-journey] a'},preventDefault(){blocked=true;}});
  assert.equal(blocked,true); assert.equal(ui.alerts.length,1); assert.equal(ui.requests.length,0);
});
test('saved state refresh uses only a private GET and failure offers reload without retrying mutations', async () => {
  const ui=harness(); await ui.events['draw-workflow-saved']();
  assert.equal(ui.requests.length,1); assert.equal(ui.requests[0].options.cache,'no-store');
  assert.equal(ui.requests[0].options.headers.Accept,'application/json');
  assert.equal(ui.requests[0].options.method,undefined);
  assert.equal(ui.next.textContent,'Reload to review saved progress');
});
test('Matches preview delegates once to the existing guarded generator without submitting its own mutation', () => {
  const ui=harness();
  ui.clicks.click({target:{closest:selector=>selector === '[data-journey-create-matches]'}});
  assert.equal(ui.generate.count,1); assert.equal(ui.requests.length,0);
});
test('AJAX guide mount activates the navigation without injecting scripts', () => {
  const ui=harness('#settings'); ui.links.forEach(link=>link.attrs={}); ui.events['draw-workflow-mounted']();
  assert.equal(ui.selected(),'setup');
  const modal=fs.readFileSync('resources/views/backend/draw/partials/guide-modal.blade.php','utf8');
  assert.match(modal,/workflow-assets/);
  assert.match(fs.readFileSync('public/js/draw-setup-guide.js','utf8'),/dispatchEvent\(new Event\('draw-workflow-mounted'\)\)/);
});
