@php
  $pointSharing = $event->team_result_settings['point_sharing'] ?? [];
  $pointSharingRules = collect($pointSharing['rules'] ?? [])->keyBy('team_id');
  $pointSharingRegions = $event->regions()->get();
@endphp
<div class="col-12"><section class="card settings-card" id="settings-point-sharing"><div class="card-body">
  <h5>Invitational point sharing</h5>
  <p>Share selected teams’ earned points with other regions in this event’s <strong>overall regional standings</strong>. The source keeps its own points. Age-group standings, draw positions and match records stay unchanged.</p>
  <label class="form-check mb-3"><input type="checkbox" class="form-check-input" id="point-sharing-enabled" @checked($pointSharing['enabled'] ?? false)><span class="form-check-label">Enable point sharing for this event</span></label>
  <p class="field-help">For a combined U13 side, enable its boys’ team and girls’ team separately, select both contributing regions for each, and choose Full points to each. Switching event sharing off preserves these choices.</p>
  @forelse($pointSharingTeams as $sharingTeam)
    @php($sharingRule = $pointSharingRules->get($sharingTeam->id, []))
    <fieldset class="border rounded p-3 mb-3" data-point-sharing-team="{{ $sharingTeam->id }}">
      <legend class="float-none w-auto fs-6 px-1">{{ $sharingTeam->name }} — {{ $sharingTeam->regions->region_name }} · {{ $sharingTeam->category->category?->name }}</legend>
      <label class="form-check mb-3"><input type="checkbox" class="form-check-input" data-sharing-active @checked($sharingRule['enabled'] ?? false)><span class="form-check-label">Share this team’s points</span></label>
      <div class="row g-3">
        <div class="col-md-5"><label class="form-label" for="sharing-mode-{{ $sharingTeam->id }}">Allocation</label><select class="form-select" id="sharing-mode-{{ $sharingTeam->id }}" data-sharing-mode style="min-height:44px"><option value="full" @selected(($sharingRule['mode'] ?? 'full') === 'full')>Full points to each region</option><option value="split" @selected(($sharingRule['mode'] ?? '') === 'split')>Split equally between selected regions</option></select></div>
        <div class="col-md-7"><p class="form-label mb-2">Recipient regions</p>
          @foreach($pointSharingRegions->where('id', '!=', $sharingTeam->region_id) as $sharingRegion)
            <label class="d-block mb-2"><input type="checkbox" data-sharing-region value="{{ $sharingRegion->id }}" @checked(in_array((int) $sharingRegion->id, $sharingRule['region_ids'] ?? [], true))> {{ $sharingRegion->region_name }}</label>
          @endforeach
        </div>
      </div>
    </fieldset>
  @empty<p>Create the invitational teams and attach their region to this event before configuring sharing.</p>@endforelse
  <p class="field-help">Full duplicates the source’s points for every selected region. Split divides them equally without rounding the calculated total. Sharing never passes through a recipient to another region. Changes immediately affect any published overall standings; they do not publish private results.</p>
  <button type="button" class="btn btn-primary" id="save-point-sharing" style="min-height:44px">Save point sharing</button>
  <span id="point-sharing-status" class="d-block mt-2" role="status" aria-live="polite"></span>
</div></section></div>
