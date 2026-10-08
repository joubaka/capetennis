@can('event.manage', $event)
@php $resultSetup = app(\App\Services\TeamResultRankingService::class)->setup($event); @endphp
<div class="tab-pane fade result-workspace" id="tab-result-rank" role="tabpanel" aria-labelledby="result-rank-button" data-selection-url="{{ route('backend.team-result-selection.show', $event) }}">
  <div class="result-page-heading">
    <div><h5>Team selection results</h5><p>Compare singles results and build a draft team of up to 10 players.</p></div>
    <button class="btn btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#result-ranks-setup" aria-expanded="false" aria-controls="result-ranks-setup"><i class="ti ti-adjustments-horizontal" aria-hidden="true"></i> Setup</button>
  </div>
  <div class="collapse" id="result-ranks-setup">
    <div class="result-setup">
      <fieldset><legend>Candidate regions</legend><div class="result-option-grid">
        @forelse($resultSetup['regions'] as $region)
          <label class="result-choice"><input class="form-check-input" type="checkbox" data-result-region value="{{ $region->id }}" checked><span>{{ $region->region_name }}</span></label>
        @empty<p class="text-muted">No event regions configured.</p>@endforelse
      </div></fieldset>
      <fieldset><legend>Match formats</legend><div class="result-format-grid">
        @foreach($resultSetup['formats'] as $format)
          <label class="result-choice"><input class="form-check-input" type="checkbox" data-result-format value="{{ $format }}" checked><span>{{ $format === 'reverse_singles' ? 'Reverse Singles' : 'Singles' }}</span></label>
        @endforeach
      </div></fieldset>
      <p class="result-setup-note">Regions choose candidates; matches against all event opponents count. Save a draft to retain your choices.</p>
    </div>
  </div>
  <div class="result-layout">
    <aside class="result-group-rail" aria-label="Age group and gender">
      <h6>Age group &amp; gender</h6>
      <div class="result-group-grid">
        @forelse($resultSetup['groups'] as $group)
          <label class="result-choice result-group-choice"><input class="form-check-input category-radio" type="radio" name="category-radio" value="{{ $group['key'] }}" data-name="{{ $group['name'] }}" data-event_id="{{ $event->id }}" {{ $loop->first ? 'checked' : '' }}><span>{{ $group['name'] }}</span></label>
        @empty<p class="result-rail-empty">Age groups appear here when singles fixtures are available.</p>@endforelse
      </div>
    </aside>
    <section class="result-ranking-panel" aria-label="Player ranking and draft selection">
      <div class="result-ranking-header">
        <div><h5 id="category-name"></h5><span class="result-heading-hint">Band-weighted match wins · Select up to 10</span></div>
        <div class="result-draft-actions"><button type="button" class="btn btn-outline-primary" data-selection-load>Load draft</button><button type="button" class="btn btn-primary" data-selection-save>Save draft</button></div>
      </div>
      <div data-selection-status role="status" class="result-draft-status"></div>
      <div id="category-table" class="result-ranking-body"></div>
    </section>
  </div>
</div>
@endcan
