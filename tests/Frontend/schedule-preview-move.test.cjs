const fs=require('node:fs');
const vm=require('node:vm');
const test=require('node:test');
const assert=require('node:assert/strict');
const source=fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php','utf8');

function mover(reject=false) {
 const controls=new Map(['schedule-status','venue-slot-grids','schedule-duration','schedule-gap','schedule-rest','schedule-daily-limit','round-progression'].map(id=>[id,{value:'30',classList:{add(){},remove(){}}}]));
 const calls=[],toasts=[],statuses=[];
 const key='individual:20',slot={dataset:{time:'2026-10-30 11:00:00',venue:'7',court:'2'}};
 const result={input:{preview_moves:[{fixture_key:key,scheduled_at:slot.dataset.time,venue_id:7,court:'2'}],preview_frozen_keys:['individual:99']},venues:[{id:7,name:'School courts'}]};
 const context={lastScheduleResult:{matches:[{fixture_key:key}]},fixtureKey:row=>row.fixture_key,payload:{day_batch:true},revision:'a'.repeat(64),previewUrl:'/preview',manualAssignmentUrl:'/manual',
  dayBatchPayload:{day_batch:true},programmePayload:null,selectedManualFixture:key,
  document:{getElementById:id=>controls.get(id)},setStatus:(element,message,tone)=>statuses.push([message,tone]),
  notify:(message,tone,pending)=>{toasts.push([message,tone,pending]);return()=>toasts.push(['cleared']);},
  post:async(url,body)=>{calls.push([url,body]);if(reject)throw new Error('Required player rest is missing.');return result;},
  render:()=>{},fixtureRef:()=>{throw new Error('An unsaved preview move must not call the saved manual path.');}};
 vm.runInNewContext(source.slice(source.indexOf('  const placeMatchManually ='),source.indexOf("  ['programme-daily-limit'"))+';globalThis.move=placeMatchManually;',context);
 return {context,key,slot,calls,toasts,statuses};
}

test('suggested drop updates validated preview and never posts an immediate saved assignment',async()=>{
 const app=mover();await app.context.move(app.key,app.slot);
 assert.equal(app.calls.length,1);assert.equal(app.calls[0][0],'/preview');
 assert.equal(app.calls[0][1].preview_move.fixture_key,'individual:20');
 assert.equal(app.calls[0][1].preview_move.venue_id,7);assert.equal(app.calls[0][1].preview_move.court,'2');
 assert.equal(app.context.payload.preview_moves.length,1);assert.equal(app.context.dayBatchPayload.preview_moves.length,1);
 assert.equal(app.context.payload.preview_frozen_keys[0],'individual:99');
 assert.ok(app.toasts.some(([message,tone])=>tone==='success'&&message.includes('School courts, Court 2')&&message.includes('save')));
 assert.deepEqual(app.toasts.at(-1),['cleared']);
});

test('rejected suggestion leaves payload unchanged and reports the error with pending cleanup',async()=>{
 const app=mover(true);await app.context.move(app.key,app.slot);
 assert.equal(app.context.payload.preview_moves,undefined);
 assert.ok(app.toasts.some(([message,tone])=>tone==='danger'&&message.includes('player rest')));
 assert.ok(app.statuses.some(([message,tone])=>tone==='danger'&&message.includes('player rest')));
 assert.deepEqual(app.toasts.at(-1),['cleared']);
});

test('an unscheduled picker match uses validated preview insertion rather than immediate saving',async()=>{
 const app=mover();app.context.lastScheduleResult.unscheduled=app.context.lastScheduleResult.matches;app.context.lastScheduleResult.matches=[];
 await app.context.move(app.key,app.slot);assert.equal(app.calls[0][0],'/preview');assert.equal(app.calls[0][1].preview_move.fixture_key,app.key);
});

test('saved drop sends its exact preview context and renders the atomic preserved response without replanning',async()=>{
 const app=mover(),oldPayload=app.context.payload,rendered=[];
 app.context.lastScheduleResult.matches=[];app.context.lastScheduleResult.unscheduled=[];
 app.context.fixtureRef=()=>({fixture_id:20});app.context.dayBatchState={updateBooking(){}};
 app.context.renderPreservedManualPreview=saved=>{rendered.push(saved);return true;};
 app.context.post=async(url,body)=>{app.calls.push([url,body]);return {message:'Saved',preview:{matches:[]},preview_options:{preview_moves:[]}};};
 app.context.fixedBookingPreviewPayload=()=>{throw new Error('Saved contextual moves must not create a new automatic plan.');};
 await app.context.move(app.key,app.slot);
 assert.equal(app.calls.length,1);assert.equal(app.calls[0][0],'/manual');assert.equal(app.calls[0][1].preview_context,oldPayload);assert.equal(app.calls[0][1].preview_revision,'a'.repeat(64));assert.equal(rendered.length,1);
});

