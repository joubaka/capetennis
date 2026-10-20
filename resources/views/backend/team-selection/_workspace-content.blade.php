<div class="operational-page" @if($embeddedTeamsWorkspace ?? false) data-embedded-teams-selection @endif>
@if(!($isRegionalPreview ?? false) && !($embeddedTeamsWorkspace ?? false))
@include('backend.partials.operational-controls')

<a class="btn btn-outline-primary mb-3" href="{{ route('backend.event-mail-log.index',$event) }}">Event email log</a>
@include('backend.event.partials.header', [
  'event' => $event,
  'eventWorkspaceActive' => 'entries',
  'eventWorkspaceRegionalOnly' => ! $isEventManager,
  'eventWorkspaceShowHome' => $isEventManager,
])
@endif
<div class="container-xxl flex-grow-1 container-p-y region-selection-container">
  @unless($embeddedTeamsWorkspace ?? false)
  @include('backend.event.partials.teams-workspace-nav', ['teamsWorkspaceRoster' => false])
  @endunless
  @if(session('replacement_confirmation'))
    @php($replacementConfirmation = session('replacement_confirmation'))
    @php($replacementDeliveryStatus = $replacementConfirmation['delivery_status'] ?? 'queued')
    <div class="alert alert-{{ $replacementDeliveryStatus === 'failed' ? 'danger' : 'success' }} d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <strong>Player replaced.</strong>
        {{ $replacementConfirmation['player_name'] }} was added and the invitation email was
        <strong>{{ $replacementDeliveryStatus === 'sent' ? 'sent' : $replacementDeliveryStatus }}</strong>
        to {{ $replacementConfirmation['recipient_email'] }}.
        @if($replacementDeliveryStatus === 'queued')
          <span class="d-block small mt-1">The application has queued it for the mail service. The player row below will show mail-server acceptance, an unverified result, or failure when processing finishes.</span>
        @elseif($replacementDeliveryStatus === 'failed')
          <span class="d-block small mt-1">Delivery failed. Review the saved email and use Resend after the mail issue is resolved.</span>
        @endif
      </div>
      <a class="btn btn-sm btn-outline-success" target="_blank" href="{{ $replacementConfirmation['preview_url'] }}">View email</a>
    </div>
  @elseif(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><strong>Action blocked.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  @if($isEventManager)
    <details class="regional-help mb-3"><summary>Event setup guidance</summary><div class="p-3 border-top">Link each ranking-fed region to its own published series. Imported outside-region rosters can remain unlinked and will not be changed.</div></details>
    @php($eventFilterTeams = $teams->flatten(1)->filter(fn($team) => $team->category_event_id)->unique('category_event_id')->sortBy(fn($team) => $team->category?->category?->name))
    @if($eventRegions->isNotEmpty() && $eventFilterTeams->isNotEmpty())
      <div class="d-flex justify-content-end mb-3" data-event-roster-email-action>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#event-roster-email"><i class="ti ti-mail-forward me-1"></i>Email selected players across regions</button>
      </div>
      <div class="modal fade" id="event-roster-email" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><form method="POST" action="{{ route('backend.team-selection.event-roster-email.send', $event) }}" class="modal-content" data-mail-compose data-event-roster-email-form>@csrf
          <input type="hidden" name="recipient_hash" data-event-roster-email-hash>
          <input type="hidden" name="send_token" data-event-roster-email-token>
          <div class="modal-header"><div><h5 class="modal-title">Email selected players</h5><div class="small text-muted">Choose regions, then teams, then the exact players. Nothing is queued until the final reviewed list is confirmed.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <style>
            #event-roster-email .event-audience-modal-body { overflow-x: hidden; }
            #event-roster-email .event-audience-min-width { min-width: 0; }
            #event-roster-email .event-audience-wrap { overflow-wrap: anywhere; word-break: break-word; white-space: normal; }
          </style>
          <div class="modal-body event-audience-modal-body">
            @include('backend.partials.email-sender-fields')
            <div class="border rounded p-3 mb-3" data-event-roster-filter-panel data-preview-url="{{ route('backend.team-selection.event-roster-email.preview', $event) }}">
              <div class="d-flex gap-2 mb-3" aria-label="Audience selection progress"><span class="badge bg-primary" data-event-roster-step-badge="1">1. Regions</span><span class="badge bg-label-secondary" data-event-roster-step-badge="2">2. Teams</span><span class="badge bg-label-secondary" data-event-roster-step-badge="3">3. Players</span></div>
              <section data-event-roster-step="1" tabindex="-1"><fieldset><legend class="form-label mb-2">Select one or more regions</legend><div class="row g-2">@foreach($eventRegions as $filterRegion)<div class="col-sm-6 col-lg-3"><label class="form-check border rounded p-2 h-100 event-audience-min-width"><input class="form-check-input ms-0 me-2 flex-shrink-0" type="checkbox" name="event_region_ids[]" value="{{ $filterRegion->id }}" data-event-roster-region><span class="form-check-label event-audience-wrap event-audience-min-width">{{ rawurldecode($filterRegion->region?->region_name ?? '') }}</span></label></div>@endforeach</div></fieldset><fieldset class="mt-3"><legend class="form-label mb-2">Choose which players to load</legend><div class="d-flex flex-wrap gap-3"><label class="form-check"><input class="form-check-input" type="radio" name="audience_mode" value="roster" checked data-event-roster-mode> Current roster players</label><label class="form-check"><input class="form-check-input" type="radio" name="audience_mode" value="ranking" data-event-roster-mode> Players on the current published ranking</label></div><div class="form-text">Published-ranking mode includes players beyond the team and reserve places.</div><div class="mt-3 d-none" data-event-ranking-status-wrap><label class="form-label" for="event-ranking-status">Which ranked players?</label><select class="form-select" id="event-ranking-status" name="ranking_status" data-event-ranking-status data-event-roster-filter><option value="all">All ranked players</option><option value="unregistered">Unregistered only</option><option value="declined">Declined only</option><option value="unregistered_or_declined">Unregistered or declined</option></select><div class="form-text">Registered means payment is confirmed. Invited players and players awaiting payment are treated as unregistered.</div></div></fieldset><button class="btn btn-primary mt-3" type="button" data-event-roster-load-teams>Show teams</button></section>
              <section class="d-none" data-event-roster-step="2" tabindex="-1"><div class="d-flex justify-content-between align-items-center gap-2"><div><h6 class="mb-0">Select teams</h6><div class="small text-muted">Teams are loaded only from the selected event regions.</div></div><label class="form-check mb-0"><input class="form-check-input" type="checkbox" data-event-roster-all-teams> Select all teams</label></div><div class="row g-2 mt-1" data-event-roster-teams></div><div class="d-flex gap-2 mt-3"><button class="btn btn-outline-secondary" type="button" data-event-roster-back="1">Back</button><button class="btn btn-primary" type="button" data-event-roster-show-players>Show players</button></div></section>
              <section class="d-none" data-event-roster-step="3" tabindex="-1"><div><h6 class="mb-0">Select players</h6><div class="small text-muted">Use each team's Select all option, then refine the exact list. Players without an email remain visible but cannot be selected.</div></div><div class="mt-3" data-event-roster-players></div><div class="row g-3 mt-1" data-event-roster-status-filters><div class="col-md-6"><label class="form-label">Gender</label><select class="form-select" name="gender" data-event-roster-filter required><option value="any">All genders</option><option value="girls">Girls</option><option value="boys">Boys</option></select></div><div class="col-md-6"><label class="form-label">Player status</label><select class="form-select" name="audience_status" data-event-roster-filter required><option value="active">All active roster players</option><option value="entered">Entered / paid</option><option value="not_entered">Selected but not entered / paid</option><option value="invited">Invitation sent</option><option value="not_invited">Not yet invited</option><option value="accepted">Accepted</option><option value="not_accepted">Invited but not accepted</option><option value="declined">Declined</option><option value="withdrawn">Withdrawn</option></select></div></div><div class="d-flex gap-2 mt-3"><button class="btn btn-outline-secondary" type="button" data-event-roster-back="2">Back</button><button class="btn btn-outline-primary" type="button" data-event-roster-preview><i class="ti ti-list-check me-1"></i>Preview exact recipients</button></div></section>
              <div class="alert alert-secondary mt-3 mb-0 d-none" role="status" aria-live="polite" data-event-roster-result></div>
              <div class="mt-3 d-none" data-event-roster-review><strong class="small">Exact recipients</strong><div class="small text-muted mt-1" data-event-roster-list></div></div>
            </div>
            <div class="mb-3"><label class="form-label">Subject</label><input class="form-control" name="subject" maxlength="180" required></div>
            <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="7" maxlength="20000" required></textarea></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_recipients" value="1" id="confirm-event-roster-email" required><label class="form-check-label" for="confirm-event-roster-email">I confirm the exact recipient details above are correct</label></div>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="ti ti-send me-1"></i>Preview email</button></div>
        </form></div>
      </div>
    @endif
  @else
    <div class="alert alert-info">You are viewing team selection, invitations and announcements for your assigned region.</div>
  @endif

  @if($eventRegions->count() > 1)
    <div class="event-region-selector mb-3" @if($embeddedTeamsWorkspace ?? false) hidden @endif>
      <label class="form-label" for="event-region">Region</label>
      <select class="form-select" id="event-region" data-region-select>
        @foreach($eventRegions as $eventRegion)
          <option value="{{ $eventRegion->id }}">{{ $eventRegion->region?->region_name }}</option>
        @endforeach
      </select>
    </div>
    <div class="nav nav-tabs d-none" role="tablist" aria-label="Event regions" data-region-tabs>
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

  <div class="region-workspaces {{ $eventRegions->count() > 1 ? 'tab-content' : 'row g-3' }}">
    @foreach($eventRegions as $eventRegion)
      @php($source = $eventRegion->rankingSource)
      @php($sourceReady = $source && $readySeriesIds->contains($source->series_id))
      @php($regionTeams = $teams->get($eventRegion->region_id, collect()))
      @php($categorySetup = $source ? $categorySetups->get($source->id) : null)
      @php($activeImport = $source?->imports?->whereIn('status', ['draft','sent'])->sortByDesc('id')->first())
      @php($contactEmailsFor = fn($invitation) => $teamSelectionContacts->emails($invitation->player))
      @php($rawContactEmailsFor = fn($invitation) => $teamSelectionContacts->rawEmails($invitation->player))
      @php($recipientEmailFor = fn($invitation) => $contactEmailsFor($invitation)->first())
      @php($usesRegionalClothing = (bool) $eventRegion->region?->usesOnlineClothingOrders())
      @php($regionClothingItems = $eventRegion->region?->clothingItems ?? collect())
      @php($clothingCatalogueReady = $regionClothingItems->isNotEmpty() && $regionClothingItems->every(fn($item) => (float)$item->price > 0 && $item->sizes->isNotEmpty()))
      @php($clothingAvailable = $usesRegionalClothing && (bool)$eventRegion->region?->clothing_order && $clothingCatalogueReady)
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
      @php($pendingActivatedInvitations = $activeImport?->invitations?->filter(fn($invitation) => $invitation->status === \App\Models\TeamSelectionInvitation::INVITED && $invitation->roster_rank && !$invitation->invited_at && data_get($invitation->snapshot_json, 'activation.pending_manual_invitation')) ?? collect())
      @php($pendingActivatedRecipients = $pendingActivatedInvitations->filter(fn($invitation) => (bool) $recipientEmailFor($invitation)))
      @php($customEmailRecipients = $activeImport?->invitations?->filter(fn($invitation) => in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, \App\Models\TeamSelectionInvitation::PAID_CONFIRMED], true) && $invitation->roster_rank && (bool) $recipientEmailFor($invitation)) ?? collect())
      @php($pendingActivatedRecipientHash = hash('sha256', $pendingActivatedRecipients->map(fn($invitation) => $invitation->id.'|'.mb_strtolower(trim((string) $recipientEmailFor($invitation))))->sort()->values()->implode("\n")))
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
            <div><h5 class="mb-1">{{ $eventRegion->region?->region_name }}</h5><span class="region-workspace-meta small">{{ $regionTeams->count() }} teams · {{ $regionTeams->sum('num_team_members') }} configured places</span></div>
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
              @foreach(['teams' => ['ti-users-group', $isEventManager ? 'Selection & reserves' : 'Players'], 'invitations' => ['ti-file-import', $isEventManager ? 'Import & send' : 'Invitations'], 'messages' => ['ti-message-circle', $usesRegionalClothing ? 'Messages & clothing' : 'Messages'], 'overview' => ['ti-layout-dashboard', 'Help & progress'], 'setup' => ['ti-settings', 'Ranking setup']] as $taskKey => [$taskIcon, $taskLabel])
                @if($taskKey === 'overview')<details class="regional-more-options"><summary>More options</summary><div class="regional-more-options-menu">@endif
                <button class="nav-link {{ $loop->first ? 'active' : '' }}" id="region-{{ $eventRegion->id }}-{{ $taskKey }}-tab" data-bs-toggle="tab" data-bs-target="#region-{{ $eventRegion->id }}-{{ $taskKey }}" type="button" role="tab" aria-controls="region-{{ $eventRegion->id }}-{{ $taskKey }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" data-region-task="{{ $taskKey }}"><i class="ti {{ $taskIcon }}"></i>{{ $taskLabel }}@if($taskKey === 'teams' && $unpublishedTeamCount)<span class="badge bg-label-warning">{{ $unpublishedTeamCount }}</span>@endif</button>
                @if($loop->last)</div></details>@endif
              @endforeach
            </div>
            <div class="tab-content">
              <div class="tab-pane fade region-task-panel" id="region-{{ $eventRegion->id }}-overview" role="tabpanel" aria-labelledby="region-{{ $eventRegion->id }}-overview-tab" tabindex="0">
                <div class="mb-3">
                  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h6 class="mb-1">Selection progress</h6><p class="text-muted small mb-0">Click the highlighted next step to open the next available options, or open any available area directly.</p></div>@if($currentSelectionStep === false)<span class="badge bg-label-success"><i class="ti ti-circle-check me-1"></i>Workflow complete</span>@else<span class="badge bg-label-primary">Step {{ $currentSelectionStep + 1 }} of {{ count($selectionSteps) }}</span>@endif</div>
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
                <div class="modal-dialog modal-lg modal-dialog-centered"><form method="POST" action="{{ route('backend.team-selection.final-reminders.send', [$event, $eventRegion]) }}" class="modal-content" data-mail-launch data-final-reminder-form data-clothing-enabled="{{ $usesRegionalClothing ? '1' : '0' }}" data-reminder-summaries='@json($reminderSummaries[$eventRegion->id] ?? [])' data-reminder-hashes='@json($reminderHashes[$eventRegion->id] ?? [])'>@csrf
                  <input type="hidden" name="send_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                  <input type="hidden" name="recipient_hash" data-reminder-hash>
                  <div class="modal-header"><div><h5 class="modal-title">Send {{ $eventRegion->region?->region_name }} reminders</h5><div class="small text-muted">Only active invitations in this region are included.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                  <div class="modal-body">
                    <div class="row g-3">
                      <div class="col-md-6"><label class="form-label">Reminder</label><select class="form-select" name="kind" data-reminder-kind required><option value="registration_clothing">{{ $usesRegionalClothing ? 'Registration and clothing reminder' : 'Registration reminder' }}</option>@if($usesRegionalClothing)<option value="incomplete_clothing">Incomplete clothing reminder</option>@endif</select></div>
                      <div class="col-md-6"><label class="form-label">Send to</label><select class="form-select" name="audience" data-reminder-audience required>@if($usesRegionalClothing)<option value="all">All active players in this region</option><option value="registered">Registered players in this region</option>@endif<option value="unregistered">Unregistered players in this region</option></select></div>
                    </div>
                    <div class="alert alert-primary mt-3 mb-3" data-reminder-summary></div>
                    <div class="border rounded p-3 bg-light"><strong data-reminder-preview-title>Registration is closing</strong><p class="mb-1 mt-2" data-reminder-preview-copy>{{ $usesRegionalClothing ? 'Unregistered players receive their registration/payment link. Registered players receive their clothing action link.' : 'Unregistered players receive their registration/payment link.' }}</p><small class="text-muted">One email is sent per address. Where a parent receives mail for several players in this region, all affected players and their individual links are included.</small></div>
                    <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="confirm_recipients" value="1" id="confirm-final-reminders-{{ $eventRegion->id }}" required><label class="form-check-label" for="confirm-final-reminders-{{ $eventRegion->id }}">I reviewed this region’s reminder and recipient group.</label></div>
                  </div>
                  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" data-reminder-submit>Write email</button></div>
                </form></div>
              </div>
            @endif
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
              <summary class="d-flex justify-content-between align-items-center gap-2"><span><strong><i class="ti ti-help-circle me-1"></i>How to manage teams in this region</strong><span class="d-block small text-muted mt-1">Replacement, reserves, reminders{{ $usesRegionalClothing ? ', clothing' : '' }} and publishing instructions.</span></span><span class="badge bg-label-primary">View steps</span></summary>
              <div class="border-top p-3">
                <div class="row g-3 small">
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Replace an unpaid player</strong><div class="text-muted">Select <strong>Show team</strong>, find the player, choose <strong>Change player</strong>, select the next reserve or another player profile, give a reason, then confirm. Paid players cannot be replaced here.</div></div>
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Leave an unavailable player's place vacant</strong><div class="text-muted">Select <strong>Show team</strong>, open the player's action menu, choose <strong>Mark unavailable / release place</strong>, give a reason and confirm. No reserve is needed. This action is for unpaid players.</div></div>
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Fill an open place</strong><div class="text-muted">Select <strong>Show team</strong>. A withdrawn or declined place shows <strong>Invite next reserve</strong>; an eligible reserve may also show <strong>Activate as Rank</strong>.</div></div>
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Change player order</strong><div class="text-muted">Select <strong>Show team</strong>, open <strong>Player order</strong>, then drag players into the required order or use Move up and Move down.</div></div>
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Contact outstanding players</strong><div class="text-muted">Use <strong>Registration reminder</strong>@if($usesRegionalClothing) or <strong>Incomplete clothing reminder</strong>@endif above. Review the regional audience and exact recipient count before sending.</div></div>
                  @if($usesRegionalClothing)<div class="col-md-6 col-xl-4 regional-help-step"><strong>Manage clothing</strong><div class="text-muted">Use <strong>Clothing setup</strong> above to review items and sizes. Use <strong>Region actions</strong> to open or close clothing ordering.</div></div>@endif
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Publish teams</strong><div class="text-muted">Resolve open places first, review each team, then select <strong>Publish all teams</strong>. Published teams can still be opened and reviewed.</div></div>
                </div>
              </div>
            </details>

              </div>
              <div class="tab-pane fade show active region-task-panel" id="region-{{ $eventRegion->id }}-teams" role="tabpanel" aria-labelledby="region-{{ $eventRegion->id }}-teams-tab" tabindex="0">

            <div class="border rounded p-3 mb-3">
              <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                <div><small class="text-muted d-block">Regional organizer</small><strong>{{ $regionManager?->name ?: trim(($regionManager?->userName ?? '').' '.($regionManager?->userSurname ?? '')) ?: $regionManager?->email ?: 'Not assigned' }}</strong>@if($regionManager?->email)<div class="small text-muted">{{ $regionManager->email }} · player profile not required</div>@endif</div>
                @if($isEventManager && $eventRegion->managerAssignment)<span class="badge bg-label-primary">Custom assignment</span>@elseif($regionManager)<span class="badge bg-label-secondary">Series organizer default</span>@endif
              </div>
              @if($isEventManager)
                @if(auth()->user()->hasRole('super-user'))
                  <a class="btn btn-outline-secondary mt-2" href="{{ route('backend.team-selection.manager.preview', [$event, $eventRegion]) }}">Preview regional organizer view</a>
                @endif
                <form method="POST" action="{{ route('backend.team-selection.manager.assign', [$event, $eventRegion]) }}" class="row g-2 align-items-end mt-1">@csrf @method('PUT')
                  <div class="col-md-7"><label class="form-label" for="region-manager-{{ $eventRegion->id }}">Select a system user</label><select id="region-manager-{{ $eventRegion->id }}" name="manager_user_id" class="form-select region-manager-select" data-placeholder="Search by name or email…"><option value=""></option>@if($eventRegion->managerAssignment && $regionManager)<option value="{{ $regionManager->id }}" selected>{{ trim($regionManager->name ?: (($regionManager->userName ?? '').' '.($regionManager->userSurname ?? ''))) }} · {{ $regionManager->email }}</option>@endif</select></div>
                  <div class="col-md-3 d-grid"><button class="btn btn-outline-primary">Assign to this region</button></div>
                  <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary" name="use_default" value="1" @disabled(!$defaultRegionManagers->get($eventRegion->id))>Use default</button></div>
                </form>
                <div class="form-text">This grants access to this region; it does not remove existing event-wide or other-region access. A player profile is not required.</div>
                @if(!$eventRegion->managerAssignment && $defaultCandidates->count() > 1)<div class="alert alert-warning mt-2 mb-0">This series has multiple common event organizers. No default was selected automatically; assign the intended account explicitly.</div>@endif
              @endif
            </div>

            <div class="regional-readonly rounded p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div><strong>Manage your teams</strong><div class="small text-muted">Choose <strong>Show team</strong> below to see players. @if($activeImport)Use <strong>Change player</strong> to replace an unpaid player.@else Use the player row to correct a name or email.@endif Open <strong>Player order</strong> to move players up or down.</div></div>
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
                  @php($openRosterRanks = $openRosterRanks->filter(fn($rank) => $regionTeam->team_players->where('rank', $rank)->count() < 2 && !$regionTeam->team_players->contains(fn($slot) => (int) $slot->rank === $rank && ((int) $slot->player_id !== 0 || (int) $slot->pay_status !== 0)))->values())
                  @php($importedRoster = $regionTeam->team_players_no_profile->sortBy('rank')->values())
                  @php($linkedImportedCount = $importedRoster->whereNotNull('player_profile')->count())
                  <div class="col-12">
                    <div class="card regional-team-card" data-team-card-id="{{ $regionTeam->id }}">
                      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2" data-team-workspace-header data-team-workspace-target="#team-workspace-{{ $regionTeam->id }}">
                        <div>
                          <h6 class="mb-1">{{ $regionTeam->name }}</h6>
                          @if($activeImport)
                            <span class="text-muted small" data-team-roster-summary>{{ $teamSelected->count() }} selected · {{ $teamReserves->count() }} reserves · {{ $regionTeam->num_team_members }} configured places</span>
                          @else
                            <span class="text-muted small">{{ $importedRoster->count() }} roster players · {{ $linkedImportedCount }} linked · {{ $importedRoster->count() - $linkedImportedCount }} unlinked · {{ $regionTeam->num_team_members }} configured places</span>
                          @endif
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center team-workspace-actions">
                          @if($activeImport)<span class="badge bg-label-danger {{ $teamOpenPlaceCount > 0 ? '' : 'd-none' }}" data-team-open-places>{{ $teamOpenPlaceCount }} open {{ \Illuminate\Support\Str::plural('place', $teamOpenPlaceCount) }}</span>@endif
                          <span class="badge {{ $regionTeam->published ? 'bg-label-success' : 'bg-label-secondary' }}" data-team-publication-status>{{ $regionTeam->published ? 'Published' : 'Not published' }}</span>
                          <button class="btn btn-sm btn-primary team-workspace-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#team-workspace-{{ $regionTeam->id }}" aria-controls="team-workspace-{{ $regionTeam->id }}" aria-expanded="false"><i class="ti ti-eye me-1"></i><span>Show team</span></button>
                          <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for {{ $regionTeam->name }}"><i class="ti ti-dots-vertical"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                              <button type="button" class="dropdown-item team-publication-button" data-url="{{ route('backend.team-selection.teams.publication.update', [$event, $eventRegion, $regionTeam]) }}" data-team-id="{{ $regionTeam->id }}" data-published="{{ $regionTeam->published ? '1' : '0' }}"><i class="ti ti-{{ $regionTeam->published ? 'world-off' : 'world-upload' }} me-2"></i><span>{{ $regionTeam->published ? 'Unpublish' : 'Publish' }}</span></button>
                              @if($activeImport ? $teamSelected->isNotEmpty() : $importedRoster->isNotEmpty())<button class="dropdown-item roster-email-button" type="button" data-bs-toggle="modal" data-bs-target="#roster-email-{{ $eventRegion->id }}" data-target-type="team" data-team-id="{{ $regionTeam->id }}" data-recipient="{{ $activeImport ? $teamSelected->count() : $importedRoster->count() }} roster player(s) in {{ $regionTeam->name }}"><i class="ti ti-mail me-2"></i>Email team</button>@endif
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
                        <form method="POST" action="{{ route('backend.team-selection.players.add', [$event, $activeImport, $regionTeam]) }}" class="row g-2 align-items-end p-3 border-bottom" data-add-team-player-form>
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
                          <div class="col-lg-2 d-grid"><button class="btn btn-outline-primary" data-add-team-player-submit><i class="ti ti-user-plus me-1"></i><span>Add as reserve</span></button></div>
                          <div class="col-12 form-text">System player profiles are shown. Player-profile email is used first, followed by a parent or linked-account email. The player is appended to the reserve queue; the active roster and published ranking snapshot stay unchanged.</div>
                        </form>
                        @php($teamCustomEmailRecipients = $teamInvitations->filter(fn($invitation) => $customEmailRecipients->contains('id', $invitation->id)))
                        @if($activeImport->status === 'sent')
                          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2 border-bottom bg-light" data-custom-email-team-toolbar="{{ $regionTeam->id }}">
                            <label class="form-check mb-0"><input class="form-check-input" type="checkbox" data-custom-email-check-all="{{ $regionTeam->id }}" @disabled($teamCustomEmailRecipients->isEmpty())><span class="form-check-label">Check all eligible players</span></label>
                            <button type="button" class="btn btn-sm btn-primary" data-custom-email-team-button="{{ $regionTeam->id }}" data-bs-toggle="modal" data-bs-target="#custom-player-email-modal-{{ $activeImport->id }}" disabled><i class="ti ti-mail-edit me-1"></i><span>Email checked players</span></button>
                          </div>
                        @endif
                        <div class="table-responsive team-player-table" data-mobile-player-cards>
                          <table class="table table-sm align-middle mb-0">
                            <thead><tr><th><span class="visually-hidden">Select</span></th><th>Rank</th><th>Player</th><th>Contact</th><th>Ranking</th><th>Selection / payment</th><th>Email</th><th>Regional action</th></tr></thead>
                            <tbody data-team-invitations>
                              @forelse($teamInvitations->sortBy(fn ($invitation) => $invitation->status === \App\Models\TeamSelectionInvitation::DECLINED ? 1 : 0) as $invitation)
                                @php($recipientEmail = $recipientEmailFor($invitation))
                                @php($rawContactEmails = $rawContactEmailsFor($invitation))
                                @php($delivery = $invitation->emailLogs->sortByDesc('id')->first())
                                @php($isReserve = $invitation->status === \App\Models\TeamSelectionInvitation::RESERVE)
                                @php($isInactive = in_array($invitation->status, [\App\Models\TeamSelectionInvitation::DECLINED, \App\Models\TeamSelectionInvitation::WITHDRAWN], true) || (!$isReserve && !$invitation->roster_rank))
                                @php($hasActiveReplacement = $teamInvitations->contains(fn($candidate) => (int) $candidate->promoted_from_id === (int) $invitation->id && in_array($candidate->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, \App\Models\TeamSelectionInvitation::PAID_CONFIRMED], true)))
                                @php($openVacancy = $isInactive && $invitation->vacated_roster_rank && !$hasActiveReplacement)
                                @php($awaitingRestoredInvitation = $invitation->status === \App\Models\TeamSelectionInvitation::INVITED && $invitation->declined_at && !$invitation->invited_at)
                                @php($awaitingActivatedInvitation = $invitation->status === \App\Models\TeamSelectionInvitation::INVITED && !$invitation->invited_at && data_get($invitation->snapshot_json, 'activation.pending_manual_invitation'))
                                @php($canSelectCustomInvitation = $activeImport->status === 'sent' && in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, \App\Models\TeamSelectionInvitation::PAID_CONFIRMED], true) && $invitation->roster_rank && (bool) $recipientEmail)
                                @php($rankLabel = $isReserve ? 'Reserve '.$invitation->queue_position : ($isInactive ? ($invitation->status === \App\Models\TeamSelectionInvitation::DECLINED ? 'Declined' : ($invitation->status === \App\Models\TeamSelectionInvitation::WITHDRAWN ? 'Withdrawn' : 'Removed')) : 'Rank '.$invitation->roster_rank))
                                @php($statusTone = $isInactive ? 'danger' : ($invitation->status === \App\Models\TeamSelectionInvitation::PAID_CONFIRMED ? 'success' : ($isReserve ? 'warning' : 'info')))
                                <tr data-order-player-id="{{ $invitation->id }}" class="{{ $isReserve ? 'reserve-row' : '' }}">
                                  <td data-label="Select">@if($canSelectCustomInvitation)<input class="form-check-input" type="checkbox" name="invitation_ids[]" value="{{ $invitation->id }}" form="custom-player-email-form-{{ $activeImport->id }}" data-custom-email-player="{{ $regionTeam->id }}" aria-label="Select {{ $invitation->player?->full_name ?: 'player' }} for a custom email">@endif</td>
                                  <td data-label="Rank"><span class="badge {{ $isInactive ? 'bg-label-danger' : ($isReserve ? 'bg-label-warning' : 'bg-label-primary') }}">{{ $rankLabel }}</span></td>
                                  <td data-label="Player"><strong>{{ $invitation->player?->full_name ?: 'Missing player' }}</strong>@if(!$invitation->player?->profile_complete)<div class="small text-warning">Profile incomplete</div>@endif</td>
                                  <td data-label="Contact">
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
                                  <td data-label="Ranking">@if(data_get($invitation->snapshot_json, 'selection_source') === 'manual_system_profile')<strong>Manual addition</strong><div class="small text-muted">Not in ranking snapshot</div>@else<strong>#{{ $invitation->ranking_position }}</strong><div class="small text-muted">{{ number_format((float)$invitation->total_points, 2) }} pts</div>@endif</td>
                                  <td data-label="Selection / payment">
                                    <span class="badge bg-label-{{ $statusTone }}">{{ str($invitation->status)->replace('_',' ')->title() }}</span>
                                    @if($invitation->status === \App\Models\TeamSelectionInvitation::PAID_CONFIRMED)<div class="small text-muted mt-1">{{ $invitation->order?->collection_status === 'paid_privately' ? 'Manual / private collection' : ($invitation->order?->payfast_paid ? ($invitation->order->wallet_debited ? 'PayFast + wallet payment' : 'PayFast payment') : ($invitation->order?->wallet_debited ? 'Wallet payment' : 'Payment evidence unavailable')) }}</div>@endif
                                    @if($invitation->decline_method === 'system_primary_team_promotion')<div class="small text-info mt-1">{{ $invitation->decline_reason }}</div>@else<div class="small text-muted mt-1">Read only</div>@endif
                                    @if(!($isRegionalPreview ?? false) && auth()->user()->hasRole('super-user') && $invitation->status === \App\Models\TeamSelectionInvitation::PAID_CONFIRMED)
                                      @include('backend.team-selection._invitation-payment-transfer')
                                    @endif
                                  </td>
                                  <td data-label="Email">
                                    <div>{{ $awaitingRestoredInvitation ? 'Restored — invitation not sent' : ($awaitingActivatedInvitation ? 'Pending invitation — not sent' : ($delivery ? $delivery->delivery_status_label : 'Not sent')) }}</div>@if($delivery?->accepted_at)<small class="d-block text-muted">{{ $delivery->accepted_at->format('d M Y H:i:s') }}</small>@endif @if($delivery?->transport_message_id)<small class="d-block text-muted text-break">Message ID: {{ $delivery->transport_message_id }}</small>@endif
                                    @if($activeImport->status === 'sent' && !$isReserve)
                                      <div class="d-flex flex-wrap gap-1 mt-1">
                                        <a class="btn btn-xs btn-outline-secondary" target="_blank" href="{{ route('backend.team-selection.invitations.email.view', [$event, $activeImport, $invitation]) }}">View email</a>
                                        @if($event->isTeam() && $awaitingActivatedInvitation && !$awaitingRestoredInvitation && $invitation->roster_rank && $recipientEmail)
                                          <form method="POST" action="{{ route('backend.team-selection.invitations.email.resend', [$event, $activeImport, $invitation]) }}" data-mail-launch>@csrf<button class="btn btn-xs btn-primary" title="Review this player's invitation before sending">Send invite</button></form>
                                        @elseif(!$awaitingRestoredInvitation && !$awaitingActivatedInvitation && in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true))
                                          <form method="POST" action="{{ route('backend.team-selection.invitations.email.resend', [$event, $activeImport, $invitation]) }}" data-mail-launch>@csrf<button class="btn btn-xs btn-outline-primary">Resend</button></form>
                                        @endif
                                      </div>
                                    @endif
                                  </td>
                                  <td data-label="Regional action">
                                    @php($reserveActivationIndex = $isReserve ? $teamReserves->values()->search(fn($candidate) => (int) $candidate->id === (int) $invitation->id) : false)
                                    @php($reserveActivationRank = $isReserve ? $openRosterRanks->first() : null)
                                    @php($canMarkPaidPrivately = !$isReserve && in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true))
                                    @php($canUndoPrivatePayment = !($isRegionalPreview ?? false) && auth()->user()->hasRole('super-user') && $invitation->status === \App\Models\TeamSelectionInvitation::PAID_CONFIRMED && $invitation->order?->collection_status === 'paid_privately' && !$invitation->order->wallet_debited && !$invitation->order->payfast_paid && !$invitation->order->payfast_handed_off_at && !$invitation->order->payfast_pf_payment_id && !$invitation->order->payfast_raw_data && (float) $invitation->order->wallet_reserved === 0.0 && !$invitation->order->withdrawn_at && !$invitation->order->hasRefund())
                                    @php($canManagePaidPayment = !($isRegionalPreview ?? false) && auth()->user()->hasRole('super-user') && $invitation->status === \App\Models\TeamSelectionInvitation::PAID_CONFIRMED)
                                    @php($hasRegionalActions = (bool) $reserveActivationRank || (in_array($invitation->status, [\App\Models\TeamSelectionInvitation::DECLINED, \App\Models\TeamSelectionInvitation::WITHDRAWN], true) && $invitation->vacated_roster_rank) || ($recipientEmail && (!$isReserve || $event->isTeam())) || $canMarkPaidPrivately || $canUndoPrivatePayment || $canManagePaidPayment || $openVacancy)
                                    @if($hasRegionalActions)
                                    <div class="dropdown">
                                      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Regional actions for {{ $invitation->player?->full_name ?: 'player' }}">
                                        Actions
                                      </button>
                                      <div class="dropdown-menu dropdown-menu-end p-1 {{ (int) old('undo_private_invitation_id') === (int) $invitation->id || (int) old('cash_refund_invitation_id') === (int) $invitation->id ? 'show' : '' }}" style="min-width:18rem;max-width:calc(100vw - 2rem);max-height:calc(100vh - 8rem);overflow-y:auto;white-space:normal;">
                                    @if($event->isTeam() && $recipientEmail)
                                      <form method="POST" action="{{ route('backend.team-selection.invitations.email.resend', [$event, $activeImport, $invitation]) }}" data-mail-launch>@csrf<input type="hidden" name="custom_email" value="1"><button class="dropdown-item">Custom email</button></form>
                                    @endif
                                    @if($canManagePaidPayment)
                                      <div class="px-2 pt-1 small text-muted">Payment management · {{ $invitation->order_id ? 'Order #'.$invitation->order_id : 'No attached order' }}</div>
                                      <div class="px-2 pb-2 small">{{ $invitation->order?->collection_status === 'paid_privately' ? 'Manual / private collection' : ($invitation->order?->payfast_paid ? ($invitation->order->wallet_debited ? 'PayFast + wallet payment' : 'PayFast payment') : ($invitation->order?->wallet_debited ? 'Wallet payment' : 'Payment evidence unavailable')) }}<br>Original payer: {{ $invitation->order?->user?->name ?: 'Payer unavailable' }}</div>
                                      <button class="dropdown-item text-primary" type="button" data-show-payment-transfer="invitation-payment-transfer-{{ $invitation->id }}">Move payment to another player</button>
                                      @include('backend.team-selection._cash-refund-action')
                                    @endif
                                    @if($awaitingRestoredInvitation)
                                      <form method="POST" action="{{ route('backend.team-selection.invitations.email.send-restored', [$event, $activeImport, $invitation]) }}" data-mail-launch>@csrf<button class="dropdown-item text-success">Send invitation</button></form>
                                    @endif
                                    @if($canMarkPaidPrivately)
                                      <form method="POST" action="{{ route('backend.team-selection.invitations.mark-paid-privately', [$event, $activeImport, $invitation]) }}" onsubmit="return confirm('Confirm that payment was collected privately for this player? This will mark the team place paid, cancel any pending checkout, and will NOT be recorded as a PayFast reconciliation. No email will be sent.');">@csrf<button class="dropdown-item text-primary">Mark paid privately (not reconciled)</button></form>
                                    @endif
                                    @if($canUndoPrivatePayment)
                                      <details class="p-2" @if((int) old('undo_private_invitation_id') === (int) $invitation->id) open @endif><summary class="text-warning">Mark as unpaid (private payment)</summary>
                                        <form method="POST" action="{{ route('backend.team-selection.invitations.undo-private-payment', [$event, $activeImport, $invitation]) }}" class="mt-2">
                                          @csrf
                                          <input type="hidden" name="undo_private_invitation_id" value="{{ $invitation->id }}">
                                          <input type="hidden" name="expected_order_id" value="{{ $invitation->order_id }}"><input type="hidden" name="expected_player_id" value="{{ $invitation->player_id }}"><input type="hidden" name="expected_roster_rank" value="{{ $invitation->roster_rank }}">
                                          @if((int) old('undo_private_invitation_id') === (int) $invitation->id && $errors->any())<div class="small text-danger mb-2">{{ $errors->first() }}</div>@endif
                                          <label class="form-label small" for="undo-private-reason-{{ $invitation->id }}">Correction reason</label><input id="undo-private-reason-{{ $invitation->id }}" name="reason" type="text" class="form-control form-control-sm mb-2" maxlength="1000" value="{{ (int) old('undo_private_invitation_id') === (int) $invitation->id ? old('reason') : '' }}" required>
                                          <label class="form-check small"><input type="checkbox" class="form-check-input" name="confirm_unpaid" value="1" required><span class="form-check-label">Correct this manual collection mark; no money will be refunded.</span></label>
                                          <button class="btn btn-sm btn-outline-warning mt-2">Confirm mark as unpaid</button>
                                        </form>
                                      </details>
                                    @endif
                                    @if($isReserve && $reserveActivationRank)
                                      @include('backend.team-selection._open-position-action', ['restorePosition' => false])
                                    @endif
                                    @if(in_array($invitation->status, [\App\Models\TeamSelectionInvitation::DECLINED, \App\Models\TeamSelectionInvitation::WITHDRAWN], true) && $invitation->vacated_roster_rank)
                                      @include('backend.team-selection._open-position-action', ['restorePosition' => true])
                                      <form method="POST" action="{{ route('backend.team-selection.invitations.restore', [$event, $activeImport, $invitation]) }}" onsubmit="return confirm('{{ $invitation->status === \App\Models\TeamSelectionInvitation::WITHDRAWN ? 'Restore this withdrawn player' : 'Restore this player' }} at Rank {{ $invitation->vacated_roster_rank }}? Active players at and below that rank will move down. {{ $invitation->status === \App\Models\TeamSelectionInvitation::WITHDRAWN ? 'Their old payment and withdrawal history stays unchanged, and they must register and pay again through a fresh order. ' : '' }}No email will be sent.');">@csrf<button class="dropdown-item text-primary">Restore at Rank {{ $invitation->vacated_roster_rank }}</button></form>
                                    @endif
                                      @if($recipientEmail && !$isReserve)<button class="dropdown-item text-success roster-email-button" type="button" data-bs-toggle="modal" data-bs-target="#roster-email-{{ $eventRegion->id }}" data-target-type="player" data-team-id="{{ $regionTeam->id }}" data-invitation-id="{{ $invitation->id }}" data-recipient="{{ $invitation->player?->full_name }} · {{ $recipientEmail }}"><i class="ti ti-mail me-1" aria-hidden="true"></i>Email player</button>@endif
                                    @if(!$isReserve && in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true))
                                      @php($unavailableFormOpen = (int) old('unavailable_invitation_id') === (int) $invitation->id)
                                      <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#unavailable-player-{{ $invitation->id }}">Mark unavailable / release place</button>
                                      <div class="modal fade" id="unavailable-player-{{ $invitation->id }}" tabindex="-1" aria-labelledby="unavailable-player-title-{{ $invitation->id }}" aria-hidden="true" data-replacement-modal @if($unavailableFormOpen) data-reopen @endif>
                                        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
                                          <div class="modal-header"><h5 class="modal-title" id="unavailable-player-title-{{ $invitation->id }}">Mark unavailable — {{ $invitation->player?->full_name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                                          <div class="modal-body">
                                            <p>{{ $regionTeam->name }} · Rank <span data-current-roster-rank>{{ $invitation->roster_rank }}</span></p>
                                            <p>The unpaid invitation will be declined and this place will stay vacant. No reserve will be promoted and no email will be sent. Any unpaid wallet reservation will be released.</p>
                                            <form method="POST" action="{{ route('backend.team-selection.invitations.unavailable', [$event, $activeImport, $invitation]) }}">
                                              @csrf
                                              <input type="hidden" name="unavailable_invitation_id" value="{{ $invitation->id }}">
                                              <input type="hidden" name="expected_team_id" value="{{ $invitation->team_id }}">
                                              <input type="hidden" name="expected_player_id" value="{{ $invitation->player_id }}">
                                              <input type="hidden" name="expected_roster_rank" value="{{ $invitation->roster_rank }}">
                                              @if($unavailableFormOpen && $errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
                                              <label class="form-label" for="unavailable-reason-{{ $invitation->id }}">Reason</label>
                                              <textarea id="unavailable-reason-{{ $invitation->id }}" name="reason" class="form-control mb-3" rows="3" maxlength="1000" required>{{ $unavailableFormOpen ? old('reason') : 'Player not available.' }}</textarea>
                                              <div class="form-check mb-3"><input type="checkbox" class="form-check-input" id="confirm-unavailable-{{ $invitation->id }}" name="confirm_unavailable" value="1" required><label class="form-check-label" for="confirm-unavailable-{{ $invitation->id }}">I confirm this player is unavailable and the place should be left vacant.</label></div>
                                              <button class="btn btn-danger w-100">Mark unavailable / release place</button>
                                            </form>
                                          </div>
                                        </div></div>
                                      </div>
                                      @php($replacementFormOpen = (int) old('replacement_invitation_id') === (int) $invitation->id)
                                      @php($replacementMode = $replacementFormOpen ? old('replacement_mode', 'next_reserve') : ($eligibleTeamReserves->isNotEmpty() ? 'next_reserve' : 'custom_profile'))
                                      @php($replacementMode = $replacementMode === 'next_reserve' && $eligibleTeamReserves->isEmpty() ? 'custom_profile' : $replacementMode)
                                      <button type="button" class="dropdown-item text-warning" data-bs-toggle="modal" data-bs-target="#replace-player-{{ $invitation->id }}">Change player</button>
                                      <div class="modal fade" id="replace-player-{{ $invitation->id }}" tabindex="-1" aria-labelledby="replace-player-title-{{ $invitation->id }}" aria-hidden="true" data-replacement-modal @if($replacementFormOpen) data-reopen @endif>
                                        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                          <div class="modal-content">
                                            <div class="modal-header">
                                              <h5 class="modal-title" id="replace-player-title-{{ $invitation->id }}">Change player — {{ $invitation->player?->full_name }}</h5>
                                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
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
                                            <div class="form-text">Any Cape Tennis player profile can be selected; no parent or linked account is required. If a valid contact email is available, it is used for a sent campaign. Existing players below this place move up, and the replacement joins the final active roster place.</div>
                                          </div>
                                          <label class="form-label small mb-1" for="replacement-reason-{{ $invitation->id }}">Reason</label>
                                          <input id="replacement-reason-{{ $invitation->id }}" type="text" name="reason"
                                                 class="form-control form-control-sm mb-2" maxlength="1000"
                                                 value="Player not available." placeholder="Required reason" required>
                                          <button class="btn btn-sm btn-warning w-100">Confirm replacement</button>
                                        </form>
                                            </div>
                                          </div>
                                        </div>
                                      </div>
                                    @endif
                                    @if($openVacancy && $eligibleTeamReserves->isNotEmpty())
                                      <form method="POST" action="{{ route('backend.team-selection.invitations.promote-reserve', [$event, $activeImport, $invitation]) }}" onsubmit="return confirm('Invite the next reserve now? Their own response and payment deadlines will start now.');">@csrf<button class="dropdown-item text-warning">Invite next reserve</button></form>
                                    @elseif($openVacancy)
                                      <span class="text-warning small">Vacancy open · no eligible reserve. Add or link a reserve below.</span>
                                    @endif
                                    @if(!$openVacancy && !$recipientEmail && ($isReserve || !in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true)))
                                      <span class="text-muted small">No action available</span>
                                    @endif
                                      </div>
                                    </div>
                                    @else
                                      <span class="text-muted small">No action available</span>
                                    @endif
                                  </td>
                                </tr>
                              @empty
                                <tr data-empty-team-invitations><td colspan="8" data-mobile-full class="text-center text-muted py-4">No ranked players have been imported for this team yet.</td></tr>
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
                              <tbody class="roster-order-sortable" data-reorder-url="{{ route('backend.team-selection.teams.order', [$event, $activeImport, $regionTeam]) }}" data-reorder-field="invitation_ids">
                                @foreach($teamSelected->sortBy('roster_rank') as $orderedInvitation)
                                  <tr draggable="true" data-order-id="{{ $orderedInvitation->id }}">
                                    <td><span class="badge bg-label-primary">Rank {{ $orderedInvitation->roster_rank }}</span></td>
                                    <td><span class="drag-handle me-2" title="Drag to reorder"><i class="ti ti-grip-vertical"></i></span><strong>{{ $orderedInvitation->player?->full_name }}</strong></td>
                                    <td>@if(data_get($orderedInvitation->snapshot_json, 'selection_source') === 'manual_system_profile')Manual addition <span class="text-muted">· not in ranking snapshot</span>@else#{{ $orderedInvitation->ranking_position }} <span class="text-muted">· {{ number_format((float)$orderedInvitation->total_points, 2) }} pts</span>@endif</td>
                                    <td>{{ str($orderedInvitation->status)->replace('_',' ')->title() }}</td>
                                    <td><div class="d-flex gap-1">
                                      <form method="POST" action="{{ route('backend.team-selection.invitations.move', [$event, $activeImport, $orderedInvitation]) }}">@csrf<input type="hidden" name="direction" value="up"><button class="btn btn-sm btn-outline-primary" title="Move up" aria-label="Move {{ $orderedInvitation->player?->full_name }} up" @disabled($loop->first)><i class="ti ti-arrow-up"></i></button></form>
                                      <form method="POST" action="{{ route('backend.team-selection.invitations.move', [$event, $activeImport, $orderedInvitation]) }}">@csrf<input type="hidden" name="direction" value="down"><button class="btn btn-sm btn-outline-primary" title="Move down" aria-label="Move {{ $orderedInvitation->player?->full_name }} down" @disabled($loop->last)><i class="ti ti-arrow-down"></i></button></form>
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
              <div class="alert alert-warning">No teams have been added for this region yet. <button type="button" class="btn btn-sm btn-outline-primary ms-2" data-open-region-task="setup">Set up teams</button></div>
            @endif
              </div>

              <div class="tab-pane fade region-task-panel" id="region-{{ $eventRegion->id }}-invitations" role="tabpanel" aria-labelledby="region-{{ $eventRegion->id }}-invitations-tab" tabindex="0">
                @if(!$activeImport)
                  <div class="alert alert-info">Import players from your region's published ranking. Review the players before importing; invitations are prepared separately.</div>
                  @if($sourceReady && $regionTeams->isNotEmpty())
                    <a class="btn btn-primary" href="{{ route('backend.team-selection.preview', [$event, $source]) }}"><i class="ti ti-file-import me-1"></i>Import players</a>
                  @else
                    <p class="text-muted small">First choose a published ranking series and create the teams.</p>
                    <button type="button" class="btn btn-outline-primary" data-open-region-task="setup">Set up teams for import</button>
                  @endif
                @else
                  @php($unansweredInvitations = $activeImport->invitations->filter(fn ($invitation) => $invitation->status === \App\Models\TeamSelectionInvitation::INVITED && $invitation->invited_at && !$invitation->accepted_at))
                  <h6 class="mb-1">Player invitations</h6>
                  <p class="text-muted small">Accept opens registration and payment. Decline asks for confirmation and records the response.</p>
                  <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge bg-label-warning">Awaiting response: {{ $unansweredInvitations->count() }}</span>
                    <span class="badge bg-label-primary">Payment pending: {{ $activeImport->invitations->where('status', 'accepted_pending_payment')->count() }}</span>
                    <span class="badge bg-label-success">Registered: {{ $activeImport->invitations->where('status', 'paid_confirmed')->count() }}</span>
                    <span class="badge bg-label-secondary">Declined: {{ $activeImport->invitations->where('status', 'declined')->count() }}</span>
                  </div>
                  @if($activeImport->status === 'sent' && $isEventManager)
                    <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#roster-email-{{ $eventRegion->id }}" data-target-type="filtered" data-unanswered-invitations="1" data-recipient="Players awaiting a response in this region" @disabled($unansweredInvitations->isEmpty())>Email players awaiting response</button>
                    @if($unansweredInvitations->isEmpty())<p class="small text-muted">No sent invitations are awaiting a response.</p>@endif
                  @elseif($activeImport->status !== 'sent')
                    <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#prepare-invitations-{{ $activeImport->id }}">Prepare and send invitations</button>
                  @endif
                  <details class="mb-3"><summary>View player responses</summary><div class="table-responsive mt-2"><table class="table table-sm"><thead><tr><th>Player</th><th>Response</th></tr></thead><tbody>
                  @foreach($activeImport->invitations->where('status', '!=', 'reserve') as $responseInvitation)
                    <tr><td>{{ $responseInvitation->player?->full_name ?: 'Player' }}</td><td>{{ match ($responseInvitation->status) { 'invited' => $responseInvitation->invited_at ? 'Awaiting response' : 'Not yet sent', 'accepted_pending_payment' => 'Payment pending', 'paid_confirmed' => 'Registered', default => ucfirst(str_replace('_', ' ', $responseInvitation->status)) } }}</td></tr>
                  @endforeach
                  </tbody></table></div></details>
                  <details class="border rounded p-3"><summary>Advanced settings and delivery details</summary><div class="mt-3">
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
                    @if($pendingActivatedInvitations->isNotEmpty())
                      <div class="alert alert-warning mt-3 mb-3">
                        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                          <div><strong>{{ $pendingActivatedInvitations->count() }} newly activated invitation(s) pending.</strong><div class="small">No email was sent during the roster changes. Review the exact recipients, then confirm one bulk send using the saved campaign and deadlines.</div></div>
                          <span class="badge bg-label-warning">{{ $pendingActivatedRecipients->count() }} ready to send</span>
                        </div>
                        <details class="mt-2"><summary>Review {{ $pendingActivatedRecipients->count() }} exact recipient(s)</summary><div class="small text-muted mt-2">@forelse($pendingActivatedRecipients as $pendingInvitation)<div>{{ $pendingInvitation->player?->full_name ?: 'Player' }} · {{ $recipientEmailFor($pendingInvitation) }}</div>@empty No pending player currently has a valid email address. @endforelse</div></details>
                        @if($pendingActivatedInvitations->count() > $pendingActivatedRecipients->count())<div class="small text-danger mt-2">{{ $pendingActivatedInvitations->count() - $pendingActivatedRecipients->count() }} pending player(s) have no valid email and will remain pending.</div>@endif
                        <div class="mt-3 form-text">Use <strong>Email checked players</strong> above a team table to compose a custom email.</div>
                        <form method="POST" action="{{ route('backend.team-selection.invitations.email.send-pending-activated', [$event, $activeImport]) }}" class="mt-3" data-mail-launch>@csrf
                          <input type="hidden" name="recipient_hash" value="{{ $pendingActivatedRecipientHash }}"><input type="hidden" name="recipient_count" value="{{ $pendingActivatedRecipients->count() }}">
                          <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirm_recipients" value="1" id="confirm-pending-activated-{{ $activeImport->id }}" required><label class="form-check-label" for="confirm-pending-activated-{{ $activeImport->id }}">I reviewed and confirm these {{ $pendingActivatedRecipients->count() }} exact recipient(s).</label></div>
                          <button class="btn btn-sm btn-success" @disabled($pendingActivatedRecipients->isEmpty())>Write email to {{ $pendingActivatedRecipients->count() }} pending players</button>
                        </form>
                      </div>
                    @endif
                    @if($customEmailRecipients->isNotEmpty())
                      <div class="modal fade" id="custom-player-email-modal-{{ $activeImport->id }}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                          <form id="custom-player-email-form-{{ $activeImport->id }}" method="POST" action="{{ route('backend.team-selection.invitations.email.custom-preview', [$event, $activeImport]) }}" class="modal-content" data-mail-compose>@csrf
                            <div class="modal-header"><div><h5 class="modal-title">Email checked players</h5><div class="small text-muted">Only checked, eligible active players in this team will be included.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                              @include('backend.partials.email-sender-fields')
                              <div class="mb-3"><label class="form-label" for="custom-invitation-subject-{{ $activeImport->id }}">Subject</label><input id="custom-invitation-subject-{{ $activeImport->id }}" class="form-control" name="email_subject" maxlength="255" value="{{ old('email_subject', data_get($activeImport->communication_snapshot, 'subject', $activeImport->email_subject)) }}" required></div>
                              <div><label class="form-label" for="custom-invitation-message-{{ $activeImport->id }}">Message</label><textarea id="custom-invitation-message-{{ $activeImport->id }}" class="form-control" name="email_message" rows="8" maxlength="10000" required>{{ old('email_message', data_get($activeImport->communication_snapshot, 'message', $activeImport->email_message)) }}</textarea><div class="form-text">Add any date you want players to see directly in this message. This custom email will not show the old response or replacement deadlines, and the saved normal campaign will not be changed.</div></div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Review exact recipients and email</button></div>
                          </form>
                        </div>
                      </div>
                    @endif
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3"><span class="badge bg-label-secondary">Email queued: {{ $emailLogs->where('status','queued')->count() }}</span><span class="badge bg-label-success">Mail server accepted: {{ $emailLogs->where('evidence_status','server_accepted')->whereNotNull('accepted_at')->count() }}</span><span class="badge bg-label-danger">Failed: {{ $emailLogs->where('status','failed')->count() }}</span><span class="badge bg-label-warning">Skipped: {{ $emailLogs->where('status','skipped')->count() }}</span>@if($emailLogs->where('status','failed')->isNotEmpty())<form method="POST" action="{{ route('backend.team-selection.emails.retry', [$event, $activeImport]) }}" data-mail-launch>@csrf<button class="btn btn-sm btn-outline-danger">Retry failed emails</button></form>@endif</div>
                    <form method="POST" action="{{ route('backend.team-selection.deadlines.extend', [$event, $activeImport]) }}" class="row g-2 align-items-end mt-2">@csrf @method('PATCH')
                      <div class="col-md-3"><label class="form-label">Response deadline</label><input type="datetime-local" name="response_deadline" value="{{ $activeImport->response_deadline?->format('Y-m-d\\TH:i') }}" class="form-control" required></div>
                      <div class="col-md-3"><label class="form-label">Payment deadline</label><input type="datetime-local" name="payment_deadline" value="{{ $activeImport->payment_deadline?->format('Y-m-d\\TH:i') }}" class="form-control" required></div>
                      <div class="col-md-3"><label class="form-label">Last reserve promotion</label><input type="datetime-local" name="replacement_payment_deadline" value="{{ ($activeImport->replacement_payment_deadline ?: $activeImport->payment_deadline)?->format('Y-m-d\\TH:i') }}" class="form-control" required><div class="form-text">A promoted reserve may receive their own later deadline, capped before the event.</div></div>
                      <div class="col-md-3 d-grid"><button class="btn btn-outline-primary">Update deadlines</button></div>
                    </form>
                  @else
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3"><button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#prepare-invitations-{{ $activeImport->id }}"><i class="ti ti-mail-cog me-1"></i>Prepare invitations</button><span class="text-muted small">Review the message, one registration deadline and exact recipients before sending.</span></div>
                    <form method="POST" action="{{ route('backend.team-selection.restart', [$event, $activeImport]) }}" class="mt-3" onsubmit="return confirm('Remove this unsent import and clear its generated roster places?');">@csrf<button class="btn btn-sm btn-outline-danger">Restart draft import</button></form>
                  @endif
                  </div></details>
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
              <div><h6 class="mb-1">{{ $usesRegionalClothing ? 'Messages & clothing' : 'Messages' }}</h6><p class="text-muted small mb-0">{{ $usesRegionalClothing ? 'Contact selected players and manage the regional clothing workflow.' : 'Contact selected players in this region.' }}</p></div>
              <div class="d-flex flex-wrap gap-2">
                @if($isEventManager)
                  <a href="{{ route('backend.regional-registration-mail.compose', ['event' => $event, 'region_id' => $eventRegion->region_id]) }}" class="btn btn-primary"><i class="ti ti-mail me-1"></i>Registration open email</a>
                  <button class="btn btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#final-team-reminders-{{ $eventRegion->id }}" data-reminder-open-kind="registration_clothing"><i class="ti ti-user-exclamation me-1"></i>Registration reminder</button>
                  @if($usesRegionalClothing)<button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#final-team-reminders-{{ $eventRegion->id }}" data-reminder-open-kind="incomplete_clothing"><i class="ti ti-shirt me-1"></i>Incomplete clothing reminder</button>@endif
                @endif
                @if($usesRegionalClothing)
                  <a href="{{ route('backend.region.clothing.edit', ['region' => $eventRegion->region_id, 'event_id' => $event->id]) }}" class="btn btn-outline-primary"><i class="ti ti-shirt me-1"></i>Clothing setup</a>
                @endif
              </div>
            </div>
            <h6>Regional announcements</h6>
            <p class="text-muted small">Visible only to this region’s invited players. Choose email to save the announcement and open Communications, where you can review all nominated players or another regional audience before sending.</p>
            @foreach($eventRegion->announcements as $announcement)
              @php($announcementLogs = $announcement->emailLogs)
              <div class="border rounded p-2 mb-2"><div class="d-flex justify-content-between gap-2"><strong>{{ $announcement->title }}</strong><form method="POST" action="{{ route('backend.team-selection.announcements.destroy', [$event, $eventRegion, $announcement]) }}" onsubmit="return confirm('Hide this announcement from the regional portal? Previously sent email cannot be recalled.');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Hide</button></form></div><div class="small mt-1">{!! $announcement->message !!}</div><div class="d-flex flex-wrap gap-2 align-items-center text-muted small mt-1"><span>{{ $announcement->created_at->format('d M Y H:i') }}{{ $announcement->emailed_at ? ' · email queued' : ' · portal only' }}</span>@if($announcementLogs->isNotEmpty())<span class="badge bg-label-secondary">Queued {{ $announcementLogs->where('status','queued')->count() }}</span><span class="badge bg-label-success">Mail server accepted {{ $announcementLogs->where('evidence_status','server_accepted')->whereNotNull('accepted_at')->count() }}</span><span class="badge bg-label-danger">Failed {{ $announcementLogs->where('status','failed')->count() }}</span>@if($announcementLogs->where('status','failed')->isNotEmpty())<form method="POST" action="{{ route('backend.team-selection.announcements.retry', [$event, $eventRegion, $announcement]) }}">@csrf<button class="btn btn-sm btn-outline-danger">Review failed emails</button></form>@endif @endif</div></div>
            @endforeach
            <form method="POST" action="{{ route('backend.team-selection.announcements.store', [$event, $eventRegion]) }}" class="row g-2">@csrf
              <div class="col-md-4"><label class="form-label">Title</label><input name="title" class="form-control" maxlength="255" required></div>
              <div class="col-md-8"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="2" maxlength="20000" required></textarea></div>
              <input type="hidden" name="recipient_hash" value="{{ hash('sha256', $regionAnnouncementRecipients->toJson()) }}">
              <div class="col-12"><details><summary>Review {{ $regionAnnouncementRecipients->count() }} exact email recipient(s)</summary><div class="small text-muted mt-1">@forelse($regionAnnouncementRecipients as $email)<div>{{ $email }}</div>@empty No active selected players currently have a valid email address. @endforelse</div></details></div>
              <div class="col-12 d-flex flex-wrap gap-3 align-items-center"><div><div class="form-check"><input type="hidden" name="send_email" value="0"><input class="form-check-input" type="checkbox" name="send_email" value="1" id="send-region-announcement-{{ $eventRegion->id }}"><label class="form-check-label" for="send-region-announcement-{{ $eventRegion->id }}">Open email review after publishing</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_recipients" value="1" id="confirm-region-announcement-{{ $eventRegion->id }}"><label class="form-check-label" for="confirm-region-announcement-{{ $eventRegion->id }}">I reviewed and confirm this exact recipient list</label></div></div><button class="btn btn-outline-primary ms-auto">Publish regional announcement</button></div>
            </form>
              </div>
            </div>
          </div>
        </div>
      </div>

      @if($activeImport?->status === 'draft')
        <div class="modal fade" id="prepare-invitations-{{ $activeImport->id }}" tabindex="-1" aria-labelledby="prepare-invitations-title-{{ $activeImport->id }}" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable"><form method="POST" action="{{ route('backend.team-selection.email.preview', [$event, $activeImport]) }}" class="modal-content" data-mail-compose>@csrf
            <input type="hidden" name="selection_import_id" value="{{ $activeImport->id }}">
            <div class="modal-header"><div><h5 class="modal-title" id="prepare-invitations-title-{{ $activeImport->id }}">Prepare regional invitations</h5><div class="text-muted small">{{ $eventRegion->region?->region_name }} · {{ $activeImport->invitations->where('status','invited')->count() }} selected recipients</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              @include('backend.partials.email-sender-fields')
              <div class="alert alert-info"><strong>Preview required.</strong> Open the actual sample email before sending. The exact message, event details and deadlines{{ $usesRegionalClothing ? ', including clothing prices,' : '' }} are snapshotted for audit and failed-email retries. Any change requires another preview.</div>
              <div class="row g-3">
                <div class="col-12"><label class="form-label">Email subject</label><input type="text" name="email_subject" maxlength="180" class="form-control" value="{{ old('email_subject', 'Platteland team invitation: '.$event->name) }}" required></div>
                <div class="col-12"><label class="form-label">Invitation message</label><textarea name="email_message" rows="4" maxlength="10000" class="form-control" required>{{ old('email_message', 'You have been selected to represent your region. Please review the event information and respond before the deadline.') }}</textarea><div class="form-text">This message appears near the top of every invitation.</div></div>
                <div class="col-12"><label class="form-label">Information shown on the player invitation page</label><textarea name="event_information" rows="7" maxlength="20000" class="form-control">{{ old('event_information', $defaultInvitationEventInformation) }}</textarea><div class="form-text">HTML from the event page is converted into readable paragraphs and bullet points. Review venues, arrival times, accommodation and team instructions before previewing the email.</div></div>
                <div class="col-md-6"><label class="form-label">Registration deadline</label><input type="datetime-local" name="registration_deadline" value="{{ old('registration_deadline', $event->registrationClosesAt()?->endOfDay()->format('Y-m-d\\TH:i')) }}" class="form-control" required><div class="form-text">One cutoff for responding, registering and paying. Reserve invitations use the same cutoff.</div></div>
                <div class="col-md-6"><div class="alert alert-light border mb-0 h-100"><strong>One send action.</strong><br><span class="small text-muted">@if($isEventManager) Confirming the send opens event registration and publishes the selected teams automatically. @else The event manager must open registration first; this send publishes the selected teams. @endif</span></div></div>

                @if($usesRegionalClothing)<div class="col-12"><input type="hidden" name="include_clothing" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="include_clothing" value="1" id="include-clothing-{{ $activeImport->id }}" @checked(old('include_clothing', $clothingAvailable)) @disabled(!$clothingAvailable)><label class="form-check-label" for="include-clothing-{{ $activeImport->id }}">Include optional regional clothing items, sizes, prices and ordering steps</label></div>@if(!$clothingAvailable)<div class="form-text text-warning">Complete this region's clothing items, sizes and approved prices, then open clothing ordering to enable this option.</div>@endif</div>@else<input type="hidden" name="include_clothing" value="0">@endif
              </div>
              <hr><div class="row g-2"><div class="col-sm-4"><div class="border rounded p-3"><small class="text-muted d-block">Invitations</small><strong>{{ $activeImport->invitations->where('status','invited')->count() }}</strong></div></div><div class="col-sm-4"><div class="border rounded p-3"><small class="text-muted d-block">Reserves held back</small><strong>{{ $activeImport->invitations->where('status','reserve')->count() }}</strong></div></div><div class="col-sm-4"><div class="border rounded p-3"><small class="text-muted d-block">Missing email</small><strong>{{ $activeImport->invitations->filter(fn($i) => !$recipientEmailFor($i))->count() }}</strong></div></div></div>
              <details class="border rounded p-3 mt-3"><summary class="fw-semibold">Review exact invitation recipients</summary><div class="table-responsive mt-3"><table class="table table-sm mb-0"><thead><tr><th>Player</th><th>Email used</th></tr></thead><tbody>@foreach($activeImport->invitations->where('status','invited')->sortBy('queue_position') as $recipientInvitation)<tr><td>{{ $recipientInvitation->player?->full_name }}</td><td>{{ $recipientEmailFor($recipientInvitation) ?: 'Skipped — no email available' }}</td></tr>@endforeach</tbody></table></div></details>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Preview email</button></div>
</form></div>
        </div>
      @endif

      <div class="modal fade" id="roster-email-{{ $eventRegion->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered"><form method="POST" action="{{ route('backend.team-selection.roster-email.send', [$event, $eventRegion]) }}" class="modal-content" data-mail-compose>@csrf
          <input type="hidden" name="target_type" value="team" data-roster-email-target>
          <input type="hidden" name="team_id" data-roster-email-team>
          <input type="hidden" name="invitation_id" data-roster-email-invitation>
          <input type="hidden" name="slot_id" data-roster-email-slot>
          <input type="hidden" name="recipient_hash" data-roster-email-hash>
          <div class="modal-header"><div><h5 class="modal-title">Email selected roster</h5><div class="small text-muted" data-roster-email-recipient></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            @include('backend.partials.email-sender-fields')
            <div class="border rounded p-3 mb-3 d-none" data-roster-filter-panel data-preview-url="{{ route('backend.team-selection.roster-email.preview', [$event, $eventRegion]) }}">
              <h6 class="mb-1">Choose players</h6>
              <p class="small text-muted mb-3">Filters are combined. Preview the exact recipient list before the email can be queued.</p>
              <div class="row g-3">
                <div class="col-12"><fieldset><legend class="form-label mb-2">Age groups</legend><div class="row g-2">@foreach($regionTeams->filter(fn($team) => $team->category_event_id)->unique('category_event_id')->sortBy(fn($team) => $team->category?->category?->name) as $filterTeam)<div class="col-sm-6 col-lg-4"><label class="form-check border rounded p-2 h-100"><input class="form-check-input ms-0 me-2" type="checkbox" name="category_event_ids[]" value="{{ $filterTeam->category_event_id }}" data-roster-filter-input disabled><span class="form-check-label">{{ $filterTeam->category?->category?->name ?: $filterTeam->name }}</span></label></div>@endforeach</div></fieldset><div class="form-text">Select one or more groups, for example U13 Girls and U12 Girls.</div></div>
                <div class="col-md-6"><label class="form-label">Gender</label><select class="form-select" name="gender" data-roster-filter-input disabled required><option value="any">All genders</option><option value="girls">Girls</option><option value="boys">Boys</option></select></div>
                <div class="col-md-6"><label class="form-label">Player status</label><select class="form-select" name="audience_status" data-roster-filter-input disabled required><option value="active">All active selected players</option><option value="entered">Entered / paid</option><option value="not_entered">Selected but not entered / paid</option><option value="invited">Invitation sent</option><option value="not_invited">Not yet invited (reserves or pending send)</option><option value="accepted">Accepted (paid or payment pending)</option><option value="not_accepted">Invited but not accepted</option><option value="declined">Declined</option><option value="withdrawn">Withdrawn</option></select></div>
              </div>
              <button class="btn btn-outline-primary mt-3" type="button" data-roster-filter-preview><i class="ti ti-list-check me-1"></i>Preview recipients</button>
              <div class="alert alert-secondary mt-3 mb-0 d-none" data-roster-filter-result></div>
              <div class="mt-3 d-none" data-roster-filter-review><strong class="small">Exact recipients</strong><div class="small text-muted mt-1" data-roster-filter-list></div></div>
            </div>
            <div class="mb-3"><label class="form-label">Subject</label><input class="form-control" name="subject" maxlength="180" required></div>
            <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="7" maxlength="20000" required></textarea></div>
            <details class="mb-3 d-none" data-roster-region-review><summary>Review all {{ $regionRosterEmailRecipients->count() }} exact regional recipient(s)</summary><div class="small text-muted mt-2">@foreach($regionRosterEmailRecipients as $recipient)<div>{{ $recipient['name'] ?: 'Player' }} · {{ $recipient['email'] }}</div>@endforeach</div></details>
            @foreach(['unlinked_imported' => $unlinkedImportedRecipients, 'linked_unpaid' => $linkedUnpaidRecipients, 'linked_all' => $allLinkedImportedRecipients] as $cohortKey => $cohortRecipients)
              <details class="mb-3 d-none" data-roster-cohort-review="{{ $cohortKey }}"><summary>Review all {{ $cohortRecipients->count() }} exact recipient(s)</summary><div class="small text-muted mt-2">@foreach($cohortRecipients as $recipient)<div>{{ $recipient['name'] ?: 'Player' }} · {{ $recipient['email'] }}</div>@endforeach</div></details>
            @endforeach
            <div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_recipients" value="1" id="confirm-roster-email-{{ $eventRegion->id }}" required><label class="form-check-label" for="confirm-roster-email-{{ $eventRegion->id }}">I confirm the recipient details above are correct</label></div>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="ti ti-send me-1"></i>Preview email</button></div>
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
</div>
