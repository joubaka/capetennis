const fs=require('node:fs'),vm=require('node:vm'),test=require('node:test'),assert=require('node:assert/strict');
const source=fs.readFileSync('public/js/flexible-monrad.js','utf8');
const refresh=source.slice(source.indexOf('  let refreshInFlight = false;'),source.indexOf('  async function request('));
function harness(){
 const scroll={scrollLeft:230,scrollTop:140},dialog={open:false};
 const state={generated:true,challenge_playoffs:true,draft:{slots:{}},positions:[{position:2,player:1}]};
 const c={state,draft:state.draft,config:{refreshUrl:'/draw/live',urls:{},state},dirty:false,busy:false,renders:0,requests:0,reloads:0,
  document:{hidden:false,activeElement:{matches:()=>false},querySelector:()=>null},
  $:id=>id==='fm-score-dialog'?dialog:scroll,clone:x=>structuredClone(x),
  AbortController,setTimeout,clearTimeout,render(){c.renders++;scroll.scrollLeft=0;scroll.scrollTop=0;},
  window:{location:{reload(){c.reloads++;}}}};
 c.next=()=>({state:{...c.state,positions:[{position:2,player:2}]},refreshUrl:'/draw/live',urls:{},canScore:false});
 c.fetch=async()=>{c.requests++;return {ok:true,status:200,json:async()=>c.next()};};
 vm.createContext(c);vm.runInContext(refresh,c);
 return {c,scroll,dialog,run:()=>vm.runInContext('refreshDraw()',c)};
}
test('remote results replace positions and permissions while preserving scroll',async()=>{
 const {c,scroll,run}=harness();await run();assert.equal(c.state.positions[0].player,2);assert.equal(c.config.canScore,false);assert.equal(c.renders,1);assert.equal(scroll.scrollLeft,230);assert.equal(scroll.scrollTop,140);
});
test('score dialog, unsaved edits, saves, hidden pages, demos and focused inputs pause refresh',async()=>{
 for(const mode of ['dialog','dirty','busy','hidden','demo','input']){
  const {c,dialog,run}=harness();if(mode==='dialog')dialog.open=true;else if(mode==='hidden')c.document.hidden=true;else if(mode==='demo')c.config.demo=true;else if(mode==='input')c.document.activeElement.matches=()=>true;else c[mode]=true;
  await run();assert.equal(c.requests,0,mode);assert.equal(c.renders,0,mode);
 }
});
test('an in-flight response cannot overwrite a local score save or newly opened score dialog',async()=>{
 for(const mode of ['saved','dialog','dirty']){
  const {c,dialog,run}=harness();let finish;
  c.fetch=()=>new Promise(resolve=>{finish=resolve;});const pending=run();
  if(mode==='saved')c.state={...c.state,revision:9};else if(mode==='dialog')dialog.open=true;else c.dirty=true;
  finish({ok:true,status:200,json:async()=>c.next()});await pending;
  assert.equal(c.renders,0,mode);assert.equal(c.state.positions[0].player,1,mode);
 }
});
test('overlapping refreshes share one request and failures recover on the next check',async()=>{
 const {c,run}=harness();let finish;
 c.fetch=()=>{c.requests++;return new Promise(resolve=>{finish=resolve;});};
 const pending=run();await run();assert.equal(c.requests,1);finish({ok:false,status:503});await pending;
 c.fetch=async()=>{c.requests++;return {ok:true,status:200,json:async()=>c.next()};};await run();assert.equal(c.requests,2);assert.equal(c.renders,1);
});
test('network errors retain the current draw and do not prevent later refreshes',async()=>{
 const {c,run}=harness();c.fetch=async()=>{throw Error('offline');};await run();assert.equal(c.state.positions[0].player,1);
 c.fetch=async()=>({ok:true,status:200,json:async()=>c.next()});await run();assert.equal(c.renders,1);
});
test('publication revocation and expired sessions reload through normal access checks and stop polling',async()=>{
 for(const status of [401,403,404,419]){const {c,run}=harness();c.fetch=async()=>{c.requests++;return {ok:false,status};};await run();await run();assert.equal(c.reloads,1);assert.equal(c.requests,1);assert.equal(c.renders,0);}
});
test('unchanged snapshots do not redraw the bracket',async()=>{
 const {c,run}=harness();c.next=()=>c.config;await run();assert.equal(c.renders,0);
});
