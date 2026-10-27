const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
const {createState,buildPayload,mount}=require('../../public/js/schedule-day-batch.js');
const days=['2026-10-30','2026-10-31'];
const group=(label,...matches)=>({label,count:matches.length,matches:matches.map(match=>typeof match==='string'?{key:match}:match)});
const draws=[
  {id:1,name:'Boys',day_round_details:{1:[group('Main draw','individual:1'),group('Feed-in','individual:2')],2:[group('Positions 3–4','individual:3')]}},
  {id:2,name:'Girls',day_round_details:{1:[group('Main draw','individual:4')],2:[group('Final',{key:'individual:5',scheduled_at:days[1]+' 09:00:00'})]}},
  {id:3,name:'Team',is_team:true,day_round_details:{1:[group('Team round','team:1')]}},
  {id:4,locked:true,day_round_details:{1:[group('Locked','individual:6')]}},
  {id:5,name:'Not generated',day_round_details:{}},
];
const venues=[{id:7,court_list:[{label:'1'},{label:'2'}]},{id:8,court_labels:['A','B']}];
function state(){const value=createState(structuredClone(draws),structuredClone(venues),days);value.setCourt(7,'2',true);return value;}

test('day batch selects one, several or all available draws without adding locked or empty draws',()=>{
 const s=state();s.selectDraw(1,true);assert.deepEqual(s.selectedDrawIds,[1]);
 s.selectAll();assert.deepEqual(s.selectedDrawIds,[1,2,3]);
 s.clear();assert.deepEqual(s.selectedDrawIds,[]);assert.equal(s.selectedRounds.size,0);assert.equal(s.selectedFixtures.size,0);
 assert.throws(()=>buildPayload(s),/draw/i);
});
test('draw order is preserved in the request and modes do not mutate selection',()=>{
 const s=state();s.selectAll();s.moveDraw(3,'up');s.moveDraw(3,'up');s.moveDraw(3,'up');
 assert.deepEqual(s.selectedDrawIds,[3,1,2]);
 for(const mode of ['sequential','mixed']){
  const p=buildPayload(s,{draw_order_mode:mode,gender_waves:'boys_then_girls'});
  assert.deepEqual(p.draw_order,[3,1,2]);assert.deepEqual(p.draw_ids,[3,1,2]);assert.equal(p.draw_order_mode,mode);assert.equal(p.gender_waves,'combined');
 }
 assert.throws(()=>buildPayload(s,{draw_order_mode:'unknown'}),/order/i);
});
test('round and positional-group selections keep explicit namespaced match keys',()=>{
 const s=state();s.selectDraw(1,true);s.selectDraw(3,true);s.selectRound(1,2,true);s.selectGroup(1,1,1,false);
 const p=buildPayload(s);
 assert.deepEqual(p.draw_rounds,[{draw_id:1,rounds:[1,2]},{draw_id:3,rounds:[1]}]);
 assert.deepEqual(p.selected_fixture_keys,['individual:1','individual:3','team:1']);
 s.selectRound(1,2,false);assert.ok(!buildPayload(s).selected_fixture_keys.includes('individual:3'));
});
test('removing every match in a selected round never silently omits that draw or selects all matches',()=>{
 const s=state();s.selectDraw(1,true);s.selectDraw(2,true);s.selectGroup(1,1,0,false);s.selectGroup(1,1,1,false);
 assert.throws(()=>buildPayload(s),/match|round/i);
 s.selectDraw(2,false);assert.throws(()=>buildPayload(s),/match|round/i);
});
test('day changes filter other-day bookings and retain explicit physical court choices',()=>{
 const s=state();s.selectDraw(2,true);s.selectRound(2,2,true);
 assert.ok(!s.selectedFixtures.get('2:2').has('individual:5'));
 s.setDay(days[1]);s.setCourt(8,'A',true);s.setCourt(8,'invalid',true);
 const p=buildPayload(s,{duration:60,player_rest:30,court_gap:5,limit_daily_matches:true});
 assert.equal(p.full_day_scope,days[1]);assert.equal(p.start,days[1]+' 08:00');assert.equal(p.end,days[1]+' 18:00');
 assert.deepEqual(p.day_court_labels,{7:['2'],8:['A']});assert.ok(p.selected_fixture_keys.includes('individual:5'));
 assert.equal(p.day_batch,true);assert.equal(p.allow_partial,false);assert.equal(p.reschedule_existing,false);assert.equal(p.limit_daily_matches,true);
 assert.throws(()=>s.setDay('not-a-day'),/day/i);
});
test('restoring an earlier draw subset does not erase matches in newly selected draws',()=>{
 const s=state();s.selectDraw(1,true);s.selectDraw(2,true);
 s.restore({full_day_scope:days[0],draw_ids:[1,3],draw_order:[3,1],draw_rounds:[{draw_id:1,rounds:[1]}],selected_fixture_keys:['individual:1'],day_court_labels:{7:['2']}});
 assert.deepEqual(s.selectedDrawIds,[1,2],'saved team draw must not be resurrected');
 assert.deepEqual(buildPayload(s).selected_fixture_keys,['individual:1','individual:4'],'new girl draw retains its selected matches');
});
test('payload cannot preview without selected courts or rounds and replanning is explicit',()=>{
 const s=state();s.selectDraw(1,true);s.setCourt(7,'2',false);assert.throws(()=>buildPayload(s),/court/i);
 s.setCourt(7,'1',true);s.selectRound(1,1,false);assert.throws(()=>buildPayload(s),/round/i);
 s.selectRound(1,1,true);assert.equal(buildPayload(s,{reschedule_existing:true}).reschedule_existing,true);
});
test('moving a saved match to another day updates eligibility without changing the chosen day or other selections',()=>{
 const s=state();s.selectDraw(1,true);s.selectRound(1,2,true);
 s.updateBooking('individual:1',days[1]+' 09:00:00');
 assert.equal(s.day,days[0]);assert.deepEqual(s.selectedDrawIds,[1]);
 assert.deepEqual([...s.selectedRounds.get(1)],[1,2]);
 assert.deepEqual(buildPayload(s).selected_fixture_keys,['individual:2','individual:3']);
 assert.equal(s.matches(1,1).find(match=>match.key==='individual:1').scheduled_at,days[1]+' 09:00:00');
 s.setDay(days[1]);assert.ok(buildPayload(s).selected_fixture_keys.includes('individual:1'));
});
test('booking updates distinguish team and individual IDs and retain same-day match selections',()=>{
 const s=state();s.selectAll();
 s.updateBooking('team:1',days[1]+' 10:00:00');
 assert.ok(s.selectedFixtures.get('1:1').has('individual:1'),'same numeric individual ID remains selected');
 assert.ok(!s.selectedFixtures.get('3:1').has('team:1'));
 s.updateBooking('individual:2',days[0]+' 11:00:00');
 assert.ok(s.selectedFixtures.get('1:1').has('individual:2'));
});

