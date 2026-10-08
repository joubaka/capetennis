const fs = require('node:fs');
const test = require('node:test');
const assert = require('node:assert/strict');
const source = fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php', 'utf8');
const modalSource = source.slice(source.indexOf('  const deleteVenueAssociation ='), source.indexOf("  document.getElementById('apply-preview').addEventListener"));

function element(values = {}) {
  return Object.assign({value:'', disabled:false, textContent:'', innerHTML:'', dataset:{}, listeners:{},
    closest(){return null;}, focus(){}, append(){}, addEventListener(name, callback){this.listeners[name] = callback;},
    classList:{contains(){return false;}}, querySelector(){return null;},
  }, values);
}

function harness({post, fetch, drawRemoval, confirm=()=>true, editors=[]} = {}) {
  const calls = [], stored = new Map();
  let reloads = 0;
  const modal = element(), add = element();
  const existing = element({value:'12', options:[]}), name = element({disabled:true});
  const nodes = {'venue-management-modal':modal, 'add-venue':add, 'venue-add-status':element(), 'allocation-status':element(),
    'new-venue-courts':element({value:'4'}), 'new-venue-ball':element({value:'yellow'}),
    'venue-editor-list':element(), 'venue-management-counts':element(), 'local-status':element(),
    'court-reset-review':element({hidden:true}), 'court-reset-message':element(),
    'court-reset-ages':element({querySelectorAll(selector){return selector==='input:checked'?(this.selected || []):[];}}),
    'court-reset-confirm':element(), 'court-reset-cancel':element()};
  const fresh = {'venue-editor-list':element({innerHTML:'fresh editors'}),
    'new-venue-id':element({innerHTML:'remaining venues'}), 'venue-management-counts':element({textContent:'2 assigned venues · 8 courts available'})};
  const assignment=element({checked:true}), court=element({checked:true}), unrelatedCourt=element({checked:true});
  const document = {getElementById:id => nodes[id], querySelector:selector => selector.includes('.venue-editor-status')?nodes['local-status']:assignment,
    querySelectorAll:selector => selector==='.venue-editor[open]'?editors.filter(editor=>editor.open):selector==='.venue-editor'?editors:selector==='.remove-draw-venue' ? (drawRemoval?[drawRemoval]:[]) : selector.startsWith('.court-allocation[data-draw=')?[court]:[]};
  const window = {location:{href:'/schedule', reload(){reloads++;}}};
  const sessionStorage = {setItem:(key,value) => stored.set(key,value)};
  const DOMParser = class {parseFromString(){return {getElementById:id => fresh[id]};}};
  const fetchClient = async (url, options) => {
    calls.push({url, options});
    if (fetch) return fetch(url, options);
    return {ok:true, text:async() => '<html/>', json:async() => ({message:'Removed.'})};
  };
  new Function('document','window','sessionStorage','DOMParser','fetch','post','existingVenue','newVenueName','confirm',
    `const csrf='test', venueUrl='/venues', courtUrl='/courts'; const escapeHtml=value=>String(value).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
     let allocationsDirty=true, scheduleDirty=true, allRankRules=[];
     const rememberRankRules=()=>{}, invalidatePreview=()=>{};
     const refreshProgrammeStageSummaries=()=>{}, loadRankScope=()=>{}, updateCourtSummary=()=>{}, updateDrawSummary=()=>{};
     const setStatus=(node,message)=>{node.textContent=message;};
     ${modalSource}`)(document,window,sessionStorage,DOMParser,fetchClient,
      post || (async() => ({message:'Added.', venue:{id:12}})),existing,name,confirm);
  return {modal,add,existing,name,nodes,calls,stored,assignment,court,unrelatedCourt,reloads:() => reloads,
    addVenue:() => add.listeners.click({currentTarget:add}),
    click:button => modal.listeners.click({target:{closest:() => button}}),
    close:() => modal.listeners['hidden.bs.modal'](),
    approve:() => nodes['court-reset-confirm'].onclick(), cancel:() => nodes['court-reset-cancel'].onclick(),
    select:keys => {nodes['court-reset-ages'].selected=keys.map(value=>({value}));nodes['court-reset-ages'].onchange();}};
}

