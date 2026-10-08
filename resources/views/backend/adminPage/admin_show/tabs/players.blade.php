@if((int) $event->eventType !== 3)
  @include('backend.adminPage.admin_show.tabs.players-legacy')
@else
@php($regionsInEvent = $event->regions ?? collect())
<div class="tab-pane fade show active" id="tab-players" role="tabpanel" aria-labelledby="players-tab">
  <div class="roster-toolbar">
    <div class="roster-filters">
      <div><label class="form-label" for="roster-region">Region</label><select id="roster-region" class="form-select" data-roster-region>
        @foreach($regionsInEvent as $region)<option value="{{ $region->id }}">{{ $region->region_name }}</option>@endforeach
      </select></div>
      <div><label class="form-label" for="roster-search">Find a player or team</label><input id="roster-search" class="form-control" type="search" placeholder="Name, team, email or cell" data-roster-search></div>
      <div><label class="form-label" for="roster-payment">Payment</label><select id="roster-payment" class="form-select" data-roster-filter="payment"><option value="">All payments</option><option value="paid">Paid</option><option value="unpaid">Unpaid</option></select></div>
    </div>
    <details class="mt-3" data-roster-more-filters><summary>More filters <span data-roster-advanced-count></span></summary><div class="roster-filters roster-advanced-filters mt-2">
      <div><label class="form-label" for="roster-category">Category</label><select id="roster-category" class="form-select" data-roster-filter="category"><option value="">All categories</option>
        @foreach($event->eventCategories as $category)<option value="{{ $category->id }}">{{ $category->category?->name }}</option>@endforeach
      </select></div>
      <div><label class="form-label" for="roster-profile">Profile</label><select id="roster-profile" class="form-select" data-roster-filter="profile"><option value="">All players</option><option value="linked">Linked profile</option><option value="imported">Imported / unlinked</option></select></div>
      <div><label class="form-label" for="roster-publication">Visibility</label><select id="roster-publication" class="form-select" data-roster-filter="publication"><option value="">All teams</option><option value="published">Published</option><option value="draft">Unpublished</option></select></div>
    </div></details>
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3">
      <div class="d-flex flex-wrap gap-2"><div class="dropdown"><button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Tools</button><div class="dropdown-menu"><button type="button" class="dropdown-item" data-roster-expand="true">Expand all</button><button type="button" class="dropdown-item" data-roster-expand="false">Collapse all</button><button type="button" class="dropdown-item" data-workspace-refresh>Refresh roster</button>
<span class="dropdown-item-text small text-muted">Export event roster - all regions - occupied places only</span><a class="dropdown-item" href="{{ route('event.players.exportPdf', $event->id) }}" target="_blank" rel="noopener">Download PDF</a><a class="dropdown-item" href="{{ route('event.players.exportExcel', $event->id) }}">Download Excel</a></div></div><button type="button" class="btn btn-outline-secondary" data-roster-clear>Clear filters</button></div>
      <div class="d-flex flex-wrap gap-2">
        <a class="btn btn-outline-secondary" href="{{ route('backend.event-mail-log.index', $event) }}">Email history</a>
      </div>
    </div>
    <p class="small text-muted mt-2 mb-0">Filters apply to the selected region. Exports cover the whole event; reserves stay in the selection queue.</p>
  </div>
  @forelse($regionsInEvent as $k => $region)
    <section id="players-region-{{ $region->id }}" data-roster-panel="players" data-region-id="{{ $region->id }}" @if($k !== 0) hidden @endif>
      @if((int) $event->eventType === 3)<div class="roster-panel-placeholder p-4" role="status">Select a region to load its roster.</div>
      @else @include('backend.adminPage.admin_show.tabs.players-region') @endif
    </section>
  @empty <div class="alert alert-light border mt-3">No regions have been added to this event.</div> @endforelse
</div>
@endif
