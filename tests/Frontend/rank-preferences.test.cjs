const fs=require('node:fs');
const test=require('node:test');
const assert=require('node:assert/strict');
const source=fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php','utf8');
const selectsSource=source.slice(source.indexOf('  const rankSelects ='), source.indexOf('  const rankVenueOptions ='));
const venueSource=source.slice(source.indexOf('  const rankVenueOptions ='), source.indexOf('  const updateRankReview ='));

function selectHarness(withPlugin=true) {
  const controls=new Map(), dispatched=[];
  const draws={classList:{contains:name=>name==='rank-band-draws'}}, venue={classList:{contains:()=>false}};
  const jquery=select=>{
    if(typeof select==='string')return select;
    if(!controls.has(select))controls.set(select,{
      active:false, settings:null, handler:null, destroyed:0,
      select2(argument){if(argument==='destroy'){this.active=false;this.destroyed++;}else{this.active=true;this.settings=argument;}return this;},
      hasClass(){return this.active;}, off(){this.handler=null;return this;},
      on(name,handler){this.handler=handler;return this;},
    });
    return controls.get(select);
  };
  jquery.fn=withPlugin?{select2(){}}:{};
  for(const select of [draws,venue])select.dispatchEvent=event=>{dispatched.push(event);jquery(select).handler({originalEvent:event});};
  const api=new Function('window','Event',`${selectsSource}; return {initializeRankSelects,destroyRankSelects};`)({jQuery:jquery},Event);
  return{...api,draws,venue,controls,dispatched,container:{querySelectorAll:()=>[draws,venue]}};
}

test('Select2 changes reach native handlers once while multiple draw selection stays open',()=>{
  const api=selectHarness();api.initializeRankSelects(api.container);
  const drawControl=api.controls.get(api.draws), venueControl=api.controls.get(api.venue);
  assert.equal(drawControl.settings.closeOnSelect,false);
  assert.equal(venueControl.settings.closeOnSelect,true);
  assert.equal(drawControl.settings.width,'100%');
  assert.equal(drawControl.settings.dropdownParent,'#rank-preferences');
  drawControl.handler({});
  assert.equal(api.dispatched.length,1);
  assert.equal(api.dispatched[0].bubbles,true);
  drawControl.handler({originalEvent:new Event('change')});
  assert.equal(api.dispatched.length,1,'Native changes must not recursively dispatch.');
});

test('dynamic row teardown removes handlers and destroys Select2 before rebuilding',()=>{
  const api=selectHarness();api.initializeRankSelects(api.container);api.destroyRankSelects(api.container);
  for(const control of api.controls.values()){assert.equal(control.destroyed,1);assert.equal(control.handler,null);}
  api.initializeRankSelects(api.container);
  for(const control of api.controls.values()){assert.equal(control.active,true);assert.equal(typeof control.handler,'function');}
});

test('native rank selectors remain usable if Select2 assets are unavailable',()=>{
  const api=selectHarness(false);api.initializeRankSelects(api.container);api.destroyRankSelects(api.container);
  assert.equal(api.controls.size,0);
});

test('preferred venues use the common assignments and explain empty intersections',()=>{
  const venues=[{id:1,name:'Shared Club',draws:[10,11]},{id:2,name:'Under 11 Club',draws:[11]}];
  const allowed=rule=>venues.filter(venue=>rule.draw_ids.length&&rule.draw_ids.every(id=>venue.draws.includes(id)));
  const api=new Function('allowedRankVenues','escapeHtml',`${venueSource};return{rankVenueOptions,rankVenueHint};`)(allowed,value=>String(value));
  const common={draw_ids:[10,11],venue_id:1};
  assert.match(api.rankVenueOptions(common),/Shared Club/);
  assert.doesNotMatch(api.rankVenueOptions(common),/Under 11 Club/);
  assert.match(api.rankVenueHint(common),/every selected draw/);
  assert.match(api.rankVenueOptions({draw_ids:[11],venue_id:2}),/Under 11 Club/);
  assert.match(api.rankVenueHint({draw_ids:[10,99],venue_id:0}),/No common venue/);
  assert.match(api.rankVenueHint({draw_ids:[],venue_id:0}),/Select draws/);
});

