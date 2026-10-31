const fs=require('node:fs');
const vm=require('node:vm');
const test=require('node:test');
const assert=require('node:assert/strict');
const source=fs.readFileSync('public/js/flexible-monrad.js','utf8');
const bracketLayout=require('../../public/js/tennis-bracket-layout.js');

function scenario(allOff=false){
 const match=(number,section='Challenge play-offs')=>({number,section,round:1,label:'Starting pair',sources:[{type:'player'},{type:'player'}],players:[null,null],sets:[],winner:null});
 const matches={final:{...match(15,'Main draw'),label:'Final',players:[1,2]}};
 for(const [key,number,pair] of [['challenge_2',37,[2,3]],['challenge_4',38,[4,5]],['challenge_6',39,[6,7]],['challenge_8',40,[8,9]],['challenge_3',41,[3,4]],['challenge_2_repeat',42,[2,3]]]){
  matches[key]={...match(number),challenge_positions:pair,sources:pair.map(rank=>({type:'player',base_position:rank})),
   round:key==='challenge_3'?2:key==='challenge_2_repeat'?3:1,
   challenge_status:allOff||['challenge_6','challenge_8'].includes(key)?'disabled':key==='challenge_4'?'skipped':'pending'};
 }
 return matches;
}
function render(matches,readOnly){
 const el=(tag,className='',text='')=>({tag,className,textContent:String(text),style:{},dataset:{},children:[],clientWidth:1000,offsetHeight:35,
  classList:{add(){},toggle(){}},append(...nodes){this.children.push(...nodes);},replaceChildren(){this.children=[];},setAttribute(){},addEventListener(){},
  querySelector(){return {textContent:''};}});
 const nodes=new Map();const get=id=>{if(!nodes.has(id))nodes.set(id,el('div'));return nodes.get(id);};
 const state={generated:true,matches,positions:[],draft:{},rolling_challenges:true};
 const context={state,config:{readOnly,canScore:false,workflow:'feed_in_monrad'},el,$:get,bracketLayout,bracketDimensions:bracketLayout.dimensions,
  playerNameNode:id=>el('span','',`Player ${id}`),generatedPlayerDrag(){},connectors(){},
  bracketMatch:(_,title)=>el('div','fm-match',title),sourceLabel:source=>`Base position ${source.base_position||''}`,
  matchParticipantLabel:(match,slot)=>match.players[slot]?`Player ${match.players[slot]}`:`Base position ${match.sources[slot].base_position||''}`,
  scheduleLabel:()=>'',scheduleParts:()=>({}),Intl,Date};
 vm.createContext(context);
 const helpers=source.slice(source.indexOf('  function challengeStageHeading('),source.indexOf('  function generatedBoard('));
 const board=source.slice(source.indexOf('  function generatedBoard('),source.indexOf('  function render()'));
 const timetable=source.slice(source.indexOf('  function renderTimetable('),source.indexOf("  if (window.location.hash === '#schedule')"));
 const print=source.slice(source.indexOf('  function renderPrint()'),source.indexOf('  async function request('));
 vm.runInContext(helpers+board+timetable+print+'\ngeneratedBoard();renderTimetable();renderPrint();',context);
 return {get,state};
}
function text(node){return [node.textContent,...node.children.map(text)].join(' ');}
function sections(root){return root.children.map(section=>section.children[0].textContent);}

test('admin and public boards, print and timetable hide disabled6/8 without hiding selected pending or skipped challenges',()=>{
 for(const readOnly of [false,true]){
  const matches=scenario();const before=JSON.stringify(matches);const page=render(matches,readOnly);
  for(const id of ['fm-board','fm-print-content','fm-timetable']){
   const rendered=text(page.get(id));
   for(const number of [37,38,41,42])assert.match(rendered,new RegExp(`(?:Match )?${number}\\b`));
   for(const number of [39,40])assert.doesNotMatch(rendered,new RegExp(`(?:Match )?${number}\\b`));
   assert.doesNotMatch(rendered,/Base position [6789]\b|Not selected/);
  }
  assert.equal(JSON.stringify(matches),before,'rendering must retain canonical state and disabled fixture history');
 }
});
test('all challenge choices off produces no challenge board, column heading, endpoint or print section',()=>{
 const page=render(scenario(true),false);
 assert.doesNotMatch(text(page.get('fm-board')),/Challenge play-offs|Step [123]|Winner takes position|Not selected/);
 assert.deepEqual(sections(page.get('fm-print-content')),['Main draw']);
 assert.match(text(page.get('fm-board')),/Match 15/);
 assert.equal(page.get('fm-timetable').children[0].children[1].children.length,1);
});
