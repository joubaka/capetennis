@extends('layouts.backend')
@section('content')
<div class="container-xxl"><div class="card"><div class="card-body">
<h4>Replace a competition player</h4>
<p>{{ $team->name }} · {{ $team->category->event->name }}</p>
<p class="text-muted">Preview the matches first. Completed, started and past scheduled matches stay unchanged. Original roster and payment records remain intact.</p>
<form id="substitutionForm">
<h5>1. Choose the players and scope</h5>
@csrf
<div class="row g-3">
<div class="col-md-6"><label class="form-label" for="oldIdentity">Current competition player</label><select id="oldIdentity" class="form-select" required>
@php
$choices = $roster->team_players->map(fn ($member) => ['rank' => $member->rank, 'value' => 'profile:'.$member->player_id, 'name' => $member->player?->full_name])
    ->concat($roster->team_players_no_profile->map(fn ($member) => ['rank' => $member->rank, 'value' => 'imported:'.$member->id, 'name' => trim($member->name.' '.$member->surname).' · imported']))->sortBy('rank');
@endphp
@foreach($choices as $choice)
<option value="{{ $choice['value'] }}">({{ $choice['rank'] }}) {{ $choice['name'] }}</option>
@endforeach
</select></div>
<div class="col-md-6"><label class="form-label" for="newType">Replacement identity</label><select id="newType" name="new_type" class="form-select"><option value="profile">Existing player profile</option><option value="imported">New imported player</option></select></div>
<div id="profileFields" class="col-12"><label class="form-label" for="playerSearch">Search player name</label><input id="playerSearch" class="form-control" placeholder="At least 2 letters"><button type="button" id="searchPlayers" class="btn btn-outline-primary my-2">Find players</button><select id="incomingProfile" name="new_id" class="form-select" aria-label="Replacement profile"><option value="">Choose a profile</option></select></div>
<div id="importedFields" class="col-12" hidden><div class="row g-3">
<div class="col-md-6"><label class="form-label" for="incomingName">First name</label><input id="incomingName" name="name" maxlength="100" class="form-control"></div>
<div class="col-md-6"><label class="form-label" for="incomingSurname">Surname</label><input id="incomingSurname" name="surname" maxlength="100" class="form-control"></div>
<div class="col-md-6"><label class="form-label" for="incomingBirth">Date of birth</label><input id="incomingBirth" name="date_of_birth" type="date" class="form-control"></div>
<div class="col-md-6"><label class="form-label" for="incomingGender">Gender</label><select id="incomingGender" name="gender" class="form-select"><option value="male">Male</option><option value="female">Female</option></select></div>
</div></div>
<div class="col-md-6"><label class="form-label" for="replacementScope">When to use the replacement</label><select id="replacementScope" name="scope" class="form-select"><option value="next">All upcoming unstarted matches</option><option value="round">From a round onward</option><option value="specific">Selected matches only (roster unchanged)</option></select></div>
<div id="roundField" class="col-md-6" hidden><label class="form-label" for="fromRound">Starting round</label><input id="fromRound" name="from_round" type="number" min="1" value="1" class="form-control"></div>
<div class="col-12"><label class="form-label" for="replacementReason">Reason</label><textarea id="replacementReason" name="reason" maxlength="1000" minlength="5" class="form-control" required></textarea></div>
</div>
<div class="alert alert-info mt-3">Payment coverage does not automatically transfer. Free replacements and returning players with their own verified team coverage are supported. A newly paid replacement needs a separate approved payment integration; ordinary registration may already occupy a roster slot and cannot be used to bypass this check. No refund or payment is changed by this action.</div>
<p class="text-muted small">A round cutoff applies to existing draws. New draws use the latest competition roster. A selected-match stand-in leaves the roster, new draws and all other assignments unchanged. Events with substitutions cannot be destructively regenerated.</p>
<h5 class="mt-3">2. Review affected matches</h5>
<p data-replacement-review-state class="small text-muted" role="status" aria-live="polite">Preview required before confirmation.</p>
<button id="previewReplacement" type="button" class="btn btn-outline-primary">Preview replacement</button>
<div id="replacementStatus" role="status" class="mt-3"></div>
<div id="replacementPreview" class="mt-3"></div>
<h5 class="mt-3">3. Confirm the reviewed replacement</h5>
<button id="confirmReplacement" type="button" class="btn btn-primary mt-2" disabled>Confirm replacement</button>
</form>
@if(isset($history) && $history->isNotEmpty())
<details class="border rounded p-3 mt-4"><summary style="min-height:44px">Recorded replacements</summary>
<p class="text-muted small">Latest 30 records. Original registration and payment records remain unchanged.</p>
@foreach($history as $record)
<div class="border rounded p-2 mb-2">
<strong>{{ $record->details['old_name'] }} → {{ $record->details['new_name'] }}</strong>
<div class="small">Rank {{ $record->details['source_rank'] }} · {{ $record->details['scope'] === 'specific' ? 'Selected matches only' : ($record->details['scope'] === 'round' ? 'From round '.$record->details['from_round'] : 'Upcoming matches') }} · {{ count($record->details['selected_ids']) }} matches</div>
<div class="small">{{ $actors->get($record->actor_id)?->name ?? 'Administrator' }} · {{ $record->created_at->format('d M Y H:i') }} · {{ $record->details['reason'] }}</div>
</div>
@endforeach
</details>
@endif
</div></div></div>
@endsection
@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
const form=document.getElementById('substitutionForm'), status=document.getElementById('replacementStatus'), output=document.getElementById('replacementPreview'), confirm=document.getElementById('confirmReplacement');
let plan=null, requestKey=crypto.randomUUID(), selected=new Set(), revision=0;
const urls={search:@json(route('backend.team-substitutions.players', $team)),preview:@json(route('backend.team-substitutions.preview', $team)),apply:@json(route('backend.team-substitutions.store', $team))};
const message=text=>{status.textContent=text;};
const reviewState=document.querySelector('[data-replacement-review-state]');
const invalidate=()=>{revision++;plan=null;confirm.disabled=true;reviewState.textContent='Preview required. Any previous preview is out of date.';};
const data=()=>{const payload=Object.fromEntries(new FormData(form));const old=document.getElementById('oldIdentity').value.split(':');payload.old_type=old[0];payload.old_id=Number(old[1]);if(payload.new_type==='profile'){delete payload.name;delete payload.surname;delete payload.date_of_birth;delete payload.gender;}else delete payload.new_id;if(payload.scope!=='round')delete payload.from_round;payload.fixture_ids=[...selected];return payload;};
async function post(url,payload){const response=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':form.querySelector('[name=_token]').value},body:JSON.stringify(payload)});const json=await response.json();if(!response.ok)throw new Error(Object.values(json.errors||{}).flat().join(' ')||json.message||'Unable to complete replacement');return json;}
form.addEventListener('input',invalidate);form.addEventListener('change',invalidate);
document.getElementById('newType').addEventListener('change',event=>{document.getElementById('profileFields').hidden=event.target.value!=='profile';document.getElementById('importedFields').hidden=event.target.value!=='imported';});
document.getElementById('replacementScope').addEventListener('change',event=>{document.getElementById('roundField').hidden=event.target.value!=='round';});
document.getElementById('searchPlayers').addEventListener('click',async()=>{try{const term=document.getElementById('playerSearch').value;if(term.length<2)return message('Enter at least two letters.');const response=await fetch(urls.search+'?search='+encodeURIComponent(term),{headers:{Accept:'application/json'}});const players=await response.json();if(!response.ok)throw new Error('Player search unavailable');const select=document.getElementById('incomingProfile');select.replaceChildren(new Option('Choose a profile',''));players.forEach(p=>select.add(new Option(p.name+' '+p.surname,p.id)));invalidate();}catch(error){message(error.message);}});
document.getElementById('previewReplacement').addEventListener('click',async()=>{invalidate();const requestedRevision=revision;try{const response=await post(urls.preview,data());if(requestedRevision!==revision)return;plan=response;output.replaceChildren();const summary=document.createElement('p');summary.textContent=plan.old_name+' → '+plan.new_name+' · source rank '+plan.source_rank+' · '+plan.selected_ids.length+' matches selected';output.append(summary);(plan.warnings||[]).forEach(text=>{const warning=document.createElement('p');warning.className='alert alert-warning';warning.textContent=text;output.append(warning);});plan.fixtures.forEach(fixture=>{const label=document.createElement('label');label.className='d-block border rounded p-2 mb-2';const checkbox=document.createElement('input');checkbox.type='checkbox';checkbox.className='form-check-input me-2';checkbox.checked=fixture.selected;checkbox.disabled=fixture.protected||data().scope!=='specific';checkbox.addEventListener('change',()=>{checkbox.checked?selected.add(fixture.id):selected.delete(fixture.id);invalidate();message('Preview again to review the selected matches.');});label.append(checkbox,document.createTextNode(fixture.draw+' · Round '+fixture.round+' · #'+fixture.id+(fixture.protected?' · protected':'')+(fixture.published?' · published':'') ));const matchup=document.createElement('div');matchup.className='small mt-1';const sideText=side=>side.region+': '+side.players.map(p=>(p.rank?'('+p.rank+') ':' ')+p.name).join(' + ');matchup.textContent=sideText(fixture.home)+' vs '+sideText(fixture.away)+(fixture.selected?' → replacement '+plan.new_name:' · unchanged');label.append(matchup);output.append(label);});confirm.disabled=data().scope==='specific'&&!plan.selected_ids.length;reviewState.textContent='Preview ready. Review affected and protected matches before confirming.';message('Review the preview before confirming.');}catch(error){message(error.message);}});
confirm.addEventListener('click', async () => {
  if (!plan) return;
  confirm.disabled = true;
  try {
    const result = await post(urls.apply, {...data(), fingerprint:plan.fingerprint, request_key:requestKey});
    message('Replacement recorded #' + result.id + '. Upcoming matches and their bookings were checked.');
    output.replaceChildren();
    const reports = Object.values(result.adaptation || result.details?.adaptation || {});
    const warnings = [...new Set(reports.flatMap(report => report.warnings || []))];
    warnings.forEach(text => {
      const warning = document.createElement('p');
      warning.className = 'alert alert-warning';
      warning.textContent = text;
      output.append(warning);
    });
    if (reports.some(report => (report.added_fixture_ids || []).length || (report.cleared_fixture_ids || []).length)) {
      const link = document.createElement('a');
      link.className = 'btn btn-primary';
      link.href = @json(route('backend.event-venue-schedule.index', $team->category->event_id));
      link.textContent = 'Review updated schedule';
      output.append(link);
    }
    plan = null;
    reviewState.textContent = 'Replacement recorded. Preview again before another replacement.';
    requestKey = crypto.randomUUID();
  } catch (error) {
    message(error.message);
    confirm.disabled = false;
  }
});
});
</script>
@endsection