test('multiple existing and new venues can be added without closing or reloading', async () => {
  const requests = [];
  const api = harness({post:async(url,body) => {requests.push(body);return {message:'Added.',venue:{id:requests.length}};}});
  await api.addVenue();
  api.existing.value='20';
  await api.addVenue();
  api.name.disabled=false;api.name.value='New Club';api.existing.value='';
  await api.addVenue();
  assert.equal(requests.length,3);
  assert.equal(requests[2].name,'New Club');
  assert.equal(api.name.value,'');
  assert.equal(api.add.disabled,false);
  assert.equal(api.reloads(),0);
  assert.equal(api.nodes['venue-editor-list'].innerHTML,'fresh editors');
  assert.equal(api.nodes['venue-management-counts'].textContent,'2 assigned venues · 8 courts available');
  api.close();
  assert.equal(api.reloads(),1);
  assert.equal(api.stored.size,1,'Closing remembers unsaved allocation and timing values.');
});

test('closing is blocked while a venue mutation is pending', async () => {
  let complete;
  const api = harness({post:() => new Promise(resolve => {complete=resolve;})});
  const operation=api.addVenue();
  let prevented=false;
  api.modal.listeners['hide.bs.modal']({preventDefault(){prevented=true;}});
  assert.equal(prevented,true);
  assert.equal(api.reloads(),0);
  complete({message:'Added.',venue:{id:12}});
  await operation;
});

test('DELETE errors remain in the modal and do not refresh or reload', async () => {
  const api=harness({fetch:async() => ({ok:false,json:async() => ({message:'This venue has saved matches.'})})});
  const button=element({dataset:{url:'/venues/12'}, classList:{contains:name => name==='remove-venue'},
    closest:() => ({dataset:{},querySelector:() => ({textContent:'Club'})})});
  await api.click(button);
  assert.equal(api.calls[0].options.method,'DELETE');
  assert.equal(api.calls[0].options.headers['X-CSRF-TOKEN'],'test');
  assert.equal(api.nodes['venue-add-status'].textContent,'This venue has saved matches.');
  assert.equal(api.calls.length,1);
  assert.equal(button.disabled,false);
  api.close();assert.equal(api.reloads(),0);
});

test('editing court counts uses AJAX and refreshes the modal before Done', async () => {
  let submitted;
  const api=harness({post:async(url,body) => {submitted={url,body};return {message:'Updated.'};}});
  const setup={dataset:{url:'/venues/12/courts'},querySelector:selector => ({value:selector==='.setup-court-count'?'6':'green'})};
  const button=element({dataset:{hasCustom:'0'},classList:{contains:name => name==='update-court-setup'},closest:() => setup});
  await api.click(button);
  assert.deepEqual(submitted,{url:'/venues/12/courts',body:{courts:6,ball_type:'green'}});
  assert.equal(api.calls.length,1);
  assert.equal(api.reloads(),0);
  api.close();assert.equal(api.reloads(),1);
});

test('removing an age-group venue updates its selection and leaves other choices intact without reloading', async () => {
  let removed=false;
  const button=element({dataset:{draw:'3',venue:'12',url:'/draws/3/venues/12'},remove(){removed=true;}});
  const api=harness({drawRemoval:button});
  await button.listeners.click();
  assert.equal(api.calls[0].options.method,'DELETE');
  assert.equal(api.assignment.checked,false);
  assert.equal(api.court.checked,false);
  assert.equal(api.unrelatedCourt.checked,true);
  assert.equal(removed,true);
  assert.equal(api.reloads(),0);
});


test('saving an individual court uses its row rather than a global escaped-label selector', async () => {
  let submitted;
  const api=harness({post:async(url,body) => {submitted={url,body};return {message:'Court saved.'};}});
  const row={querySelector:() => ({value:'orange'})};
  const button=element({dataset:{venue:'12',label:'Court "A"'},classList:{contains:name => name==='update-court-type'},
    closest:selector => selector==='.court-editor-row'?row:null});
  await api.click(button);
  assert.deepEqual(submitted,{url:'/courts',body:{venue_id:12,label:'Court "A"',ball_type:'orange'}});
  assert.equal(api.reloads(),0);
});

