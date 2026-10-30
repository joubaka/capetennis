// Fictional, offline fixture exercising the shipped day planner and actual partial.
const test=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'../..');
test('saved day keeps exact matches, open selection panels and keyboard focus at phone and desktop widths',async()=>{
 const browser=await chromium.launch({headless:true,channel:'msedge'});
 try{
  const page=await browser.newPage();await page.route('**/*',route=>route.abort());
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  let partial=fs.readFileSync(path.join(root,'resources/views/backend/schedule/partials/day-batch.blade.php'),'utf8');
  partial=partial.replace(/\s*@if\(\$savedDayReplan[^\n]+/,'')
    .replace(/\s*@if\(\(\$pendingSecondRoundRobinDraws[\s\S]*?(?=\s*<div id="day-batch-rounds")/,'')
    .replace(/@checked\([^)]*\)/g,'').replace(/\{\{[^}]*\}\}/g,'75');
  const pageSource=fs.readFileSync(path.join(root,'resources/views/backend/schedule/event-venue-schedule.blade.php'),'utf8');
  const css=['core.css','theme-default.css'].map(file=>fs.readFileSync(path.join(root,'public/assets/vendor/css',file),'utf8')).join('\n')+pageSource.match(/<style>([\s\S]*?)<\/style>/)[1];
  for(const width of [320,390,768,1280]){
   await page.setViewportSize({width,height:900});
   await page.setContent(`<meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style><main class="container-xxl schedule-workspace day-batch-mode">${partial}</main>`);
   await page.addScriptTag({path:path.join(root,'public/js/schedule-day-batch.js')});
   await page.evaluate(()=>{
    const groups=(id)=>({2:[{label:'Main draw · Quarterfinals',count:4,matches:Array.from({length:4},(_,i)=>({key:`individual:${id*100+i}`,number:i+1,scheduled_at:'2026-10-31 08:30'}))}],3:[{label:'Main draw · Semifinals',count:2,matches:[{key:`individual:${id*100+4}`,number:5},{key:`individual:${id*100+5}`,number:6}]},{label:'Feed-in',count:4,matches:Array.from({length:4},(_,i)=>({key:`individual:${id*100+6+i}`,number:i+7}))}],4:[{label:'Feed-in next',count:2,matches:[{key:`individual:${id*100+10}`,number:11},{key:`individual:${id*100+11}`,number:12}]},{label:'Unscheduled placement',count:1,matches:[{key:`individual:${id*100+12}`,number:13}]}]});
    const draws=[1,2].map(id=>({id,name:id===1?'Boys U19':'Girls U19',day_round_details:groups(id)}));
    const saved={full_day_scope:'2026-10-31',draw_ids:[1,2],draw_order:[2,1],draw_rounds:[{draw_id:1,rounds:[2,3,4]},{draw_id:2,rounds:[2,3,4]}],selected_fixture_keys:[1,2].flatMap(id=>Array.from({length:12},(_,i)=>`individual:${id*100+i}`)),day_court_labels:{1:['1','2','3','4','5','6','7','8']},duration:75,player_rest:45,court_gap:0,start:'2026-10-31 08:30',end:'2026-10-31 18:00'};
    window.savedKeys=saved.selected_fixture_keys;window.planner=CapeDayBatch.mount({draws,venues:[{id:1,name:'Fictional school courts',court_labels:Array.from({length:10},(_,i)=>String(i+1))}],dates:['2026-10-30','2026-10-31'],initialDay:'2026-10-31',initialDrawIds:[1,2],savedByDay:{'2026-10-31':saved},replanSaved:true});
   });
   assert.match(await page.locator('#day-batch-selection-count').innerText(),/Total: 24 selected matches/);
   assert.deepEqual(await page.evaluate(()=>CapeDayBatch.buildPayload(planner).selected_fixture_keys),await page.evaluate(()=>savedKeys.slice().sort()));
   const panel=page.locator('[data-day-round-panel="2:3"]');await panel.locator('summary').click();
   const group=panel.locator('.day-batch-group').first();await group.focus();await group.press('Space');
   assert.equal(await panel.getAttribute('open'),'');
   assert.equal(await page.evaluate(()=>document.activeElement?.classList.contains('day-batch-group')),true);
   assert.match(await page.locator('#day-batch-selection-count').innerText(),/Total: 22 selected matches/);
   await panel.locator('.day-batch-group').first().press('Space');
   assert.match(await page.locator('#day-batch-selection-count').innerText(),/Total: 24 selected matches/);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
   await page.screenshot({path:path.join(root,`storage/framework/testing/saved-day-${width}.png`),fullPage:true});
  }
  assert.deepEqual(errors,[]);
 }finally{await browser.close();}
});
