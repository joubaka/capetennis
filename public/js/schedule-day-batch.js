(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.CapeDayBatch = api;
})(typeof globalThis === 'object' ? globalThis : this, function () {
  'use strict';
  const details = draw => draw.day_round_details || {};
  const rounds = draw => Object.keys(details(draw)).map(Number).sort((a,b) => a-b);
  const courtLabels = venue => (venue.court_labels || (venue.court_list || []).map(court => court.label)).map(String);
  function createState(draws, venues, dates) {
    const state = {draws, venues, dates, day:dates[0] || '', selectedDrawIds:[], selectedRounds:new Map(), selectedFixtures:new Map(), courts:new Map()};
    state.ready = draw => !draw.locked && rounds(draw).length > 0;
    state.matches = (drawId, round) => (details(draws.find(draw => Number(draw.id) === Number(drawId)) || {})[round] || []).flatMap(group => group.matches || []);
    state.eligible = match => !match.scheduled_at || String(match.scheduled_at).slice(0,10) === state.day;
    state.updateBooking = (key, scheduledAt) => {
      draws.forEach(draw=>rounds(draw).forEach(round=>state.matches(draw.id,round).forEach(match=>{
        if(match.key!==key)return;
        match.scheduled_at=String(scheduledAt).replace('T',' ');
        if(!state.eligible(match))state.selectedFixtures.get(Number(draw.id)+':'+round)?.delete(key);
      })));
    };
    state.selectRound = (drawId, round, checked) => {
      drawId=Number(drawId); round=Number(round);
      if (!state.selectedDrawIds.includes(drawId)) return;
      if (!rounds(draws.find(draw=>Number(draw.id)===drawId)||{}).includes(round)) return;
      const selected = state.selectedRounds.get(drawId) || new Set();
      if (checked) selected.add(round); else selected.delete(round);
      state.selectedRounds.set(drawId,selected);
      state.selectedFixtures.set(drawId+':'+round,new Set(checked ? state.matches(drawId,round).filter(state.eligible).map(match => match.key) : []));
    };
    state.selectDraw = (id, checked) => {
      id=Number(id); const draw=draws.find(draw => Number(draw.id)===id);
      if (!draw || !state.ready(draw)) return;
      if (checked && !state.selectedDrawIds.includes(id)) {
        state.selectedDrawIds.push(id); state.selectRound(id,rounds(draw)[0],true);
      } else if (!checked) {
        state.selectedDrawIds=state.selectedDrawIds.filter(value=>value!==id); state.selectedRounds.delete(id);
        [...state.selectedFixtures.keys()].filter(key=>key.startsWith(id+':')).forEach(key=>state.selectedFixtures.delete(key));
      }
    };
    state.selectAll = () => draws.filter(state.ready).forEach(draw=>state.selectDraw(draw.id,true));
    state.selectionCounts = () => state.selectedDrawIds.map(id => ({id, name:draws.find(draw=>Number(draw.id)===id)?.name || '',
      count:[...(state.selectedRounds.get(id)||[])].reduce((sum,round)=>sum+(state.selectedFixtures.get(id+':'+round)?.size||0),0)}));
    state.clear = () => [...state.selectedDrawIds].forEach(id=>state.selectDraw(id,false));
    state.moveDraw = (id, direction) => {
      const index=state.selectedDrawIds.indexOf(Number(id));
      const next=index+(direction==='up' || direction===-1 ? -1 : 1);
      if(index<0 || next<0 || next>=state.selectedDrawIds.length) return;
      [state.selectedDrawIds[index],state.selectedDrawIds[next]]=[state.selectedDrawIds[next],state.selectedDrawIds[index]];
    };
    state.setDay = day => {
      if(!dates.includes(day)) throw new Error('Choose a playing day within this event.');
      state.day=day;
      state.selectedDrawIds.forEach(id=>[...(state.selectedRounds.get(id)||[])].forEach(round=>state.selectRound(id,round,true)));
    };
    state.selectFixture = (drawId, round, key, checked) => {
      const match=state.matches(drawId,round).find(match=>match.key===key);
      if(!match || !state.eligible(match)) return;
      const selected=state.selectedFixtures.get(Number(drawId)+':'+Number(round)) || new Set();
      if(checked) selected.add(key); else selected.delete(key);
      state.selectedFixtures.set(Number(drawId)+':'+Number(round),selected);
    };
    state.selectGroup = (drawId, round, index, checked) => {
      const draw=draws.find(draw=>Number(draw.id)===Number(drawId));
      (details(draw||{})[round]?.[index]?.matches || []).forEach(match=>state.selectFixture(drawId,round,match.key,checked));
    };
    state.setCourt = (id, label, checked) => {
      id=Number(id); label=String(label);
      if(!courtLabels(venues.find(venue=>Number(venue.id)===id)||{}).includes(label)) return;
      const selected=state.courts.get(id)||new Set(); if(checked) selected.add(label);else selected.delete(label);
      if(selected.size) state.courts.set(id,selected);else state.courts.delete(id);
    };
    state.restore = saved => {
      if(!saved || saved.full_day_scope!==state.day) return;
      const current=[...state.selectedDrawIds];
      state.selectedDrawIds=[...(saved.draw_order||saved.draw_ids||[]).map(Number).filter(id=>current.includes(id)),...current.filter(id=>!(saved.draw_order||saved.draw_ids||[]).map(Number).includes(id))];
      current.forEach(id=>{
        const entry=(saved.draw_rounds||[]).find(row=>Number(row.draw_id)===id);
        if(!entry) return;
        [...(state.selectedRounds.get(id)||[])].forEach(round=>state.selectRound(id,round,false));
        entry.rounds.filter(round=>rounds(draws.find(draw=>Number(draw.id)===id)).includes(Number(round))).forEach(round=>state.selectRound(id,round,true));
      });
      const restoredIds=(saved.draw_ids||[]).map(Number).filter(id=>current.includes(id));
      if(Array.isArray(saved.selected_fixture_keys)) state.selectedFixtures.forEach((selected,key)=>{
        if(!restoredIds.includes(Number(key.split(':')[0])))return;
        [...selected].forEach(fixture=>{if(!saved.selected_fixture_keys.includes(fixture))selected.delete(fixture);});
      });
      state.courts.clear(); Object.entries(saved.day_court_labels||{}).forEach(([id,labels])=>labels.forEach(label=>state.setCourt(id,label,true)));
    };
    return state;
  }
  function buildPayload(state, config={}) {
    if(!state.selectedDrawIds.length) throw new Error('Select at least one draw.');
    if(!state.dates.includes(state.day)) throw new Error('Choose a playing day.');
    const drawRounds=state.selectedDrawIds.map(id=>({draw_id:id,rounds:[...(state.selectedRounds.get(id)||[])].sort((a,b)=>a-b)}));
    if(drawRounds.some(row=>!row.rounds.length)) throw new Error('Choose at least one round for every selected draw.');
    if(drawRounds.some(row=>row.rounds.some(round=>!(state.selectedFixtures.get(row.draw_id+':'+round)?.size)))) throw new Error('Choose matches in every selected round, or remove the empty round.');
    const keys=[...new Set(drawRounds.flatMap(row=>row.rounds.flatMap(round=>[...(state.selectedFixtures.get(row.draw_id+':'+round)||[])])))].sort();
    if(!keys.length) throw new Error('Choose at least one match within the selected rounds.');
    const courts=Object.fromEntries([...state.courts].filter(([,labels])=>labels.size).map(([id,labels])=>[id,[...labels]]));
    if(!Object.keys(courts).length) throw new Error('Choose the courts available for this day.');
    const mode=config.draw_order_mode||config.orderMode||'mixed';
    if(!['mixed','sequential'].includes(mode)) throw new Error('Choose Mix draws or In order.');
    const stamp=value=>/^\d{4}-/.test(String(value))?String(value):state.day+' '+value;
    const payload = {...config, day_batch:true, full_day_scope:state.day, draw_ids:[...state.selectedDrawIds], draw_order:[...state.selectedDrawIds],
      draw_order_mode:mode, draw_rounds:drawRounds, selected_fixture_keys:keys, venue_ids:Object.keys(courts).map(Number), day_court_labels:courts,
      start:stamp(config.start_time||config.start||'08:00'), end:stamp(config.end_time||config.end||'18:00'),
      duration:Number(config.duration??60), player_rest:Number(config.player_rest??30), court_gap:Number(config.court_gap??5),wave_minutes:90,
      gender_waves:'combined', reschedule_existing:Boolean(config.reschedule_existing), allow_partial:false, replan_venue_ids:[],
    };
    ['programme','day_scope','draw_starts','venue_starts','apply_venue_ids'].forEach(key=>delete payload[key]);
    return payload;
  }
  function mount(options) {
    const doc=options.document || document, get=id=>doc.getElementById(id);
    const state=createState(options.draws,options.venues,options.dates);
    let preparationResume=null;
    const resumeKey=options.eventId ? 'cape-schedule-playoff-preparation:'+options.eventId : null;
    try {
      if(resumeKey){preparationResume=JSON.parse(doc.defaultView?.sessionStorage?.getItem(resumeKey)||'null');doc.defaultView?.sessionStorage?.removeItem(resumeKey);}
      if(preparationResume && Date.now()-preparationResume.createdAt<300000 && state.dates.includes(preparationResume.payload?.full_day_scope)){
        const prior=preparationResume.payload;
        options.initialDay=prior.full_day_scope;options.initialDrawIds=prior.draw_ids;
        options.savedByDay={...options.savedByDay,[prior.full_day_scope]:prior};
      }else preparationResume=null;
    } catch(error){preparationResume=null;}
    const escape=value=>String(value??'').replace(/[&<>"']/g,char=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    const label=date=>new Date(date+'T12:00:00').toLocaleDateString('en-GB',{weekday:'long',day:'numeric',month:'long'});
    const status=(message,error=false)=>{get('day-batch-status').textContent=message;get('day-batch-status').className='mt-3 '+(error?'text-danger':'text-muted');if(error)options.notify?.(message,'danger');};
    const changed=()=>{options.onChange?.(state);};
    if(options.initialDay && state.dates.includes(options.initialDay))state.setDay(options.initialDay);
    (options.initialDrawIds||[]).forEach(id=>state.selectDraw(id,true));
    const saved=()=>options.savedByDay?.[state.day];
    const renderCounts=()=>{const counts=state.selectionCounts();get('day-batch-selection-count').textContent=counts.length
      ? counts.map(row=>`${row.name}: ${row.count}`).join(' · ')+` · Total: ${counts.reduce((sum,row)=>sum+row.count,0)} selected matches`
      : 'No matches selected.';};
    const renderDraws=()=>{
      const preparationRows=[...(get('prepare-playoff-form')?.querySelectorAll('[data-playoff-draw]')||[])];
      preparationRows.forEach(row=>{const selected=state.selectedDrawIds.includes(Number(row.dataset.playoffDraw));row.hidden=!selected;const input=row.querySelector('input');input.disabled=!selected||row.dataset.playoffProtected==='1';input.checked=!input.disabled;});
      const preparationNotice=get('pending-playoff-rounds');if(preparationNotice)preparationNotice.hidden=!preparationRows.some(row=>!row.hidden);
      get('day-batch-draws').innerHTML=state.draws.map(draw=>`<label class="col-12 col-md-6 d-flex gap-2 align-items-center"><input type="checkbox" class="form-check-input mt-0 day-batch-draw" value="${Number(draw.id)}" ${state.selectedDrawIds.includes(Number(draw.id))?'checked':''} ${state.ready(draw)?'':'disabled'}><span>${escape(draw.name)}${draw.locked?' (locked)':state.ready(draw)?'':' (generate matches first)'}</span></label>`).join('');
      get('day-batch-order').innerHTML=state.selectedDrawIds.length?'<div class="small fw-semibold mb-1">Selected draw order</div>'+state.selectedDrawIds.map((id,index)=>`<div class="d-flex gap-2 align-items-center mb-1"><span class="flex-grow-1">${index+1}. ${escape(state.draws.find(draw=>Number(draw.id)===id)?.name)}</span><button type="button" class="btn btn-sm btn-outline-secondary" data-day-draw-move="${id}" data-direction="up" ${index?'':'disabled'} aria-label="Move draw up">Up</button><button type="button" class="btn btn-sm btn-outline-secondary" data-day-draw-move="${id}" data-direction="down" ${index<state.selectedDrawIds.length-1?'':'disabled'} aria-label="Move draw down">Down</button></div>`).join(''):'';
      get('day-batch-next-courts').disabled=!state.selectedDrawIds.length;
      get('day-batch-team-options').hidden=!state.draws.some(draw=>state.selectedDrawIds.includes(Number(draw.id))&&draw.is_team);
    };
    const renderVenues=()=>{
      get('day-batch-venues').innerHTML=state.venues.length?state.venues.map(venue=>{
        const id=Number(venue.id),labels=courtLabels(venue),selected=state.courts.get(id)||new Set(),groups=saved()?.day_venue_groups?.[id];
        return `<section class="border rounded p-3 mb-2" data-day-venue="${id}"><label class="fw-semibold"><input type="checkbox" class="form-check-input me-2 day-batch-venue" value="${id}" ${selected.size?'checked':''}>${escape(venue.name)}</label><div class="d-flex flex-wrap gap-3 mt-2">${labels.map(court=>`<label><input type="checkbox" class="form-check-input me-1 day-batch-court" data-venue="${id}" value="${escape(court)}" ${selected.has(court)?'checked':''}>Court ${escape(court)}</label>`).join('')}</div><details class="mt-2"><summary>Venue hours and court groups (optional)</summary><div class="row g-2 mt-1"><label class="col-6 small">Venue opens<input type="time" class="form-control form-control-sm day-batch-venue-open" value="${escape(saved()?.full_day_venue_windows?.[id]?.start?.slice(11,16)||'')}"></label><label class="col-6 small">Venue finishes<input type="time" class="form-control form-control-sm day-batch-venue-close" value="${escape(saved()?.full_day_venue_windows?.[id]?.end?.slice(11,16)||'')}"></label></div><label class="small mt-2"><input class="form-check-input me-1 day-batch-split" type="checkbox" ${groups?'checked':''} ${labels.length<2?'disabled':''}>Split selected courts into Group A and Group B</label><div class="small text-muted">Whole team ties stay in one group. Position-band allocations retain their own venue rules.</div>${labels.map((court,index)=>{const window=saved()?.full_day_court_starts?.[id]?.find(row=>String(row.court)===court);return `<div class="d-flex flex-wrap gap-2 align-items-end mt-2" data-day-court-hours="${escape(court)}"><span class="small">Court ${escape(court)}</span><label class="small">Group<select class="form-select form-select-sm day-batch-court-group"><option value="A">A</option><option value="B" ${(groups?.B?.map(String).includes(court)||!groups&&index>=Math.ceil(labels.length/2))?'selected':''}>B</option></select></label><label class="small">Available from<input type="time" class="form-control form-control-sm day-batch-court-open" value="${escape(window?.start?.slice(11,16)||'')}"></label><label class="small">Until<input type="time" class="form-control form-control-sm day-batch-court-close" value="${escape(window?.end?.slice(11,16)||'')}"></label></div>`;}).join('')}</details></section>`;
      }).join(''):'<div class="alert alert-info">No event venues are available yet. Use Add or edit event venues &amp; courts above, then choose the courts for this day.</div>';
    };
    const renderRounds=()=>{
      const container=get('day-batch-rounds');
      const opened=new Set([...container.querySelectorAll('details[data-day-round-panel][open]')].map(panel=>panel.dataset.dayRoundPanel));
      const active=doc.activeElement;
      const focus=active && container.contains?.(active) ? {kind:['round','group','fixture'].find(kind=>active.matches?.('.day-batch-'+kind)),draw:active.dataset?.draw,round:active.dataset?.round,value:active.value} : null;
      get('day-batch-rounds').innerHTML=state.selectedDrawIds.map(id=>{
        const draw=state.draws.find(draw=>Number(draw.id)===id);
        return `<section class="mb-3"><h6>${escape(draw.name)}</h6>${rounds(draw).map(round=>{
          const checked=state.selectedRounds.get(id)?.has(round),groups=details(draw)[round];
          return `<div class="border rounded p-2 mb-2"><label class="fw-semibold"><input type="checkbox" class="form-check-input me-2 day-batch-round" data-draw="${id}" value="${round}" ${checked?'checked':''}>${escape(groups.map(group=>group.label).join(' / '))} <span class="small text-muted">(Round ${round})</span></label><ul class="small mb-1">${groups.map(group=>`<li>${escape(group.label)}: ${Number(group.count)} ${Number(group.count)===1?'match':'matches'}</li>`).join('')}</ul>${checked?`<details data-day-round-panel="${id}:${round}" ${opened.has(id+':'+round)?'open':''}><summary class="small">Choose position groups or specific matches</summary>${groups.map((group,index)=>{const eligible=(group.matches||[]).filter(state.eligible),selected=state.selectedFixtures.get(id+':'+round)||new Set();return `<div class="border-top mt-2 pt-2"><label class="small fw-semibold"><input type="checkbox" class="form-check-input me-1 day-batch-group" data-draw="${id}" data-round="${round}" value="${index}" ${eligible.length&&eligible.every(match=>selected.has(match.key))?'checked':''} ${eligible.length?'':'disabled'}>${escape(group.label)}</label><div class="d-flex flex-wrap gap-3 mt-1">${(group.matches||[]).map(match=>`<label class="small"><input type="checkbox" class="form-check-input me-1 day-batch-fixture" data-draw="${id}" data-round="${round}" value="${escape(match.key)}" ${selected.has(match.key)?'checked':''} ${state.eligible(match)?'':'disabled'}>Match ${Number(match.number)}${match.scheduled_at?` (${escape(match.scheduled_at.slice(0,16))}${state.eligible(match)?' · kept unless replanning':' · another day, kept'})`:''}</label>`).join('')}</div></div>`;}).join('')}</details>`:''}</div>`;
        }).join('')}</section>`;
      }).join('');
      if(focus?.kind) [...container.querySelectorAll('.day-batch-'+focus.kind)].find(input=>input.dataset.draw===focus.draw && input.dataset.round===focus.round && input.value===focus.value)?.focus({preventScroll:true});
      renderCounts();
    };
    const applySavedControls=()=>{
      get('day-batch-replan').checked=false;
      const prefs=saved(); if(!prefs)return;
      [['duration','duration'],['player_rest','rest'],['court_gap','gap']].forEach(([key,suffix])=>{if(prefs[key]!==undefined)get('day-batch-'+suffix).value=prefs[key];});
      get('day-batch-start').value=String(prefs.start||'').slice(11,16)||'08:00';get('day-batch-end').value=String(prefs.end||'').slice(11,16)||'18:00';
      get('day-batch-order-mode').value=prefs.draw_order_mode||'mixed';get('day-batch-progression').value=prefs.round_progression||'team_ready';get('day-batch-tie-allocation').value=prefs.tie_allocation||'balanced';
      get('day-batch-replan').checked=false;
    };
    const updateBooking=state.updateBooking;
    state.updateBooking=(key,scheduledAt)=>{updateBooking(key,scheduledAt);renderRounds();};
    const readOptions=()=>{
      const config={...(options.getConfig?.()||{}), start_time:get('day-batch-start').value,end_time:get('day-batch-end').value,
        duration:Number(get('day-batch-duration').value),player_rest:Number(get('day-batch-rest').value),court_gap:Number(get('day-batch-gap').value),
        limit_daily_matches:get('day-batch-limit').checked,reschedule_existing:get('day-batch-replan').checked,
        draw_order_mode:get('day-batch-order-mode').value,round_progression:get('day-batch-progression').value,tie_allocation:get('day-batch-tie-allocation').value,
        day_venue_groups:{},full_day_venue_windows:{},full_day_court_starts:{}};
      state.courts.forEach((courts,id)=>{
        const section=get('day-batch-venues').querySelector(`[data-day-venue="${id}"]`);
        const opens=section.querySelector('.day-batch-venue-open').value,closes=section.querySelector('.day-batch-venue-close').value;
        if(opens||closes)config.full_day_venue_windows[id]={start:state.day+' '+(opens||config.start_time),end:state.day+' '+(closes||config.end_time)};
        const split=section.querySelector('.day-batch-split').checked;
        if(split)config.day_venue_groups[id]={A:[],B:[]};
        section.querySelectorAll('[data-day-court-hours]').forEach(row=>{
          const court=row.dataset.dayCourtHours;if(!courts.has(court))return;
          if(split)config.day_venue_groups[id][row.querySelector('.day-batch-court-group').value].push(court);
          const start=row.querySelector('.day-batch-court-open').value,end=row.querySelector('.day-batch-court-close').value;
          if(start||end)(config.full_day_court_starts[id]||=[]).push({court,start:state.day+' '+(start||opens||config.start_time),end:state.day+' '+(end||closes||config.end_time)});
        });
      });
      return config;
    };
    get('day-batch-date').innerHTML=state.dates.map(date=>`<option value="${date}" ${date===state.day?'selected':''}>${escape(label(date))}</option>`).join('');
    state.restore(saved());applySavedControls();renderDraws();renderVenues();renderRounds();
    if(options.replanSaved){get('day-batch-replan').checked=true;get('day-batch-courts-step').hidden=false;get('day-batch-rounds-step').hidden=false;}
    if(preparationResume){get('day-batch-courts-step').hidden=false;get('day-batch-rounds-step').hidden=false;get('day-batch-limit').checked=Boolean(preparationResume.payload.limit_daily_matches);}
    get('prepare-playoff-form')?.addEventListener('submit',()=>{
      try{if(resumeKey)doc.defaultView?.sessionStorage?.setItem(resumeKey,JSON.stringify({createdAt:Date.now(),payload:buildPayload(state,readOptions())}));}catch(error){/* Preparation remains available when browser storage is unavailable. */}
    });
    get('day-batch-all').addEventListener('click',()=>{state.selectAll();renderDraws();renderRounds();changed();});
    get('day-batch-clear').addEventListener('click',()=>{state.clear();renderDraws();renderRounds();changed();});
    get('day-batch-workflow').addEventListener('change',event=>{
      const input=event.target;
      if(input.matches('.day-batch-draw')){state.selectDraw(input.value,input.checked);renderDraws();renderRounds();}
      else if(input.id==='day-batch-date'){state.setDay(input.value);state.courts.clear();state.restore(saved());applySavedControls();renderVenues();renderRounds();}
      else if(input.matches('.day-batch-court')){state.setCourt(input.dataset.venue,input.value,input.checked);const section=input.closest('[data-day-venue]');section.querySelector('.day-batch-venue').checked=Boolean(state.courts.get(Number(input.dataset.venue))?.size);}
      else if(input.matches('.day-batch-venue')){
        const venue=state.venues.find(venue=>Number(venue.id)===Number(input.value));
        courtLabels(venue).forEach(court=>state.setCourt(venue.id,court,input.checked));
        input.closest('[data-day-venue]').querySelectorAll('.day-batch-court').forEach(court=>{court.checked=input.checked;});
      }
      else if(input.matches('.day-batch-round')){state.selectRound(input.dataset.draw,input.value,input.checked);renderRounds();}
      else if(input.matches('.day-batch-fixture')){state.selectFixture(input.dataset.draw,input.dataset.round,input.value,input.checked);renderRounds();}
      else if(input.matches('.day-batch-group')){state.selectGroup(input.dataset.draw,input.dataset.round,input.value,input.checked);renderRounds();}
      changed();
    });
    get('day-batch-order').addEventListener('click',event=>{const button=event.target.closest('[data-day-draw-move]');if(!button)return;state.moveDraw(button.dataset.dayDrawMove,button.dataset.direction);renderDraws();changed();});
    get('day-batch-next-courts').addEventListener('click',()=>{if(!state.selectedDrawIds.length)return status('Select at least one draw.',true);get('day-batch-courts-step').hidden=false;get('day-batch-courts-step').scrollIntoView({behavior:'smooth',block:'start'});});
    get('day-batch-next-rounds').addEventListener('click',()=>{if(!state.courts.size)return status('Choose the courts available for this day.',true);get('day-batch-rounds-step').hidden=false;renderRounds();get('day-batch-rounds-step').scrollIntoView({behavior:'smooth',block:'start'});});
    get('day-batch-preview').addEventListener('click',async event=>{
      const invalid=get('day-batch-workflow').querySelector('input:invalid');if(invalid){invalid.reportValidity();return;}
      const button=event.currentTarget;button.disabled=true;
      try{const payload=buildPayload(state,readOptions());status('Checking selected matches, courts, player rest and draw order…');await options.onPreview(payload,state);status('Review this day’s selected matches below. Saving and publishing are separate.');}
      catch(error){if(!error.stalePreview)status(error.message,true);}finally{button.disabled=false;}
    });
    get('day-batch-save-timing').addEventListener('click',async event=>{const button=event.currentTarget;button.disabled=true;try{await options.onSaveTiming?.(readOptions());status('Timing settings saved. Existing match times are unchanged.');}catch(error){status(error.message,true);}finally{button.disabled=false;}});
    return state;
  }
  return {createState,buildPayload,mount};
});
