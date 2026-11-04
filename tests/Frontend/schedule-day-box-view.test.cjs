const fs=require('node:fs'),vm=require('node:vm'),test=require('node:test'),assert=require('node:assert/strict');
const source=fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php','utf8');
test('optional view switches back without mutation and invalidates in-flight previews',()=>{
 const elements=new Map(),classes=new Set(['day-batch-mode']);const get=id=>{if(!elements.has(id))elements.set(id,{hidden:false,textContent:'Original instructions',dataset:{},setAttribute(key,value){this[key]=value;}});return elements.get(id);};
 let invalidations=0;const context={document:{getElementById:get},workspace:{dataset:{},classList:{toggle(name,enabled){enabled?classes.add(name):classes.delete(name);},remove(name){classes.delete(name);}}},individualSelection:null,programmeDayBoxMode:false,programmeCreateLabel:'Create preview',dayBatchPayload:{},payload:{},programmePayload:{},programmeRefreshVersion:5,invalidatePreview(){invalidations++;},showWorkflowStep(){}};
 vm.createContext(context);const start=source.indexOf('  const setDayBoxPlanner =');const end=source.indexOf("  document.getElementById('toggle-day-box-planner')?.addEventListener",start);vm.runInContext(source.slice(start,end)+';globalThis.switchView=setDayBoxPlanner;',context);
 context.switchView(true);assert.equal(context.programmeDayBoxMode,true);assert.equal(classes.has('day-batch-mode'),false);assert.equal(get('day-batch-workflow').hidden,true);assert.equal(get('programme-create').textContent,'Generate suggested schedule');assert.equal(context.programmeRefreshVersion,6);assert.equal(context.payload,null);assert.equal(get('toggle-day-box-planner')['aria-pressed'],'true');
 context.switchView(false);assert.equal(classes.has('day-batch-mode'),true);assert.equal(get('day-batch-workflow').hidden,false);assert.equal(get('programme-drag-instructions').textContent,'Original instructions');assert.equal(context.programmeRefreshVersion,7);assert.equal(invalidations,2);
});

function initialWorkspace({team=false, scoped=false, hash='', wizardHidden=false}={}) {
 const elements=new Map();const get=id=>{if(!elements.has(id))elements.set(id,{hidden:id==='programme-wizard'&&wizardHidden,open:false,value:'',events:[],dispatchEvent(event){this.events.push(event.type);}});return elements.get(id);};
 const switches=[],steps=[];
 const context={workspace:{dataset:{teamProgrammeDefault:String(team)}},window:{location:{hash}},document:{getElementById:get},setDayBoxPlanner(value){switches.push(value);},showWorkflowStep(step){steps.push(step);},Event:class{constructor(type){this.type=type;}}};
 const start=source.indexOf('  const initializeScheduleWorkspace =');
 const code=source.slice(start,source.indexOf('\n  initializeScheduleWorkspace();',start)).replace("@json(request()->has('draw_ids'))",String(scoped));
 vm.runInNewContext(code+';initializeScheduleWorkspace();',context);
 return {get,switches,steps};
}

test('team event root opens age-group scheduling and bulk ages without requesting a preview or saving',()=>{
 const app=initialWorkspace({team:true});
 assert.deepEqual(app.switches,[true]);assert.equal(app.get('programme-age').value,'bulk');assert.deepEqual(app.get('programme-age').events,['change']);assert.equal(app.get('advanced-scheduling').open,false);
});

test('explicit team draw selection is never replaced by bulk-all on entry',()=>{
 const app=initialWorkspace({team:true,scoped:true});
 assert.deepEqual(app.switches,[true]);assert.equal(app.get('programme-age').value,'');assert.deepEqual(app.get('programme-age').events,[]);
});

test('individual, fixed-day and unavailable age programmes retain one-day entry',()=>{
 for(const wizardHidden of [false,true]) {
  const app=initialWorkspace({wizardHidden});assert.deepEqual(app.switches,[]);assert.deepEqual(app.get('programme-age').events,[]);
 }
 const linked=initialWorkspace({hash:'#round-days'});assert.deepEqual(linked.switches,[true]);assert.equal(linked.get('programme-age').value,'');
 const unavailable=initialWorkspace({hash:'#round-days',wizardHidden:true});assert.deepEqual(unavailable.switches,[]);
});
test('day-constrained planner entry navigates to unscoped workspace',()=>{
 const start=source.indexOf('@if($planningDate)',source.indexOf('<p class="text-muted mb-0 day-batch-intro"'));const end=source.indexOf('@else',start);const link=source.slice(start,end);
 assert.match(link,/event-venue-schedule.index/);assert.match(link,/#round-days/);assert.doesNotMatch(link,/'date'|planningScope|replan_saved/);
});
