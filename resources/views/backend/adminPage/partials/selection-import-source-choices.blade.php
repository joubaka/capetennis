@if($sourceEvent)
@if(!$finalization)<p class="alert alert-info" role="status">No teams are approved yet.</p>@endif
@if($previewErrors)<div class="alert alert-danger" role="alert">@foreach($previewErrors as $message)<p class="mb-0">{{ $message }}</p>@endforeach</div>@endif
<fieldset class="card mb-3"><div class="card-body"><legend class="h5">What do you want to import?</legend><label class="d-block mb-3"><input type="radio" name="source-choice-mode" value="approved_team" @checked($importMode === 'approved_team') @disabled(!$finalization)> Approved selected team · {{ $sourceEvent->name }}</label><label class="d-block"><input type="radio" name="source-choice-mode" value="regions" @checked($importMode !== 'approved_team' || !$finalization)> Source regions</label></div></fieldset>
<section class="card mb-3" data-source-mode="approved_team"><div class="card-body">
  <h4>Approved selected team · {{ $sourceEvent->name }}</h4>
  @if($finalization)
    <p>Approved {{ $finalization->finalized_at->format('d M Y H:i') }} · {{ count($finalization->groups) }} age/gender {{ count($finalization->groups) === 1 ? 'group' : 'groups' }}. Includes the complete saved selection and its reserves.</p>
    <form method="GET" action="{{ route('backend.selection-import.index', $event) }}">
      <input type="hidden" name="source_event_id" value="{{ $sourceEvent->id }}">
      <input type="hidden" name="import_mode" value="approved_team">
      <button class="btn btn-primary" style="min-height:44px">Review approved selected team</button>
    </form>
  @else
    <p class="mb-0">No teams are approved yet.</p>
  @endif
</div></section>
<form method="GET" action="{{ route('backend.selection-import.index', $event) }}" class="card mb-3" data-source-mode="regions"><div class="card-body">
  <input type="hidden" name="source_event_id" value="{{ $sourceEvent->id }}">
  <input type="hidden" name="choose_regions" value="1">
  <input type="hidden" name="review_regions" value="1">
  <input type="hidden" name="import_mode" value="regions">
  <h4>Regions in {{ $sourceEvent->name }}</h4>
  <p>Check the regions to import. A finalized age/gender selection can combine several regions; select those regions together to keep its team and reserves intact.</p>
  @forelse($sourceRegions as $sourceRegion)
  @php($available = isset($regionGroups[$sourceRegion->id]))
  <label class="d-flex align-items-start gap-3 border rounded p-3 mb-2" for="source-region-{{ $sourceRegion->id }}">
    <input type="checkbox" class="form-check-input mt-1" id="source-region-{{ $sourceRegion->id }}" name="source_region_ids[]" value="{{ $sourceRegion->id }}" @disabled(!$available) @checked($available && in_array((int)$sourceRegion->id, array_map('intval', (array)old('source_region_ids', $selectedRegionIds)), true))>
    <span><strong>{{ $sourceRegion->region_name }}</strong><br>
      @if($available)<span class="badge bg-label-success">Included in finalized selection</span> <span class="small">{{ count($regionGroups[$sourceRegion->id]) }} age/gender {{ count($regionGroups[$sourceRegion->id]) === 1 ? 'group' : 'groups' }}</span>
      @else<span class="badge bg-label-secondary">No finalized selection</span>@endif
    </span>
  </label>
  @empty<p>No regions are linked to this source event.</p>@endforelse
  @if(!$finalization)<p class="mt-3">Finalize the saved teams in the source event to make its selections available here.</p>@endif
  <button class="btn btn-primary mt-3" style="min-height:44px" @disabled($regionGroups === [])>Review selected regions</button>
</div></form>
@endif
