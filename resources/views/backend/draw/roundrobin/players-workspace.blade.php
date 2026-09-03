<div class="tab-pane fade" id="groups-pane" role="tabpanel" aria-labelledby="groups-tab">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <span class="small text-muted">Step 2 · Round robin → playoffs</span>
    <a href="{{ route('draw.setup.show', $draw) }}" class="btn btn-sm btn-outline-secondary">Draw format</a>
  </div>
  <div class="rr-assignment-heading">
    <div><h5 class="mb-1">Build your groups</h5><p class="text-muted mb-0">Choose players, arrange their seed order, then preview your fixtures.</p></div>
    <div class="d-flex align-items-center gap-2">
      <label for="groups-tab-boxes" class="mb-0">Groups</label>
      <select id="groups-tab-boxes" class="form-select form-select-sm" style="width:90px" aria-label="Number of groups">
        @foreach(range(1, max(8, $groups->count())) as $n)
          <option value="{{ $n }}" @selected($n === $currentBoxes)>{{ $n }}</option>
        @endforeach
      </select>
      <button type="button" id="rr-apply-group-count" class="btn btn-sm btn-outline-primary">Apply</button>
    </div>
  </div>
  <div class="alert alert-warning rr-locked-overlay {{ ($draw->locked || $draw->published) ? '' : 'd-none' }}">Assignments are read-only while the draw is published or locked.</div>
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
          <a class="small" href="{{ route('headOffice.show', $draw->event_id) }}">Manage event entries</a>
          @if(optional($draw->event)->isTeam())
            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-import-teams">Import from Teams</button>
          @endif
        </div>
      </div>
    </aside>
    <div><div class="rr-group-summary"><span id="rr-assigned-count">0 assigned</span><span class="text-muted">Order within each group sets the seeds</span></div><div id="rr-groups-row" class="rr-group-grid"></div></div>
  </div>
  <div class="rr-assignment-savebar">
    <div><strong id="rr-save-status" role="status" aria-live="polite">Saved</strong><div id="rr-assignment-message" class="small text-muted">Your event registrations are kept when players leave a group.</div></div>
    <div class="d-flex flex-wrap gap-2">
      <button type="button" id="rr-discard-groups" class="btn btn-sm btn-outline-secondary">Discard changes</button>
      <button type="button" id="btn-save-groups" class="btn btn-sm btn-outline-primary">Save assignments</button>
      <button type="button" id="btn-regenerate-fixtures" class="btn btn-sm btn-primary" data-rr-destructive>Save &amp; preview fixtures</button>
    </div>
  </div>
  <noscript><p class="alert alert-warning">Enable JavaScript to assign players and manage this draw.</p></noscript>
</div>
