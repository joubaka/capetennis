const fs=require('node:fs');
const test=require('node:test');
const assert=require('node:assert/strict');
const source=fs.readFileSync('resources/views/backend/schedule/event-venue-schedule.blade.php','utf8');
const postSource=source.slice(source.indexOf('  const post = async'),source.indexOf('  const announcementTitle'));
function client(fetch){return new Function('fetch', `let previewGeneration=0; const previewUrl='/preview',csrf='test'; ${postSource}; return {post,invalidate(){previewGeneration++;}};`)(fetch);}
function deferred(){let resolve,reject;const promise=new Promise((a,b)=>{resolve=a;reject=b});return{promise,resolve,reject};}
test('changing selected draws rejects a late preview success',async()=>{
 const pending=deferred();const api=client(()=>pending.promise);const result=api.post('/preview',{draw_ids:[1474]});api.invalidate();pending.resolve({ok:true,json:async()=>({matches:[{draw_id:1474}]})});
 await assert.rejects(result,error=>error.stalePreview===true);
});
test('changing selection suppresses a stale server or network error',async()=>{
 for(const network of [false,true]){const pending=deferred();const api=client(()=>pending.promise);const result=api.post('/preview',{draw_ids:[1474]});api.invalidate();network?pending.reject(new Error('Old network error')):pending.resolve({ok:false,json:async()=>({message:'Old scope error'})});await assert.rejects(result,error=>error.stalePreview===true);}
});
test('latest preview preserves selected payload and ordinary errors',async()=>{
 let submitted;const api=client(async(url,options)=>{submitted=JSON.parse(options.body);return{ok:true,json:async()=>({input:submitted,matches:[]})};});
 const result=await api.post('/preview',{draw_ids:[1474],venue_ids:[1,2,3]});assert.deepEqual(submitted,{draw_ids:[1474],venue_ids:[1,2,3]});assert.equal(result.previewGeneration,1);
 const failed=client(async()=>({ok:false,json:async()=>({message:'Current scope error'})}));await assert.rejects(failed.post('/preview',{}),error=>error.message==='Current scope error'&&!error.stalePreview);
});
