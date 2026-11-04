const test=require('node:test'),assert=require('node:assert/strict');
const {payload,mount}=require('../../public/js/team-age-venue-setup.js');
test('age setup uses original roster bands without pair conversion and no client draw IDs',()=>{
 const value=payload('10',{revision:'a'.repeat(64),draw_ids:[1,2]},[{venue_id:7,court_labels:['1','3']}],[{min_rank:3,max_rank:4,venue_id:7}]);
 assert.deepEqual(value.rank_venue_preferences,[{min_rank:3,max_rank:4,venue_id:7}]);assert.equal(value.draw_ids,undefined);assert.equal(value.age,10);
});
test('missing courts overlapping bands and outside venues are blocked before posting',()=>{
 const state={revision:'x',draw_ids:[1]},courts=[{venue_id:7,court_labels:['1']}],band={min_rank:1,max_rank:4,venue_id:7};
 assert.throws(()=>payload(10,state,[],[]),/court/);
 assert.throws(()=>payload(10,state,courts,[band,{...band,min_rank:4,max_rank:5}]),/overlap/);
 assert.throws(()=>payload(10,state,courts,[{...band,venue_id:8}]),/selected venue/);
});
function harness(fail=false){
 const elements=new Map(),get=id=>{if(!elements.has(id))elements.set(id,{value:'',textContent:'',hidden:false,disabled:false,listeners:{},addEventListener(name,fn){this.listeners[name]=fn;},querySelectorAll(){return[];},set innerHTML(value){this.html=value;},get innerHTML(){return this.html||'';}});return elements.get(id);};
 const doc={getElementById:get,createElement(){throw Error('No bands in this scenario');}};
 get('age-venue-setup').querySelectorAll=()=>[get('age-setup-save')];
 const age=get('programme-age');age.value='10';
 let selected=[{value:'7',dataset:{name:'Ten courts'}}],resolve,posted;
 const courts=get('age-setup-courts');courts.querySelectorAll=selector=>selector==='[data-age-venue]:checked'?selected:selector.includes(':checked')?[{value:'1'}]:[];
 const dirty=[];const states={'10':{draw_ids:[1],revision:'a'.repeat(64),locked:false,setup:null},'12':{draw_ids:[2],revision:'b'.repeat(64),locked:false,setup:null}};
 const controller=mount({document:doc,states,venues:[{id:7,name:'Ten courts',labels:['1']},{id:8,name:'Twelve courts',labels:['1']}],onDirty:value=>dirty.push(value),onSaved(){},post:body=>{posted=body;return new Promise((yes,no)=>{resolve=()=>fail?no(Error('Save failed')):yes({state:{...states['10'],setup:body.age_setup},round_venue_setups:[],message:'Saved'});});}});
 return{get,age,courts,controller,dirty,states,setSelected:value=>selected=value,start:()=>get('age-setup-save').listeners.click(),complete:()=>resolve(),posted:()=>posted};
}
test('dirty age drafts survive switching to another age and bulk',()=>{
 const app=harness();app.get('age-venue-setup').listeners.change();assert.equal(app.controller.isDirty(),true);
 app.age.value='12';app.age.listeners.change();app.age.value='bulk';app.age.listeners.change();
 assert.equal(app.get('age-venue-setup').hidden,true);assert.equal(app.get('age-setup-bulk-help').hidden,false);assert.equal(app.controller.isDirty(),true);
 app.age.value='10';app.age.listeners.change();assert.match(app.courts.innerHTML,/value="7" checked/);
});
test('failed save retains draft and blocks scheduling until successful explicit save',async()=>{
 const app=harness(true);app.get('age-venue-setup').listeners.input();const pending=app.start();assert.equal(app.get('age-setup-save').disabled,true);app.complete();await pending;
 assert.equal(app.controller.isDirty(),true);assert.equal(app.get('age-setup-save').disabled,false);assert.equal(app.get('age-setup-status').textContent,'Save failed');assert.equal(app.posted().age_setup.age,10);
});
test('saving locks the age selector and clears dirty state only for submitted age',async()=>{
 const app=harness();app.get('age-venue-setup').listeners.change();const pending=app.start();assert.equal(app.age.disabled,true);app.age.value='12';app.age.listeners.change();app.get('age-venue-setup').listeners.change();app.complete();await pending;
 assert.equal(app.controller.isDirty(),true);assert.equal(app.states['10'].setup.age,10);assert.equal(app.states['12'].setup,null);
});
