const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function scenario({ready = true, qualified = false, confirm = false, modal = false, hiding = false, destination = 'playoffs', pending = true, blocked = false, keepVisible = false} = {}) {
  const handlers = {}, prompts = [], reviews = [], classes = new Set(['d-none']);
  let hidden;
  const button = {dataset: {url:'/progress', reviewUrl:'/review', drawName:'Example'}, disabled:true,
    classList: {add:c=>classes.add(c), remove:c=>classes.delete(c)}};
  const message = {};
  const section = {dataset:{hasQualifiers:qualified?'1':'0',progressKeepVisible:keepVisible?'1':'0',progressDestination:destination}, querySelector:s=>s==='.progress-draw'?button:message};
  const document = {querySelector:()=>section, getElementById:()=>(modal||hiding)?{classList:{contains:()=>modal},style:{display:'block'}}:null};
  const $ = arg => {if(typeof arg==='function') return arg(); return {one:(_,cb)=>{hidden=cb;}};};
  $.get = async () => {
    if (control.blocked) throw {status:422,responseJSON:{message:'Resolve the tied first-stage positions.',pending:true,round_robin:{total:6,completed:6}}};
    if (!control.ready) throw {status:422,responseJSON:{message:'One match remains.'}};
    return {success:true,review:{groups:[{id:1}],destination,pending}};
  };
  const control = {ready,blocked};
  const window = {jQuery:$, AdminState:{on:(name,cb)=>{handlers[name]=cb;}},
    CTDrawProgression:{review:async opts=>reviews.push(opts)}, location:{reload(){}}};
  vm.runInNewContext(fs.readFileSync('public/js/roundrobin-progression-prompt.js','utf8'),{
    window,document,Swal:{fire:async opts=>{prompts.push(opts);return {isConfirmed:confirm};}}
  });
  return {handlers,prompts,reviews,button,classes,message,control,hidden:()=>hidden?.()};
}
const rr = {detail:{mode:'RR'}};
test('last RR save exposes review and offers progression once; cancel never progresses',async()=>{
  const app=scenario(); await app.handlers['score:saved'](rr); await app.handlers['score:saved'](rr);
  assert.equal(app.prompts.length,1); assert.equal(app.reviews.length,0);
  assert.equal(app.button.disabled,false); assert.equal(app.classes.has('d-none'),false);
});
test('confirm reuses authorized standings review and waits until score modal closes',async()=>{
  const app=scenario({confirm:true,modal:true}); await app.handlers['score:saved'](rr);
  assert.equal(app.prompts.length,0); await app.hidden();
  assert.equal(app.prompts.length,1); assert.equal(app.reviews[0].reviewUrl,'/review');
});
test('a hiding score modal finishes its transition before the progression offer opens',async()=>{
  const app=scenario({hiding:true}); await app.handlers['score:saved'](rr);
  assert.equal(app.prompts.length,0); await app.hidden(); assert.equal(app.prompts.length,1);
});
test('incomplete, already qualified, bracket saves and deletions never auto-progress',async()=>{
  const incomplete=scenario({ready:false}); await incomplete.handlers['score:saved'](rr);
  assert.equal(incomplete.prompts.length,0); assert.equal(incomplete.button.disabled,true);
  assert.equal(incomplete.message.textContent,'One match remains.');
  const qualified=scenario({qualified:true}); await qualified.handlers['score:saved'](rr);
  assert.equal(qualified.prompts.length,0);
  const bracket=scenario(); await bracket.handlers['score:saved']({detail:{mode:'BRACKET'}});
  assert.equal(bracket.prompts.length,0); assert.equal(bracket.button.disabled,true);
  const deleted=scenario(); await deleted.handlers['score:deleted']({});
  assert.equal(deleted.prompts.length,0);
});

test('staged destinations reuse review and confirmed staged draws never offer progression',async()=>{
  for (const destination of ['second round robin','top-four playoffs and the lower round robin']) {
    const app=scenario({destination,confirm:true,keepVisible:true});
    await app.handlers['score:saved'](rr);
    assert.match(app.prompts[0].text,new RegExp(destination));
    assert.equal(app.reviews.length,1);
    const confirmed=scenario({destination,pending:false}); await confirmed.handlers['score:saved'](rr);
    assert.equal(confirmed.prompts.length,0); assert.equal(confirmed.button.disabled,true);
  }
});

test('complete but tied groups warn once without progression and can offer review after resolution',async()=>{
  const app=scenario({blocked:true,keepVisible:true});
  app.classes.delete('d-none');
  await app.handlers['score:saved'](rr); await app.handlers['score:saved'](rr);
  assert.equal(app.prompts.length,1); assert.equal(app.prompts[0].icon,'warning');
  assert.equal(app.reviews.length,0); assert.equal(app.button.disabled,true); assert.equal(app.classes.has('d-none'),false);
  app.control.blocked=false;
  await app.handlers['score:saved'](rr);
  assert.equal(app.prompts.length,2); assert.equal(app.prompts[1].icon,'question');
});

test('a newer score while the modal closes still offers the latest ready review',async()=>{
  const app=scenario({modal:true}); await app.handlers['score:saved'](rr); await app.handlers['score:saved'](rr);
  await app.hidden(); assert.equal(app.prompts.length,1);
});
