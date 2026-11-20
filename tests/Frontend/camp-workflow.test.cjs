const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function page() {
  const events = {}, windowEvents = {}, hidden = [];
  const button = {tagName:'BUTTON', name:'decision', value:'reject', textContent:'Reject', disabled:false, dataset:{}};
  const originalDisabled = {tagName:'INPUT', value:'Unavailable', disabled:true, dataset:{}};
  const form = {method:'post', action:'/camp-action', dataset:{}, setAttribute(){}, removeAttribute(){}, append(input){ hidden.push(input); }, querySelector(){return hidden.find(input=>input.name==='_camp_form');}, querySelectorAll(selector){ return selector.includes('submit-copy') ? hidden.filter(input=>input.dataset.campSubmitCopy) : [button, originalDisabled]; }};
  const root = {contains(node){return node===form;}, addEventListener(name,fn){events['root:'+name]=fn;}};
  const document = {querySelector(selector){return selector.includes('workflow') ? root : null;}, querySelectorAll(selector){return selector.includes('busy') && form.dataset.campBusy ? [form] : [];}, addEventListener(name,fn){events[name]=fn;}, createElement(){const input={dataset:{},remove(){hidden.splice(hidden.indexOf(input),1);}};return input;}};
  const window = {addEventListener(name,fn){windowEvents[name]=fn;}};
  vm.runInNewContext(fs.readFileSync('public/assets/js/camp-workflow.js','utf8'),{document,window});
  return {events,windowEvents,form,button,originalDisabled,hidden};
}
test('native submit preserves decision and prevents repeats; bfcache restores original controls',()=>{
 const p=page(); let blocked=false;
 p.events.submit({target:p.form,submitter:p.button});
 assert.equal(p.hidden[1].name,'decision');assert.equal(p.hidden[1].value,'reject');assert.equal(p.button.disabled,true);
 p.events.submit({target:p.form,submitter:p.button,preventDefault(){blocked=true;}});assert.equal(blocked,true);
 p.windowEvents.pageshow();assert.equal(p.hidden.length,1);assert.equal(p.button.disabled,false);assert.equal(p.button.textContent,'Reject');assert.equal(p.originalDisabled.disabled,true);assert.equal(p.originalDisabled.value,'Unavailable');
});
test('cancelled and unrelated forms are never made busy',()=>{
 const p=page();p.events.submit({target:p.form,defaultPrevented:true});assert.equal(p.form.dataset.campBusy,undefined);
 p.events.submit({target:{method:'post',dataset:{}}});assert.equal(p.button.disabled,false);
});
test('native invalid inputs open containing disclosures before browser focus',()=>{
 const p=page();const details={tagName:'DETAILS',parentElement:null,open:false};p.events['root:invalid']({target:{parentElement:details}});assert.equal(details.open,true);
});
test('changing a coach identity choice clears its previous review consent',()=>{
 const p=page();const confirmation={checked:true};const form={action:'/backend/camps/2/submissions/4',querySelector(){return confirmation;}};
 p.events['root:change']({target:{form,name:'player_id'}});assert.equal(confirmation.checked,false);
 confirmation.checked=true;p.events['root:change']({target:{form,name:'identity_confirmed'}});assert.equal(confirmation.checked,true);
});