test('removing a court saves the exact label with AJAX before refreshing', async () => {
  const api=harness();
  const button=element({dataset:{url:'/venues/12/courts',label:'Court 5'},classList:{contains:name => name==='remove-court'}});
  await api.click(button);
  assert.equal(api.calls[0].options.method,'DELETE');
  assert.deepEqual(JSON.parse(api.calls[0].options.body),{label:'Court 5'});
  assert.equal(api.calls.length,2);
  assert.equal(api.reloads(),0);
  api.close();assert.equal(api.reloads(),1);
});

test('a rejected court removal does not refresh or reload', async () => {
  const api=harness({fetch:async() => ({ok:false,json:async() => ({message:'This court has saved matches.'})})});
  const button=element({dataset:{url:'/venues/12/courts',label:'3'},classList:{contains:name => name==='remove-court'}});
  await api.click(button);
  assert.equal(api.nodes['venue-add-status'].textContent,'This court has saved matches.');
  assert.equal(api.calls.length,1);
  assert.equal(button.disabled,false);
  api.close();assert.equal(api.reloads(),0);
});


test('cancelling court setup confirmation leaves no saving message or request', async () => {
  let requests=0;
  const api=harness({confirm:()=>false,post:async()=>{requests++;}});
  const setup={dataset:{url:'/venues/12/courts'},querySelector:selector => ({value:selector==='.setup-court-count'?'6':'green'})};
  const button=element({dataset:{hasCustom:'1'},classList:{contains:name => name==='update-court-setup'},closest:() => setup});
  await api.click(button);
  assert.equal(requests,0);
  assert.equal(api.nodes['venue-add-status'].textContent,'');
  assert.equal(button.disabled,false);
});


test('refreshing a court keeps its venue open and reports success beside its editor', async () => {
  const editor=element({dataset:{venue:'12'},open:true});
  const other=element({dataset:{venue:'20'},open:false});
  const api=harness({editors:[editor,other]});
  const button=element({dataset:{url:'/venues/12/courts',label:'2'},classList:{contains:name=>name==='remove-court'},closest:()=>editor});
  await api.click(button);
  assert.equal(editor.open,true);
  assert.equal(other.open,false);
  assert.equal(api.nodes['local-status'].textContent,'Removed.');
});

test('court errors are visible beside the affected venue', async () => {
  const editor=element({dataset:{venue:'12'},open:true});
  const api=harness({editors:[editor],fetch:async()=>({ok:false,json:async()=>({message:'Published court protected.'})})});
  const button=element({dataset:{url:'/venues/12/courts',label:'2'},classList:{contains:name=>name==='remove-court'},closest:()=>editor});
  await api.click(button);
  assert.equal(api.nodes['local-status'].textContent,'Published court protected.');
  assert.equal(editor.open,true);
});



