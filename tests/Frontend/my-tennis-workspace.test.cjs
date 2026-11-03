const test = require('node:test');
const assert = require('node:assert/strict');
const integrate = require('../../public/assets/js/my-tennis-workspace.js');

test('embedded rating updates the persistent header snapshot and hides only its repeated hero', () => {
  const snapshot = {score: 66.6}, host = {replaceChildren(value) {this.child = value;}};
  const hero = {dataset: {}, hidden: false, querySelector: () => snapshot};
  const document = {querySelector: () => host}, panel = {querySelector: () => hero};
  assert.equal(integrate(document, panel), true);
  assert.equal(host.child, snapshot); assert.equal(hero.hidden, true);
  assert.equal(hero.dataset.embeddedRatingHero, '');
  const withheld = {reason: 'Results awaiting update'};
  hero.querySelector = () => withheld;
  integrate(document, panel); assert.equal(host.child, withheld);
});
test('standalone and incomplete fragments retain their own visible header', () => {
  const hero = {hidden: false, querySelector: () => null};
  assert.equal(integrate({querySelector: () => null}, {querySelector: () => hero}), false);
  assert.equal(integrate({querySelector: () => ({})}, {querySelector: () => hero}), false);
  assert.equal(hero.hidden, false);
});
const vm = require('node:vm'), fs = require('node:fs');

test('locked feature clicks repeatedly reveal and focus a useful explanation without fetching', () => {
  const listeners = {}, frames = [], panels = ['record','opponents','pathway','events','rating'].map(key => ({key,hidden:true,focuses:0,scrolls:0,focus(){this.focuses++},scrollIntoView(options){assert.equal(options.behavior,'instant'); assert.equal(options.block,'start'); this.scrolls++}}));
  const tab = {classList:{contains:()=>true}, addEventListener(){throw Error('active tab needs no pending event')}};
  const document = {addEventListener:(name, fn)=>{listeners[name]=fn}, getElementById:()=>tab,
    querySelector:selector=>panels.find(p=>selector.includes('"'+p.key+'"')),
    querySelectorAll:()=>panels};
  const context = {document,location:{href:'http://localhost/my-tennis?player=42'},URL,requestAnimationFrame:callback=>frames.push(callback),bootstrap:{Tab:{getOrCreateInstance:()=>({show(){}})}}};
  context.window=context;
  vm.runInNewContext(fs.readFileSync(require.resolve('../../public/assets/js/my-tennis-workspace.js'),'utf8'), context);
  listeners.DOMContentLoaded();
  for(const key of ['record','opponents','pathway','events']) for(let repeat=0;repeat<2;repeat++){
    const event={target:{closest:()=>({dataset:{plusFeature:key}})},preventDefault(){this.prevented=true}};
    listeners.click(event);
    while(frames.length) frames.shift()();
    assert.equal(event.prevented,true); assert.equal(panels.find(p=>p.key===key).hidden,false);
    assert.equal(panels.filter(p=>!p.hidden).length,1);
  }
  for(const p of panels.filter(p=>p.key!=='rating')) {assert.equal(p.focuses,2);assert.equal(p.scrolls,4);}
});
