@php
  $pointSharing = $event->team_result_settings['point_sharing'] ?? [];
  $pointSharingRules = collect($pointSharing['rules'] ?? [])->keyBy('team_id');
  $pointSharingRegions = $event->regions()->get();
  $configured = fn ($rule) => ($rule['enabled'] ?? false) || !empty($rule['region_ids']) || ($rule['mode'] ?? 'full') === 'split';
  $sharingTeams = $pointSharingTeams->sortByDesc(fn ($team) => $configured($pointSharingRules->get($team->id, [])));
@endphp
<div class="col-12"><section class="card settings-card" id="settings-point-sharing"><div class="card-body">
  <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
    <h5 class="mb-0">Invitational point sharing</h5>
    <span class="badge bg-label-secondary" data-sharing-state>{{ ($pointSharing['enabled'] ?? false) ? 'On' : 'Off' }}</span>
  </div>
  <p class="text-muted small mb-2">Share points in the overall regional standings. The source keeps its points. Saving applies to standings that are already published.</p>
  <label class="form-check mb-3"><input type="checkbox" class="form-check-input" id="point-sharing-enabled" @checked($pointSharing['enabled'] ?? false)><span class="form-check-label">Enable point sharing for this event</span></label>
  @if($sharingTeams->isNotEmpty())
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
      <label class="visually-hidden" for="sharing-team-search">Find a team</label>
      <input class="form-control form-control-sm" type="search" id="sharing-team-search" placeholder="Find a team or region" style="max-width:19rem">
      <label class="visually-hidden" for="sharing-team-scope">Show teams</label>
      <select class="form-select form-select-sm w-auto" id="sharing-team-scope">
        <option value="all">All teams</option>
        <option value="configured" @selected($pointSharingRules->contains($configured))>Configured teams</option>
      </select>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-1 sharing-table">
        <caption class="visually-hidden">Point sharing by source team</caption>
        <thead><tr><th scope="col">Source team</th><th scope="col">Recipient regions</th><th scope="col">Allocation</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
        @foreach($sharingTeams as $sharingTeam)
          @php($sharingRule = $pointSharingRules->get($sharingTeam->id, []))
          <tbody data-point-sharing-team="{{ $sharingTeam->id }}" data-sharing-saved="{{ $pointSharingRules->has($sharingTeam->id) ? 'true' : 'false' }}">
            <tr>
              <th scope="row" data-sharing-source><strong>{{ $sharingTeam->name }}</strong><small class="d-block text-muted">{{ $sharingTeam->regions->region_name }} · {{ $sharingTeam->category->category?->name }}</small></th>
              <td data-sharing-summary-regions></td>
              <td data-sharing-summary-mode></td>
              <td><span class="badge bg-label-secondary" data-sharing-summary-status></span></td>
              <td><button type="button" class="btn btn-sm btn-outline-secondary" data-sharing-edit aria-expanded="false" aria-controls="sharing-editor-{{ $sharingTeam->id }}" aria-label="Edit point sharing for {{ $sharingTeam->name }}">Edit</button></td>
            </tr>
            <tr id="sharing-editor-{{ $sharingTeam->id }}" data-sharing-editor hidden><td colspan="5">
              <fieldset class="p-2">
                <legend class="fs-6 mb-2">Point sharing for {{ $sharingTeam->name }}</legend>
                <div class="row g-2">
                  <div class="col-md-5">
                    <label class="form-check mb-2"><input type="checkbox" class="form-check-input" data-sharing-active @checked($sharingRule['enabled'] ?? false)><span class="form-check-label">Share this team’s points</span></label>
                    <label class="form-label" for="sharing-mode-{{ $sharingTeam->id }}">Allocation</label>
                    <select class="form-select" id="sharing-mode-{{ $sharingTeam->id }}" data-sharing-mode><option value="full" @selected(($sharingRule['mode'] ?? 'full') === 'full')>Full to each region</option><option value="split" @selected(($sharingRule['mode'] ?? '') === 'split')>Split equally</option></select>
                  </div>
                  <fieldset class="col-md-7">
                    <legend class="fs-6 mb-1">Recipient regions</legend>
                    @foreach($pointSharingRegions->where('id', '!=', $sharingTeam->region_id) as $sharingRegion)
                      <label class="form-check"><input type="checkbox" class="form-check-input" data-sharing-region value="{{ $sharingRegion->id }}" @checked(in_array((int) $sharingRegion->id, $sharingRule['region_ids'] ?? [], true))> <span class="form-check-label">{{ $sharingRegion->region_name }}</span></label>
                    @endforeach
                  </fieldset>
                </div>
                <p class="small text-muted mt-2 mb-0" data-sharing-example aria-live="polite"></p>
              </fieldset>
            </td></tr>
          </tbody>
        @endforeach
      </table>
    </div>
    <p class="text-muted small mt-2" data-sharing-empty hidden>No teams match. Change the search or show all teams.</p>
  @else
    <p>Create the invitational teams and attach their region to this event before configuring sharing.</p>
  @endif
  <details class="small mb-3">
    <summary>How point sharing works</summary>
    <p class="mt-2 mb-0">For a combined U13 side, configure its boys’ and girls’ teams separately, select both contributing regions for each, and choose Full points to each. Switching sharing off keeps your choices. Full gives each recipient all of the source team’s points. Split divides the points equally without rounding the calculated total. Sharing does not pass through recipients or change age-group standings, draw positions, match records or result publication.</p>
  </details>
  <button type="button" class="btn btn-primary" id="save-point-sharing">Save point sharing</button>
  <span id="point-sharing-status" class="d-block mt-2" role="status" aria-live="polite"></span>
