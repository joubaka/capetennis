const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const {html,mount}=require('../../public/js/schedule-move-conflicts.js');
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
 const callbacks=new Map(),actions=[],body={innerHTML:''};
 const element={addEventListener:(name,callback)=>callbacks.set(name,callback)};
 const other={addEventListener:(name,callback)=>callbacks.set('other:'+name,callback)};
 const doc={getElementById:id=>id==='scheduleMoveConflictModal'?element:body,querySelector:()=>openOther?other:null};
 const bootstrap={Modal:{getOrCreateInstance:target=>({show:()=>actions.push(target===element?'show conflict':'show other'),hide:()=>actions.push(target===element?'hide conflict':'hide other')})}};
 const dialog=mount({document:doc,bootstrap,onSuggestion:slot=>actions.push(slot),onReview:key=>actions.push(key)});
 return{dialog,actions,callbacks,body};
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
