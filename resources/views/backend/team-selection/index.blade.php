@extends('layouts.backend')

@section('title', 'Team Selection & Invitations')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('page-style')
<style>
  .region-workspace-card { border: 0; box-shadow: 0 .35rem 1.25rem rgba(31, 57, 104, .09); overflow: hidden; }
  .region-workspace-card > .card-header { background: linear-gradient(115deg, #173f78, #2563a9); color: #fff; }
  .region-workspace-card > .card-header .text-muted { color: rgba(255,255,255,.76) !important; }
  .regional-summary { display: flex; flex-wrap: wrap; gap: .35rem 1.25rem; padding: .7rem 1rem; border: 1px solid #dbe6f4; border-radius: .65rem; background: #f8fbff; }
  .regional-summary-item { color: #68778c; white-space: nowrap; }
  .regional-summary-item strong { color: #173f78; font-size: 1rem; }
  .regional-team-card { border: 1px solid #dbe6f4; border-top: 4px solid #2374bb; box-shadow: 0 .2rem .7rem rgba(31, 57, 104, .07); }
  .regional-team-card .card-header { background: linear-gradient(90deg, #f3f8ff, #fff8ef); }
  .regional-team-card .card-header[data-team-workspace-header] { cursor: pointer; }
  .regional-team-card .table > :not(caption) > * > * { padding: .7rem .65rem; }
  .regional-team-card .reserve-row { background: #fffaf0; }
  .regional-team-card .replacement-player-form { min-width: 20rem; max-width: min(26rem, 80vw); }
  .regional-readonly { border-left: 4px solid #f59e0b; background: #fff9ed; }
  .regional-readonly .dropdown-menu, .regional-team-card .dropdown-menu { min-width: 15rem; }
  .regional-help { border: 1px solid #cfe0f4; border-radius: .65rem; background: #f7fbff; }
  .regional-help > summary { cursor: pointer; list-style: none; padding: .85rem 1rem; }
  .regional-help > summary::-webkit-details-marker { display: none; }
  .regional-help-step { border-left: 3px solid #80aee0; padding-left: .75rem; }
  .region-task-tabs { display: flex; flex-direction: row !important; flex-wrap: nowrap; gap: .35rem; padding: .4rem; border: 1px solid #dbe6f4; border-radius: .75rem; background: #f7faff; }
  .region-task-tabs .nav-link { display: flex; flex: 1 1 0; align-items: center; justify-content: center; gap: .4rem; min-width: 0; min-height: 2.75rem; border: 0; border-radius: .55rem; color: #506176; font-weight: 600; white-space: nowrap; }
  .region-task-tabs .nav-link.active { color: #173f78; background: #fff; box-shadow: 0 .15rem .5rem rgba(31, 57, 104, .12); }
  .region-task-panel { padding-top: 1rem; }
  .selection-progress { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .6rem; margin: 0; padding: 0; list-style: none; }
  .selection-progress-step { position: relative; min-width: 0; padding: .85rem; border: 1px solid #dbe6f4; border-radius: .65rem; background: #fff; }
  .selection-progress-step::before { display: inline-grid; place-items: center; width: 1.65rem; height: 1.65rem; margin-bottom: .55rem; border-radius: 50%; content: attr(data-step); background: #edf2f7; color: #68778c; font-weight: 700; }
  .selection-progress-step.is-complete { border-color: #b7e2ca; background: #f2fbf6; }
  .selection-progress-step.is-complete::before { content: '\2713'; background: #20a464; color: #fff; }
  .selection-progress-step.is-current { border-color: #80aee0; background: #f3f8ff; box-shadow: inset 0 0 0 1px #80aee0; }
  .selection-progress-step.is-current::before { background: #2374bb; color: #fff; }
  .selection-progress-step .btn-link { padding: 0; color: #173f78; font-weight: 600; text-align: left; text-decoration: none; }
  .selection-progress-step small { display: block; margin-top: .2rem; color: #68778c; }
  .regional-attention { display: flex; flex-wrap: wrap; gap: .5rem; }
  .region-action-status { padding: .45rem 1rem .3rem; color: #68778c; font-size: .75rem; }
  .imported-roster-sortable tr[draggable="true"] { cursor: grab; }
  .imported-roster-sortable tr.is-dragging { opacity: .45; }
  .imported-roster-sortable .drag-handle { cursor: grab; touch-action: none; }
  .team-publication-button[disabled], .clothing-order-toggle[disabled] { cursor: wait; }
  @media (max-width: 767.98px) {
    .regional-team-card .table { min-width: 760px; }
    .region-task-tabs { flex-wrap: nowrap; justify-content: flex-start; overflow-x: auto; scroll-snap-type: x proximity; }
    .region-task-tabs .nav-link { flex: 0 0 auto; min-width: max-content; scroll-snap-align: start; }
    .selection-progress { grid-template-columns: 1fr; }
    .selection-progress-step { display: grid; grid-template-columns: 2rem 1fr; column-gap: .65rem; align-items: start; }
    .selection-progress-step::before { grid-row: 1 / span 2; margin-bottom: 0; }
  }
</style>
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
@include('backend.event.partials.header', [
  'event' => $event,
  'eventWorkspaceActive' => 'entries',
  'eventWorkspaceRegionalOnly' => ! $isEventManager,
  'eventWorkspaceShowHome' => $isEventManager,
])
<div class="container-xxl flex-grow-1 container-p-y">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><strong>Action blocked.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  @if($isEventManager)
    <div class="alert alert-info">Link each ranking-fed region to its own published series. Imported outside-region rosters can remain unlinked and will not be changed.</div>
  @else
    <div class="alert alert-info">You are viewing team selection, invitations and announcements for your assigned region.</div>
  @endif

  @if($eventRegions->count() > 1)
    <div class="nav nav-tabs flex-nowrap overflow-auto mb-3" role="tablist" aria-label="Event regions" data-region-tabs>
      @foreach($eventRegions as $eventRegion)
        <button
          type="button"
          class="nav-link text-nowrap {{ $loop->first ? 'active' : '' }}"
          id="region-tab-{{ $eventRegion->id }}"
          data-bs-toggle="tab"
          data-bs-target="#region-panel-{{ $eventRegion->id }}"
          data-region-id="{{ $eventRegion->id }}"
          role="tab"
          aria-controls="region-panel-{{ $eventRegion->id }}"
          aria-selected="{{ $loop->first ? 'true' : 'false' }}"
        >{{ $eventRegion->region?->region_name }}</button>
      @endforeach
    </div>
  @endif

  <div class="{{ $eventRegions->count() > 1 ? 'tab-content' : 'row g-3' }}">
    @foreach($eventRegions as $eventRegion)
      @php($source = $eventRegion->rankingSource)
      @php($sourceReady = $source && $readySeriesIds->contains($source->series_id))
      @php($regionTeams = $teams->get($eventRegion->region_id, collect()))
      @php($categorySetup = $source ? $categorySetups->get($source->id) : null)
      @php($activeImport = $source?->imports?->whereIn('status', ['draft','sent'])->sortByDesc('id')->first())
      @php($contactEmailsFor = fn($invitation) => $teamSelectionContacts->emails($invitation->player))
      @php($rawContactEmailsFor = fn($invitation) => $teamSelectionContacts->rawEmails($invitation->player))
      @php($recipientEmailFor = fn($invitation) => $contactEmailsFor($invitation)->first())
      @php($regionClothingItems = $eventRegion->region?->clothingItems ?? collect())
      @php($clothingCatalogueReady = $regionClothingItems->isNotEmpty() && $regionClothingItems->every(fn($item) => (float)$item->price > 0 && $item->sizes->isNotEmpty()))
      @php($clothingAvailable = $eventRegion->region?->usesOnlineClothingOrders() && (bool)$eventRegion->region?->clothing_order && $clothingCatalogueReady)
      @php($regionManager = $regionManagers->get($eventRegion->id))
      @php($defaultCandidates = $defaultRegionManagerCandidates->get($eventRegion->id, collect()))
      @php($regionAnnouncementRecipients = $announcementRecipients->get($eventRegion->id, collect()))
      @php($regionRosterEmailRecipients = $regionRosterRecipients->get($eventRegion->id, collect()))
      @php($regionImportedCohorts = $importedRecipientCohorts->get($eventRegion->id, collect()))
      @php($unlinkedImportedRecipients = $regionImportedCohorts->get('unlinked_imported', collect()))
      @php($linkedUnpaidRecipients = $regionImportedCohorts->get('linked_unpaid', collect()))
      @php($allLinkedImportedRecipients = $regionImportedCohorts->get('linked_all', collect()))
      @php($unpublishedTeamCount = $regionTeams->where('published', false)->count())
      @php($selectionSent = $activeImport?->status === 'sent')
      @php($selectedInvitations = $activeImport?->invitations?->whereIn('status', [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, \App\Models\TeamSelectionInvitation::PAID_CONFIRMED]) ?? collect())
      @php($outstandingRegistrationCount = $selectedInvitations->whereNotIn('status', [\App\Models\TeamSelectionInvitation::PAID_CONFIRMED])->count())
      @php($regionalOpenPlaces = $regionTeams->sum(fn($team) => max(0, (int) $team->num_team_members - $selectedInvitations->where('team_id', $team->id)->count())))
      @php($missingContactCount = $activeImport?->invitations?->filter(fn($invitation) => ! $recipientEmailFor($invitation))->count() ?? 0)
      @php($selectionSteps = collect([
        ['label' => 'Link ranking series', 'tab' => 'setup', 'complete' => (bool) $source],
        ['label' => 'Create teams', 'tab' => 'setup', 'complete' => $regionTeams->isNotEmpty()],
        ['label' => 'Import players', 'tab' => 'invitations', 'complete' => (bool) $activeImport],
        ['label' => 'Send invitations', 'tab' => 'invitations', 'complete' => $selectionSent],
        ['label' => 'Resolve exceptions', 'tab' => 'teams', 'complete' => $selectionSent && $outstandingRegistrationCount === 0 && $regionalOpenPlaces === 0 && $missingContactCount === 0],
        ['label' => 'Publish teams', 'tab' => 'teams', 'complete' => $regionTeams->isNotEmpty() && $unpublishedTeamCount === 0],
      ]))
      @php($currentSelectionStep = $selectionSteps->search(fn($step) => ! $step['complete']))
      <div
        id="region-panel-{{ $eventRegion->id }}"
        class="{{ $eventRegions->count() > 1 ? 'tab-pane fade'.($loop->first ? ' show active' : '') : 'col-12' }}"
        role="tabpanel"
        aria-labelledby="region-tab-{{ $eventRegion->id }}"
        tabindex="0"
      >
        <div class="card region-workspace-card">
          <div class="card-header d-flex flex-wrap justify-content-between gap-2">
            <div><h5 class="mb-1">{{ $eventRegion->region?->region_name }}</h5><span class="text-muted small">{{ $regionTeams->count() }} teams · {{ $regionTeams->sum('num_team_members') }} configured places</span></div>
            @if($activeImport)
              <span class="badge bg-label-{{ $activeImport->status === 'sent' ? 'success' : 'warning' }}">{{ ucfirst($activeImport->status) }}</span>
            @elseif($source)
              <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge bg-label-primary">Series linked</span>
                <form method="POST" action="{{ route('backend.team-selection.unlink', [$event, $source]) }}" onsubmit="return confirm('Unlink this ranking series? Existing event categories and teams will be kept.');">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ti ti-unlink me-1"></i>Unlink series</button>
                </form>
              </div>
            @else
              <span class="badge bg-label-secondary">Manual/imported or not linked</span>
            @endif
          </div>
          <div class="card-body">
            <div class="nav nav-pills region-task-tabs" role="tablist" aria-label="{{ $eventRegion->region?->region_name }} workspace" data-region-task-tabs="{{ $eventRegion->id }}">
              @foreach(['overview' => ['ti-layout-dashboard', 'Overview'], 'teams' => ['ti-users-group', 'Teams & players'], 'invitations' => ['ti-mail-forward', 'Invitations'], 'messages' => ['ti-message-circle', 'Messages & clothing'], 'setup' => ['ti-settings', 'Setup']] as $taskKey => [$taskIcon, $taskLabel])
                <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="region-{{ $eventRegion->id }}-{{ $taskKey }}-tab" data-bs-toggle="tab" data-bs-target="#region-{{ $eventRegion->id }}-{{ $taskKey }}" type="button" role="tab" aria-controls="region-{{ $eventRegion->id }}-{{ $taskKey }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-region-task="{{ $taskKey }}"><i class="ti {{ $taskIcon }}"></i>{{ $taskLabel }}@if($taskKey === 'teams' && $unpublishedTeamCount)<span class="badge bg-label-warning">{{ $unpublishedTeamCount }}</span>@endif</button>
              @endforeach
            </div>
            <div class="tab-content">
              <div class="tab-pane fade show active region-task-panel" id="region-{{ $eventRegion->id }}-overview" role="tabpanel" aria-labelledby="region-{{ $eventRegion->id }}-overview-tab" tabindex="0">
                <div class="mb-3">
                  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h6 class="mb-1">Selection progress</h6><p class="text-muted small mb-0">Follow the regional workflow, or open any available area directly.</p></div>@if($currentSelectionStep === false)<span class="badge bg-label-success"><i class="ti ti-circle-check me-1"></i>Workflow complete</span>@else<span class="badge bg-label-primary">Step {{ $currentSelectionStep + 1 }} of {{ count($selectionSteps) }}</span>@endif</div>
                  <ol class="selection-progress" aria-label="Regional selection progress">
                    @foreach($selectionSteps as $stepIndex => $selectionStep)
                      @php($stepState = $selectionStep['complete'] ? 'is-complete' : ($currentSelectionStep === $stepIndex ? 'is-current' : 'is-upcoming'))
                      <li class="selection-progress-step {{ $stepState }}" data-step="{{ $stepIndex + 1 }}">
                        <button type="button" class="btn btn-link" data-open-region-task="{{ $selectionStep['tab'] }}">{{ $selectionStep['label'] }}</button>
                        <small>{{ $selectionStep['complete'] ? 'Complete' : ($currentSelectionStep === $stepIndex ? 'Next action' : 'Not started') }}</small>
                      </li>
                    @endforeach
                  </ol>
                </div>
            @if($isEventManager)
              <div class="modal fade" id="final-team-reminders-{{ $eventRegion->id }}" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered"><form method="POST" action="{{ route('backend.team-selection.final-reminders.send', [$event, $eventRegion]) }}" class="modal-content" data-final-reminder-form data-reminder-summaries='@json($reminderSummaries[$eventRegion->id] ?? [])' data-reminder-hashes='@json($reminderHashes[$eventRegion->id] ?? [])'>@csrf
                  <input type="hidden" name="send_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                  <input type="hidden" name="recipient_hash" data-reminder-hash>
                  <div class="modal-header"><div><h5 class="modal-title">Send {{ $eventRegion->region?->region_name }} reminders</h5><div class="small text-muted">Only active invitations in this region are included.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                  <div class="modal-body">
                    <div class="row g-3">
                      <div class="col-md-6"><label class="form-label">Reminder</label><select class="form-select" name="kind" data-reminder-kind required><option value="registration_clothing">Registration and clothing reminder</option><option value="incomplete_clothing">Incomplete clothing reminder</option></select></div>
                      <div class="col-md-6"><label class="form-label">Send to</label><select class="form-select" name="audience" data-reminder-audience required><option value="all">All active players in this region</option><option value="registered">Registered players in this region</option><option value="unregistered">Unregistered players in this region</option></select></div>
                    </div>
                    <div class="alert alert-primary mt-3 mb-3" data-reminder-summary></div>
                    <div class="border rounded p-3 bg-light"><strong data-reminder-preview-title>Registration is closing</strong><p class="mb-1 mt-2" data-reminder-preview-copy>Unregistered players receive their registration/payment link. Registered players receive their clothing action link.</p><small class="text-muted">One email is sent per address. Where a parent receives mail for several players in this region, all affected players and their individual links are included.</small></div>
                    <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="confirm_recipients" value="1" id="confirm-final-reminders-{{ $eventRegion->id }}" required><label class="form-check-label" for="confirm-final-reminders-{{ $eventRegion->id }}">I reviewed this region’s reminder and recipient group.</label></div>
                  </div>
                  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" data-reminder-submit onclick="return confirm('Queue this reminder for the reviewed regional recipients?');">Send reminder</button></div>
                </form></div>
              </div>
            @endif
            <div class="border rounded p-3 mb-3">
              <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                <div><small class="text-muted d-block">Regional organizer</small><strong>{{ $regionManager?->name ?: trim(($regionManager?->userName ?? '').' '.($regionManager?->userSurname ?? '')) ?: $regionManager?->email ?: 'Not assigned' }}</strong>@if($regionManager?->email)<div class="small text-muted">{{ $regionManager->email }} · player profile not required</div>@endif</div>
                @if($isEventManager && $eventRegion->managerAssignment)<span class="badge bg-label-primary">Custom assignment</span>@elseif($regionManager)<span class="badge bg-label-secondary">Series organizer default</span>@endif
              </div>
              @if($isEventManager)
                <form method="POST" action="{{ route('backend.team-selection.manager.assign', [$event, $eventRegion]) }}" class="row g-2 align-items-end mt-1">@csrf @method('PUT')
                  <div class="col-md-7"><label class="form-label" for="region-manager-{{ $eventRegion->id }}">Select a system user</label><select id="region-manager-{{ $eventRegion->id }}" name="manager_user_id" class="form-select region-manager-select" data-placeholder="Search by name or email…"><option value=""></option>@if($eventRegion->managerAssignment && $regionManager)<option value="{{ $regionManager->id }}" selected>{{ trim($regionManager->name ?: (($regionManager->userName ?? '').' '.($regionManager->userSurname ?? ''))) }} · {{ $regionManager->email }}</option>@endif</select></div>
                  <div class="col-md-3 d-grid"><button class="btn btn-outline-primary">Assign to this region</button></div>
                  <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary" name="use_default" value="1" @disabled(!$defaultRegionManagers->get($eventRegion->id))>Use default</button></div>
                </form>
                <div class="form-text">This grants access to this region; it does not remove existing event-wide or other-region access. A player profile is not required.</div>
                @if(!$eventRegion->managerAssignment && $defaultCandidates->count() > 1)<div class="alert alert-warning mt-2 mb-0">This series has multiple common event organizers. No default was selected automatically; assign the intended account explicitly.</div>@endif
              @endif
            </div>
            @if($activeImport)
              <div class="regional-summary mb-3" aria-label="Regional roster summary">
                <span class="regional-summary-item"><strong>{{ $selectedInvitations->count() }}</strong> selected</span>
                <span class="regional-summary-item"><strong>{{ $activeImport->invitations->where('status', \App\Models\TeamSelectionInvitation::RESERVE)->count() }}</strong> reserves</span>
                <span class="regional-summary-item"><strong>{{ $activeImport->invitations->where('status', \App\Models\TeamSelectionInvitation::PAID_CONFIRMED)->count() }}</strong> paid</span>
                <span class="regional-summary-item"><strong>{{ $missingContactCount }}</strong> need contact</span>
              </div>
              @if($outstandingRegistrationCount || $regionalOpenPlaces || $missingContactCount)
                <div class="alert alert-warning mb-3">
                  <strong class="d-block mb-2">What needs attention</strong>
                  <div class="regional-attention">
                    @if($outstandingRegistrationCount)<span class="badge bg-label-warning">{{ $outstandingRegistrationCount }} registration/payment {{ \Illuminate\Support\Str::plural('response', $outstandingRegistrationCount) }} outstanding</span>@endif
                    @if($regionalOpenPlaces)<span class="badge bg-label-danger">{{ $regionalOpenPlaces }} open team {{ \Illuminate\Support\Str::plural('place', $regionalOpenPlaces) }}</span>@endif
                    @if($missingContactCount)<span class="badge bg-label-danger">{{ $missingContactCount }} without contact email</span>@endif
                  </div>
                </div>
              @endif
            @endif

            <details class="regional-help mb-3">
              <summary class="d-flex justify-content-between align-items-center gap-2"><span><strong><i class="ti ti-help-circle me-1"></i>How to manage teams in this region</strong><span class="d-block small text-muted mt-1">Replacement, reserves, reminders, clothing and publishing instructions.</span></span><span class="badge bg-label-primary">View steps</span></summary>
              <div class="border-top p-3">
                <div class="row g-3 small">
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Replace an unpaid player</strong><div class="text-muted">Select <strong>Show team</strong>, find the player, choose <strong>Change player</strong>, select the next reserve or another player profile, give a reason, then confirm. Paid players cannot be replaced here.</div></div>
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Fill an open place</strong><div class="text-muted">Select <strong>Show team</strong>. A withdrawn or declined place shows <strong>Invite next reserve</strong>; an eligible reserve may also show <strong>Activate as Rank</strong>.</div></div>
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Change player order</strong><div class="text-muted">Select <strong>Show team</strong>, open <strong>Player order</strong>, then drag players into the required order.</div></div>
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Contact outstanding players</strong><div class="text-muted">Use <strong>Registration reminder</strong> or <strong>Incomplete clothing reminder</strong> above. Review the regional audience and exact recipient count before sending.</div></div>
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Manage clothing</strong><div class="text-muted">Use <strong>Clothing setup</strong> above to review items and sizes. Use <strong>Region actions</strong> to open or close clothing ordering.</div></div>
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Publish teams</strong><div class="text-muted">Resolve open places first, review each team, then select <strong>Publish all teams</strong>. Published teams can still be opened and reviewed.</div></div>
                </div>
              </div>
            </details>

              </div>
              <div class="tab-pane fade region-task-panel" id="region-{{ $eventRegion->id }}-teams" role="tabpanel" aria-labelledby="region-{{ $eventRegion->id }}-teams-tab" tabindex="0">

            <div class="regional-readonly rounded p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div><strong>Regional teams &amp; players</strong><div class="small text-muted">Region-scoped workspace · ranking positions, selection history and payment state are read-only records.</div></div>
              <div class="d-flex flex-wrap gap-2 align-items-center">
                @if($activeImport?->status === 'draft')
                  <button class="btn btn-sm btn-success" type="button" data-bs-toggle="modal" data-bs-target="#prepare-invitations-{{ $activeImport->id }}"><i class="ti ti-send me-1"></i>Send all invitations</button>
                @elseif($unpublishedTeamCount > 0)
                  <button type="button" class="btn btn-sm btn-success publish-all-teams" data-url="{{ route('backend.team-selection.teams.publish-all', [$event, $eventRegion]) }}"><i class="ti ti-world-upload me-1"></i>Publish all teams</button>
                @elseif($regionTeams->isNotEmpty())
                  <span class="badge bg-label-success"><i class="ti ti-circle-check me-1"></i>All teams published</span>
                @endif
                <div class="dropdown">
                  <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti ti-dots me-1"></i>Region actions</button>
                  <div class="dropdown-menu dropdown-menu-end">
                    @if($activeImport?->status === 'draft' && $unpublishedTeamCount > 0)
                      <button type="button" class="dropdown-item publish-all-teams" data-url="{{ route('backend.team-selection.teams.publish-all', [$event, $eventRegion]) }}"><i class="ti ti-world-upload me-2"></i>Publish all teams</button>
                    @endif
                    @if($isEventManager)
                      <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#reimport-roster-{{ $eventRegion->id }}"><i class="ti ti-file-spreadsheet me-2"></i>Re-import roster contacts</button>
                    @endif
                    @if($unlinkedImportedRecipients->isNotEmpty())
                      <button class="dropdown-item roster-email-button" type="button" data-bs-toggle="modal" data-bs-target="#roster-email-{{ $eventRegion->id }}" data-target-type="unlinked_imported" data-recipient="{{ $unlinkedImportedRecipients->count() }} unlinked imported player email(s)" data-recipient-hash="{{ hash('sha256', $unlinkedImportedRecipients->pluck('email')->toJson()) }}"><i class="ti ti-user-question me-2"></i>Email unlinked / not registered</button>
                    @endif
                    @if($linkedUnpaidRecipients->isNotEmpty())
                      <button class="dropdown-item roster-email-button" type="button" data-bs-toggle="modal" data-bs-target="#roster-email-{{ $eventRegion->id }}" data-target-type="linked_unpaid" data-recipient="{{ $linkedUnpaidRecipients->count() }} linked player email(s) still not registered/paid" data-recipient-hash="{{ hash('sha256', $linkedUnpaidRecipients->pluck('email')->toJson()) }}"><i class="ti ti-credit-card-off me-2"></i>Email linked, not registered / paid</button>
                    @endif
                    @if($allLinkedImportedRecipients->isNotEmpty())
                      <button class="dropdown-item roster-email-button" type="button" data-bs-toggle="modal" data-bs-target="#roster-email-{{ $eventRegion->id }}" data-target-type="linked_all" data-recipient="{{ $allLinkedImportedRecipients->count() }} linked player email(s) in {{ $eventRegion->region?->region_name }}" data-recipient-hash="{{ hash('sha256', $allLinkedImportedRecipients->pluck('email')->toJson()) }}"><i class="ti ti-users me-2"></i>Email all linked players</button>
                    @endif
                    @if($eventRegion->region?->usesOnlineClothingOrders())
                      <div class="dropdown-divider"></div>
                      <div class="region-action-status" data-clothing-status>{{ $eventRegion->region->clothing_order ? 'Clothing ordering open' : 'Clothing ordering closed' }}</div>
                      <form method="POST" action="{{ route('backend.region.clothing.toggle', $eventRegion->region_id) }}" class="clothing-order-form">
                        @csrf @method('PATCH')
                        <button type="submit" class="dropdown-item clothing-order-toggle" data-catalogue-ready="{{ $clothingCatalogueReady ? '1' : '0' }}" @disabled(! $eventRegion->region->clothing_order && ! $clothingCatalogueReady) @if(! $eventRegion->region->clothing_order && ! $clothingCatalogueReady) title="Finish clothing setup before opening orders" @endif><i class="ti ti-{{ $eventRegion->region->clothing_order ? 'lock' : 'shopping-cart' }} me-2"></i>{{ $eventRegion->region->clothing_order ? 'Close ordering' : 'Open ordering' }}</button>
                      </form>
                    @endif
                  </div>
                </div>
              </div>
            </div>

            @if($regionTeams->isNotEmpty())
              <div class="row g-3 mb-3">
                @foreach($regionTeams as $regionTeam)
                  @php($teamInvitations = $activeImport?->invitations?->where('team_id', $regionTeam->id)->sortBy('queue_position') ?? collect())
                  @php($teamSelected = $teamInvitations->whereIn('status', [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, \App\Models\TeamSelectionInvitation::PAID_CONFIRMED]))
                  @php($teamOpenPlaceCount = max(0, (int) $regionTeam->num_team_members - $teamSelected->count()))
                  @php($teamReserves = $teamInvitations->where('status', \App\Models\TeamSelectionInvitation::RESERVE))
                  @php($eligibleTeamReserves = $teamReserves->filter(fn($reserve) => $recipientEmailFor($reserve)))
                  @php($openRosterRanks = (int) $regionTeam->num_team_members > 0 ? collect(range(1, (int) $regionTeam->num_team_members))->reject(fn($rank) => $teamSelected->contains(fn($selected) => (int) $selected->roster_rank === $rank))->values() : collect())
                  @php($importedRoster = $regionTeam->team_players_no_profile->sortBy('rank')->values())
                  @php($linkedImportedCount = $importedRoster->whereNotNull('player_profile')->count())
                  <div class="col-12">
                    <div class="card regional-team-card">
                      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2" data-team-workspace-header data-team-workspace-target="#team-workspace-{{ $regionTeam->id }}">
                        <div>
                          <h6 class="mb-1">{{ $regionTeam->name }}</h6>
                          @if($activeImport)
                            <span class="text-muted small">{{ $teamSelected->count() }} selected · {{ $teamReserves->count() }} reserves · {{ $regionTeam->num_team_members }} configured places</span>
                          @else
                            <span class="text-muted small">{{ $importedRoster->count() }} roster players · {{ $linkedImportedCount }} linked · {{ $importedRoster->count() - $linkedImportedCount }} unlinked · {{ $regionTeam->num_team_members }} configured places</span>
                          @endif
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                          @if($activeImport && $teamOpenPlaceCount > 0)<span class="badge bg-label-danger">{{ $teamOpenPlaceCount }} open {{ \Illuminate\Support\Str::plural('place', $teamOpenPlaceCount) }}</span>@endif
                          <span class="badge {{ $regionTeam->published ? 'bg-label-success' : 'bg-label-secondary' }}" data-team-publication-status>{{ $regionTeam->published ? 'Published' : 'Not published' }}</span>
                          <button class="btn btn-sm btn-primary team-workspace-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#team-workspace-{{ $regionTeam->id }}" aria-controls="team-workspace-{{ $regionTeam->id }}" aria-expanded="false"><i class="ti ti-eye me-1"></i><span>Show team</span></button>
                          <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for {{ $regionTeam->name }}"><i class="ti ti-dots-vertical"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                              <button type="button" class="dropdown-item team-publication-button" data-url="{{ route('backend.team-selection.teams.publication.update', [$event, $eventRegion, $regionTeam]) }}" data-team-id="{{ $regionTeam->id }}" data-published="{{ $regionTeam->published ? '1' : '0' }}"><i class="ti ti-{{ $regionTeam->published ? 'world-off' : 'world-upload' }} me-2"></i><span>{{ $regionTeam->published ? 'Unpublish' : 'Publish' }}</span></button>
                              @if($teamSelected->isNotEmpty())<button class="dropdown-item roster-email-button" type="button" data-bs-toggle="modal" data-bs-target="#roster-email-{{ $eventRegion->id }}" data-target-type="team" data-team-id="{{ $regionTeam->id }}" data-recipient="{{ $teamSelected->count() }} active player(s) in {{ $regionTeam->name }}"><i class="ti ti-mail me-2"></i>Email team</button>@endif
                              <button class="dropdown-item team-settings-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#team-settings-{{ $regionTeam->id }}" aria-controls="team-settings-{{ $regionTeam->id }}" aria-expanded="false"><i class="ti ti-settings me-2"></i><span>Team settings</span></button>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="collapse" id="team-settings-{{ $regionTeam->id }}">
                        <div class="card-body border-bottom">
                          <form method="POST" action="{{ route('backend.team-selection.teams.update', [$event, $eventRegion, $regionTeam]) }}" class="row g-2 align-items-end">@csrf @method('PATCH')
                            <input type="hidden" name="settings_team_id" value="{{ $regionTeam->id }}">
                            <div class="col-md-5"><label class="form-label">Team name</label><input name="name" value="{{ $regionTeam->name }}" class="form-control" maxlength="255" required></div>
                            <div class="col-md-2"><label class="form-label">Players in team</label><input type="number" name="num_team_members" value="{{ (int) old('settings_team_id') === (int) $regionTeam->id ? old('num_team_members', $regionTeam->num_team_members) : $regionTeam->num_team_members }}" class="form-control" min="1" max="50" required></div>
                            <div class="col-md-3"><input type="hidden" name="published" value="0"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="published" value="1" id="published-team-{{ $regionTeam->id }}" @checked($regionTeam->published)><label class="form-check-label" for="published-team-{{ $regionTeam->id }}">Published for registration</label></div></div>
                            <div class="col-md-2 d-grid"><button class="btn btn-primary">Save team</button></div>
                            <div class="col-12 form-text">Increasing the number adds open player places. Reducing it moves the highest unpaid selected players into the reserve queue; accepted or paid players remain protected.</div>
                          </form>
                        </div>
                      </div>
                      <div class="collapse" id="team-workspace-{{ $regionTeam->id }}">
                      @if($activeImport)
                        <ul class="nav nav-tabs px-3 pt-3" role="tablist">
                          <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#team-players-{{ $regionTeam->id }}" type="button"><i class="ti ti-users me-1"></i>Players</button></li>
                          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#team-order-{{ $regionTeam->id }}" type="button"><i class="ti ti-list-numbers me-1"></i>Player order</button></li>
                        </ul>
                        <div class="tab-content p-0">
                        <div class="tab-pane fade show active" id="team-players-{{ $regionTeam->id }}">
                        <form method="POST" action="{{ route('backend.team-selection.players.add', [$event, $activeImport, $regionTeam]) }}" class="row g-2 align-items-end p-3 border-bottom">
                          @csrf
                          <input type="hidden" name="add_team_id" value="{{ $regionTeam->id }}">
                          <div class="col-lg-5">
                            <label class="form-label" for="add-player-{{ $regionTeam->id }}">Add an existing system player profile</label>
                            <select id="add-player-{{ $regionTeam->id }}" name="player_id" class="form-select team-player-select" data-placeholder="Search player name, email or cell…" data-search-url="{{ route('backend.team-selection.players.search', [$event, $activeImport, $regionTeam]) }}" required><option value=""></option></select>
                          </div>
                          <div class="col-lg-5">
                            <label class="form-label" for="add-player-reason-{{ $regionTeam->id }}">Reason</label>
                            <input id="add-player-reason-{{ $regionTeam->id }}" type="text" name="reason" class="form-control" maxlength="1000" placeholder="Why this player is being added" required>
                          </div>
                          <div class="col-lg-2 d-grid"><button class="btn btn-outline-primary"><i class="ti ti-user-plus me-1"></i>Add as reserve</button></div>
                          <div class="col-12 form-text">System player profiles are shown. Player-profile email is used first, followed by a parent or linked-account email. The player is appended to the reserve queue; the active roster and published ranking snapshot stay unchanged.</div>
                        </form>
                        <div class="table-responsive">
                          <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Rank</th><th>Player</th><th>Contact</th><th>Ranking</th><th>Selection / payment</th><th>Email</th><th>Regional action</th></tr></thead>
                            <tbody>
                              @forelse($teamInvitations as $invitation)
                                @php($recipientEmail = $recipientEmailFor($invitation))
                                @php($rawContactEmails = $rawContactEmailsFor($invitation))
                                @php($delivery = $invitation->emailLogs->sortByDesc('id')->first())
                                @php($isReserve = $invitation->status === \App\Models\TeamSelectionInvitation::RESERVE)
                                @php($isInactive = in_array($invitation->status, [\App\Models\TeamSelectionInvitation::DECLINED, \App\Models\TeamSelectionInvitation::WITHDRAWN], true) || (!$isReserve && !$invitation->roster_rank))
                                @php($hasActiveReplacement = $teamInvitations->contains(fn($candidate) => (int) $candidate->promoted_from_id === (int) $invitation->id && in_array($candidate->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, \App\Models\TeamSelectionInvitation::PAID_CONFIRMED], true)))
                                @php($openVacancy = $isInactive && $invitation->vacated_roster_rank && !$hasActiveReplacement)
                                @php($rankLabel = $isReserve ? 'Reserve '.$invitation->queue_position : ($isInactive ? ($invitation->status === \App\Models\TeamSelectionInvitation::DECLINED ? 'Declined' : ($invitation->status === \App\Models\TeamSelectionInvitation::WITHDRAWN ? 'Withdrawn' : 'Removed')) : 'Rank '.$invitation->roster_rank))
                                @php($statusTone = $isInactive ? 'danger' : ($invitation->status === \App\Models\TeamSelectionInvitation::PAID_CONFIRMED ? 'success' : ($isReserve ? 'warning' : 'info')))
                                <tr class="{{ $isReserve ? 'reserve-row' : '' }}">
                                  <td><span class="badge {{ $isInactive ? 'bg-label-danger' : ($isReserve ? 'bg-label-warning' : 'bg-label-primary') }}">{{ $rankLabel }}</span></td>
                                  <td><strong>{{ $invitation->player?->full_name ?: 'Missing player' }}</strong>@if(!$invitation->player?->profile_complete)<div class="small text-warning">Profile incomplete</div>@endif</td>
                                  <td>
                                    @if($recipientEmail)
                                      <div>{{ $recipientEmail }}</div>
                                    @elseif($rawContactEmails->isNotEmpty())
                                      <div class="text-warning">Profile/account email invalid</div>
                                      <div class="small text-muted">{{ $rawContactEmails->first() }}</div>
                                    @else
                                      <div>Email required</div>
                                    @endif
                                    <div class="small text-muted">{{ $invitation->player?->cellNr ?: 'No cell number' }}</div>
                                  </td>
                                  <td>@if(data_get($invitation->snapshot_json, 'selection_source') === 'manual_system_profile')<strong>Manual addition</strong><div class="small text-muted">Not in ranking snapshot</div>@else<strong>#{{ $invitation->ranking_position }}</strong><div class="small text-muted">{{ number_format((float)$invitation->total_points, 2) }} pts</div>@endif</td>
                                  <td><span class="badge bg-label-{{ $statusTone }}">{{ str($invitation->status)->replace('_',' ')->title() }}</span>@if($invitation->decline_method === 'system_primary_team_promotion')<div class="small text-info mt-1">{{ $invitation->decline_reason }}</div>@else<div class="small text-muted mt-1">Read only</div>@endif</td>
                                  <td>
                                    <div>{{ $delivery ? ucfirst($delivery->status) : 'Not sent' }}</div>
                                    @if($activeImport->status === 'sent' && !$isReserve)
                                      <div class="d-flex flex-wrap gap-1 mt-1">
                                        <a class="btn btn-xs btn-outline-secondary" target="_blank" href="{{ route('backend.team-selection.invitations.email.view', [$event, $activeImport, $invitation]) }}">View email</a>
                                        @if(in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true))
                                          <form method="POST" action="{{ route('backend.team-selection.invitations.email.resend', [$event, $activeImport, $invitation]) }}" onsubmit="return confirm('Resend the saved invitation email to this player?');">@csrf<button class="btn btn-xs btn-outline-primary">Resend</button></form>
                                        @endif
                                      </div>
                                    @endif
                                  </td>
                                  <td>
                                    <div class="d-flex flex-wrap gap-1">
                                    @if($isReserve && ($reserveActivationIndex = $teamReserves->values()->search(fn($candidate) => (int) $candidate->id === (int) $invitation->id)) !== false && ($reserveActivationRank = $openRosterRanks->get($reserveActivationIndex)))
                                      <form method="POST" action="{{ route('backend.team-selection.invitations.activate', [$event, $activeImport, $invitation]) }}" onsubmit="return confirm('Activate this reserve in the next open team place?');">@csrf<button class="btn btn-sm btn-success">Activate as Rank {{ $reserveActivationRank }}</button></form>
                                    @endif
                                      @if($recipientEmail && !$isReserve)<button class="btn btn-sm btn-outline-success roster-email-button" type="button" data-bs-toggle="modal" data-bs-target="#roster-email-{{ $eventRegion->id }}" data-target-type="player" data-team-id="{{ $regionTeam->id }}" data-invitation-id="{{ $invitation->id }}" data-recipient="{{ $invitation->player?->full_name }} · {{ $recipientEmail }}"><i class="ti ti-mail"></i></button>@endif
                                    @if(!$isReserve && in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true))
                                      @php($replacementFormOpen = (int) old('replacement_invitation_id') === (int) $invitation->id)
                                      @php($replacementMode = $replacementFormOpen ? old('replacement_mode', 'next_reserve') : ($eligibleTeamReserves->isNotEmpty() ? 'next_reserve' : 'custom_profile'))
                                      @php($replacementMode = $replacementMode === 'next_reserve' && $eligibleTeamReserves->isEmpty() ? 'custom_profile' : $replacementMode)
                                      <details @if($replacementFormOpen) open @endif>
                                        <summary class="btn btn-sm btn-outline-warning">Change player</summary>
                                        <form method="POST" action="{{ route('backend.team-selection.invitations.replace', [$event, $activeImport, $invitation]) }}"
                                              class="mt-2 replacement-player-form" data-replacement-player-form
                                              onsubmit="return confirm('Replace this unpaid player? The roster change is audited and cannot be undone.');">
                                          @csrf
                                          <input type="hidden" name="replacement_invitation_id" value="{{ $invitation->id }}">
                                          <label class="form-label small mb-1" for="replacement-mode-{{ $invitation->id }}">Replacement source</label>
                                          <select id="replacement-mode-{{ $invitation->id }}" name="replacement_mode"
                                                  class="form-select form-select-sm mb-2 replacement-mode-select" data-replacement-mode>
                                            <option value="next_reserve" @disabled($eligibleTeamReserves->isEmpty()) @selected($replacementMode === 'next_reserve')>
                                              @if($eligibleTeamReserves->isNotEmpty()) Next reserve — {{ $eligibleTeamReserves->first()->player?->full_name }} @else No eligible reserve available @endif
                                            </option>
                                            <option value="custom_profile" @selected($replacementMode === 'custom_profile')>Choose a Cape Tennis player profile</option>
                                          </select>
                                          <div @class(['mb-2', 'd-none' => $replacementMode !== 'custom_profile']) data-custom-replacement-profile>
                                            <label class="form-label small mb-1" for="replacement-player-{{ $invitation->id }}">Player profile</label>
                                            <select id="replacement-player-{{ $invitation->id }}" name="replacement_player_id"
                                                    class="form-select team-player-select replacement-profile-select"
                                                    data-placeholder="Search player name, email or cell…"
                                                    data-search-url="{{ route('backend.team-selection.players.search', [$event, $activeImport, $regionTeam]) }}"
                                                    @disabled($replacementMode !== 'custom_profile') @required($replacementMode === 'custom_profile')>
                                              <option value=""></option>
                                            </select>
                                            <div class="form-text">A valid player-profile email is used first, followed by a parent or linked-account email. Existing players below this place move up, and the replacement joins the final active roster place.</div>
                                          </div>
                                          <label class="form-label small mb-1" for="replacement-reason-{{ $invitation->id }}">Reason</label>
                                          <input id="replacement-reason-{{ $invitation->id }}" type="text" name="reason"
                                                 class="form-control form-control-sm mb-2" maxlength="1000"
                                                 value="Player not available." placeholder="Required reason" required>
                                          <button class="btn btn-sm btn-warning w-100">Confirm replacement</button>
                                        </form>
                                      </details>
                                    @endif
                                    @if($openVacancy && $eligibleTeamReserves->isNotEmpty())
                                      <form method="POST" action="{{ route('backend.team-selection.invitations.promote-reserve', [$event, $activeImport, $invitation]) }}" onsubmit="return confirm('Invite the next reserve now? Their own response and payment deadlines will start now.');">@csrf<button class="btn btn-sm btn-warning">Invite next reserve</button></form>
                                    @elseif($openVacancy)
                                      <span class="text-warning small">Vacancy open · no eligible reserve. Add or link a reserve below.</span>
                                    @endif
                                    @if(!$openVacancy && !$recipientEmail && ($isReserve || !in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true)))
                                      <span class="text-muted small">No action available</span>
                                    @endif
                                    </div>
                                  </td>
                                </tr>
                              @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No ranked players have been imported for this team yet.</td></tr>
                              @endforelse
                            </tbody>
                          </table>
                        </div>
                        </div>
                        <div class="tab-pane fade" id="team-order-{{ $regionTeam->id }}">
                          <div class="p-3 border-bottom small text-muted">Change the playing order without changing the selected players, their payment state, or the original ranking snapshot. Every move is audited.</div>
                          <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                              <thead><tr><th>Roster rank</th><th>Player</th><th>Published ranking</th><th>Selection status</th><th>Move</th></tr></thead>
                              <tbody>
                                @foreach($teamSelected->sortBy('roster_rank') as $orderedInvitation)
                                  <tr>
                                    <td><span class="badge bg-label-primary">Rank {{ $orderedInvitation->roster_rank }}</span></td>
                                    <td><strong>{{ $orderedInvitation->player?->full_name }}</strong></td>
                                    <td>@if(data_get($orderedInvitation->snapshot_json, 'selection_source') === 'manual_system_profile')Manual addition <span class="text-muted">· not in ranking snapshot</span>@else#{{ $orderedInvitation->ranking_position }} <span class="text-muted">· {{ number_format((float)$orderedInvitation->total_points, 2) }} pts</span>@endif</td>
                                    <td>{{ str($orderedInvitation->status)->replace('_',' ')->title() }}</td>
                                    <td><div class="d-flex gap-1">
                                      <form method="POST" action="{{ route('backend.team-selection.invitations.move', [$event, $activeImport, $orderedInvitation]) }}">@csrf<input type="hidden" name="direction" value="up"><button class="btn btn-sm btn-outline-primary" title="Move up" @disabled($loop->first)><i class="ti ti-arrow-up"></i></button></form>
                                      <form method="POST" action="{{ route('backend.team-selection.invitations.move', [$event, $activeImport, $orderedInvitation]) }}">@csrf<input type="hidden" name="direction" value="down"><button class="btn btn-sm btn-outline-primary" title="Move down" @disabled($loop->last)><i class="ti ti-arrow-down"></i></button></form>
                                    </div></td>
                                  </tr>
                                @endforeach
                              </tbody>
                            </table>
                          </div>
                        </div>
                        </div>
                      @else
                        @include('backend.team-selection._imported-roster')
                      @endif
                      </div>
                    </div>
                  </div>
                @endforeach
              </div>
            @else
              <div class="alert alert-warning">No regional teams exist yet. Link a ranking series and set up the region’s categories and teams below.</div>
            @endif
              </div>

              <div class="tab-pane fade region-task-panel" id="region-{{ $eventRegion->id }}-invitations" role="tabpanel" aria-labelledby="region-{{ $eventRegion->id }}-invitations-tab" tabindex="0">
                @if(!$activeImport)
                  <div class="alert alert-info mb-0">Complete the ranking and team setup first, then import the ranked players to prepare invitations.</div>
                @else
                  <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-3"><div><h6 class="mb-1">Invitation campaign</h6><div class="text-muted small">Manage reserve promotion, delivery, response and payment deadlines.</div></div><span class="badge bg-label-{{ $activeImport->status === 'sent' ? 'success' : 'warning' }}">{{ ucfirst($activeImport->status) }}</span></div>
                  <div class="regional-readonly rounded p-2 mb-3 small"><strong>Ranking snapshot:</strong> <code>{{ $activeImport->ranking_run_id }}</code> · locked to preserve the imported selection record.</div>
                  <form method="POST" action="{{ route('backend.team-selection.replacement-mode.update', [$event, $activeImport]) }}" class="border rounded p-3">@csrf @method('PATCH')
                    <div class="row g-3 align-items-end">
                      <div class="col-lg-8"><label class="form-label">Reserve replacement</label><select name="replacement_mode" class="form-select"><option value="automatic" @selected($activeImport->auto_replacement_enabled)>Automatic</option><option value="manual" @selected(!$activeImport->auto_replacement_enabled)>Manual approval</option></select></div>
                      <div class="col-lg-4 d-grid"><button class="btn btn-outline-primary">Save setting</button></div>
                      <div class="col-12 form-text">Automatic invites the next eligible reserve immediately. Manual leaves a visible vacancy with an <strong>Invite next reserve</strong> button. A replacement keeps the campaign deadline when it is still more than 24 hours away; otherwise they receive 24 hours from invitation, capped before the event starts.</div>
                    </div>
                  </form>
                  @php($emailLogs = $activeImport->invitations->flatMap->emailLogs)
                  @if($activeImport->status === 'sent')
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3"><span class="badge bg-label-secondary">Email queued: {{ $emailLogs->where('status','queued')->count() }}</span><span class="badge bg-label-success">Sent: {{ $emailLogs->where('status','sent')->count() }}</span><span class="badge bg-label-danger">Failed: {{ $emailLogs->where('status','failed')->count() }}</span><span class="badge bg-label-warning">Skipped: {{ $emailLogs->where('status','skipped')->count() }}</span>@if($emailLogs->where('status','failed')->isNotEmpty())<form method="POST" action="{{ route('backend.team-selection.emails.retry', [$event, $activeImport]) }}">@csrf<button class="btn btn-sm btn-outline-danger">Retry failed emails</button></form>@endif</div>
                    <form method="POST" action="{{ route('backend.team-selection.deadlines.extend', [$event, $activeImport]) }}" class="row g-2 align-items-end mt-2">@csrf @method('PATCH')
                      <div class="col-md-3"><label class="form-label">Response deadline</label><input type="datetime-local" name="response_deadline" value="{{ $activeImport->response_deadline?->format('Y-m-d\\TH:i') }}" class="form-control" required></div>
                      <div class="col-md-3"><label class="form-label">Payment deadline</label><input type="datetime-local" name="payment_deadline" value="{{ $activeImport->payment_deadline?->format('Y-m-d\\TH:i') }}" class="form-control" required></div>
                      <div class="col-md-3"><label class="form-label">Last reserve promotion</label><input type="datetime-local" name="replacement_payment_deadline" value="{{ ($activeImport->replacement_payment_deadline ?: $activeImport->payment_deadline)?->format('Y-m-d\\TH:i') }}" class="form-control" required><div class="form-text">A promoted reserve may receive their own later deadline, capped before the event.</div></div>
                      <div class="col-md-3 d-grid"><button class="btn btn-outline-primary">Extend deadlines</button></div>
                    </form>
                  @else
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3"><button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#prepare-invitations-{{ $activeImport->id }}"><i class="ti ti-mail-cog me-1"></i>Prepare invitations</button><span class="text-muted small">Review the message, deadlines and exact recipients before sending.</span></div>
                    <form method="POST" action="{{ route('backend.team-selection.restart', [$event, $activeImport]) }}" class="mt-3" onsubmit="return confirm('Remove this unsent import and clear its generated roster places?');">@csrf<button class="btn btn-sm btn-outline-danger">Restart draft import</button></form>
                  @endif
                @endif
              </div>

              <div class="tab-pane fade region-task-panel" id="region-{{ $eventRegion->id }}-setup" role="tabpanel" aria-labelledby="region-{{ $eventRegion->id }}-setup-tab" tabindex="0">
                <div class="mb-3"><h6 class="mb-1">Ranking and team setup</h6><p class="text-muted small mb-0">Link the regional ranking source and create its event categories and teams.</p></div>
            @if(!$source)
              <form method="POST" action="{{ route('backend.team-selection.link', [$event, $eventRegion]) }}" class="row g-2 align-items-end">@csrf
                <div class="col-lg-7"><label class="form-label">Ranking series</label><select name="series_id" class="form-select" required><option value="">Choose {{ $event->start_date?->format('Y') }} series…</option>@foreach($series as $item)<option value="{{ $item->id }}">{{ $item->name }}{{ $readySeriesIds->contains($item->id) ? ' · published and ready' : ' · not published' }}</option>@endforeach</select></div>
                <div class="col-sm-5 col-lg-2"><label class="form-label">Reserves per team</label><input type="number" name="reserve_count" min="0" max="20" value="2" class="form-control" required></div>
                <div class="col-sm-7 col-lg-3 d-grid"><button class="btn btn-primary">Link ranking series</button></div>
              </form>
              <div class="form-text">Choose the regional series that will supply the official player rankings.</div>
            @else
              <div class="border rounded p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div><small class="text-muted d-block">Linked ranking series</small><strong>{{ $source->series?->name }}</strong><div class="small text-muted">{{ $source->reserve_count }} reserves per team</div></div>
                <span class="badge bg-label-{{ $sourceReady ? 'success' : 'warning' }}">{{ $sourceReady ? 'Published and ready' : 'Ranking not published' }}</span>
              </div>
              @if(!$sourceReady)
                <div class="alert alert-warning mb-0" role="alert">
                  <div class="d-flex gap-3 align-items-start"><i class="ti ti-alert-triangle fs-3 mt-1"></i><div class="flex-grow-1"><h6 class="alert-heading mb-1">Team setup is waiting for a published ranking</h6><p class="mb-2">The linked series does not have a current published ranking. Categories, teams and player imports are intentionally unavailable so this event cannot be built from draft or unreviewed positions.</p><div class="small mb-3"><strong>Next step:</strong> open the ranking, resolve any audit or tie issues, mark it reviewed, and publish it. Then return here to create the event teams.</div>@if($isEventManager)<a class="btn btn-warning" href="{{ route('ranking.series.list', $source->series) }}"><i class="ti ti-trophy me-1"></i>Open ranking workflow</a>@else<span class="fw-semibold">Ask the event administrator to publish this ranking.</span>@endif</div></div>
                </div>
              @elseif(!$activeImport)
                <div class="alert alert-success"><strong>Ranking ready.</strong> The ranking is published. Create the event teams from its categories, then review the player import.</div>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ranking-category-setup-{{ $source->id }}"><i class="ti ti-category-plus me-1"></i>{{ $regionTeams->isEmpty() ? 'Create categories & teams' : 'Review categories & teams' }}</button>
                  @if($regionTeams->isNotEmpty())<a class="btn btn-outline-primary" href="{{ route('backend.team-selection.preview', [$event, $source]) }}"><i class="ti ti-download me-1"></i>Review ranked-player import</a>@endif
                </div>
              @else
                <div class="alert alert-success mb-0"><strong>Setup complete.</strong> The published ranking snapshot has already been imported. Continue in Invitations or Teams &amp; players.</div>
              @endif
            @endif
              </div>

              <div class="tab-pane fade region-task-panel" id="region-{{ $eventRegion->id }}-messages" role="tabpanel" aria-labelledby="region-{{ $eventRegion->id }}-messages-tab" tabindex="0">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
              <div><h6 class="mb-1">Messages &amp; clothing</h6><p class="text-muted small mb-0">Contact selected players and manage the regional clothing workflow.</p></div>
              <div class="d-flex flex-wrap gap-2">
                @if($isEventManager)
                  <button class="btn btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#final-team-reminders-{{ $eventRegion->id }}" data-reminder-open-kind="registration_clothing"><i class="ti ti-user-exclamation me-1"></i>Registration reminder</button>
                  <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#final-team-reminders-{{ $eventRegion->id }}" data-reminder-open-kind="incomplete_clothing"><i class="ti ti-shirt me-1"></i>Incomplete clothing reminder</button>
                @endif
                @if($eventRegion->region?->usesOnlineClothingOrders())
                  <a href="{{ route('backend.region.clothing.edit', ['region' => $eventRegion->region_id, 'event_id' => $event->id]) }}" class="btn btn-outline-primary"><i class="ti ti-shirt me-1"></i>Clothing setup</a>
                @endif
              </div>
            </div>
            <h6>Regional announcements</h6>
            <p class="text-muted small">Visible only to this region’s invited players. Email, when selected, is sent only to active selected players in this region.</p>
            @foreach($eventRegion->announcements as $announcement)
              @php($announcementLogs = $announcement->emailLogs)
              <div class="border rounded p-2 mb-2"><div class="d-flex justify-content-between gap-2"><strong>{{ $announcement->title }}</strong><form method="POST" action="{{ route('backend.team-selection.announcements.destroy', [$event, $eventRegion, $announcement]) }}" onsubmit="return confirm('Hide this announcement from the regional portal? Previously sent email cannot be recalled.');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Hide</button></form></div><div class="small mt-1">{!! $announcement->message !!}</div><div class="d-flex flex-wrap gap-2 align-items-center text-muted small mt-1"><span>{{ $announcement->created_at->format('d M Y H:i') }}{{ $announcement->emailed_at ? ' · email queued' : ' · portal only' }}</span>@if($announcementLogs->isNotEmpty())<span class="badge bg-label-secondary">Queued {{ $announcementLogs->where('status','queued')->count() }}</span><span class="badge bg-label-success">Sent {{ $announcementLogs->where('status','sent')->count() }}</span><span class="badge bg-label-danger">Failed {{ $announcementLogs->where('status','failed')->count() }}</span>@if($announcementLogs->where('status','failed')->isNotEmpty())<form method="POST" action="{{ route('backend.team-selection.announcements.retry', [$event, $eventRegion, $announcement]) }}">@csrf<button class="btn btn-sm btn-outline-danger">Retry current recipients</button></form>@endif @endif</div></div>
            @endforeach
            <form method="POST" action="{{ route('backend.team-selection.announcements.store', [$event, $eventRegion]) }}" class="row g-2">@csrf
              <div class="col-md-4"><label class="form-label">Title</label><input name="title" class="form-control" maxlength="255" required></div>
              <div class="col-md-8"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="2" maxlength="20000" required></textarea></div>
              <input type="hidden" name="recipient_hash" value="{{ hash('sha256', $regionAnnouncementRecipients->toJson()) }}">
              <div class="col-12"><details><summary>Review {{ $regionAnnouncementRecipients->count() }} exact email recipient(s)</summary><div class="small text-muted mt-1">@forelse($regionAnnouncementRecipients as $email)<div>{{ $email }}</div>@empty No active selected players currently have a valid email address. @endforelse</div></details></div>
              <div class="col-12 d-flex flex-wrap gap-3 align-items-center"><div><div class="form-check"><input type="hidden" name="send_email" value="0"><input class="form-check-input" type="checkbox" name="send_email" value="1" id="send-region-announcement-{{ $eventRegion->id }}" @disabled($regionAnnouncementRecipients->isEmpty())><label class="form-check-label" for="send-region-announcement-{{ $eventRegion->id }}">Also email these {{ $regionAnnouncementRecipients->count() }} recipient(s)</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_recipients" value="1" id="confirm-region-announcement-{{ $eventRegion->id }}"><label class="form-check-label" for="confirm-region-announcement-{{ $eventRegion->id }}">I reviewed and confirm this exact recipient list</label></div></div><button class="btn btn-outline-primary ms-auto">Publish regional announcement</button></div>
            </form>
              </div>
            </div>
          </div>
        </div>
      </div>

      @if($activeImport?->status === 'draft')
        <div class="modal fade" id="prepare-invitations-{{ $activeImport->id }}" tabindex="-1" aria-labelledby="prepare-invitations-title-{{ $activeImport->id }}" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable"><form method="POST" action="{{ route('backend.team-selection.send', [$event, $activeImport]) }}" class="modal-content">@csrf
            <input type="hidden" name="selection_import_id" value="{{ $activeImport->id }}">
            <div class="modal-header"><div><h5 class="modal-title" id="prepare-invitations-title-{{ $activeImport->id }}">Prepare regional invitations</h5><div class="text-muted small">{{ $eventRegion->region?->region_name }} · {{ $activeImport->invitations->where('status','invited')->count() }} selected recipients</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <div class="alert alert-info"><strong>Preview required.</strong> Open the actual sample email before sending. The exact message, event details, deadlines and clothing prices are snapshotted for audit and failed-email retries. Any change requires another preview.</div>
              <div class="row g-3">
                <div class="col-12"><label class="form-label">Email subject</label><input type="text" name="email_subject" maxlength="180" class="form-control" value="{{ old('email_subject', 'Platteland team invitation: '.$event->name) }}" required></div>
                <div class="col-12"><label class="form-label">Invitation message</label><textarea name="email_message" rows="4" maxlength="10000" class="form-control" required>{{ old('email_message', 'You have been selected to represent your region. Please review the event information and respond before the deadline.') }}</textarea><div class="form-text">This message appears near the top of every invitation.</div></div>
                <div class="col-12"><label class="form-label">Information shown on the player invitation page</label><textarea name="event_information" rows="7" maxlength="20000" class="form-control">{{ old('event_information', $defaultInvitationEventInformation) }}</textarea><div class="form-text">HTML from the event page is converted into readable paragraphs and bullet points. Review venues, arrival times, accommodation and team instructions before previewing the email.</div></div>
                <div class="col-md-4"><label class="form-label">Response deadline</label><input type="datetime-local" name="response_deadline" value="{{ old('response_deadline') }}" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Payment deadline</label><input type="datetime-local" name="payment_deadline" value="{{ old('payment_deadline') }}" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Replacement payment deadline</label><input type="datetime-local" name="replacement_payment_deadline" value="{{ old('replacement_payment_deadline') }}" class="form-control" required><div class="form-text">Final payment cutoff for a promoted reserve.</div></div>
                <div class="col-md-4"><label class="form-label">Reply-to email</label><input type="email" name="reply_to" value="{{ old('reply_to', $event->email) }}" class="form-control" maxlength="255"><div class="form-text">Optional contact for player replies.</div></div>
                <div class="col-12"><input type="hidden" name="include_clothing" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="include_clothing" value="1" id="include-clothing-{{ $activeImport->id }}" @checked(old('include_clothing', $clothingAvailable)) @disabled(!$clothingAvailable)><label class="form-check-label" for="include-clothing-{{ $activeImport->id }}">Include optional regional clothing items, sizes, prices and ordering steps</label></div>@if(!$clothingAvailable)<div class="form-text text-warning">Complete this region's clothing items, sizes and approved prices, then open clothing ordering to enable this option.</div>@endif</div>
              </div>
              <hr><div class="row g-2"><div class="col-sm-4"><div class="border rounded p-3"><small class="text-muted d-block">Invitations</small><strong>{{ $activeImport->invitations->where('status','invited')->count() }}</strong></div></div><div class="col-sm-4"><div class="border rounded p-3"><small class="text-muted d-block">Reserves held back</small><strong>{{ $activeImport->invitations->where('status','reserve')->count() }}</strong></div></div><div class="col-sm-4"><div class="border rounded p-3"><small class="text-muted d-block">Missing email</small><strong>{{ $activeImport->invitations->filter(fn($i) => !$recipientEmailFor($i))->count() }}</strong></div></div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-outline-primary" formaction="{{ route('backend.team-selection.email.preview', [$event, $activeImport]) }}" formtarget="_blank">Preview actual email</button><button type="submit" class="btn btn-success" onclick="return confirm('Queue these invitations for the selected players in this region?');">Confirm and send {{ $activeImport->invitations->where('status','invited')->count() }} invitations</button></div>
          </form></div>
        </div>
      @endif

      <div class="modal fade" id="roster-email-{{ $eventRegion->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered"><form method="POST" action="{{ route('backend.team-selection.roster-email.send', [$event, $eventRegion]) }}" class="modal-content">@csrf
          <input type="hidden" name="target_type" value="team" data-roster-email-target>
          <input type="hidden" name="team_id" data-roster-email-team>
          <input type="hidden" name="invitation_id" data-roster-email-invitation>
          <input type="hidden" name="recipient_hash" data-roster-email-hash>
          <div class="modal-header"><div><h5 class="modal-title">Email selected roster</h5><div class="small text-muted" data-roster-email-recipient></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="mb-3"><label class="form-label">Subject</label><input class="form-control" name="subject" maxlength="180" required></div>
            <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="7" maxlength="20000" required></textarea></div>
            <details class="mb-3 d-none" data-roster-region-review><summary>Review all {{ $regionRosterEmailRecipients->count() }} exact regional recipient(s)</summary><div class="small text-muted mt-2">@foreach($regionRosterEmailRecipients as $recipient)<div>{{ $recipient['name'] ?: 'Player' }} · {{ $recipient['email'] }}</div>@endforeach</div></details>
            @foreach(['unlinked_imported' => $unlinkedImportedRecipients, 'linked_unpaid' => $linkedUnpaidRecipients, 'linked_all' => $allLinkedImportedRecipients] as $cohortKey => $cohortRecipients)
              <details class="mb-3 d-none" data-roster-cohort-review="{{ $cohortKey }}"><summary>Review all {{ $cohortRecipients->count() }} exact recipient(s)</summary><div class="small text-muted mt-2">@foreach($cohortRecipients as $recipient)<div>{{ $recipient['name'] ?: 'Player' }} · {{ $recipient['email'] }}</div>@endforeach</div></details>
            @endforeach
            <div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_recipients" value="1" id="confirm-roster-email-{{ $eventRegion->id }}" required><label class="form-check-label" for="confirm-roster-email-{{ $eventRegion->id }}">I confirm the recipient details above are correct</label></div>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="ti ti-send me-1"></i>Queue email</button></div>
        </form></div>
      </div>

      @if($isEventManager)
        <div class="modal fade" id="reimport-roster-{{ $eventRegion->id }}" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <form class="modal-content roster-contact-import-form" method="POST" enctype="multipart/form-data" action="{{ route('backend.team-selection.imported-contacts.enrich', [$event, $eventRegion]) }}">
              @csrf
              <input type="hidden" name="confirmed" value="0" data-import-confirmed>
              <input type="hidden" name="preview_fingerprint" value="" data-import-fingerprint>
              <input type="hidden" name="expected_players" value="{{ max(1, (int) ($regionTeams->first()?->num_team_members ?: 8)) }}">
              <input type="hidden" name="team_prefix" value="{{ $eventRegion->region?->short_name ?: $eventRegion->region?->region_name }}">
              <div class="modal-header"><div><h5 class="modal-title">Re-import roster names and contacts</h5><div class="small text-muted">{{ $eventRegion->region?->region_name }}</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
              <div class="modal-body">
                <div class="alert alert-info">Upload the original workbook. This action can only add an email to a blank imported roster email field after one unique name match. It cannot create, delete, rename or reorder players, and cannot change links, DOBs, phones or payment state.</div>
                <label class="form-label">Excel workbook</label><input class="form-control" type="file" name="file" accept=".xls,.xlsx,.csv" required data-import-file>
                <div class="alert alert-danger d-none mt-3" data-import-errors></div>
                <div class="table-responsive d-none mt-3" data-import-preview><table class="table table-sm align-middle"><thead><tr><th>Existing player</th><th>Email to add</th></tr></thead><tbody></tbody></table></div>
                <div class="alert alert-warning d-none mt-3" data-import-anomalies><strong>Review required — these rows will be skipped.</strong><ul class="mb-2 mt-1"></ul><div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_anomalies" value="1" id="confirm-import-anomalies-{{ $eventRegion->id }}"><label class="form-check-label" for="confirm-import-anomalies-{{ $eventRegion->id }}">I have reviewed these exceptions and understand they will not be changed</label></div></div>
              </div>
              <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" data-import-submit>Preview workbook</button></div>
            </form>
          </div>
        </div>
      @endif

      @if($sourceReady && $source && !$activeImport && $categorySetup)
        @php($setupRows = $categorySetup['rows'])
        @php($missingSetupRows = $setupRows->reject(fn($row) => $row['ready']))
        <div class="modal fade" id="ranking-category-setup-{{ $source->id }}" tabindex="-1" aria-labelledby="ranking-category-setup-title-{{ $source->id }}" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title" id="ranking-category-setup-title-{{ $source->id }}">Create teams from ranking categories</h5>
                  <div class="text-muted small">{{ $eventRegion->region?->region_name }} · {{ $source->series?->name }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="alert alert-info">
                  Select the categories this region will enter. Existing event teams are preserved; only missing categories, team links and empty roster places are created.
                </div>
                @if($setupRows->isEmpty())
                  <div class="alert alert-warning mb-0">This series has no ranking categories yet. Add its ranking lists first.</div>
                @else
                  <form id="ranking-category-form-{{ $source->id }}" method="POST" action="{{ route('backend.team-selection.teams.create', [$event, $source]) }}">
                    @csrf
                    <input type="hidden" name="setup_source" value="{{ $source->id }}">
                    <div class="row g-2 align-items-end mb-3">
                      <div class="col-sm-6 col-md-4">
                        <label class="form-label" for="default-team-size-{{ $source->id }}">Default team size</label>
                        <input type="number" class="form-control default-team-size" id="default-team-size-{{ $source->id }}" value="8" min="1" max="50" inputmode="numeric">
                        <div class="form-text">Selected players per team, excluding reserves.</div>
                      </div>
                      <div class="col-sm-6 col-md-4 d-grid">
                        <button type="button" class="btn btn-outline-primary apply-default-team-size">Apply to checked teams</button>
                      </div>
                    </div>
                    <div class="table-responsive">
                      <table class="table align-middle mb-0">
                        <thead><tr><th style="width:48px">Use</th><th>Ranking category</th><th>Ranked</th><th>Event status</th><th>Team name</th><th style="width:140px">Team size</th></tr></thead>
                        <tbody>
                          @foreach($setupRows as $rowIndex => $row)
                            @php($ready = $row['ready'])
                            <tr>
                              <td>
                                @if($ready)
                                  <i class="ti ti-circle-check text-success" aria-label="Ready"></i>
                                @else
                                  <input type="hidden" name="categories[{{ $rowIndex }}][selected]" value="0">
                                  <input class="form-check-input" type="checkbox" name="categories[{{ $rowIndex }}][selected]" value="1" checked aria-label="Create {{ $row['category_name'] }} team">
                                @endif
                              </td>
                              <td><strong>{{ $row['category_name'] }}</strong></td>
                              <td>{{ $categorySetup['published_ready'] ? $row['ranked_count'] : 'Pending publication' }}</td>
                              <td>
                                @if($ready)
                                  <span class="badge bg-label-success">Team ready</span>
                                @elseif($row['team'])
                                  <span class="badge bg-label-info">Existing team will be linked</span>
                                @elseif($row['event_category'])
                                  <span class="badge bg-label-warning">Category exists · team missing</span>
                                @else
                                  <span class="badge bg-label-secondary">New category &amp; team</span>
                                @endif
                              </td>
                              <td>
                                @if($ready)
                                  {{ $row['team']->name }}
                                @else
                                  <input type="hidden" name="categories[{{ $rowIndex }}][ranking_list_id]" value="{{ $row['ranking_list_id'] }}">
                                  <input type="text" class="form-control" name="categories[{{ $rowIndex }}][team_name]" value="{{ old("categories.$rowIndex.team_name", $row['team']?->name ?: $row['suggested_team_name']) }}" required maxlength="255">
                                @endif
                              </td>
                              <td>
                                @if($ready)
                                  {{ $row['team']->num_team_members }}
                                @else
                                  <input type="number" class="form-control team-size-input" name="categories[{{ $rowIndex }}][num_players]" value="{{ old("categories.$rowIndex.num_players", $row['team']?->num_team_members ?: 8) }}" min="1" max="50" inputmode="numeric" aria-label="Team size for {{ $row['category_name'] }}" required>
                                @endif
                              </td>
                            </tr>
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                  </form>
                @endif
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                @if($missingSetupRows->isNotEmpty())
                  <button type="submit" form="ranking-category-form-{{ $source->id }}" class="btn btn-primary">Create selected teams</button>
                @elseif($categorySetup['published_ready'])
                  <a class="btn btn-primary" href="{{ route('backend.team-selection.preview', [$event, $source]) }}">Continue to ranked-player preview</a>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endif
    @endforeach
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[id^="final-team-reminders-"]').forEach(function (modal) {
    document.body.appendChild(modal);
  });
  const workspaceStateKey = 'team-selection-workspace-{{ $event->id }}';
  const openWorkspace = function (regionId, taskKey, updateLocation = true) {
    const regionTab = document.querySelector(`[data-bs-target="#region-panel-${regionId}"]`);
    const taskTab = document.querySelector(`#region-${regionId}-${taskKey}-tab`);
    if (!taskTab || !window.bootstrap?.Tab) return;
    if (regionTab) bootstrap.Tab.getOrCreateInstance(regionTab).show();
    bootstrap.Tab.getOrCreateInstance(taskTab).show();
    const state = `${regionId}/${taskKey}`;
    sessionStorage.setItem(workspaceStateKey, state);
    if (updateLocation) history.replaceState(null, '', `${window.location.pathname}${window.location.search}#region-${state}`);
  };
  const requestedWorkspace = window.location.hash.match(/^#region-(\d+)\/(overview|teams|invitations|messages|setup)$/)?.slice(1).join('/')
    || sessionStorage.getItem(workspaceStateKey);
  if (requestedWorkspace) {
    const [requestedRegion, requestedTask] = requestedWorkspace.replace(/^region-/, '').split('/');
    openWorkspace(requestedRegion, requestedTask, false);
  }
  document.querySelectorAll('[data-region-task]').forEach(function (taskTab) {
    taskTab.addEventListener('shown.bs.tab', function () {
      const regionId = taskTab.closest('[data-region-task-tabs]')?.dataset.regionTaskTabs;
      if (regionId) openWorkspace(regionId, taskTab.dataset.regionTask);
    });
  });
  document.querySelectorAll('[data-region-tabs] [data-region-id]').forEach(function (regionTab) {
    regionTab.addEventListener('shown.bs.tab', function () {
      const regionId = regionTab.dataset.regionId;
      const activeTask = document.querySelector(`#region-panel-${regionId} [data-region-task].active`)?.dataset.regionTask || 'overview';
      const state = `${regionId}/${activeTask}`;
      sessionStorage.setItem(workspaceStateKey, state);
      history.replaceState(null, '', `${window.location.pathname}${window.location.search}#region-${state}`);
    });
  });
  document.querySelectorAll('[data-open-region-task]').forEach(function (button) {
    button.addEventListener('click', function () {
      const regionId = button.closest('[data-region-task-tabs]')?.dataset.regionTaskTabs
        || button.closest('.region-workspace-card')?.querySelector('[data-region-task-tabs]')?.dataset.regionTaskTabs;
      if (regionId) openWorkspace(regionId, button.dataset.openRegionTask);
    });
  });
  document.querySelectorAll('[data-final-reminder-form]').forEach(function (reminderForm) {
    const summaries = JSON.parse(reminderForm.dataset.reminderSummaries || '{}');
    const hashes = JSON.parse(reminderForm.dataset.reminderHashes || '{}');
    const kind = reminderForm.querySelector('[data-reminder-kind]');
    const audience = reminderForm.querySelector('[data-reminder-audience]');
    const refreshReminder = function () {
      const summary = summaries[kind.value]?.[audience.value] || { emails: 0, players: 0 };
      reminderForm.querySelector('[data-reminder-summary]').textContent = `${summary.emails} email(s) will cover ${summary.players} player(s).`;
      reminderForm.querySelector('[data-reminder-hash]').value = hashes[kind.value]?.[audience.value] || '';
      const incomplete = kind.value === 'incomplete_clothing';
      reminderForm.querySelector('[data-reminder-preview-title]').textContent = incomplete ? 'Clothing ordering is closing' : 'Registration is closing';
      reminderForm.querySelector('[data-reminder-preview-copy]').textContent = incomplete
        ? 'Registered players without a completed clothing decision are reminded to order, finish payment, or confirm that no clothing is required. Unregistered recipients are told to register first.'
        : 'Unregistered players receive their registration/payment link. Registered players receive their clothing action link.';
      reminderForm.querySelector('[data-reminder-submit]').disabled = summary.emails === 0;
    };
    kind.addEventListener('change', refreshReminder);
    audience.addEventListener('change', refreshReminder);
    reminderForm.closest('.modal')?.addEventListener('show.bs.modal', function (event) {
      const selectedKind = event.relatedTarget?.dataset?.reminderOpenKind;
      if (selectedKind) kind.value = selectedKind;
      refreshReminder();
    });
    refreshReminder();
  });
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const ajaxHeaders = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': csrfToken,
  };
  const renderTeamPublication = function (button, published) {
    const card = button.closest('.regional-team-card');
    const badge = card?.querySelector('[data-team-publication-status]');
    const settingsCheckbox = card?.querySelector('input[name="published"][type="checkbox"]');
    button.dataset.published = published ? '1' : '0';
    if (button.classList.contains('btn')) {
      button.classList.toggle('btn-outline-danger', published);
      button.classList.toggle('btn-outline-success', !published);
    }
    button.querySelector('span').textContent = published ? 'Unpublish' : 'Publish';
    button.querySelector('i').className = `ti ti-${published ? 'world-off' : 'world-upload'} me-1`;
    if (badge) {
      badge.textContent = published ? 'Published' : 'Not published';
      badge.classList.toggle('bg-label-success', published);
      badge.classList.toggle('bg-label-secondary', !published);
    }
    if (settingsCheckbox) settingsCheckbox.checked = published;
  };

  document.querySelectorAll('.team-publication-button').forEach(function (button) {
    button.addEventListener('click', async function () {
      const published = button.dataset.published !== '1';
      button.disabled = true;
      try {
        const response = await fetch(button.dataset.url, {
          method: 'PATCH', headers: ajaxHeaders, body: JSON.stringify({ published }),
        });
        if (!response.ok) throw await AppFeedback.responseError(response, 'The team publication could not be changed.');
        const data = await response.json();
        renderTeamPublication(button, data.published);
        AppFeedback.success(data.message);
      } catch (error) {
        AppFeedback.fromError(error, 'The team publication could not be changed.');
      } finally {
        button.disabled = false;
      }
    });
  });

  document.querySelectorAll('.publish-all-teams').forEach(function (button) {
    button.addEventListener('click', async function () {
      button.disabled = true;
      try {
        const response = await fetch(button.dataset.url, { method: 'POST', headers: ajaxHeaders, body: '{}' });
        if (!response.ok) throw await AppFeedback.responseError(response, 'The teams could not be published.');
        const data = await response.json();
        const teamIds = new Set((data.team_ids || []).map(String));
        button.closest('.region-workspace-card')?.querySelectorAll('.team-publication-button').forEach(function (teamButton) {
          if (teamIds.has(teamButton.dataset.teamId)) renderTeamPublication(teamButton, true);
        });
        AppFeedback.success(data.message);
      } catch (error) {
        AppFeedback.fromError(error, 'The teams could not be published.');
      } finally {
        button.disabled = false;
      }
    });
  });

  document.querySelectorAll('.clothing-order-form').forEach(function (form) {
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const button = form.querySelector('.clothing-order-toggle');
      const status = form.parentElement.querySelector('[data-clothing-status]');
      button.disabled = true;
      try {
        const response = await fetch(form.action, {
          method: 'PATCH',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: new FormData(form),
        });
        if (!response.ok) throw await AppFeedback.responseError(response, 'Clothing ordering could not be changed.');
        const data = await response.json();
        const open = Boolean(data.state);
        status.textContent = open ? 'Clothing ordering open' : 'Clothing ordering closed';
        if (status.classList.contains('badge')) {
          status.classList.toggle('bg-label-success', open);
          status.classList.toggle('bg-label-secondary', !open);
        }
        if (button.classList.contains('btn')) {
          button.classList.toggle('btn-danger', open);
          button.classList.toggle('btn-success', !open);
          button.classList.remove('btn-warning');
        }
        button.innerHTML = `<i class="ti ti-${open ? 'lock' : 'shopping-cart'} me-2"></i>${open ? 'Close ordering' : 'Open ordering'}`;
        AppFeedback.success(data.message);
      } catch (error) {
        AppFeedback.fromError(error, 'Clothing ordering could not be changed.');
      } finally {
        button.disabled = !Boolean(Number(button.dataset.catalogueReady)) && button.textContent.includes('Open');
      }
    });
  });

  document.querySelectorAll('[id^="team-workspace-"]').forEach(function (workspace) {
    const toggle = document.querySelector(`[data-bs-target="#${workspace.id}"]`);
    const header = document.querySelector(`[data-team-workspace-target="#${workspace.id}"]`);
    const label = toggle?.querySelector('span');
    const icon = toggle?.querySelector('i');
    header?.addEventListener('click', function (event) {
      if (event.target.closest('a, button, input, select, textarea, summary, details, form, label')) return;
      toggle?.click();
    });
    workspace.addEventListener('shown.bs.collapse', function () {
      if (label) label.textContent = 'Hide team';
      icon?.classList.replace('ti-eye', 'ti-eye-off');
    });
    workspace.addEventListener('hidden.bs.collapse', function () {
      if (label) label.textContent = 'Show team';
      icon?.classList.replace('ti-eye-off', 'ti-eye');
    });
  });

  document.querySelectorAll('[id^="team-settings-"]').forEach(function (settings) {
    const toggle = document.querySelector(`[data-bs-target="#${settings.id}"]`);
    const label = toggle?.querySelector('span');
    settings.addEventListener('shown.bs.collapse', function () {
      if (label) label.textContent = 'Close settings';
    });
    settings.addEventListener('hidden.bs.collapse', function () {
      if (label) label.textContent = 'Team settings';
    });
  });

  document.querySelectorAll('.imported-name-form').forEach(function (form) {
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const button = form.querySelector('button[type="submit"], button:not([type])');
      button?.setAttribute('disabled', 'disabled');
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: new FormData(form),
        });
        if (!response.ok) throw await AppFeedback.responseError(response, 'The player name could not be saved.');
        const data = await response.json();
        const orderName = document.querySelector(`.imported-roster-sortable [data-slot-id="${form.dataset.slotId}"] [data-imported-player-name]`);
        if (orderName) orderName.textContent = `${data.player.name} ${data.player.surname}`.trim();
        AppFeedback.success(data.message);
      } catch (error) {
        AppFeedback.fromError(error, 'The player name could not be saved.');
      } finally {
        button?.removeAttribute('disabled');
      }
    });
  });

  document.querySelectorAll('.imported-email-form').forEach(function (form) {
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      const button = form.querySelector('button');
      button.disabled = true;
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: new FormData(form),
        });
        if (!response.ok) throw await AppFeedback.responseError(response, 'The email could not be saved.');
        const data = await response.json();
        form.querySelector('input[name="email"]').value = data.imported_email;
        const row = form.closest('tr[data-slot-id]');
        row.querySelector('[data-effective-email]').textContent = data.effective_email || 'No email';
        const source = row.querySelector('[data-effective-email-source]');
        source.textContent = data.effective_email_source;
        source.classList.toggle('bg-label-success', data.effective_email_source === 'Linked profile email');
        source.classList.toggle('bg-label-info', data.effective_email_source !== 'Linked profile email');
        AppFeedback.success(data.message);
      } catch (error) {
        AppFeedback.fromError(error, 'The email could not be saved.');
      } finally {
        button.disabled = false;
      }
    });
  });

  document.querySelectorAll('.imported-roster-sortable').forEach(function (tbody) {
    let dragged = null;
    tbody.addEventListener('dragstart', function (event) {
      dragged = event.target.closest('tr[data-slot-id]');
      if (!dragged) return;
      dragged.classList.add('is-dragging');
      event.dataTransfer.effectAllowed = 'move';
    });
    tbody.addEventListener('dragover', function (event) {
      if (!dragged) return;
      event.preventDefault();
      const target = event.target.closest('tr[data-slot-id]');
      if (!target || target === dragged) return;
      const below = event.clientY > target.getBoundingClientRect().top + target.offsetHeight / 2;
      tbody.insertBefore(dragged, below ? target.nextSibling : target);
    });
    tbody.addEventListener('dragend', async function () {
      if (!dragged) return;
      dragged.classList.remove('is-dragging');
      dragged = null;
      const rows = Array.from(tbody.querySelectorAll('tr[data-slot-id]'));
      rows.forEach(function (row, index) { row.querySelector('.badge').textContent = `Rank ${index + 1}`; });
      try {
        const response = await fetch(tbody.dataset.reorderUrl, {
          method: 'PUT',
          headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
          },
          body: JSON.stringify({ slot_ids: rows.map(row => Number(row.dataset.slotId)) }),
        });
        if (!response.ok) throw await AppFeedback.responseError(response, 'The player order could not be saved.');
        const data = await response.json();
        const playersBody = tbody.closest('.tab-content')?.querySelector('.imported-roster-players');
        if (playersBody) {
          rows.forEach(function (row, index) {
            const playerRow = playersBody.querySelector(`tr[data-slot-id="${row.dataset.slotId}"]`);
            if (!playerRow) return;
            playerRow.querySelector('.badge').textContent = `Rank ${index + 1}`;
            playersBody.appendChild(playerRow);
          });
        }
        AppFeedback.success(data.message);
      } catch (error) {
        AppFeedback.fromError(error, 'The player order could not be saved. Refreshing the current roster.');
        window.setTimeout(() => window.location.reload(), 900);
      }
    });
  });

  document.querySelectorAll('.roster-contact-import-form').forEach(function (form) {
    const escape = value => { const node = document.createElement('div'); node.textContent = value ?? ''; return node.innerHTML; };
    const confirmed = form.querySelector('[data-import-confirmed]');
    const file = form.querySelector('[data-import-file]');
    const preview = form.querySelector('[data-import-preview]');
    const previewBody = preview.querySelector('tbody');
    const errors = form.querySelector('[data-import-errors]');
    const anomalies = form.querySelector('[data-import-anomalies]');
    const fingerprint = form.querySelector('[data-import-fingerprint]');
    const submit = form.querySelector('[data-import-submit]');
    const resetPreview = function () {
      confirmed.value = '0';
      preview.classList.add('d-none');
      previewBody.innerHTML = '';
      errors.classList.add('d-none');
      anomalies.classList.add('d-none');
      anomalies.querySelector('ul').innerHTML = '';
      anomalies.querySelector('input').checked = false;
      anomalies.querySelector('input').required = false;
      fingerprint.value = '';
      submit.textContent = 'Preview workbook';
    };
    file.addEventListener('change', resetPreview);
    form.addEventListener('submit', async function (event) {
      event.preventDefault();
      if (!form.reportValidity()) return;
      submit.disabled = true;
      errors.classList.add('d-none');
      try {
        const response = await fetch(form.action, {
          method: 'POST', headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form),
        });
        if (!response.ok) throw await AppFeedback.responseError(response, 'The workbook could not be processed.');
        const data = await response.json();
        if (data.requires_confirmation) {
          previewBody.innerHTML = (data.updates || []).map(row => `<tr><td>${escape(row.name)}</td><td>${escape(row.email)}</td></tr>`).join('')
            || '<tr><td colspan="2" class="text-center text-muted">No blank emails can be safely enriched.</td></tr>';
          preview.classList.remove('d-none');
          fingerprint.value = data.preview_fingerprint || '';
          if ((data.issues || []).length) {
            anomalies.querySelector('ul').innerHTML = data.issues.map(message => `<li>${escape(message)}</li>`).join('');
            anomalies.classList.remove('d-none');
            anomalies.querySelector('input').required = true;
          } else {
            anomalies.querySelector('input').required = false;
          }
          confirmed.value = '1';
          submit.textContent = `Add ${data.updates.length} missing email${data.updates.length === 1 ? '' : 's'}`;
          AppFeedback.info(`${data.updates.length} safe email update(s) found. Review before applying.`);
        } else {
          AppFeedback.success(data.message);
          bootstrap.Modal.getInstance(form.closest('.modal'))?.hide();
          window.setTimeout(() => window.location.reload(), 700);
        }
      } catch (error) {
        const messages = error?.messages?.length ? error.messages : [error?.message || 'The workbook could not be processed.'];
        errors.innerHTML = `<strong>Nothing was imported.</strong><ul class="mb-0 mt-1">${messages.map(message => `<li>${escape(message)}</li>`).join('')}</ul>`;
        errors.classList.remove('d-none');
        AppFeedback.fromError(error, 'The workbook could not be processed.');
      } finally {
        submit.disabled = false;
      }
    });
  });

  document.querySelectorAll('[id^="roster-email-"]').forEach(function (modal) {
    modal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) return;
      modal.querySelector('[data-roster-email-target]').value = button.dataset.targetType || 'team';
      modal.querySelector('[data-roster-email-team]').value = button.dataset.teamId || '';
      modal.querySelector('[data-roster-email-invitation]').value = button.dataset.invitationId || '';
      modal.querySelector('[data-roster-email-hash]').value = button.dataset.recipientHash || '';
      modal.querySelector('[data-roster-email-recipient]').textContent = button.dataset.recipient || '';
      modal.querySelector('[data-roster-region-review]')?.classList.toggle('d-none', button.dataset.targetType !== 'region');
      modal.querySelectorAll('[data-roster-cohort-review]').forEach(function (review) {
        review.classList.toggle('d-none', review.dataset.rosterCohortReview !== button.dataset.targetType);
      });
    });
  });

  const syncReplacementProfile = function (modeSelect) {
    const form = modeSelect.closest('[data-replacement-player-form]');
    const profileWrap = form?.querySelector('[data-custom-replacement-profile]');
    const profileSelect = form?.querySelector('.replacement-profile-select');
    if (!profileWrap || !profileSelect) return;
    const customProfile = modeSelect.value === 'custom_profile';
    profileWrap.classList.toggle('d-none', !customProfile);
    profileSelect.disabled = !customProfile;
    profileSelect.required = customProfile;
    if (!customProfile) profileSelect.value = '';
    if (window.jQuery?.fn?.select2 && window.jQuery(profileSelect).hasClass('select2-hidden-accessible')) {
      window.jQuery(profileSelect).trigger('change.select2');
    }
  };
  document.querySelectorAll('[data-replacement-mode]').forEach(function (modeSelect) {
    syncReplacementProfile(modeSelect);
    modeSelect.addEventListener('change', function () { syncReplacementProfile(modeSelect); });
  });

  if (window.jQuery?.fn?.select2) {
    window.jQuery('.region-manager-select').select2({
      width: '100%',
      placeholder: function () { return window.jQuery(this).data('placeholder'); },
      allowClear: true,
      minimumInputLength: 2,
      ajax: {
        url: @json(route('backend.team-selection.users.search', $event)),
        dataType: 'json',
        delay: 250,
        data: function (params) { return { q: params.term }; },
        processResults: function (data) { return data; },
        cache: true
      }
    });
    window.jQuery('.team-player-select').each(function () {
      const select = window.jQuery(this);
      select.select2({
        width: '100%',
        placeholder: select.data('placeholder'),
        minimumInputLength: 2,
        ajax: {
          url: select.data('search-url'),
          dataType: 'json',
          delay: 250,
          data: function (params) { return { q: params.term }; },
          processResults: function (data) { return data; },
          cache: true
        }
      });
    });
  }

  const addTeamId = @json(old('add_team_id'));
  if (addTeamId && typeof bootstrap !== 'undefined') {
    const workspace = document.getElementById(`team-workspace-${addTeamId}`);
    if (workspace) bootstrap.Collapse.getOrCreateInstance(workspace, { toggle: false }).show();
  }

  const settingsTeamId = @json(old('settings_team_id'));
  if (settingsTeamId && typeof bootstrap !== 'undefined') {
    const settings = document.getElementById(`team-settings-${settingsTeamId}`);
    if (settings) bootstrap.Collapse.getOrCreateInstance(settings, { toggle: false }).show();
  }

  const sourceId = @json(session('open_team_setup_source') ?: old('setup_source'));
  if (sourceId && typeof bootstrap !== 'undefined') {
    const modal = document.getElementById(`ranking-category-setup-${sourceId}`);
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  const invitationImportId = @json(old('selection_import_id'));
  if (invitationImportId && typeof bootstrap !== 'undefined') {
    const invitationModal = document.getElementById(`prepare-invitations-${invitationImportId}`);
    if (invitationModal) bootstrap.Modal.getOrCreateInstance(invitationModal).show();
  }

  document.querySelectorAll('[id^="ranking-category-form-"]').forEach(function (form) {
    const defaultSize = form.querySelector('.default-team-size');
    const applyDefault = form.querySelector('.apply-default-team-size');
    if (defaultSize && applyDefault) {
      applyDefault.addEventListener('click', function () {
        const value = defaultSize.value;
        if (!value) return;
        form.querySelectorAll('input[type="checkbox"][name$="[selected]"]:checked').forEach(function (checkbox) {
          const input = checkbox.closest('tr').querySelector('.team-size-input');
          if (input) input.value = value;
        });
      });
    }

    form.querySelectorAll('input[type="checkbox"][name$="[selected]"]').forEach(function (checkbox) {
      const row = checkbox.closest('tr');
      const toggleInputs = function () {
        row.querySelectorAll('input[name$="[team_name]"], input[name$="[num_players]"]').forEach(function (input) {
          input.disabled = !checkbox.checked;
        });
      };
      checkbox.addEventListener('change', toggleInputs);
      toggleInputs();
    });
  });
});
</script>
@endsection
