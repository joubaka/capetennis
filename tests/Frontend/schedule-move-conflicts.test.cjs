const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const {html,checkingHtml,checkingContext,mount}=require('../../public/js/schedule-move-conflicts.js');
const match={fixture_key:'individual:6',draw_name:'U/19 Boys Draw',match:6,scheduled_at:'2026-10-30 09:30',venue_name:'Courts',court:'2'};
const report={message:'No match times were changed.',moving:{...match,match:5},requested:{...match,scheduled_at:'2026-10-30 09:05'},
 issues:[{type:'qualifier',match,required_at:'2026-10-30 09:30',message:'An earlier qualifier needs to finish.'},{type:'dependent',match,required_at:'2026-10-30 10:35',message:'Move this later match first.'}],
 suggestions:[{fixture_key:'individual:5',scheduled_at:'2026-10-30 08:00',venue_id:1,venue_name:'Courts',court:'1',current_slot:true}]};

test('conflict dialog names direct later blockers first, required start, and keeps current slot explicit',()=>{
 const output=html(report);
 assert.ok(output.indexOf('Move this later match first')<output.indexOf('Qualifying match'));
 for(const text of ['U/19 Boys Draw','Match 5','Match 6','10:35','timing requirement','Keep current slot','Other match times stay unchanged'])assert.ok(output.includes(text),text);
 assert.ok(output.includes('data-conflict-review="individual:6"'));
 assert.ok(!output.includes('10:35 is available'));
});

test('all server-provided labels, locations and explanations are escaped',()=>{
 const malicious='<img src=x onerror="alert(1)">';
 const output=html({...report,message:malicious,moving:{...match,draw_name:malicious},issues:[{type:'court',match:{...match,venue_name:malicious},message:malicious}],suggestions:[{...report.suggestions[0],court:malicious}]});
 assert.ok(!output.includes('<img'));
 assert.ok(output.includes('&lt;img'));
});

test('private booking details are absent and lack of suggestion never claims a valid destination',()=>{
 const output=html({message:'Rejected',issues:[{type:'court',match:null,required_at:'2026-10-30 10:00',message:'Another event has a booking on this court.'}],suggestions:[]});
 assert.ok(output.includes('Another event'));
 assert.ok(!output.includes('data-conflict-review'));
 assert.ok(!output.includes('data-conflict-suggestion'));
 assert.ok(output.includes('Each move is checked again'));
});