test('new and preset rank bands start with empty draws while saved selections remain intact',()=>{
  const handlers=new Map();
  let rendered;
  const saved={draw_ids:[10,11],min_rank:1,max_rank:4,venue_id:1};
  const addSource=source.slice(source.indexOf("  document.getElementById('add-rank-band').addEventListener"),source.indexOf("  document.getElementById('cross-band-policy').addEventListener"));
  new Function('document','readRankRules','renderRankRows','markScheduleDirty',addSource)(
    {getElementById:id=>({addEventListener:(event,handler)=>handlers.set(id,handler)})},
    ()=>[saved],rows=>{rendered=rows;},()=>{});
  handlers.get('add-rank-band')();
  assert.deepEqual(rendered[0],saved);
  assert.deepEqual(rendered[1].draw_ids,[]);
  handlers.get('default-rank-bands')();
  assert.deepEqual(rendered[0],saved);
  assert.deepEqual(rendered.slice(1).map(rule=>rule.draw_ids),[[],[],[]]);
});

test('unfinished bands stay separate and are submitted for required-draw validation',()=>{
  const helperSource=source.slice(source.indexOf('  const coalesceRankRules ='),source.indexOf('  const rememberRankRules ='));
  const rules=[{draw_ids:[],min_rank:1,max_rank:4,venue_id:0},{draw_ids:[],min_rank:1,max_rank:4,venue_id:0},
    {draw_ids:[],min_rank:5,max_rank:6,venue_id:1}];
  const api=new Function('readRankRules','document',`${helperSource};return{coalesceRankRules,applicableRankRules};`)(()=>rules,{querySelector:()=>({checked:true})});
  assert.deepEqual(api.coalesceRankRules(rules),rules,'Repeated Add must retain separate editable blank cards.');
  assert.deepEqual(api.applicableRankRules(),rules,'Incomplete draw selections must reach validation rather than silently disappear.');
});

test('unfinished bands survive remembering and reloading the selected draw scope',()=>{
  const rememberSource=source.slice(source.indexOf('  const rememberRankRules ='),source.indexOf('  const allowedRankVenues ='));
  const scopeSource=source.slice(source.indexOf('  const loadRankScope ='),source.indexOf("  document.querySelectorAll('.draw-choice').forEach",source.indexOf('  const loadRankScope =')));
  const helperSource=source.slice(source.indexOf('  const coalesceRankRules ='),source.indexOf('  const applicableRankRules ='));
  let rendered;
  const blank={draw_ids:[],min_rank:1,max_rank:4,venue_id:0};
  const api=new Function('readRankRules','selectedRankDraws','renderRankRows',
    `let allRankRules=[],rankScopeIds=[10];${helperSource}${rememberSource}${scopeSource};return{rememberRankRules,loadRankScope};`)(
      ()=>[blank],()=>[{id:11}],rules=>{rendered=rules;});
  api.rememberRankRules();api.loadRankScope();
  assert.deepEqual(rendered,[blank]);
});

test('blank cards render no selected draw options and saved cards retain their selections',()=>{
  const renderSource=source.slice(source.indexOf('  const renderRankRows ='),source.indexOf('  const loadRankScope ='));
  const nodes=new Map();
  const document={getElementById:id=>{if(!nodes.has(id))nodes.set(id,{innerHTML:'',disabled:false});return nodes.get(id);}};
  const render=new Function('document',`let rankScopeIds=[],rankBandSequence=0;
    const selectedRankDraws=()=>[{id:10,name:'Boys'},{id:11,name:'Girls'}],coalesceRankRules=rules=>rules;
    const updateRankReview=()=>{},destroyRankSelects=()=>{},initializeRankSelects=()=>{},escapeHtml=String;
    const rankVenueOptions=()=>'<option value="">Choose assigned venue</option>',rankVenueHint=()=>'';
    ${renderSource};return renderRankRows;`)(document);
  render([{draw_ids:[],min_rank:1,max_rank:4,venue_id:0}]);
  const blank=nodes.get('rank-band-rows').innerHTML;
  assert.doesNotMatch(blank,/<option[^>]+selected/);
  assert.match(blank,/0 selected/);
  render([{draw_ids:[11],min_rank:1,max_rank:4,venue_id:0}]);
  const saved=nodes.get('rank-band-rows').innerHTML;
  assert.match(saved,/<option value="11" selected>Girls/);
  assert.doesNotMatch(saved,/<option value="10" selected/);
});