</div></section></div>
<style>
  #settings-point-sharing [hidden] { display: none !important; }
  #settings-point-sharing :is(button, select, input[type="search"]) { min-height: 44px; }
  #settings-point-sharing .sharing-table td, #settings-point-sharing .sharing-table th { white-space: normal; padding: .35rem .5rem; }
  #settings-point-sharing [data-sharing-source] { text-transform: none; letter-spacing: normal; }
  #settings-point-sharing .badge.bg-label-secondary { color: var(--bs-body-color) !important; }
  @media (max-width: 575.98px) {
    #settings-point-sharing .sharing-table, #settings-point-sharing .sharing-table tbody { display: block; }
    #settings-point-sharing .sharing-table thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
    #settings-point-sharing .sharing-table tbody > tr:first-child { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; }
    #settings-point-sharing .sharing-table tbody > tr:first-child > :first-child { grid-column: 1 / -1; }
    #settings-point-sharing [data-sharing-summary-regions]::before { content: 'Recipients'; }
    #settings-point-sharing [data-sharing-summary-mode]::before { content: 'Allocation'; }
    #settings-point-sharing :is([data-sharing-summary-regions], [data-sharing-summary-mode])::before { display: block; font-size: .75rem; font-weight: 600; }
    #settings-point-sharing .sharing-table [data-sharing-editor], #settings-point-sharing .sharing-table [data-sharing-editor] td { display: block; }
  }
</style>
<script>
(() => {
  const section = document.getElementById('settings-point-sharing');
  const rows = [...section.querySelectorAll('[data-point-sharing-team]')];
  const search = section.querySelector('#sharing-team-search');
  const scope = section.querySelector('#sharing-team-scope');
  const enabled = section.querySelector('#point-sharing-enabled');
  const state = section.querySelector('[data-sharing-state]');
  let dirty = false;
  function render() {
    const query = (search?.value || '').trim().toLowerCase();
    let visible = 0;
    for (const row of rows) {
      const active = row.querySelector('[data-sharing-active]').checked;
      const split = row.querySelector('[data-sharing-mode]').value === 'split';
      const regions = [...row.querySelectorAll('[data-sharing-region]:checked')];
      const configured = active || split || regions.length > 0;
      const editing = !row.querySelector('[data-sharing-editor]').hidden;
      row.hidden = (scope.value === 'configured' && !configured && !editing) || !row.querySelector('[data-sharing-source]').textContent.toLowerCase().includes(query);
      if (!row.hidden) visible++;
      row.querySelector('[data-sharing-summary-regions]').textContent = regions.map(region => region.closest('label').textContent.trim()).join(', ') || 'No recipients';
      row.querySelector('[data-sharing-summary-mode]').textContent = configured ? (split ? 'Split equally' : 'Full to each') : '—';
      const status = row.querySelector('[data-sharing-summary-status]');
      status.textContent = active ? (enabled.checked ? 'Enabled' : 'Ready') : (configured ? 'Disabled' : 'No rule');
      status.classList.toggle('bg-label-success', active && enabled.checked);
      status.classList.toggle('bg-label-secondary', !active || !enabled.checked);
      row.querySelector('[data-sharing-example]').textContent = regions.length
        ? 'Example: if this team earns 12 points, its region keeps 12 and each recipient gets ' + (split && regions.length > 1 ? '12 ÷ ' + regions.length : '12') + ' points when sharing is active.'
        : 'Select recipients to see an example.';
    }
    state.textContent = dirty ? 'Unsaved changes' : (enabled.checked ? 'On' : 'Off');
    state.classList.toggle('bg-label-warning', dirty);
    state.classList.toggle('bg-label-secondary', !dirty);
    const empty = section.querySelector('[data-sharing-empty]');
    if (empty) empty.hidden = visible > 0;
  }
  search?.addEventListener('input', render);
  section.addEventListener('change', event => {
    if (event.target.matches('#point-sharing-enabled, [data-sharing-active], [data-sharing-mode], [data-sharing-region]')) dirty = true;
    render();
  });
  section.addEventListener('point-sharing:saved', event => {
    dirty = event.detail.dirty;
    render();
  });
  section.addEventListener('click', event => {
    const button = event.target.closest('[data-sharing-edit]');
    if (!button) return;
    const editor = document.getElementById(button.getAttribute('aria-controls'));
    editor.hidden = !editor.hidden;
    button.setAttribute('aria-expanded', String(!editor.hidden));
    button.textContent = editor.hidden ? 'Edit' : 'Close';
    render();
  });
  render();
})();
</script>
