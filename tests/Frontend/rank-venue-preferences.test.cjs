const fs=require('node:fs');const test=require('node:test');const assert=require('node:assert/strict');
const source=fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php','utf8');
const helpers=source.slice(source.indexOf('  const coalesceRankRules ='),source.indexOf('  const rememberRankRules ='));
function client(rows,assigned){return new Function('readRankRules','document',`${helpers};return {coalesceRankRules,applicableRankRules};`)(()=>rows,{querySelector(selector){const values=[...selector.matchAll(/"(\d+)"/g)].map(m=>Number(m[1]));return{checked:assigned(values[0],values[1])};}});}
test('split shared boys and girls bands coalesce to three rows when both are selected again',()=>{
 const api=client([],()=>true);const split=[];for(const [min_rank,max_rank,venue_id]of[[1,4,1],[5,6,2],[7,8,3]])for(const draw of[1474,1475])split.push({min_rank,max_rank,venue_id,draw_ids:[draw]});const result=api.coalesceRankRules(split);assert.equal(result.length,3);for(const row of result)assert.deepEqual(row.draw_ids,[1474,1475]);
});
test('removed venue mapping is excluded from preview payload only for the affected draw',()=>{
 const rules=[{min_rank:1,max_rank:4,venue_id:1,draw_ids:[1474,1475]},{min_rank:5,max_rank:6,venue_id:2,draw_ids:[1474,1475]}];const api=client(rules,(draw,venue)=>draw!==1474||venue!==1);assert.deepEqual(api.applicableRankRules(),[{...rules[0],draw_ids:[1475]},rules[1]]);assert.deepEqual(rules[0].draw_ids,[1474,1475]);
});
test('new blank venue band remains visible to validation rather than silently disappearing',()=>{const rule={min_rank:1,max_rank:4,venue_id:0,draw_ids:[1474]};assert.deepEqual(client([rule],()=>false).applicableRankRules(),[rule]);});