test('preserved saved response carries exact slots and frozen matches into subsequent save state',()=>{
 const rendered=[],context={payload:{},dayBatchPayload:{},programmePayload:null,render:value=>rendered.push(value),document:{getElementById:()=>({})},setStatus(){}};
 vm.runInNewContext(source.slice(source.indexOf('  const renderPreservedManualPreview ='),source.indexOf('  const placeMatchManually ='))+';globalThis.accept=renderPreservedManualPreview;',context);
 const options={preview_moves:[{fixture_key:'individual:2',scheduled_at:'2026-10-30 08:00',venue_id:7,court:'2'}],preview_frozen_keys:['individual:3']};
 const saved={message:'Saved',preview:{matches:[]},preview_options:options};
 assert.equal(context.accept(saved),true);assert.equal(context.payload,options);assert.equal(context.dayBatchPayload.preview_moves[0].scheduled_at,'2026-10-30 08:00');assert.equal(context.dayBatchPayload.preview_frozen_keys[0],'individual:3');assert.equal(rendered[0],saved.preview);
});

test('native card drag transfers typed fixture identity and drop uses destination venue and court',()=>{
 const listeners={},moves=[],data=new Map(),card={dataset:{manualFixture:'team:20'}},slot={dataset:{venue:'9',court:'1'},classList:{remove(){}}};
 const context={slotGrids:{addEventListener:(name,fn)=>listeners[name]=fn},selectedManualFixture:null,placeMatchManually:(key,target)=>moves.push([key,target])};
 vm.runInNewContext(source.slice(source.indexOf("  slotGrids.addEventListener('dragstart'"),source.indexOf('  const refreshVenuePreview =')),context);
 const transfer={setData:(type,value)=>data.set(type,value),getData:type=>data.get(type)};
 listeners.dragstart({target:{closest:()=>card},dataTransfer:transfer});
 assert.equal(data.get('text/plain'),'team:20');assert.equal(transfer.effectAllowed,'move');
 listeners.drop({target:{closest:()=>slot},dataTransfer:transfer,preventDefault(){}});
 assert.equal(moves[0][0],'team:20');assert.equal(moves[0][1].dataset.venue,'9');assert.equal(moves[0][1].dataset.court,'1');
});

test('toasts map danger to error, retain info and clear a pending toast',()=>{
 const calls=[],context={window:{toastr:{error:message=>calls.push(['error',message]),info:(message,title,options)=>{calls.push(['info',message,options]);return 'pending';},clear:toast=>calls.push(['clear',toast])}},Date};
 vm.runInNewContext(source.slice(source.indexOf('  let lastToast ='),source.indexOf('  const updateScheduleActivity ='))+';globalThis.notify=notify;',context);
 context.notify('Cannot move','danger');const clear=context.notify('Checking move','info',true);clear();
 assert.equal(calls[0][0],'error');assert.equal(calls[1][0],'info');assert.equal(calls[1][2].timeOut,0);assert.equal(calls[1][2].escapeHtml,true);assert.deepEqual(calls[2],['clear','pending']);
});

test('AJAX render preserves collapsed venues and scroll while opening newly shown venues',()=>{
 const list=[{dataset:{previewVenue:'7'},open:false},{dataset:{previewVenue:'8'},open:true}];
 const grid=[{dataset:{previewVenue:'7'},open:true}];
 const display={scrollTop:180},scrolls=[];
 const context={Map,document:{getElementById:id=>id==='schedule-display'?display:{querySelectorAll:()=>id==='venue-timelines'?list:grid}},
  window:{scrollX:0,scrollY:410,requestAnimationFrame:fn=>fn(),scrollTo:position=>scrolls.push(position)}};
 vm.runInNewContext(source.slice(source.indexOf('  const capturePreviewLayout ='),source.indexOf('  function render(result)'))+';globalThis.capture=capturePreviewLayout;globalThis.restore=restorePreviewLayout;',context);
 const saved=context.capture();
 list[0].open=true;grid[0].open=false;grid.push({dataset:{previewVenue:'9'},open:false});display.scrollTop=0;
 context.restore(saved);
 assert.equal(list[0].open,false);assert.equal(list[1].open,true);assert.equal(grid[0].open,true);assert.equal(grid[1].open,true);
 assert.equal(display.scrollTop,180);assert.equal(scrolls[0].top,410);
});

test('slot picker exposes selected unsaved candidates and checks them only on selection',async()=>{
 const controls=new Map(),rendered=[],slot={dataset:{venue:'7',court:'2',time:'2026-10-30 11:00'}};
 const get=id=>{if(!controls.has(id))controls.set(id,{textContent:'',value:'',innerHTML:'',classList:{add(){},remove(){}},addEventListener(){}});return controls.get(id);};
 const suggested={fixture_key:'individual:20'};
 const context={pendingManualSlot:null,lastScheduleResult:{venues:[{id:7,name:'School'}]},document:{getElementById:get},matchPickerSearch:get('search'),matchPickerList:get('list'),matchPickerEmpty:get('empty'),matchPickerModal:{addEventListener(){}},
  bootstrap:{Modal:{getOrCreateInstance:()=>({show(){}})}},formatSlotTime:()=> '11:00',asDate:value=>value,pickerCandidates:()=>[suggested],canUseCourt:()=>true,
  renderPickerCandidates:rows=>rendered.push(rows),filterMatchPicker(){},post:()=>{throw new Error('Picker must not reject unsaved day courts through saved-only options.');}};
 vm.runInNewContext(source.slice(source.indexOf('  const openMatchPicker ='),source.indexOf("  matchPickerSearch.addEventListener('input'"))+';globalThis.open=openMatchPicker;',context);
 await context.open(slot);assert.equal(rendered[0][0],suggested);assert.equal(context.pendingManualSlot,slot);
});
