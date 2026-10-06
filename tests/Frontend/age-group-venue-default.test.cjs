const fs = require('node:fs');
const vm = require('node:vm');
const test = require('node:test');
const assert = require('node:assert/strict');
const template = fs.readFileSync('resources/views/backend/draw/_modals/age-group-venue-default.blade.php', 'utf8');
function harness() {
  const choices = [
    {id:1,name:'Under 10 boys singles',key:{age:10,gender:'boys'}},
    {id:2,name:'Under 10 girls doubles',key:{age:10,gender:'girls'}},
    {id:3,name:'Under 10 mixed doubles',key:{age:10,gender:'mixed'}},
    {id:4,name:'Under 11 boys',key:{age:11,gender:'boys'}}
  ];
  function element() {
    return {children:[],listeners:{},disabled:false,hidden:false,checked:false,value:'age',
      addEventListener(name, fn){this.listeners[name]=fn;},setAttribute(){},after(){},
      append(...items){this.children.push(...items);},replaceChildren(){this.children=[];}};
  }
  const ids = Object.fromEntries(['venuesForm','age-group-venue-default','age-group-venue-options','age-group-venue-scope','age-group-draw-selection','age-group-venue-draws'].map(id=>[id,element()]));
  const button = element(); ids.venuesForm.querySelector = () => button;
  const document = element(); document.getElementById = id => ids[id]; document.createElement = element;
  let requests = 0;
  const script = template.split('<script>')[1].split('</script>')[0]
    .replace('@json($venueDefaultChoices)',JSON.stringify(choices)).replace('@json(isset($draw) ? $draw->id : null)','1');
  class FormData {
    constructor() {
      this.drawIds = ids['age-group-venue-draws'].children.flatMap(row => row.children.filter(input=>input.name==='age_group_draw_ids[]'&&input.checked&&!input.disabled).map(input=>input.value));
    }
  }
  vm.runInNewContext(script,{document,FormData,fetch:()=>{requests++;return new Promise(()=>{});},window:{},TypeError,Error});
  document.listeners.DOMContentLoaded();
  return {ids,document,button,FormData,requests:()=>requests};
}
test('whole-age option selects boys girls and mixed draws, exclusions affect submitted IDs',()=>{
  const h = harness(); const checkbox = h.ids['age-group-venue-default']; checkbox.checked=true; checkbox.listeners.change();
  const inputs = h.ids['age-group-venue-draws'].children.map(row=>row.children[0]);
  assert.deepEqual(inputs.map(input=>input.value),[1,2,3]); assert.ok(inputs.every(input=>input.checked&&!input.disabled));
  inputs[1].checked=false; assert.deepEqual(new h.FormData().drawIds,[1,3]);
});
test('gender scope narrows choices and reopening restores whole-age default',()=>{
  const h = harness(); const checkbox=h.ids['age-group-venue-default']; checkbox.checked=true; checkbox.listeners.change();
  const scope=h.ids['age-group-venue-scope']; scope.value='gender';scope.listeners.change();
  assert.equal(h.ids['age-group-venue-draws'].children.length,1);
  h.document.listeners.click({target:{closest:()=>({dataset:{drawId:2}})}});
  assert.equal(scope.value,'age');assert.equal(checkbox.checked,false);assert.equal(scope.disabled,true);
  checkbox.checked=true;checkbox.listeners.change();assert.equal(h.ids['age-group-venue-draws'].children.length,3);
});
test('pending bulk save prevents repeated requests',()=>{
  const h=harness();h.ids['age-group-venue-default'].checked=true;
  const event={preventDefault(){},stopImmediatePropagation(){}};
  h.ids.venuesForm.listeners.submit(event);h.ids.venuesForm.listeners.submit(event);
  assert.equal(h.requests(),1);assert.equal(h.button.disabled,true);
});
