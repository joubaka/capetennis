@php
  $pointSharing = $event->team_result_settings['point_sharing'] ?? [];
  $pointSharingRules = collect($pointSharing['rules'] ?? [])->keyBy('team_id');
  $pointSharingRegions = $event->regions()->get();
  $configured = fn ($rule) => ($rule['enabled'] ?? false) || !empty($rule['region_ids']) || ($rule['mode'] ?? 'full') === 'split';
  $sharingTeams = $pointSharingTeams->sortByDesc(fn ($team) => $configured($pointSharingRules->get($team->id, [])));
  $hasConfiguredTeams = $sharingTeams->contains(fn ($team) => $configured($pointSharingRules->get($team->id, [])));
@endphp
<div class="col-12"><section class="card settings-card" id="settings-point-sharing"><div class="card-body">
  <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-2">
    <h5 class="mb-0">Invitational point sharing</h5>
    <span class="badge bg-label-secondary" data-sharing-state>{{ ($pointSharing['enabled'] ?? false) ? 'On' : 'Off' }}</span>
  </div>
  <p class="mb-2">Use this when a combined team represents more than one region. You can give those regions credit for the team’s points in the <strong>overall regional standings</strong>.</p>
  <p class="small text-muted mb-2">If the team earns 12 points, its own region keeps all 12. With two recipient regions, you can give them 12 points each or split the credit so they get 6 each. Age-group standings and match results stay the same.</p>
  <p class="small text-muted mb-3">Saving can update standings that are already published. To remove the extra points, turn sharing off and save. Your team rules are saved for later.</p>
  <label class="form-check mb-3"><input type="checkbox" class="form-check-input" id="point-sharing-enabled" @checked($pointSharing['enabled'] ?? false)><span class="form-check-label">Enable point sharing for this event</span></label>
  @if($sharingTeams->isNotEmpty())
    <details class="mb-3" data-sharing-setup @if($hasConfiguredTeams) open @endif>
    <summary class="fw-semibold mb-2">Configure teams <span class="badge bg-label-secondary ms-1" data-sharing-configured></span></summary>
    <p class="small text-muted mb-2">Find the team that earned the points, then select Edit to choose which regions receive credit. Set up boys’ and girls’ teams separately.</p>
    <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
      <label class="visually-hidden" for="sharing-team-search">Find a team</label>
      <input class="form-control form-control-sm" type="search" id="sharing-team-search" placeholder="Find a team or region" style="max-width:19rem">
      <label class="visually-hidden" for="sharing-team-scope">Show teams</label>
      <select class="form-select form-select-sm w-auto" id="sharing-team-scope">
        <option value="all">All teams</option>
        <option value="configured" @selected($hasConfiguredTeams)>Configured teams</option>
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
    </details>
  @else
    <p>Create the invitational teams and attach their region to this event before configuring sharing.</p>
  @endif
  <button type="button" class="btn btn-primary" id="save-point-sharing">Save point sharing</button>
  <span id="point-sharing-status" class="d-block mt-2" role="status" aria-live="polite"></span>
</div></section></div>
<style>
  #settings-point-sharing [hidden] { display: none !important; }
  #settings-point-sharing [data-sharing-setup] > summary { cursor: pointer; padding-block: .65rem; }
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
  const setup = section.querySelector('[data-sharing-setup]');
  const configuredBadge = section.querySelector('[data-sharing-configured]');
  const state = section.querySelector('[data-sharing-state]');
  let dirty = false;
  function render() {
    const query = (search?.value || '').trim().toLowerCase();
    let visible = 0;
    let configuredCount = 0;
    for (const row of rows) {
      const active = row.querySelector('[data-sharing-active]').checked;
      const split = row.querySelector('[data-sharing-mode]').value === 'split';
      const regions = [...row.querySelectorAll('[data-sharing-region]:checked')];
      const configured = active || split || regions.length > 0;
      if (configured) configuredCount++;
      const editing = !row.querySelector('[data-sharing-editor]').hidden;
      row.hidden = (scope.value === 'configured' && !configured && !editing) || !row.querySelector('[data-sharing-source]').textContent.toLowerCase().includes(query);
      if (!row.hidden) visible++;
      row.querySelector('[data-sharing-summary-regions]').textContent = regions.map(region => region.closest('label').textContent.trim()).join(', ') || 'No recipients';
      row.querySelector('[data-sharing-summary-mode]').textContent = configured ? (split ? 'Split equally' : 'Full to each') : '—';
      const status = row.querySelector('[data-sharing-summary-status]');
      const sharing = active && regions.length > 0 && enabled.checked;
      status.textContent = active ? (!regions.length ? 'Choose regions' : (enabled.checked ? 'Sharing' : 'Paused')) : (configured ? 'Off' : 'No rule');
      status.classList.toggle('bg-label-success', sharing);
      status.classList.toggle('bg-label-secondary', !sharing);
      row.querySelector('[data-sharing-example]').textContent = regions.length
        ? 'Example: if this team earns 12 points, its region keeps 12 and each recipient gets ' + (split && regions.length > 1 ? '12 ÷ ' + regions.length : '12') + ' points when sharing is active.'
        : 'Select recipients to see an example.';
    }
    state.textContent = dirty ? 'Unsaved changes' : (enabled.checked ? 'On' : 'Off');
    state.classList.toggle('bg-label-warning', dirty);
    state.classList.toggle('bg-label-secondary', !dirty);
    const empty = section.querySelector('[data-sharing-empty]');
    if (empty) empty.hidden = visible > 0;
    if (configuredBadge) configuredBadge.textContent = configuredCount + ' configured';
  }
  search?.addEventListener('input', render);
  section.addEventListener('change', event => {
    if (event.target === enabled && enabled.checked && setup) setup.open = true;
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
