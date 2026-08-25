<div class="card">
  <div class="card-header d-flex justify-content-between align-items-start"><div><h5 class="mb-1">Masters setup</h5><p class="text-muted small mb-0">Choose the Series rankings to use. Changes save immediately.</p></div><span class="badge bg-label-primary">{{ $rankingCategoryLinks->where('enabled', true)->count() }} selected</span></div>
  <div class="card-body">
    <div class="row g-3 mb-3"><div class="col-md-6"><span class="text-muted small d-block">Parent series</span><strong>{{ $event->series?->name ?? 'Not linked' }}</strong></div><div class="col-md-3"><span class="text-muted small d-block">Ranking lists</span><strong>{{ $rankingLists->count() }}</strong></div><div class="col-md-3"><span class="text-muted small d-block">Published runs</span><strong>{{ $publishedRuns->count() }}</strong></div></div>
    <div class="d-flex justify-content-end mb-3"><form method="POST" action="{{ route('backend.masters.sync-categories', $event) }}">@csrf<button class="btn btn-outline-primary btn-sm">Sync new Series rankings</button></form></div>
    @if(!$event->series)<div class="alert alert-danger">Blocked: link this event to a series.</div>
    @elseif($rankingCategoryLinks->isEmpty())<div class="alert alert-warning">Sync the Series ranking lists to create the available Masters categories.</div><form method="POST" action="{{ route('backend.masters.sync-categories', $event) }}" class="mt-3">@csrf<button class="btn btn-primary">Sync categories from Series rankings</button></form>
    @elseif($rankingLists->isEmpty() || $publishedRuns->isEmpty())<div class="alert alert-warning">A published ranking source is required before invitations can be generated.</div>
    @else
      <div class="mb-4" id="masters-category-manager" data-update-url="{{ route('backend.masters.category.update', [$event, '__LINK__']) }}">
        <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">Series ranking categories</h6><small class="text-muted">Enable or disable · adjust Top X</small></div>
        @foreach($rankingCategoryLinks as $link)
          <div class="category-row row g-2 align-items-center" data-link-id="{{ $link->id }}"><div class="col-7 col-md-8"><strong>{{ $link->rankingList?->category?->name ?? 'Ranking list #'.$link->ranking_list_id }}</strong><div class="small text-muted">{{ $link->categoryEvent?->category?->name ?? $link->category_name }}</div></div><div class="col-5 col-md-4 d-flex justify-content-end align-items-center gap-2"><div class="stepper" aria-label="Top X"><button type="button" data-step="-1" aria-label="Decrease Top X">−</button><output data-top-x>{{ $link->top_x }}</output><button type="button" data-step="1" aria-label="Increase Top X">+</button></div><button type="button" class="btn btn-sm {{ $link->enabled ? 'btn-success' : 'btn-outline-secondary' }} category-toggle" data-enabled="{{ $link->enabled ? 1 : 0 }}"><span class="category-state">{{ $link->enabled ? 'Enabled' : 'Off' }}</span></button></div></div>
        @endforeach
      </div>
      <form method="POST" action="{{ route('backend.masters.generate', $event) }}">@csrf<input type="hidden" name="ranking_run_id" value="{{ $publishedRuns->last() }}"><input type="hidden" name="top_x" value="8"><input type="hidden" name="series_id" value="{{ $event->series_id }}"><h6 class="mt-4">Ranking-to-category mapping</h6>
        @foreach($rankingCategoryLinks->where('enabled', true) as $index => $link)<div class="row g-2 align-items-end mb-2"><div class="col-md-5"><label class="form-label">Ranking list</label><input class="form-control" value="{{ $link->rankingList?->category?->name ?? 'Ranking list #'.$link->ranking_list_id }}" readonly><input type="hidden" name="mappings[{{ $index }}][ranking_list_id]" value="{{ $link->ranking_list_id }}"></div><div class="col-md-7"><label class="form-label">Masters category</label><input class="form-control" value="{{ $link->categoryEvent?->category?->name ?? $link->category_name }}" readonly><input type="hidden" name="mappings[{{ $index }}][category_event_id]" value="{{ $link->category_event_id }}"></div></div>@endforeach
        <button class="btn btn-primary mt-3">Generate invitation batch</button>
      </form>
    @endif
  </div>
</div>
