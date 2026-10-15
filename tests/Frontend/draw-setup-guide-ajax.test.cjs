const test=require('node:test');
const assert=require('node:assert/strict');
const vm=require('node:vm');
const fs=require('node:fs');
function harness(){
  const events={},requests=[];
  const element=()=>({children:[],dataset:{},attributes:{},hidden:false,textContent:'',innerHTML:'',addEventListener(name,fn){this[name]=fn;},setAttribute(k,v){this.attributes[k]=v;},removeAttribute(k){delete this.attributes[k];},appendChild(child){this.children.push(child);},prepend(child){this.children.unshift(child);},replaceChildren(){this.children=[];this.innerHTML='';},querySelector(){return null;}});
  const body=element(),host=element();host.contains=node=>node?.inside===true;host.querySelector=()=>body;
  const document={activeElement:null,body:element(),querySelector:selector=>selector==='[data-draw-guide-modal]'?host:null,createElement:element,addEventListener(name,fn){events[name]=fn;}};
  const window={location:{href:'https://test.local/events/1',origin:'https://test.local'},bootstrap:{Modal:{getOrCreateInstance(){return{show(){host.shown=true;},hide(){host['hidden.bs.modal']?.();}};}}},fetch(url,options){let resolve,reject;const pending=new Promise((yes,no)=>{resolve=yes;reject=no;});requests.push({url,options,resolve,reject});return pending;}};
  vm.runInNewContext(fs.readFileSync('public/js/draw-setup-guide.js','utf8'),{window,document,URL,AbortController,FormData:class{constructor(form){this.fields=form.fields;}}});
  const reply=(index,data,status=200,type='application/json')=>requests[index].resolve({ok:status<400,status,headers:{get:()=>type},json:async()=>data});
  return{window,document,events,host,body,requests,reply,element};
}
const tick=()=>new Promise(resolve=>setImmediate(resolve));
test('Ajax guide loads same-origin HTML fragment and ignores stale responses',async()=>{
  const app=harness();const first=app.window.DrawSetupGuide.open('/guide/1');await tick();
  assert.equal(app.requests.length,1);assert.equal(app.requests[0].options.headers.Accept,'application/json');
  const second=app.window.DrawSetupGuide.open('/guide/2');await tick();
  assert.equal(app.requests[0].options.signal.aborted,true);
  app.reply(1,{html:'<div data-draw-guide>Second</div>'});await second;
  app.reply(0,{html:'<div data-draw-guide>Old</div>'});await first;
  assert.match(app.body.innerHTML,/Second/);assert.doesNotMatch(app.body.innerHTML,/Old/);
  await app.window.DrawSetupGuide.open('https://other.local/guide');assert.equal(app.requests.length,2);
});
test('login HTML is never inserted and a failed guide displays a retry',async()=>{
  const app=harness();const pending=app.window.DrawSetupGuide.open('/guide/1');await tick();
  app.reply(0,{},200,'text/html');await pending;
  assert.equal(app.body.innerHTML,'');assert.match(app.body.children[0].textContent,/session/);assert.equal(app.body.children[1].textContent,'Try again');
});
test('closing a loading modal cancels the request and prevents stale insertion',async()=>{
  const app=harness();const pending=app.window.DrawSetupGuide.open('/guide/1');await tick();
  app.host['hidden.bs.modal']();app.reply(0,{html:'<div data-draw-guide>Old</div>'});await pending;
  assert.equal(app.requests[0].options.signal.aborted,true);assert.equal(app.body.innerHTML,'');
});
test('Ajax category validation preserves selected input, prevents duplicate posts and permits retry',async()=>{
  const app=harness();const field={name:'category_event_id',value:'17',disabled:false,setAttribute(){},removeAttribute(){}},button={disabled:false,setAttribute(){},removeAttribute(){}};
  const form=app.element();form.inside=true;form.action='https://test.local/category';form.fields={category_event_id:'17'};form.querySelectorAll=()=>[field,button];form.closest=()=>form;
  const event={target:form,preventDefault(){}};
  const first=app.events.submit(event);app.events.submit(event);assert.equal(app.requests.length,1);assert.equal(button.disabled,true);
  let blocked=false;app.host['hide.bs.modal']({preventDefault(){blocked=true;}});assert.equal(blocked,true);
  app.reply(0,{errors:{category_event_id:['Choose an event category.']}},422);await first;
  assert.equal(field.value,'17');assert.equal(button.disabled,false);assert.match(form.children[0].textContent,/Choose an event category/);
  const retry=app.events.submit(event);app.reply(1,{guide_url:'/guide/1'});await retry;await tick();
  assert.equal(app.requests.length,3);assert.equal(app.requests[2].options.method,undefined,'save reloads only the guide fragment');
  app.reply(2,{html:'<div data-draw-guide>Saved</div>'});await tick();assert.match(app.body.innerHTML,/Saved/);
});
test('existing Continue setup links open through Ajax and retain modified-click fallback',async()=>{
  const app=harness();let prevented=false;const link={href:'https://test.local/guide/1'};
  app.events.click({target:{closest:selector=>selector==='[data-draw-guide-open]'?link:null},button:0,preventDefault(){prevented=true;}});await tick();
  assert.equal(prevented,true);assert.equal(app.requests.length,1);app.reply(0,{html:'<div data-draw-guide>Open</div>'});await tick();
  app.events.click({target:{closest:selector=>selector==='[data-draw-guide-open]'?link:null},button:0,ctrlKey:true,preventDefault(){assert.fail('modified click intercepted');}});
  assert.equal(app.requests.length,1);
});