const flush = () => new Promise(resolve => setImmediate(resolve));
function setupButton() {
  const setup={dataset:{url:'/venues/12/courts'},querySelector:selector=>({value:selector==='.setup-court-count'?'4':'standard'})};
  return element({dataset:{hasCustom:'0'},classList:{contains:name=>name==='update-court-setup'},closest:selector=>selector==='.venue-editor'?null:setup});
}
function warning(revision='revision-a',message='Clear 12 event matches and withdraw 8 public times?') {
  return Object.assign(new Error(message),{response:{requires_confirmation:true,message,correction_revision:revision,
    age_group_options:[{key:'under:12',label:'Under 12',draw_ids:[1,2]}]}});
}
test('court reduction waits for inline review then submits the reviewed revision', async () => {
  const requests=[];
  const api=harness({post:async(url,body)=>{requests.push(body);if(requests.length===1) throw warning();return {message:'Corrected.'};}});
  const operation=api.click(setupButton());await flush();
  assert.equal(requests.length,1);
  assert.equal(api.nodes['court-reset-review'].hidden,false);
  assert.match(api.nodes['court-reset-ages'].innerHTML,/Clear all Under 12 schedules across all venues/);
  await api.approve();await operation;
  assert.deepEqual(requests[1],{courts:4,ball_type:'standard',reset_age_keys:[],confirm_reset:true,correction_revision:'revision-a'});
  assert.equal(api.nodes['court-reset-review'].hidden,true);
});
test('cancel review leaves courts and schedules unchanged', async () => {
  let requests=0;
  const api=harness({post:async()=>{requests++;throw warning();}});
  const button=setupButton(),operation=api.click(button);await flush();api.cancel();await operation;
  assert.equal(requests,1);assert.equal(api.calls.length,0);assert.equal(button.disabled,false);
  api.close();assert.equal(api.reloads(),0);
});
test('individual removal uses the same review with exact court label', async () => {
  let requests=0;
  const api=harness({fetch:async(url,options)=>{
    if(options.method!=='DELETE')return {ok:true,text:async()=>'<html/>'};
    requests++;return requests===1?{ok:false,json:async()=>warning().response}:{ok:true,json:async()=>({message:'Removed.'})};
  }});
  const button=element({dataset:{url:'/venues/12/courts',label:'5'},classList:{contains:name=>name==='remove-court'}});
  const operation=api.click(button);await flush();await api.approve();await operation;
  assert.deepEqual(JSON.parse(api.calls[1].options.body),{label:'5',reset_age_keys:[],confirm_reset:true,correction_revision:'revision-a'});
});
test('age selection refreshes impact and confirms only the fresh revision', async () => {
  const requests=[];
  const api=harness({post:async(url,body)=>{requests.push(body);if(!body.confirm_reset)throw warning('revision-'+requests.length,'Impact '+requests.length);return {message:'Corrected.'};}});
  const operation=api.click(setupButton());await flush();api.select(['under:12']);await flush();
  assert.deepEqual(requests[1],{courts:4,ball_type:'standard',reset_age_keys:['under:12'],review_only:true});
  assert.equal(api.nodes['court-reset-message'].textContent,'Impact 2');
  await api.approve();await operation;
  assert.equal(requests[2].correction_revision,'revision-2');
  assert.deepEqual(requests[2].reset_age_keys,['under:12']);
});
test('stale confirmation shows the new warning and requires another explicit confirmation', async () => {
  const requests=[];
  const api=harness({post:async(url,body)=>{requests.push(body);if(requests.length<3)throw warning('revision-'+requests.length);return {message:'Corrected.'};}});
  const operation=api.click(setupButton());await flush();await api.approve();
  assert.equal(requests.length,2);assert.equal(api.calls.length,0);assert.equal(api.nodes['court-reset-review'].hidden,false);
  await api.approve();await operation;assert.equal(requests[2].correction_revision,'revision-2');
});
test('out of order scope checks cannot replace the latest warning or revision', async () => {
  const requests=[],pending=[];
  const api=harness({post:async(url,body)=>{
    requests.push(body);if(requests.length===1)throw warning();if(body.confirm_reset)return {message:'Corrected.'};
    return new Promise((resolve,reject)=>pending.push(reject));
  }});
  const operation=api.click(setupButton());await flush();api.select(['under:12']);api.select([]);
  assert.equal(api.nodes['court-reset-confirm'].disabled,true);
  pending[1](warning('latest','Latest impact'));await flush();pending[0](warning('old','Old impact'));await flush();
  assert.equal(api.nodes['court-reset-message'].textContent,'Latest impact');
  await api.approve();await operation;assert.equal(requests.at(-1).correction_revision,'latest');assert.deepEqual(requests.at(-1).reset_age_keys,[]);
});
test('cancelling during preflight ignores its late response', async () => {
  let reject;
  const api=harness({post:async(url,body)=>{if(!body.reset_age_keys)throw warning();return new Promise((resolve,no)=>{reject=no;});}});
  const operation=api.click(setupButton());await flush();api.select(['under:12']);api.cancel();await operation;
  reject(warning('late'));await flush();assert.equal(api.nodes['court-reset-review'].hidden,true);assert.equal(api.calls.length,0);
});

test('zero-impact review remains read-only and can be cancelled without a mutation', async () => {
  const requests=[];let writes=0;
  const api=harness({post:async(url,body)=>{
    requests.push(body);
    if(requests.length===1)throw warning();
    if(body.review_only)throw warning('empty','Clear 0 scheduled matches and withdraw 0 public times.');
    writes++;return {message:'Saved.'};
  }});
  const operation=api.click(setupButton());await flush();api.select(['under:12']);await flush();
  assert.equal(requests[1].review_only,true);
  assert.equal(api.nodes['court-reset-review'].hidden,false);
  assert.match(api.nodes['court-reset-message'].textContent,/0 scheduled matches/);
  api.cancel();await operation;
  assert.equal(writes,0);assert.equal(api.calls.length,0);assert.equal(requests.length,2);
});
