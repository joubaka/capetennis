const fs=require('node:fs');
const vm=require('node:vm');
const test=require('node:test');
const assert=require('node:assert/strict');
const source=fs.readFileSync('public/js/interpro-bracket.js','utf8');
const block=source.slice(source.indexOf('          if (match.schedule) {'),source.indexOf('          if (match.note)'));
function render(preview) {
  const nodes=[];
  vm.runInNewContext(block,{match:{schedule:{time:'2026-11-28 08:00:00',venue:'Bloemhof',court:'10',...(preview ? {preview:true} : {})}},
    Date,Intl,card:{append:node=>nodes.push(node)},create:(tag,className,text)=>({tag,className,text,setAttribute(key,value){this[key]=value;}})});
  return nodes[0];
}
test('published schedule stays green with day time venue and court',()=>{
  const node=render(false);
  assert.match(node.className,/is-published/);
  for(const text of ['Published','Sat','08:00','Bloemhof','Court 10']) assert.ok(node.text.includes(text),text);
  assert.equal(node['aria-label'],node.text);
});
test('private preview carries explicit warning and orange scoped print-safe styling',()=>{
  const node=render(true);
  assert.match(node.className,/is-preview/); assert.match(node.text,/Preview only/);
  const view=fs.readFileSync('resources/views/backend/draw/roundrobin/interpro-feed-in.blade.php','utf8');
  assert.match(view,/\.interpro-feed-in \.fm-match-schedule\.is-preview\s*\{\s*color: #b45309/);
});