function mounted() {
 const elements=new Map(),sections=new Map();
 const field=(value='')=>({value,checked:false});
 venues.forEach(venue=>{
  const fields={'.day-batch-venue-open':field(),'.day-batch-venue-close':field(),'.day-batch-split':field()};
  const rows=(venue.court_labels||venue.court_list.map(court=>court.label)).map((court,index)=>{
   const controls={'.day-batch-court-group':field(index?'B':'A'),'.day-batch-court-open':field(),'.day-batch-court-close':field()};
   return {dataset:{dayCourtHours:court},controls,querySelector:key=>controls[key]};
  });
  sections.set(venue.id,{dataset:{dayVenue:String(venue.id)},fields,rows,querySelector:key=>fields[key],querySelectorAll:()=>rows});
 });
 const get=id=>{
  if(!elements.has(id)){
   const element={value:'',checked:false,listeners:{},addEventListener(name,fn){this.listeners[name]=fn;},scrollIntoView(){},
   querySelector(selector){const match=selector.match(/data-day-venue="(\d+)"/);return match?sections.get(Number(match[1])):null;},
   querySelectorAll(selector){return selector.includes('data-day-venue')?[...sections.values()]:[];}};
   let html='';
   Object.defineProperty(element,'innerHTML',{get:()=>html,set:value=>{
    html=value;
    if(id==='day-batch-venues')sections.forEach(section=>{
     const content=value.match(new RegExp('data-day-venue="'+section.dataset.dayVenue+'"[^>]*>([\\s\\S]*?)</section>'))?.[1]||'';
     Object.entries(section.fields).forEach(([selector,control])=>{
      const tag=content.match(new RegExp('<input[^>]*class="[^"]*'+selector.slice(1)+'[^"]*"[^>]*>'))?.[0]||'';
      control.value=tag.match(/value="([^"]*)"/)?.[1]||'';control.checked=/\bchecked\b/.test(tag);
     });
    });
   }});
   elements.set(id,element);
  }
  return elements.get(id);
 };
 const saved={full_day_scope:days[0],draw_ids:[1],draw_order:[1],draw_rounds:[{draw_id:1,rounds:[1]}],selected_fixture_keys:['individual:1','individual:2'],day_court_labels:{7:['1','2']},duration:60,player_rest:30,court_gap:5,start:days[0]+' 08:00:00',end:days[0]+' 18:00:00'};
 const s=mount({document:{getElementById:get},draws,venues,dates:days,initialDrawIds:[1],savedByDay:{[days[0]]:saved}});
 return {s,get,sections,change(input){get('day-batch-workflow').listeners.change({target:input});}};
}
test('selecting another venue preserves edited venue hours and court groups',()=>{
 const app=mounted(),a=app.sections.get(7);
 a.fields['.day-batch-venue-open'].value='09:15';a.fields['.day-batch-venue-close'].value='17:00';a.fields['.day-batch-split'].checked=true;
 a.rows[1].controls['.day-batch-court-open'].value='09:30';
 app.change({value:'8',checked:true,matches:selector=>selector==='.day-batch-venue',closest:()=>app.sections.get(8)});
 assert.equal(a.fields['.day-batch-venue-open'].value,'09:15');
 assert.equal(a.fields['.day-batch-venue-close'].value,'17:00');
 assert.equal(a.rows[1].controls['.day-batch-court-open'].value,'09:30');
 assert.equal(a.fields['.day-batch-split'].checked,true);
});
test('changing day never carries an earlier opt-in to replan saved matches',()=>{
 const app=mounted();app.get('day-batch-replan').checked=true;
 app.change({id:'day-batch-date',value:days[1],matches:()=>false});
 assert.equal(app.get('day-batch-replan').checked,false);
});
test('normal day preview reveals review even after legacy selection hides advanced controls',async()=>{
 const source=fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php','utf8');
 const start=source.indexOf('    onPreview:async config => {');
 const end=source.indexOf('\n    },',start);
 assert.ok(start>=0&&end>start);
 const elements=new Map();
 const get=id=>{
  if(!elements.has(id))elements.set(id,{hidden:true,open:false,innerHTML:'',scrollIntoView(){}});
  return elements.get(id);
 };
 let rendered=false;
 const context={document:{getElementById:get},workspace:{classList:{add(){}}},syncDayTiming(){},post:async()=>({matches:[]}),previewUrl:'/preview',
  showWorkflowStep(){},notify(){},render(){rendered=true;},programmePayload:{},dayBatchPayload:null,payload:null};
 vm.runInNewContext(source.slice(start,end).replace('    onPreview:async config => {','globalThis.preview=async config => {')+'\n};',context);
 await context.preview({day_batch:true,full_day_scope:days[0]});
 assert.equal(get('advanced-scheduling').hidden,false);
 assert.equal(get('advanced-scheduling').open,true);
 assert.equal(rendered,true);
});
test('saving a day stays disabled until every selected match fits while legacy partial saves remain available',()=>{
 const source=fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php','utf8');
 const assignment=source.match(/    applyPreview\.disabled =[^\n]+/)[0];
 const context={applyPreview:{},hasSuggestions:true,result:{input:{day_batch:true},unscheduled:[{}]}};
 vm.runInNewContext(assignment,context);assert.equal(context.applyPreview.disabled,true);
 context.result.unscheduled=[];vm.runInNewContext(assignment,context);assert.equal(context.applyPreview.disabled,false);
 context.result={input:{allow_partial:true},unscheduled:[{}]};vm.runInNewContext(assignment,context);assert.equal(context.applyPreview.disabled,false);
});
