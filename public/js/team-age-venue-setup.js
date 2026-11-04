(function (root) {
  'use strict';
  const payload = (age, state, courts, bands) => {
    if (!state || !state.draw_ids.length) throw Error('Choose a team age group first.');
    if (!courts.length || courts.some(row => !row.court_labels.length)) throw Error('Choose at least one court at each selected venue.');
    if (bands.length > 50) throw Error('Use at most 50 playing-order bands.');
    bands.forEach((band, index) => {
      if (!Number.isInteger(band.min_rank) || !Number.isInteger(band.max_rank) || band.min_rank < 1 || band.max_rank < band.min_rank || band.max_rank > 100 || !courts.some(row => row.venue_id === band.venue_id)) throw Error('Use valid playing-order numbers and a selected venue for every band.');
      if (bands.slice(0,index).some(other => band.min_rank <= other.max_rank && band.max_rank >= other.min_rank)) throw Error('Playing-order bands cannot overlap.');
    });
    return {age:Number(age), revision:state.revision, court_allocations:courts, rank_venue_preferences:bands};
  };
  function mount(options) {
    const {document:doc, states, venues, post, onSaved, onDirty = () => {}} = options;
    const panel = doc.getElementById('age-venue-setup');
    if (!panel) return;
    const ageSelect = doc.getElementById('programme-age'), drafts = new Map(), dirty = new Set();
    const markDirty = () => {if(active){dirty.add(active);onDirty(true);}};
    const courtsBox = doc.getElementById('age-setup-courts'), bandsBox = doc.getElementById('age-setup-bands'), status = doc.getElementById('age-setup-status'), save = doc.getElementById('age-setup-save');
    const escape = value => String(value).replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
    let active = '', saving = false, step = 2;
    const read = () => ({court_allocations:[...courtsBox.querySelectorAll('[data-age-venue]:checked')].map(input => ({venue_id:Number(input.value),court_labels:[...courtsBox.querySelectorAll(`[data-age-court="${input.value}"]:checked`)].map(court => court.value)})),
      rank_venue_preferences:[...bandsBox.querySelectorAll('[data-age-band]')].map(row => ({min_rank:Number(row.querySelector('[data-age-min]').value),max_rank:Number(row.querySelector('[data-age-max]').value),venue_id:Number(row.querySelector('[data-age-band-venue]').value)}))});
    const venueOptions = selected => '<option value="">Choose selected venue</option>'+[...courtsBox.querySelectorAll('[data-age-venue]:checked')].map(input => `<option value="${input.value}" ${Number(selected) === Number(input.value) ? 'selected' : ''}>${escape(input.dataset.name)}</option>`).join('');
    const setStep = value => {
      step=value;
      [2,3,4].forEach(number => {
        doc.getElementById('age-setup-step-'+number).hidden=number!==step;
        const marker=doc.getElementById('age-setup-progress-'+number);
        marker.setAttribute('aria-current',number===step?'step':'false');
        marker.className=number===step?'text-primary':'text-muted';
      });
      doc.getElementById('age-setup-back').hidden=step===2;
      doc.getElementById('age-setup-next').hidden=step===4;
      doc.getElementById('age-setup-next').textContent=step===2?'Next: playing-order numbers':'Next: review age setup';
      if(step===4) {
        const setup=read();
        doc.getElementById('age-setup-review').innerHTML=`<strong>Under ${escape(active)}</strong><ul>${setup.court_allocations.map(row=>`<li>${escape(venues.find(venue=>Number(venue.id)===row.venue_id)?.name || 'Venue')} · Courts ${row.court_labels.map(escape).join(', ')}</li>`).join('')}</ul>`
          +(setup.rank_venue_preferences.length?`<ul>${setup.rank_venue_preferences.map(row=>`<li>Playing-order numbers ${row.min_rank}–${row.max_rank}: ${escape(venues.find(venue=>Number(venue.id)===row.venue_id)?.name || 'Venue')}</li>`).join('')}</ul>`:'<p>All playing-order numbers use these selected courts.</p>');
      }
    };
    const addBand = (rule = {}) => {
      const row = doc.createElement('div'); row.dataset.ageBand=''; row.className='row g-2 align-items-end mb-2';
      row.innerHTML=`<div class="col-6 col-sm-2"><label class="form-label small">From number<input type="number" min="1" max="100" class="form-control" data-age-min value="${escape(rule.min_rank ?? 1)}"></label></div><div class="col-6 col-sm-2"><label class="form-label small">To number<input type="number" min="1" max="100" class="form-control" data-age-max value="${escape(rule.max_rank ?? 4)}"></label></div><div class="col-9 col-sm-6"><label class="form-label small d-block">Venue<select class="form-select" data-age-band-venue>${venueOptions(rule.venue_id)}</select></label></div><div class="col-3 col-sm-2"><button type="button" class="btn btn-outline-danger mb-2" data-age-remove>Remove</button></div>`;
      bandsBox.appendChild(row);
    };
    const render = () => {
      if (active) drafts.set(active,read());
      active = ageSelect.value;
      panel.hidden=!states[active]; doc.getElementById('age-setup-bulk-help').hidden=active !== 'bulk';
      status.textContent='';
      if (panel.hidden) { active=''; return; }
      const state=states[active], setup=drafts.get(active) || state.setup || {court_allocations:[],rank_venue_preferences:[]};
      courtsBox.innerHTML=venues.map(venue => {
        const allocation=setup.court_allocations.find(row => Number(row.venue_id)===Number(venue.id));
        return `<div class="col-12 col-md-6"><div class="border rounded p-3 h-100"><label class="form-check-label"><input type="checkbox" class="form-check-input me-2" data-age-venue data-name="${escape(venue.name)}" value="${venue.id}" ${allocation?'checked':''}>${escape(venue.name)}</label><div class="d-flex flex-wrap gap-3 mt-2">${venue.labels.map(label => `<label><input type="checkbox" class="form-check-input me-1" data-age-court="${venue.id}" value="${escape(label)}" ${allocation?.court_labels.map(String).includes(String(label))?'checked':''} ${allocation?'':'disabled'}>Court ${escape(label)}</label>`).join('')}</div></div></div>`;
      }).join('');
      bandsBox.innerHTML=''; setup.rank_venue_preferences.forEach(addBand);
      setStep(2);
      save.disabled=state.locked || saving;
      status.textContent=state.locked?'This age contains a locked draw. Unlock it before changing its setup.':!venues.length?'Add event venues and courts above, then reload this page.':state.exceptions?'Some rounds differ from this saved age setup. Saving replaces those exceptions.':state.setup?'Saved age setup loaded. Saving again replaces all round-specific exceptions.':'Choose venues and courts, then add playing-order bands if this age uses more than one venue.';
    };
    ageSelect.addEventListener('change',render);
    doc.getElementById('age-setup-next').addEventListener('click',() => {
      if(saving || !active)return;
      const draft=read();
      try {payload(active,states[active],draft.court_allocations,step===2?[]:draft.rank_venue_preferences);status.textContent='';setStep(step+1);}
      catch(error) {status.textContent=error.message;}
    });
    doc.getElementById('age-setup-back').addEventListener('click',() => {if(!saving)setStep(Math.max(2,step-1));});
    courtsBox.addEventListener('change',event => {
      if (!event.target.matches('[data-age-venue]')) return;
      courtsBox.querySelectorAll(`[data-age-court="${event.target.value}"]`).forEach(input => {input.disabled=!event.target.checked; if(!event.target.checked)input.checked=false;});
      bandsBox.querySelectorAll('[data-age-band-venue]').forEach(select => {const value=select.value;select.innerHTML=venueOptions(value);});
    });
    panel.addEventListener('change',markDirty);
    panel.addEventListener('input',markDirty);
    doc.getElementById('age-setup-add-band').addEventListener('click',() => {addBand();markDirty();});
    bandsBox.addEventListener('click',event => {const button=event.target.closest('[data-age-remove]');if(button){button.closest('[data-age-band]').remove();markDirty();}});
    save.addEventListener('click',async () => {
      if(saving || !active)return;
      const age=active, draft=read(); let data;
      try {data=payload(age,states[age],draft.court_allocations,draft.rank_venue_preferences);} catch(error) {status.textContent=error.message;return;}
      saving=true; const saveLabel=save.innerHTML;save.innerHTML='<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Saving age setup…';save.setAttribute('aria-busy','true');
      const controls=[ageSelect,...panel.querySelectorAll('button,input,select')]; const prior=controls.map(control=>control.disabled);controls.forEach(control=>control.disabled=true);
      status.textContent='Saving this age’s venues, courts and playing-order numbers…';
      try {
        const result=await post({age_setup:data}); states[age]=result.state;drafts.set(age,result.state.setup);dirty.delete(age);onSaved(result);onDirty(dirty.size>0);
        if(active===age)status.textContent=result.message;
      } catch(error) {if(active===age)status.textContent=error.message;}
      finally {saving=false;save.innerHTML=saveLabel;save.setAttribute('aria-busy','false');controls.forEach((control,index)=>control.disabled=prior[index]);if(active!==age)render();}
    });
    render();
    return {isDirty:() => dirty.size>0};
  }
  root.TeamAgeVenueSetup={payload,mount};
  if(typeof module !== 'undefined')module.exports=root.TeamAgeVenueSetup;
})(typeof window !== 'undefined' ? window : globalThis);
