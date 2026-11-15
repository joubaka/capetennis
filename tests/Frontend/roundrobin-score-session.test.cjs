const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const assets = ['resources/js/admin/roundrobin/scores.js', 'public/assets/js/admin/roundrobin/scores.js'];
function editor(asset) {
  const values = new Map(), properties = new Map(), handlers = new Map(), requests = [], messages = [];
  const modalElement = {id:'rrScoreModal'}, form = {id:'rr-score-modal-form'}, submit = {id:'submit'}, row = {id:'row',setRow:1};
  const elements = new Map([['#rrScoreModal',modalElement],['#rr-score-modal-form',form],['[type="submit"]',submit]]);
  const inputs = ['set1-p1','set1-p2'].map(id=>({id}));
  inputs.forEach(input=>elements.set('#'+input.id,input));
  const key = value => typeof value === 'string' ? value : value?.id ? '#'+value.id : '[document]';
  function resolve(selector) {
    if (typeof selector !== 'string') return [selector];
    if (selector.includes('.score-set-row') && selector.includes(':input')) return inputs;
    if (selector === '.score-set-row') return [row];
    if (selector === '#rrm-delete-score') return [{id:'rrm-delete-score'}];
    if (!elements.has(selector)) elements.set(selector,{id:selector.replace(/^#/,'')});
    return [elements.get(selector)];
  }
  function $(selector) {
    if (typeof selector === 'function') {selector();return;}
    const targets = resolve(selector);
    const chain = {0:targets[0],length:targets.length,
      val(value) {if(value===undefined)return values.get(key(targets[0]))??'';targets.forEach(target=>values.set(key(target),String(value)));return chain;},
      text(value) {if(value===undefined)return values.get(key(targets[0])+':text')??'';targets.forEach(target=>values.set(key(target)+':text',String(value)));return chain;},
      html(value) {if(value===undefined)return values.get(key(targets[0])+':text')??'';targets.forEach(target=>values.set(key(target)+':text',String(value)));return chain;},
      prop(name,value) {if(value===undefined)return properties.get(key(targets[0])+':'+name);targets.forEach(target=>properties.set(key(target)+':'+name,value));return chain;},
      on(events,delegate,callback) {const fn=callback||delegate;events.split(' ').forEach(event=>{
        const name=key(targets[0])+':'+event+(callback?':'+delegate:'');
        if(!handlers.has(name))handlers.set(name,[]);handlers.get(name).push(fn);
      });return chain;},
      each(callback) {targets.forEach(target=>callback.call(target));return chain;},
      find(query) {return $(query===':input'?'.score-set-row :input':query);},
      data(name) {return name==='set-row'?targets[0].setRow:undefined;},
      toggleClass(){return chain;},addClass(){return chain;},removeClass(){return chain;}
    };
    return chain;
  }
  function fire(target,event) {(handlers.get(key(target)+':'+event)||[]).forEach(fn=>fn({target,preventDefault(){}}));}
  let instance=null, instances=0;
  class Modal {
    constructor() {instance=this;instances++;this.visible=false;this._isTransitioning=false;}
    show(){if(this._isTransitioning)return;this.visible=true;this._isTransitioning=true;fire(modalElement,'show.bs.modal');}
    hide(){if(this._isTransitioning)return;this.visible=false;this._isTransitioning=true;fire(modalElement,'hide.bs.modal');}
    static getInstance(){return instance;}
    static getOrCreateInstance(){return instance||new Modal();}
  }
  const fixtures = [1,2].map(id=>({id,stage:'RR',r1_id:id*10+1,r2_id:id*10+2,home:'Home '+id,away:'Away '+id,all_sets:[]}));
  const AdminState = {getOop:()=>fixtures,getFixtures:()=>({A:fixtures}),setFixtures(){},setStandings(){},setOop(){},scoreSaved(){},scoreDeleted(){}};
  function mutation(method,url,payload) {
    let resolve,reject;
    const promise=new Promise((yes,no)=>{resolve=yes;reject=no;});
    requests.push({method,url,payload,resolve,reject});return promise;
  }
  const window={RR_SCORE_MAX_SETS:1,RR_CAN_SCORE:true,RR_DRAW_LOCKED:false,CTSetScoreGrid:{bind(){}},confirm:()=>true,
    RR_RECOVERY:{preview:'/recovery-preview'}};
  const context={window,jQuery:$,document:{getElementById:id=>id==='rrScoreModal'?modalElement:form},bootstrap:{Modal},AdminState,
    AdminToast:{success:message=>messages.push(message),error:message=>messages.push(message),warning:message=>messages.push(message)},
    AdminRoutes:{score:id=>'/score/'+id,scoreDelete:id=>'/score/'+id},
    AdminApi:{post:(url,payload)=>mutation('POST',url,payload),delete:url=>mutation('DELETE',url),postJson:(url,payload)=>mutation('PREVIEW',url,payload)},
    AdminLoading:{button:button=>{const previous=button.text();button.prop('disabled',true).text('Saving…');return()=>button.prop('disabled',false).text(previous);}}
  };
  vm.runInNewContext(fs.readFileSync(asset,'utf8'),context);
  return {window,values,properties,requests,messages,
    open:id=>window.RRScores.open(id,'Home '+id,'Away '+id),
    enter:(a,b)=>{$('#set1-p1').val(a);$('#set1-p2').val(b);fire(form,'input');},
    scores:()=>[$('#set1-p1').val(),$('#set1-p2').val()],
    fixture:()=>$('#rrm-fixture-id').val(),
    submit:()=>fire(form,'submit'),
    delete:()=>{(handlers.get('[document]:click:#rrm-delete-score')||[]).forEach(fn=>fn());},
    preview:()=>{(handlers.get('[document]:click:#rrm-open-recovery')||[]).forEach(fn=>fn());},
    shown:()=>{instance._isTransitioning=false;fire(modalElement,'shown.bs.modal');},
    dismiss:()=>instance.hide(),
    hidden:()=>{instance._isTransitioning=false;fire(modalElement,'hidden.bs.modal');},
    visible:()=>!!instance?.visible,instances:()=>instances,
    settle:async()=>{await new Promise(resolve=>setImmediate(resolve));}
  };
}

for (const asset of assets) {
  test(asset+': inputs and submit wait for Bootstrap focus readiness and stay disabled during hide',()=>{
    const page=editor(asset);page.open(1);
    assert.equal(page.properties.get('#set1-p1:disabled'),true);
    assert.equal(page.properties.get('#submit:disabled'),true);
    page.enter(6,2);page.submit();assert.equal(page.requests.length,0);
    page.shown();assert.equal(page.properties.get('#set1-p1:disabled'),false);
    assert.equal(page.properties.get('#submit:disabled'),false);
    page.dismiss();assert.equal(page.properties.get('#set1-p1:disabled'),true);
    assert.equal(page.properties.get('#submit:disabled'),true);
    page.submit();assert.equal(page.requests.length,0);
  });
  test(asset+': delayed save cannot clear or close a newly opened fixture',async()=>{
    const page=editor(asset);page.open(1);page.shown();page.enter(6,1);page.submit();
    page.open(2);page.enter(6,3);
    page.requests[0].resolve({});await page.settle();
    assert.equal(page.fixture(),'2');assert.deepEqual(page.scores(),['6','3']);assert.equal(page.visible(),true);
    assert.equal(page.requests[0].url,'/score/1');
    assert.deepEqual(Array.from(page.requests[0].payload.sets),['6-1']);
    page.submit();assert.equal(page.requests[1].url,'/score/2');
    assert.deepEqual(Array.from(page.requests[1].payload.sets),['6-3']);
  });
  test(asset+': same-fixture double-open while showing keeps rapid entries and one modal instance',()=>{
    const page=editor(asset);page.open(1);page.enter(6,2);page.open(1);
    assert.deepEqual(page.scores(),['6','2']);assert.equal(page.instances(),1);
  });
  test(asset+': late success leaves edits made after submission intact',async()=>{
    const page=editor(asset);page.open(1);page.shown();page.enter(6,1);page.submit();page.enter(7,5);
    page.requests[0].resolve({});await page.settle();
    assert.equal(page.fixture(),'1');assert.deepEqual(page.scores(),['7','5']);assert.equal(page.visible(),true);
  });
  test(asset+': duplicate submit is suppressed and failure retains retryable inputs',async()=>{
    const page=editor(asset);page.open(1);page.shown();page.enter(6,1);page.submit();page.submit();
    assert.equal(page.requests.length,1);
    page.requests[0].reject({status:422,message:'Check the set'});await page.settle();
    assert.deepEqual(page.scores(),['6','1']);assert.equal(page.visible(),true);
    page.submit();assert.equal(page.requests.length,2);assert.equal(page.requests[1].url,'/score/1');
  });
  test(asset+': reopen during a save-triggered hide waits for hidden and stale callbacks cannot clear it',async()=>{
    const page=editor(asset);page.open(1);page.shown();page.enter(6,1);page.submit();
    page.requests[0].resolve({});await page.settle();
    page.open(2);page.hidden();page.shown();page.enter(6,4);page.open(2);
    assert.equal(page.fixture(),'2');assert.deepEqual(page.scores(),['6','4']);assert.equal(page.visible(),true);assert.equal(page.instances(),1);
  });
  test(asset+': cancel invalidates the old session while its pending response preserves the queued fixture',async()=>{
    const page=editor(asset);page.open(1);page.shown();page.enter(6,1);page.submit();page.dismiss();page.open(2);
    page.requests[0].resolve({});await page.settle();page.hidden();page.shown();page.enter(6,2);
    assert.equal(page.fixture(),'2');assert.deepEqual(page.scores(),['6','2']);assert.equal(page.visible(),true);
  });
  test(asset+': delayed delete cannot clear another fixture and repeated delete/save sends one request',async()=>{
    const page=editor(asset);page.open(1);page.shown();page.enter(6,1);page.delete();page.delete();page.submit();
    assert.equal(page.requests.length,1);assert.equal(page.requests[0].method,'DELETE');
    page.open(2);page.enter(6,3);page.submit();assert.equal(page.requests.length,1);
    page.requests[0].resolve({});await page.settle();
    assert.equal(page.fixture(),'2');assert.deepEqual(page.scores(),['6','3']);assert.equal(page.visible(),true);
    page.submit();assert.equal(page.requests[1].url,'/score/2');
  });
  test(asset+': an old duplicate hidden event cannot clear the queued modal after it has opened',()=>{
    const page=editor(asset);page.open(1);page.shown();page.dismiss();page.open(2);page.hidden();page.shown();page.enter(6,4);
    page.hidden();
    assert.equal(page.fixture(),'2');assert.deepEqual(page.scores(),['6','4']);assert.equal(page.visible(),true);
  });
  test(asset+': delayed recovery preview cannot move a newer fixture into recovery',async()=>{
    const page=editor(asset);page.open(1);page.shown();page.enter(6,1);page.preview();page.preview();
    assert.equal(page.requests.length,1);assert.equal(page.requests[0].payload.fixture_id,1);
    page.open(2);page.enter(6,4);
    page.requests[0].resolve({impact:{effect:'Reset playoffs',playoff_fixtures:2,scored_playoff_fixtures:0,scheduled_playoff_fixtures:0}});
    await page.settle();
    assert.equal(page.fixture(),'2');assert.deepEqual(page.scores(),['6','4']);assert.equal(page.visible(),true);assert.equal(page.instances(),1);
  });
}
