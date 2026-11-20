@if(!$config)
<p class="alert alert-info">Save camp setup first. Then approve roster players together here.</p>
@elseif($rosterRows->isEmpty())
<p>Choose regions and teams to bring their players into your draft, or add a player below.</p>
@else
<form method="POST" action="{{ route('backend.camps.approve-roster',$plan) }}" class="mb-4" data-camp-selection="keys[]">@csrf
<input type="hidden" name="revision" value="{{ $plan->revision }}">
<p>Select the players you want to invite. A single matching camp division is suggested; check the list before approval. Imported identities are reviewed individually below.</p>
<label><input type="checkbox" data-camp-select-all="keys[]"> Select all ready roster players</label>
<div class="mt-2 mb-3" style="max-height:30rem;overflow-y:auto" tabindex="0" role="region" aria-label="Roster players to review">
@foreach($rosterRows as $row)
@php($candidate=$row['candidate'])
<div class="camp-item row g-2 align-items-center"><div class="col-12 col-md-7"><label><input type="checkbox" name="keys[]" value="{{ $candidate['key'] }}" data-camp-ready="{{ $row['matches']->count()===1 ? '1' : '0' }}" @disabled(!$row['player'] || $row['matches']->isEmpty() || $row['decision'] || !str_starts_with($candidate['key'],'player:'))> <strong>{{ $candidate['name'] }}</strong></label>
@if($row['decision'])<small class="d-block">Already {{ $row['decision'] }} — review in nominations below.</small>@endif
<small class="d-block">{{ $row['player']?->dateOfBirth ? \Carbon\Carbon::parse($row['player']->dateOfBirth)->format('j M Y') : 'Identity needs individual review' }}</small></div>
<div class="col-12 col-md-5">@if($row['player'] && $row['matches']->isNotEmpty())<label class="visually-hidden" for="roster-division-{{ $row['player']->id }}">Camp division for {{ $candidate['name'] }}</label><select class="form-select" id="roster-division-{{ $row['player']->id }}" name="division_ids[{{ $row['player']->id }}]"><option value="">Choose division</option>@foreach($divisions->whereIn('id',$row['matches']->keys()) as $division)<option value="{{ $division->id }}" @selected($row['matches']->count()===1)>{{ $division->category?->name }}</option>@endforeach</select>@else<small>Resolve this player individually.</small>@endif</div></div>
@endforeach
</div>
<label class="form-label" for="roster-reason">Reason for this selection</label><input class="form-control mb-3" id="roster-reason" name="reason" maxlength="2000" required value="{{ old('reason','Reviewed regional team roster candidates for this camp.') }}">
<label class="d-block mb-3"><input type="checkbox" name="identity_confirmed" value="1" required> I reviewed the selected players and their camp divisions.</label>
<button class="btn btn-primary">Approve selected roster players</button>
<p class="small mt-2" data-camp-selection-count aria-live="polite">Choose players to approve (up to 200 at once).</p>
</form>
@endif
