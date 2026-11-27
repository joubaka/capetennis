const fs=require('node:fs');
const vm=require('node:vm');
const test=require('node:test');
const assert=require('node:assert/strict');
const source=fs.readFileSync('public/js/interpro-bracket.js','utf8');
const layout=require('../../public/js/tennis-bracket-layout.js');
const block=source.slice(source.indexOf('          if (match.schedule) {'),source.indexOf('          if (match.note)'));
function render(preview) {
  const nodes=[];
  const doc={createElement(tag){return {tag,children:[],textContent:'',append(...children){this.children.push(...children);},setAttribute(key,value){this[key]=value;}};}};
  vm.runInNewContext(block,{match:{schedule:{time:'2026-11-28 08:00:00',venue:'Bloemhof',court:'10',...(preview ? {preview:true} : {})}},
    Date,Intl,card:{append:node=>nodes.push(node),classList:{add(){}}},engine:{createSchedule:options=>layout.createSchedule(options,doc)}});
  return nodes[0];
}
test('published schedule stays green with day time venue and court',()=>{
  const node=render(false);
  assert.match(node.className,/is-published/);
  for(const text of ['Published','Sat','2026','08:00','Bloemhof','Court 10']) assert.ok(node.title.includes(text),text);
  assert.equal(node['aria-label'],node.title);
  assert.equal(node.children[0].children[1].textContent,'Court 10');
  assert.equal(node.children[1].textContent,'Bloemhof');
});
test('private preview carries explicit warning and orange scoped print-safe styling',()=>{
  const node=render(true);
  assert.match(node.className,/is-preview/); assert.match(node.title,/Preview only/);
  const view=fs.readFileSync('resources/views/backend/draw/roundrobin/interpro-feed-in.blade.php','utf8');
  assert.match(view,/\.interpro-feed-in \.fm-match-schedule\.is-preview\s*\{\s*color: #b45309/);
});
test('shared schedule preserves an existing Court prefix and its full accessible label',()=>{
  const doc={createElement(){return {children:[],append(...children){this.children.push(...children);},setAttribute(key,value){this[key]=value;}};}};
  const node=layout.createSchedule({label:'Full original date, venue and court',when:'Sat 28 Nov 08:00',venue:'An unusually long fictional tennis campus',court:'Court 10'},doc);
  assert.equal(node.children[0].children[1].textContent,'Court 10');
  assert.equal(node['aria-label'],'Full original date, venue and court');
});
