<div class="tab-pane fade" id="groups-pane" role="tabpanel" aria-labelledby="groups-tab">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <span class="small text-muted">Step 2 of 5 · Players &amp; Positions · {{ $draw->roundRobinLabel() }}</span>
    <a href="{{ route('draw.setup.show', $draw) }}" class="btn btn-sm btn-outline-secondary">Draw format</a>
  </div>
  <p class="mb-3" id="rr-category-scope"><strong>Player category:</strong> <span id="rr-category-name">{{ $draw->categoryEvent?->category?->name ?? 'All event categories' }}</span></p>
  <form method="POST" action="{{ route('draw.setup.store', $draw) }}" class="card card-body mb-3">
    @csrf
    <fieldset @disabled($draw->locked || $draw->published || $draw->oop_published || ($draw->isTwoStageRoundRobin() && $draw->groups->contains('round_robin_phase', 2)) || $draw->drawFixtures->contains(fn ($fixture) => $fixture->stage !== 'RR'))>
      <legend class="h6">How should these groups compete?</legend>
      <label class="form-check mb-2"><input class="form-check-input" type="radio" name="workflow" value="round_robin_playoffs" @checked(!$draw->isRoundRobinOnly())><span class="form-check-label">One category competition — groups feed shared playoffs for one overall winner.</span></label>
      <label class="form-check mb-2"><input class="form-check-input" type="radio" name="workflow" value="round_robin" @checked($draw->settings?->workflow === 'round_robin')><span class="form-check-label">Separate group competitions — each group has its own winner in this category, with no cross-group playoffs.</span></label>
      <label class="form-check mb-2"><input class="form-check-input" type="radio" name="workflow" value="round_robin_round_robin" @checked($draw->isTwoStageRoundRobin())><span class="form-check-label">Round robin → round robin — equal finishing positions enter new groups for final positions.</span></label>
      <p class="small text-muted">These options keep the same event, category and entries. Complete the group matches and resolve tied qualifying positions before progressing.</p>
      <button class="btn btn-sm btn-outline-primary" type="submit">Save competition option</button>
    </fieldset>
    @if($draw->drawFixtures->contains(fn ($fixture) => $fixture->stage !== 'RR'))
      <p class="small text-warning mt-2 mb-0">Playoff matches already exist. Use Draw format to review a reset before changing this option.</p>
    @endif
  </form>
  <section class="card card-body mb-3 rr-group-setup" aria-labelledby="rr-group-setup-title">
    <div><h5 class="mb-1" id="rr-group-setup-title">{{ $groups->isEmpty() ? '1. Create groups' : '1. Groups' }}</h5><p class="text-muted mb-0">Choose how many groups you need. Each player plays everyone in their group.</p></div>
    <div class="rr-group-count-controls">
      <label for="groups-tab-boxes" class="mb-0">Number of groups</label>
      <div class="d-flex gap-2">
      <select id="groups-tab-boxes" class="form-select" aria-label="Number of groups">
        @foreach(range(1, max(8, $groups->count())) as $n)
          <option value="{{ $n }}" @selected($n === $currentBoxes)>{{ $n }}</option>
        @endforeach
      </select>
      <button type="button" id="rr-apply-group-count" class="btn {{ $groups->isEmpty() ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $groups->isEmpty() ? 'Create groups' : 'Update groups' }}</button>
      </div>
    </div>
  </section>
  <details class="alert alert-info py-2 mb-3">
    <summary class="small fw-semibold" style="cursor:pointer">How do groups and seeds work?</summary>
    <p class="small mb-1 mt-2">Each player meets everyone in their group. More players in one group create more matches.</p>
    <p class="small mb-0">The order inside a group is the seed order. Save assignments, then use <strong>Save &amp; preview fixtures</strong> to check the matches before generating them.</p>
  </details>
  <div class="alert alert-warning rr-locked-overlay {{ ($draw->locked || $draw->published) ? '' : 'd-none' }}">Assignments are read-only while the draw is published or locked.</div>
  <h5 class="mt-4 mb-1">2. Add players</h5>
  <p class="text-muted mb-3" id="rr-placement-next-step">{{ $groups->isEmpty() ? 'Create your groups first. Then choose players and add them to a group.' : 'Choose players, add them to a group, then save assignments.' }}</p>
  @if(\App\Services\Performance\PlayerRatingBadgeService::visible())
    <p class="small text-muted mb-3">Available ratings appear beside player names to help with seeding.</p>
  @endif
  <div class="rr-player-layout">
    <aside class="rr-player-picker">
      <div class="rr-picker-heading"><h6 class="mb-1">Available players <span id="rr-available-count" class="badge bg-label-primary">0</span></h6><small class="text-muted">Paid, active entries for this draw</small></div>
      <div class="p-3 border-bottom">
        <label for="rr-player-search" class="visually-hidden">Search players</label>
        <input id="rr-player-search" class="form-control mb-2" type="search" placeholder="Search players…">
        <label for="rr-player-category" class="visually-hidden">Player category</label>
        <select id="rr-player-category" class="form-select form-select-sm"><option value="">All categories</option></select>
        <label class="form-check mt-3 mb-0"><input type="checkbox" id="rr-select-visible" class="form-check-input"><span class="form-check-label">Select visible players</span></label>
      </div>
      <div id="available-players-list" aria-label="Available players"><p class="p-3 text-muted">Loading players…</p></div>
      <div class="rr-picker-footer">
        <label for="rr-assign-target" class="form-label small">Assign selected players to</label>
        <div class="d-flex gap-2"><select id="rr-assign-target" class="form-select form-select-sm" aria-label="Destination group"></select><button type="button" id="rr-assign-selected" class="btn btn-sm btn-primary">Assign</button></div>
        <div class="mt-3 d-flex flex-wrap gap-2">
          <button type="button" id="rr-refresh-players" class="btn btn-sm btn-link p-0">Refresh players</button>
          @can('event-draw.view', $draw->event)
            <a class="small" href="{{ route($draw->event->isTeam() ? 'admin.events.teams' : 'admin.events.entries.new', $draw->event_id) }}">Manage event entries</a>
          @endcan
          @if(optional($draw->event)->isTeam())
            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-import-teams">Import from Teams</button>
          @endif
        </div>
      </div>
    </aside>
    <div><div class="rr-group-summary"><span id="rr-assigned-count">0 assigned</span><span class="text-muted">Order within each group sets the seeds</span></div><div id="rr-groups-row" class="rr-group-grid"></div></div>
  </div>
  <div class="rr-assignment-savebar">
    <div><strong class="d-block">3. Save and preview</strong><span id="rr-save-status" role="status" aria-live="polite">Saved</span><div id="rr-assignment-message" class="small text-muted">Your event registrations are kept when players leave a group.</div></div>
    <div class="d-flex flex-wrap gap-2">
      <button type="button" id="rr-discard-groups" class="btn btn-sm btn-outline-secondary">Discard changes</button>
      <button type="button" id="btn-save-groups" class="btn btn-sm btn-outline-primary">Save assignments</button>
      <button type="button" id="btn-regenerate-fixtures" class="btn btn-sm btn-primary" data-rr-destructive>Save &amp; preview fixtures</button>
    </div>
  </div>
  <noscript><p class="alert alert-warning">Enable JavaScript to assign players and manage this draw.</p></noscript>
</div>