function harness(openOther=false){
 const callbacks=new Map(),actions=[],attributes={},elapsed={textContent:''},title={},dismiss={},timers=new Map();let clock=0,nextTimer=0,otherVisible=openOther;
 const body={innerHTML:'',setAttribute:(key,value)=>attributes[key]=value,querySelector:()=>elapsed};
 const listeners=new Map();
 const element={addEventListener(name,callback,options={}){
  if(!listeners.has(name))listeners.set(name,[]);
  listeners.get(name).push({callback,once:options.once});
  callbacks.set(name,()=>{const rows=[...listeners.get(name)];listeners.set(name,rows.filter(row=>!row.once));rows.forEach(row=>row.callback());});
  if(name==='click')callbacks.set(name,event=>listeners.get(name).forEach(row=>row.callback(event)));
 }};
 const other={addEventListener:(name,callback)=>callbacks.set('other:'+name,callback)};
 const doc={getElementById:id=>({'scheduleMoveConflictModal':element,'schedule-move-conflict-body':body,'scheduleMoveConflictLabel':title,'schedule-move-conflict-dismiss':dismiss})[id],querySelector:()=>otherVisible?other:null};
 const bootstrap={Modal:{getOrCreateInstance:target=>({show:()=>actions.push(target===element?'show conflict':'show other'),hide:()=>{actions.push(target===element?'hide conflict':'hide other');if(target===other)otherVisible=false;}})}};
 const dialog=mount({document:doc,bootstrap,onSuggestion:slot=>actions.push(slot),onReview:key=>actions.push(key),now:()=>clock,
  setInterval:fn=>{const id=++nextTimer;timers.set(id,fn);return id;},clearInterval:id=>timers.delete(id)});
 return{dialog,actions,callbacks,body,attributes,title,dismiss,elapsed,timers,tick(ms){clock+=ms;[...timers.values()].forEach(fn=>fn());}};
}
test('a suggestion requires an explicit click, closes the modal, then uses the selected slot only',()=>{
 const app=harness();app.dialog.show(report);assert.deepEqual(app.actions,['show conflict']);
 app.callbacks.get('click')({target:{closest:selector=>selector==='[data-conflict-suggestion]'?{dataset:{conflictSuggestion:'0'}}:null}});
 assert.deepEqual(app.actions,['show conflict','hide conflict']);
 app.callbacks.get('hidden.bs.modal')();assert.equal(app.actions[2],report.suggestions[0]);
});
test('review closes the conflict dialog before selecting the named blocker',()=>{
 const app=harness();app.dialog.show(report);
 app.callbacks.get('click')({target:{closest:selector=>selector==='[data-conflict-review]'?{dataset:{conflictReview:'individual:6'}}:null}});
 app.callbacks.get('hidden.bs.modal')();assert.equal(app.actions[2],'individual:6');
});
test('an open reschedule modal finishes hiding before the conflict dialog opens',()=>{
 const app=harness(true);app.dialog.show(report);assert.deepEqual(app.actions,['hide other']);
 app.callbacks.get('other:hidden.bs.modal')();assert.deepEqual(app.actions,['hide other','show conflict']);
});
test('workspace suppresses the generic toast for structured errors and retries through canonical move flow',()=>{
 const source=fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php','utf8');
 assert.match(source,/!error\.stalePreview && !error\.response\?\.move_conflict/);
 assert.match(source,/onSuggestion:slot => placeMatchManually\(slot\.fixture_key/);
 assert.match(source,/if \(error\.response\?\.move_conflict\) \{ moveConflictDialog\.show\(error\.response\.move_conflict\)/);
 assert.match(source,/showMoveConflict\(error, fixtureId, slot\.dataset\)/);
});

test('checking context includes named same-event qualifiers and dependents but masks other events and bounds the list',()=>{
 const preview={venues:[{id:1,name:'U19 courts'}],matches:[{...match,fixture_key:'individual:5',match:5,draw_id:7,venue_id:1,dependencies:[4]}],
  existing_matches:[{...match,fixture_key:'individual:4',match:4,draw_id:7,venue_id:1},
   {...match,fixture_key:'individual:999',draw_id:999,draw_name:'PRIVATE FOREIGN',dependencies:[5]},
   ...Array.from({length:10},(_,index)=>({...match,fixture_key:'individual:'+(10+index),draw_id:7,venue_id:1,dependencies:[5]}))]};
 const value=checkingContext(preview,[7],'individual:5',{time:'2026-10-30 09:05',venue:1,court:'4'});
 assert.equal(value.moving.match,5);assert.equal(value.requested.venue_name,'U19 courts');assert.equal(value.related.length,8);assert.equal(value.omitted_related,3);
 assert.equal(value.related[0].relationship,'qualifier');assert.equal(value.related[1].relationship,'dependent');
 assert.ok(!JSON.stringify(value).includes('PRIVATE FOREIGN'));assert.ok(!JSON.stringify(value).includes('individual:999'));
 assert.equal(value.checks_alternatives,true);
});
test('checking copy is escaped and honest about elapsed time, incomplete checks and hiding the request',()=>{
 const output=checkingHtml({moving:{...match,draw_name:'<img src=x onerror=alert(1)>'},requested:match,related:[{...match,draw_name:'<script>bad()</script>',relationship:'dependent'}],checks_alternatives:true});
 for(const text of ['data-move-check-elapsed','Elapsed: 0s','has not returned a result','does not cancel','Nearby alternatives','Later dependent match'])assert.ok(output.includes(text),text);
 assert.ok(!output.includes('<img'));assert.ok(!output.includes('<script>'));assert.ok(!output.includes('progressbar'));assert.ok(!output.includes('%'));
 assert.ok(!checkingHtml({checks_alternatives:false}).includes('Nearby alternatives'));
});
test('checking shows elapsed time and finish clears its timer and busy state',()=>{
 const app=harness();app.dialog.checking({moving:match,requested:match});
 assert.equal(app.title.textContent,'Checking this move');assert.equal(app.dismiss.textContent,'Hide details');assert.equal(app.attributes['aria-busy'],'true');assert.equal(app.timers.size,1);
 app.tick(2500);assert.equal(app.elapsed.textContent,'Elapsed: 2s');app.tick(60000);assert.equal(app.elapsed.textContent,'Elapsed: 1m 2s');
 app.dialog.finishChecking();assert.equal(app.timers.size,0);assert.equal(app.attributes['aria-busy'],'false');assert.equal(app.actions.at(-1),'hide conflict');
});
test('a returned conflict ends checking without the final cleanup closing the conflict modal',()=>{
 const app=harness();app.dialog.checking({moving:match});app.dialog.show(report);
 const actions=[...app.actions];app.dialog.finishChecking();
 assert.deepEqual(app.actions,actions);assert.equal(app.attributes['aria-busy'],'false');assert.equal(app.timers.size,0);assert.equal(app.title.textContent,'Why this match cannot move');
});
test('hiding checking details leaves the timer running and reopening does not reset it',()=>{
 const app=harness();app.dialog.checking({moving:match});app.callbacks.get('hide.bs.modal')();app.callbacks.get('hidden.bs.modal')();
 assert.equal(app.timers.size,1);app.tick(5000);app.dialog.reopenChecking();assert.equal(app.elapsed.textContent,'Elapsed: 5s');assert.equal(app.actions.at(-1),'show conflict');
 app.dialog.finishChecking();assert.equal(app.timers.size,0);
});
test('fast success during modal opening queues a hide after its shown event',()=>{
 const app=harness();app.dialog.checking({moving:match});app.dialog.finishChecking();
 app.callbacks.get('shown.bs.modal')();assert.equal(app.actions.at(-1),'hide conflict');
 assert.equal(app.attributes['aria-busy'],'false');assert.equal(app.timers.size,0);
});
test('an error that arrives during a hide transition reopens the conflict details after hidden',()=>{
 const app=harness();app.dialog.checking({moving:match});app.callbacks.get('hide.bs.modal')();
 app.dialog.show(report);app.callbacks.get('hidden.bs.modal')();
 assert.equal(app.actions.at(-1),'show conflict');assert.equal(app.title.textContent,'Why this match cannot move');assert.equal(app.timers.size,0);
 app.dialog.finishChecking();assert.equal(app.actions.at(-1),'show conflict');
});
test('a quick error waits for the saved editor to finish closing before showing conflict details',()=>{
 const app=harness(true);app.dialog.checking({moving:match});app.dialog.show(report);
 assert.deepEqual(app.actions,['hide other']);
 app.callbacks.get('other:hidden.bs.modal')();assert.deepEqual(app.actions,['hide other','show conflict']);
 assert.equal(app.title.textContent,'Why this match cannot move');assert.equal(app.timers.size,0);
});
