const fs=require('node:fs');
const test=require('node:test');
const assert=require('node:assert/strict');
const source=fs.readFileSync('resources/js/pages/regions.js','utf8');
test('region editor escapes quotes as well as HTML in attribute values',()=>{
 const body=source.match(/function escapeHtml\(value\) \{([\s\S]*?)\n  \}/)[1];
 const $=()=>({text(value){this.value=value;return this;},html(){return this.value.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}});
 const escape=new Function('$','value',body).bind(null,$);
 assert.equal(escape('" onfocus="x\'<b>'), '&quot; onfocus=&quot;x&#39;&lt;b&gt;');
});
