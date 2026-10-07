@can('event.manage', $event)
@php $resultSetup = app(\App\Services\TeamResultRankingService::class)->setup($event); @endphp
<div class="tab-pane fade" id="tab-result-rank" role="tabpanel" aria-labelledby="result-rank-button" data-selection-url="{{ route('backend.team-result-selection.show', $event) }}">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h5 class="mb-0">Suggested top 10 by age group and gender</h5>
    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#result-ranks-setup" aria-expanded="false" aria-controls="result-ranks-setup" style="min-height:44px">Setup</button>
  </div>
  <div class="collapse mb-3" id="result-ranks-setup">
    <div class="card card-body">
      <fieldset class="mb-3"><legend class="h6">Include candidates from regions</legend>
        @forelse($resultSetup['regions'] as $region)
          <label class="form-check form-check-inline py-2"><input class="form-check-input" type="checkbox" data-result-region value="{{ $region->id }}" checked><span class="form-check-label">{{ $region->region_name }}</span></label>
        @empty<p class="text-muted">No event regions configured.</p>@endforelse
      </fieldset>
      <fieldset><legend class="h6">Include match formats</legend>
        @foreach($resultSetup['formats'] as $format)
          <label class="form-check form-check-inline py-2"><input class="form-check-input" type="checkbox" data-result-format value="{{ $format }}" checked><span class="form-check-label">{{ $format === 'reverse_singles' ? 'Reverse Singles' : 'Singles' }}</span></label>
        @endforeach
      </fieldset>
      <p class="small text-muted mb-0">Setup is retained in this page URL. Save a draft to retain your team selection and reasons in Cape Tennis.</p>
    </div>
  </div>
  <div class="row g-3">
    <div class="col-md-3"><div class="text-muted small mb-2">Fixture age groups and gender</div>
      @forelse($resultSetup['groups'] as $group)
        <label class="form-check py-2"><input class="form-check-input category-radio" type="radio" name="category-radio" value="{{ $group['key'] }}" data-name="{{ $group['name'] }}" data-event_id="{{ $event->id }}" {{ $loop->first ? 'checked' : '' }}><span class="form-check-label">{{ $group['name'] }}</span></label>
      @empty<p>No singles fixtures with an identifiable age group and gender.</p>@endforelse
    </div>
    <div class="col-md-9"><div class="card"><div class="card-header"><h5 id="category-name" class="m-0"></h5></div><div class="card-body">
      <div class="d-flex flex-wrap gap-2 mb-3"><button type="button" class="btn btn-outline-primary" data-selection-load>Load saved draft</button><button type="button" class="btn btn-primary" data-selection-save>Save draft selection</button></div>
      <div data-selection-status role="status" class="small mb-3"></div>
      <div id="category-table"></div>
    </div></div></div>
  </div>
</div>
@endcan
