const fs=require('node:fs');
const vm=require('node:vm');
const test=require('node:test');
const assert=require('node:assert/strict');
const source=fs.readFileSync('public/js/flexible-monrad.js','utf8');
const stages=['challenge_4','challenge_2','challenge_3','challenge_2_repeat'];

function harness({selected=stages,available=stages,responses=[],confirm=true}={}){
 const nodes=new Map();
 const get=id=>{
  if(!nodes.has(id))nodes.set(id,{textContent:'',listeners:{},addEventListener(event,handler){this.listeners[event]=handler;}});
  return nodes.get(id);
 };
 const inputs=available.map(key=>({dataset:{rollingStage:key},checked:selected.includes(key)}));
 const context={state:{revision:7,rolling_challenge_stages:[...stages]},config:{urls:{configureRollingChallenges:'/configure'}},
  busy:false,render(){},message(){},$:get,requests:[],confirmations:[],
  document:{querySelector:()=>({content:'csrf'}),querySelectorAll:selector=>selector.includes(':checked')?inputs.filter(input=>input.checked):inputs},
  confirm:text=>{context.confirmations.push(text);return confirm;},
  fetch:async(url,options)=>{
   context.requests.push({url,body:JSON.parse(options.body)});
   const result=responses.shift()||{ok:true,data:{revision:8,rolling_challenge_stages:JSON.parse(options.body).stages}};
   return {ok:result.ok,json:async()=>result.data};
  }};
 vm.createContext(context);
 const requests=source.slice(source.indexOf('  async function request('),source.indexOf('  async function saveDraft('));
 const listener=source.slice(source.indexOf("  $('fm-rolling-challenges')?.addEventListener"),source.indexOf("  $('fm-withdrawals').addEventListener"));
 vm.runInContext(requests+listener,context);
 return {context,inputs,get,click:()=>get('fm-rolling-challenges').listeners.click()};
}
const review={message:'Review private times.',schedule_confirmation:'a'.repeat(64),schedule_removals:[
 {match:38,time:'2026-11-01 10:30:00',venue:'Fictional courts',court:'1'},
 {match:41,time:'2026-11-01 12:30:00',venue:'Fictional courts',court:'3'},
]};

test('four independent choices submit only selected stages, including an explicit empty selection',async()=>{
 for(const selected of [['challenge_3'],[]]){
  const h=harness({selected});await h.click();
  assert.deepEqual(h.context.requests[0].body,{revision:7,stages:selected});
  assert.equal(h.context.confirmations.length,0);
  assert.deepEqual(h.inputs.filter(input=>input.checked).map(input=>input.dataset.rollingStage),selected);
  assert.match(h.get('fm-rolling-feedback').textContent,/saved for this draw/);
 }
});
test('saving affected private bookings asks for exact times before resubmitting the bound confirmation',async()=>{
 const h=harness({selected:['challenge_3'],responses:[{ok:false,data:review}]});await h.click();
 assert.equal(h.context.requests.length,2);
 assert.match(h.context.confirmations[0],/Match 38: 2026-11-01 10:30:00 · Fictional courts · Court 1/);
 assert.match(h.context.confirmations[0],/Match 41: 2026-11-01 12:30:00 · Fictional courts · Court 3/);
 assert.equal(h.context.requests[1].body.schedule_confirmation,review.schedule_confirmation);
 assert.deepEqual(h.context.requests[1].body.stages,['challenge_3']);
});
test('cancelling private-time removal leaves saved state and times unchanged',async()=>{
 const h=harness({selected:[],confirm:false,responses:[{ok:false,data:review}]});await h.click();
 assert.equal(h.context.requests.length,1);assert.equal(h.context.state.revision,7);
 assert.match(h.get('fm-rolling-feedback').textContent,/No changes saved.*retained/);
});
test('dependency and protected-result errors stay visible without silently enabling another stage',async()=>{
 const h=harness({selected:['challenge_2_repeat'],responses:[{ok:false,data:{message:'Select Current 3 vs 4 first.'}}]});await h.click();
 assert.equal(h.context.requests.length,1);assert.equal(h.context.confirmations.length,0);
 assert.deepEqual(h.inputs.filter(input=>input.checked).map(input=>input.dataset.rollingStage),['challenge_2_repeat']);
 assert.equal(h.get('fm-rolling-feedback').textContent,'Select Current 3 vs 4 first.');
});
test('a stale removal confirmation does not silently retry with a newer set of times',async()=>{
 const h=harness({selected:[],responses:[{ok:false,data:review},{ok:false,data:{...review,message:'Review the changed private times.',schedule_confirmation:'b'.repeat(64)}}]});await h.click();
 assert.equal(h.context.requests.length,2);assert.equal(h.context.confirmations.length,1);
 assert.equal(h.context.state.revision,7);assert.equal(h.get('fm-rolling-feedback').textContent,'Review the changed private times.');
});
test('editing higher base pairs keeps the selected top-five stages and other base pairs intact',async()=>{
 const h=harness({available:[...stages,'challenge_6','challenge_8','challenge_14'],selected:[...stages,'challenge_6','challenge_8']});
 h.inputs.find(input=>input.dataset.rollingStage==='challenge_8').checked=false;
 h.inputs.find(input=>input.dataset.rollingStage==='challenge_14').checked=true;
 await h.click();
 assert.deepEqual(h.context.requests[0].body.stages,[...stages,'challenge_6','challenge_14']);
 for(const key of [...stages,'challenge_6','challenge_14'])assert.equal(h.inputs.find(input=>input.dataset.rollingStage===key).checked,true);
 assert.equal(h.inputs.find(input=>input.dataset.rollingStage==='challenge_8').checked,false);
});
