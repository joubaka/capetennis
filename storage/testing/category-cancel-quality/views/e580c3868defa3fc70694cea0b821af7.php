<div class="operational-page" <?php if($embeddedTeamsWorkspace ?? false): ?> data-embedded-teams-selection <?php endif; ?>>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!($isRegionalPreview ?? false) && !($embeddedTeamsWorkspace ?? false)): ?>
<?php echo $__env->make('backend.partials.operational-controls', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<a class="btn btn-outline-primary mb-3" href="<?php echo e(route('backend.event-mail-log.index',$event)); ?>">Event email log</a>
<?php echo $__env->make('backend.event.partials.header', [
  'event' => $event,
  'eventWorkspaceActive' => 'entries',
  'eventWorkspaceRegionalOnly' => ! $isEventManager,
  'eventWorkspaceShowHome' => $isEventManager,
], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="container-xxl flex-grow-1 container-p-y region-selection-container">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($embeddedTeamsWorkspace ?? false)): ?>
  <?php echo $__env->make('backend.event.partials.teams-workspace-nav', ['teamsWorkspaceRoster' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('replacement_confirmation')): ?>
    <?php ($replacementConfirmation = session('replacement_confirmation')); ?>
    <?php ($replacementDeliveryStatus = $replacementConfirmation['delivery_status'] ?? 'queued'); ?>
    <div class="alert alert-<?php echo e($replacementDeliveryStatus === 'failed' ? 'danger' : 'success'); ?> d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div>
        <strong>Player replaced.</strong>
        <?php echo e($replacementConfirmation['player_name']); ?> was added and the invitation email was
        <strong><?php echo e($replacementDeliveryStatus === 'sent' ? 'sent' : $replacementDeliveryStatus); ?></strong>
        to <?php echo e($replacementConfirmation['recipient_email']); ?>.
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($replacementDeliveryStatus === 'queued'): ?>
          <span class="d-block small mt-1">The application has queued it for the mail service. The player row below will show mail-server acceptance, an unverified result, or failure when processing finishes.</span>
        <?php elseif($replacementDeliveryStatus === 'failed'): ?>
          <span class="d-block small mt-1">Delivery failed. Review the saved email and use Resend after the mail issue is resolved.</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <a class="btn btn-sm btn-outline-success" target="_blank" href="<?php echo e($replacementConfirmation['preview_url']); ?>">View email</a>
    </div>
  <?php elseif(session('success')): ?><div class="alert alert-success"><?php echo e(session('success')); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger"><strong>Action blocked.</strong><ul class="mb-0 mt-2"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEventManager): ?>
    <details class="regional-help mb-3"><summary>Event setup guidance</summary><div class="p-3 border-top">Link each ranking-fed region to its own published series. Imported outside-region rosters can remain unlinked and will not be changed.</div></details>
    <?php ($eventFilterTeams = $teams->flatten(1)->filter(fn($team) => $team->category_event_id)->unique('category_event_id')->sortBy(fn($team) => $team->category?->category?->name)); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventRegions->isNotEmpty() && $eventFilterTeams->isNotEmpty()): ?>
      <div class="d-flex justify-content-end mb-3" data-event-roster-email-action>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#event-roster-email"><i class="ti ti-mail-forward me-1"></i>Email selected players across regions</button>
      </div>
      <div class="modal fade" id="event-roster-email" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><form method="POST" action="<?php echo e(route('backend.team-selection.event-roster-email.send', $event)); ?>" class="modal-content" data-mail-compose data-event-roster-email-form><?php echo csrf_field(); ?>
          <input type="hidden" name="recipient_hash" data-event-roster-email-hash>
          <input type="hidden" name="send_token" data-event-roster-email-token>
          <div class="modal-header"><div><h5 class="modal-title">Email selected players</h5><div class="small text-muted">Choose regions, then teams, then the exact players. Nothing is queued until the final reviewed list is confirmed.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <style>
            #event-roster-email .event-audience-modal-body { overflow-x: hidden; }
            #event-roster-email .event-audience-min-width { min-width: 0; }
            #event-roster-email .event-audience-wrap { overflow-wrap: anywhere; word-break: break-word; white-space: normal; }
          </style>
          <div class="modal-body event-audience-modal-body">
            <?php echo $__env->make('backend.partials.email-sender-fields', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <div class="border rounded p-3 mb-3" data-event-roster-filter-panel data-preview-url="<?php echo e(route('backend.team-selection.event-roster-email.preview', $event)); ?>">
              <div class="d-flex gap-2 mb-3" aria-label="Audience selection progress"><span class="badge bg-primary" data-event-roster-step-badge="1">1. Regions</span><span class="badge bg-label-secondary" data-event-roster-step-badge="2">2. Teams</span><span class="badge bg-label-secondary" data-event-roster-step-badge="3">3. Players</span></div>
              <section data-event-roster-step="1" tabindex="-1"><fieldset><legend class="form-label mb-2">Select one or more regions</legend><div class="row g-2"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventRegions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filterRegion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="col-sm-6 col-lg-3"><label class="form-check border rounded p-2 h-100 event-audience-min-width"><input class="form-check-input ms-0 me-2 flex-shrink-0" type="checkbox" name="event_region_ids[]" value="<?php echo e($filterRegion->id); ?>" data-event-roster-region><span class="form-check-label event-audience-wrap event-audience-min-width"><?php echo e(rawurldecode($filterRegion->region?->region_name ?? '')); ?></span></label></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></fieldset><fieldset class="mt-3"><legend class="form-label mb-2">Choose which players to load</legend><div class="d-flex flex-wrap gap-3"><label class="form-check"><input class="form-check-input" type="radio" name="audience_mode" value="roster" checked data-event-roster-mode> Current roster players</label><label class="form-check"><input class="form-check-input" type="radio" name="audience_mode" value="ranking" data-event-roster-mode> Players on the current published ranking</label></div><div class="form-text">Published-ranking mode includes players beyond the team and reserve places.</div><div class="mt-3 d-none" data-event-ranking-status-wrap><label class="form-label" for="event-ranking-status">Which ranked players?</label><select class="form-select" id="event-ranking-status" name="ranking_status" data-event-ranking-status data-event-roster-filter><option value="all">All ranked players</option><option value="unregistered">Unregistered only</option><option value="declined">Declined only</option><option value="unregistered_or_declined">Unregistered or declined</option></select><div class="form-text">Registered means payment is confirmed. Invited players and players awaiting payment are treated as unregistered.</div></div></fieldset><button class="btn btn-primary mt-3" type="button" data-event-roster-load-teams>Show teams</button></section>
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
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php else: ?>
    <div class="alert alert-info">You are viewing team selection, invitations and announcements for your assigned region.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventRegions->count() > 1): ?>
    <div class="event-region-selector mb-3" <?php if($embeddedTeamsWorkspace ?? false): ?> hidden <?php endif; ?>>
      <label class="form-label" for="event-region">Region</label>
      <select class="form-select" id="event-region" data-region-select>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventRegions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventRegion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <option value="<?php echo e($eventRegion->id); ?>"><?php echo e($eventRegion->region?->region_name); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </select>
    </div>
    <div class="nav nav-tabs d-none" role="tablist" aria-label="Event regions" data-region-tabs>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventRegions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventRegion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <button
          type="button"
          class="nav-link text-nowrap <?php echo e($loop->first ? 'active' : ''); ?>"
          id="region-tab-<?php echo e($eventRegion->id); ?>"
          data-bs-toggle="tab"
          data-bs-target="#region-panel-<?php echo e($eventRegion->id); ?>"
          data-region-id="<?php echo e($eventRegion->id); ?>"
          role="tab"
          aria-controls="region-panel-<?php echo e($eventRegion->id); ?>"
          aria-selected="<?php echo e($loop->first ? 'true' : 'false'); ?>"
        ><?php echo e($eventRegion->region?->region_name); ?></button>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="region-workspaces <?php echo e($eventRegions->count() > 1 ? 'tab-content' : 'row g-3'); ?>">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventRegions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventRegion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php ($source = $eventRegion->rankingSource); ?>
      <?php ($canManageRegion = app(\App\Services\TeamSelection\RegionManagerAccessService::class)->canManage(auth()->user(), $eventRegion)); ?>
      <?php ($sourceReady = $source && $readySeriesIds->contains($source->series_id)); ?>
      <?php ($regionTeams = $teams->get($eventRegion->region_id, collect())); ?>
      <?php ($categorySetup = $source ? $categorySetups->get($source->id) : null); ?>
      <?php ($activeImport = $source?->imports?->whereIn('status', ['draft','sent'])->sortByDesc('id')->first()); ?>
      <?php ($contactEmailsFor = fn($invitation) => $teamSelectionContacts->emails($invitation->player)); ?>
      <?php ($rawContactEmailsFor = fn($invitation) => $teamSelectionContacts->rawEmails($invitation->player)); ?>
      <?php ($recipientEmailFor = fn($invitation) => $contactEmailsFor($invitation)->first()); ?>
      <?php ($usesRegionalClothing = (bool) $eventRegion->region?->usesOnlineClothingOrders()); ?>
      <?php ($regionClothingItems = $eventRegion->region?->clothingItems ?? collect()); ?>
      <?php ($clothingCatalogueReady = $regionClothingItems->isNotEmpty() && $regionClothingItems->every(fn($item) => (float)$item->price > 0 && $item->sizes->isNotEmpty())); ?>
      <?php ($clothingAvailable = $usesRegionalClothing && (bool)$eventRegion->region?->clothing_order && $clothingCatalogueReady); ?>
      <?php ($regionManager = $regionManagers->get($eventRegion->id)); ?>
      <?php ($defaultCandidates = $defaultRegionManagerCandidates->get($eventRegion->id, collect())); ?>
      <?php ($regionAnnouncementRecipients = $announcementRecipients->get($eventRegion->id, collect())); ?>
      <?php ($regionRosterEmailRecipients = $regionRosterRecipients->get($eventRegion->id, collect())); ?>
      <?php ($regionImportedCohorts = $importedRecipientCohorts->get($eventRegion->id, collect())); ?>
      <?php ($unlinkedImportedRecipients = $regionImportedCohorts->get('unlinked_imported', collect())); ?>
      <?php ($linkedUnpaidRecipients = $regionImportedCohorts->get('linked_unpaid', collect())); ?>
      <?php ($allLinkedImportedRecipients = $regionImportedCohorts->get('linked_all', collect())); ?>
      <?php ($unpublishedTeamCount = $regionTeams->where('published', false)->count()); ?>
      <?php ($selectionSent = $activeImport?->status === 'sent'); ?>
      <?php ($pendingActivatedInvitations = $activeImport?->invitations?->filter(fn($invitation) => $invitation->status === \App\Models\TeamSelectionInvitation::INVITED && $invitation->roster_rank && !$invitation->invited_at && data_get($invitation->snapshot_json, 'activation.pending_manual_invitation')) ?? collect()); ?>
      <?php ($pendingActivatedRecipients = $pendingActivatedInvitations->filter(fn($invitation) => (bool) $recipientEmailFor($invitation))); ?>
      <?php ($customEmailRecipients = $activeImport?->invitations?->filter(fn($invitation) => in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, \App\Models\TeamSelectionInvitation::PAID_CONFIRMED], true) && $invitation->roster_rank && (bool) $recipientEmailFor($invitation)) ?? collect()); ?>
      <?php ($pendingActivatedRecipientHash = hash('sha256', $pendingActivatedRecipients->map(fn($invitation) => $invitation->id.'|'.mb_strtolower(trim((string) $recipientEmailFor($invitation))))->sort()->values()->implode("\n"))); ?>
      <?php ($selectedInvitations = $activeImport?->invitations?->whereIn('status', [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, \App\Models\TeamSelectionInvitation::PAID_CONFIRMED]) ?? collect()); ?>
      <?php ($outstandingRegistrationCount = $selectedInvitations->whereNotIn('status', [\App\Models\TeamSelectionInvitation::PAID_CONFIRMED])->count()); ?>
      <?php ($regionalOpenPlaces = $regionTeams->sum(fn($team) => max(0, (int) $team->num_team_members - $selectedInvitations->where('team_id', $team->id)->count()))); ?>
      <?php ($missingContactCount = $activeImport?->invitations?->filter(fn($invitation) => ! $recipientEmailFor($invitation))->count() ?? 0); ?>
      <?php ($selectionSteps = collect([
        ['label' => 'Link ranking series', 'tab' => 'setup', 'complete' => (bool) $source],
        ['label' => 'Create teams', 'tab' => 'setup', 'complete' => $regionTeams->isNotEmpty()],
        ['label' => 'Import players', 'tab' => 'invitations', 'complete' => (bool) $activeImport],
        ['label' => 'Send invitations', 'tab' => 'invitations', 'complete' => $selectionSent],
        ['label' => 'Resolve exceptions', 'tab' => 'teams', 'complete' => $selectionSent && $outstandingRegistrationCount === 0 && $regionalOpenPlaces === 0 && $missingContactCount === 0],
        ['label' => 'Publish teams', 'tab' => 'teams', 'complete' => $regionTeams->isNotEmpty() && $unpublishedTeamCount === 0],
      ])); ?>
      <?php ($currentSelectionStep = $selectionSteps->search(fn($step) => ! $step['complete'])); ?>
      <div
        id="region-panel-<?php echo e($eventRegion->id); ?>"
        class="<?php echo e($eventRegions->count() > 1 ? 'tab-pane fade'.($loop->first ? ' show active' : '') : 'col-12'); ?>"
        role="tabpanel"
        aria-labelledby="region-tab-<?php echo e($eventRegion->id); ?>"
        tabindex="0"
      >
        <div class="card region-workspace-card">
          <div class="card-header d-flex flex-wrap justify-content-between gap-2">
            <div><h5 class="mb-1"><?php echo e($eventRegion->region?->region_name); ?></h5><span class="region-workspace-meta small"><?php echo e($regionTeams->count()); ?> teams · <?php echo e($regionTeams->sum('num_team_members')); ?> configured places</span></div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeImport): ?>
              <span class="badge bg-label-<?php echo e($activeImport->status === 'sent' ? 'success' : 'warning'); ?>"><?php echo e(ucfirst($activeImport->status)); ?></span>
            <?php elseif($source): ?>
              <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge bg-label-primary">Series linked</span>
                <form method="POST" action="<?php echo e(route('backend.team-selection.unlink', [$event, $source])); ?>" onsubmit="return confirm('Unlink this ranking series? Existing event categories and teams will be kept.');">
                  <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ti ti-unlink me-1"></i>Unlink series</button>
                </form>
              </div>
            <?php else: ?>
              <span class="badge bg-label-secondary">Manual/imported or not linked</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
          <div class="card-body">
            <div class="nav nav-pills region-task-tabs" role="tablist" aria-label="<?php echo e($eventRegion->region?->region_name); ?> workspace" data-region-task-tabs="<?php echo e($eventRegion->id); ?>">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['teams' => ['ti-users-group', 'Selection & reserves'], 'invitations' => ['ti-file-import', 'Import & send'], 'messages' => ['ti-message-circle', $usesRegionalClothing ? 'Messages & clothing' : 'Messages'], 'overview' => ['ti-layout-dashboard', 'Help & progress'], 'setup' => ['ti-settings', 'Ranking setup']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $taskKey => [$taskIcon, $taskLabel]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($taskKey === 'overview'): ?><details class="regional-more-options"><summary>More options</summary><div class="regional-more-options-menu"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <button class="nav-link <?php echo e($loop->first ? 'active' : ''); ?>" id="region-<?php echo e($eventRegion->id); ?>-<?php echo e($taskKey); ?>-tab" data-bs-toggle="tab" data-bs-target="#region-<?php echo e($eventRegion->id); ?>-<?php echo e($taskKey); ?>" type="button" role="tab" aria-controls="region-<?php echo e($eventRegion->id); ?>-<?php echo e($taskKey); ?>" aria-selected="<?php echo e($loop->first ? 'true' : 'false'); ?>" data-region-task="<?php echo e($taskKey); ?>"><i class="ti <?php echo e($taskIcon); ?>"></i><?php echo e($taskLabel); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($taskKey === 'teams' && $unpublishedTeamCount): ?><span class="badge bg-label-warning"><?php echo e($unpublishedTeamCount); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loop->last): ?></div></details><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="tab-content">
              <div class="tab-pane fade region-task-panel" id="region-<?php echo e($eventRegion->id); ?>-overview" role="tabpanel" aria-labelledby="region-<?php echo e($eventRegion->id); ?>-overview-tab" tabindex="0">
                <div class="mb-3">
                  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h6 class="mb-1">Selection progress</h6><p class="text-muted small mb-0">Click the highlighted next step to open the next available options, or open any available area directly.</p></div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($currentSelectionStep === false): ?><span class="badge bg-label-success"><i class="ti ti-circle-check me-1"></i>Workflow complete</span><?php else: ?><span class="badge bg-label-primary">Step <?php echo e($currentSelectionStep + 1); ?> of <?php echo e(count($selectionSteps)); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                  <ol class="selection-progress" aria-label="Regional selection progress">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $selectionSteps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stepIndex => $selectionStep): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <?php ($stepState = $selectionStep['complete'] ? 'is-complete' : ($currentSelectionStep === $stepIndex ? 'is-current' : 'is-upcoming')); ?>
                      <li class="selection-progress-step <?php echo e($stepState); ?>" data-step="<?php echo e($stepIndex + 1); ?>">
                        <button type="button" class="btn btn-link" data-open-region-task="<?php echo e($selectionStep['tab']); ?>"><?php echo e($selectionStep['label']); ?></button>
                        <small><?php echo e($selectionStep['complete'] ? 'Complete' : ($currentSelectionStep === $stepIndex ? 'Next action' : 'Not started')); ?></small>
                      </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </ol>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManageRegion): ?>
              <div class="modal fade" id="final-team-reminders-<?php echo e($eventRegion->id); ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered"><form method="POST" action="<?php echo e(route('backend.team-selection.final-reminders.send', [$event, $eventRegion])); ?>" class="modal-content" data-mail-launch data-final-reminder-form data-clothing-enabled="<?php echo e($usesRegionalClothing ? '1' : '0'); ?>" data-reminder-summaries='<?php echo json_encode($reminderSummaries[$eventRegion->id] ?? [], 15, 512) ?>' data-reminder-hashes='<?php echo json_encode($reminderHashes[$eventRegion->id] ?? [], 15, 512) ?>'><?php echo csrf_field(); ?>
                  <input type="hidden" name="send_token" value="<?php echo e((string) \Illuminate\Support\Str::uuid()); ?>">
                  <input type="hidden" name="recipient_hash" data-reminder-hash>
                  <div class="modal-header"><div><h5 class="modal-title">Send <?php echo e($eventRegion->region?->region_name); ?> reminders</h5><div class="small text-muted">Only active invitations in this region are included.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                  <div class="modal-body">
                    <div class="row g-3">
                      <div class="col-md-6"><label class="form-label">Reminder</label><select class="form-select" name="kind" data-reminder-kind required><option value="registration_clothing"><?php echo e($usesRegionalClothing ? 'Registration and clothing reminder' : 'Registration reminder'); ?></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($usesRegionalClothing): ?><option value="incomplete_clothing">Incomplete clothing reminder</option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
                      <div class="col-md-6"><label class="form-label">Send to</label><select class="form-select" name="audience" data-reminder-audience required><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($usesRegionalClothing): ?><option value="all">All active players in this region</option><option value="registered">Registered players in this region</option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><option value="unregistered">Unregistered players in this region</option></select></div>
                    </div>
                    <div class="alert alert-primary mt-3 mb-3" data-reminder-summary></div>
                    <div class="border rounded p-3 bg-light"><strong data-reminder-preview-title>Registration is closing</strong><p class="mb-1 mt-2" data-reminder-preview-copy><?php echo e($usesRegionalClothing ? 'Unregistered players receive their registration/payment link. Registered players receive their clothing action link.' : 'Unregistered players receive their registration/payment link.'); ?></p><small class="text-muted">One email is sent per address. Where a parent receives mail for several players in this region, all affected players and their individual links are included.</small></div>
                    <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="confirm_recipients" value="1" id="confirm-final-reminders-<?php echo e($eventRegion->id); ?>" required><label class="form-check-label" for="confirm-final-reminders-<?php echo e($eventRegion->id); ?>">I reviewed this region’s reminder and recipient group.</label></div>
                  </div>
                  <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" data-reminder-submit>Write email</button></div>
                </form></div>
              </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeImport): ?>
              <div class="regional-summary mb-3" aria-label="Regional roster summary">
                <span class="regional-summary-item"><strong><?php echo e($selectedInvitations->count()); ?></strong> selected</span>
                <span class="regional-summary-item"><strong><?php echo e($activeImport->invitations->where('status', \App\Models\TeamSelectionInvitation::RESERVE)->count()); ?></strong> reserves</span>
                <span class="regional-summary-item"><strong><?php echo e($activeImport->invitations->where('status', \App\Models\TeamSelectionInvitation::PAID_CONFIRMED)->count()); ?></strong> paid</span>
                <span class="regional-summary-item"><strong><?php echo e($missingContactCount); ?></strong> need contact</span>
              </div>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($outstandingRegistrationCount || $regionalOpenPlaces || $missingContactCount): ?>
                <div class="alert alert-warning mb-3">
                  <strong class="d-block mb-2">What needs attention</strong>
                  <div class="regional-attention">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($outstandingRegistrationCount): ?><span class="badge bg-label-warning"><?php echo e($outstandingRegistrationCount); ?> registration/payment <?php echo e(\Illuminate\Support\Str::plural('response', $outstandingRegistrationCount)); ?> outstanding</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($regionalOpenPlaces): ?><span class="badge bg-label-danger"><?php echo e($regionalOpenPlaces); ?> open team <?php echo e(\Illuminate\Support\Str::plural('place', $regionalOpenPlaces)); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($missingContactCount): ?><span class="badge bg-label-danger"><?php echo e($missingContactCount); ?> without contact email</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div>
                </div>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <details class="regional-help mb-3">
              <summary class="d-flex justify-content-between align-items-center gap-2"><span><strong><i class="ti ti-help-circle me-1"></i>How to manage teams in this region</strong><span class="d-block small text-muted mt-1">Replacement, reserves, reminders<?php echo e($usesRegionalClothing ? ', clothing' : ''); ?> and publishing instructions.</span></span><span class="badge bg-label-primary">View steps</span></summary>
              <div class="border-top p-3">
                <div class="row g-3 small">
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Replace an unpaid player</strong><div class="text-muted">Select <strong>Show team</strong>, find the player, choose <strong>Change player</strong>, select the next reserve or another player profile, give a reason, then confirm. Paid players cannot be replaced here.</div></div>
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Leave an unavailable player's place vacant</strong><div class="text-muted">Select <strong>Show team</strong>, open the player's action menu, choose <strong>Mark unavailable / release place</strong>, give a reason and confirm. No reserve is needed. This action is for unpaid players.</div></div>
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Fill an open place</strong><div class="text-muted">Select <strong>Show team</strong>. A withdrawn or declined place shows <strong>Invite next reserve</strong>; an eligible reserve may also show <strong>Activate as Rank</strong>.</div></div>
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Change player order</strong><div class="text-muted">Select <strong>Show team</strong>, open <strong>Player order</strong>, then drag players into the required order or use Move up and Move down.</div></div>
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Contact outstanding players</strong><div class="text-muted">Use <strong>Registration reminder</strong><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($usesRegionalClothing): ?> or <strong>Incomplete clothing reminder</strong><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> above. Review the regional audience and exact recipient count before sending.</div></div>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($usesRegionalClothing): ?><div class="col-md-6 col-xl-4 regional-help-step"><strong>Manage clothing</strong><div class="text-muted">Use <strong>Clothing setup</strong> above to review items and sizes. Use <strong>Region actions</strong> to open or close clothing ordering.</div></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <div class="col-md-6 col-xl-4 regional-help-step"><strong>Publish teams</strong><div class="text-muted">Resolve open places first, review each team, then select <strong>Publish all teams</strong>. Published teams can still be opened and reviewed.</div></div>
                </div>
              </div>
            </details>

              </div>
              <div class="tab-pane fade show active region-task-panel" id="region-<?php echo e($eventRegion->id); ?>-teams" role="tabpanel" aria-labelledby="region-<?php echo e($eventRegion->id); ?>-teams-tab" tabindex="0">

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($embeddedTeamsWorkspace ?? false)): ?>
            <div class="border rounded p-3 mb-3">
              <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                <div><small class="text-muted d-block">Regional organizer</small><strong><?php echo e($regionManager?->name ?: trim(($regionManager?->userName ?? '').' '.($regionManager?->userSurname ?? '')) ?: $regionManager?->email ?: 'Not assigned'); ?></strong><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($regionManager?->email): ?><div class="small text-muted"><?php echo e($regionManager->email); ?> · player profile not required</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEventManager && $eventRegion->managerAssignment): ?><span class="badge bg-label-primary">Custom assignment</span><?php elseif($regionManager): ?><span class="badge bg-label-secondary">Series organizer default</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEventManager): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->hasRole('super-user')): ?>
                  <a class="btn btn-outline-secondary mt-2" href="<?php echo e(route('backend.team-selection.manager.preview', [$event, $eventRegion])); ?>">Preview regional organizer view</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <form method="POST" action="<?php echo e(route('backend.team-selection.manager.assign', [$event, $eventRegion])); ?>" class="row g-2 align-items-end mt-1"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                  <div class="col-md-7"><label class="form-label" for="region-manager-<?php echo e($eventRegion->id); ?>">Select a system user</label><select id="region-manager-<?php echo e($eventRegion->id); ?>" name="manager_user_id" class="form-select region-manager-select" data-placeholder="Search by name or email…"><option value=""></option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventRegion->managerAssignment && $regionManager): ?><option value="<?php echo e($regionManager->id); ?>" selected><?php echo e(trim($regionManager->name ?: (($regionManager->userName ?? '').' '.($regionManager->userSurname ?? '')))); ?> · <?php echo e($regionManager->email); ?></option><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
                  <div class="col-md-3 d-grid"><button class="btn btn-outline-primary">Assign to this region</button></div>
                  <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary" name="use_default" value="1" <?php if(!$defaultRegionManagers->get($eventRegion->id)): echo 'disabled'; endif; ?>>Use default</button></div>
                </form>
                <div class="form-text">This grants access to this region; it does not remove existing event-wide or other-region access. A player profile is not required.</div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$eventRegion->managerAssignment && $defaultCandidates->count() > 1): ?><div class="alert alert-warning mt-2 mb-0">This series has multiple common event organizers. No default was selected automatically; assign the intended account explicitly.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="regional-readonly rounded p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div><strong>Manage your teams</strong><div class="small text-muted">Choose <strong>Show team</strong> below to see players. <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeImport): ?>Use <strong>Change player</strong> to replace an unpaid player.<?php else: ?> Use the player row to correct a name or email.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> Open <strong>Player order</strong> to move players up or down.</div></div>
              <div class="d-flex flex-wrap gap-2 align-items-center">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeImport?->status === 'draft'): ?>
                  <button class="btn btn-sm btn-success" type="button" data-bs-toggle="modal" data-bs-target="#prepare-invitations-<?php echo e($activeImport->id); ?>"><i class="ti ti-send me-1"></i>Send all invitations</button>
                <?php elseif($unpublishedTeamCount > 0): ?>
                  <button type="button" class="btn btn-sm btn-success publish-all-teams" data-url="<?php echo e(route('backend.team-selection.teams.publish-all', [$event, $eventRegion])); ?>"><i class="ti ti-world-upload me-1"></i>Publish all teams</button>
                <?php elseif($regionTeams->isNotEmpty()): ?>
                  <span class="badge bg-label-success"><i class="ti ti-circle-check me-1"></i>All teams published</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="dropdown">
                  <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="ti ti-dots me-1"></i>Region actions</button>
                  <div class="dropdown-menu dropdown-menu-end">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeImport?->status === 'draft' && $unpublishedTeamCount > 0): ?>
                      <button type="button" class="dropdown-item publish-all-teams" data-url="<?php echo e(route('backend.team-selection.teams.publish-all', [$event, $eventRegion])); ?>"><i class="ti ti-world-upload me-2"></i>Publish all teams</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManageRegion): ?>
                      <button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#reimport-roster-<?php echo e($eventRegion->id); ?>"><i class="ti ti-file-spreadsheet me-2"></i>Re-import roster contacts</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($unlinkedImportedRecipients->isNotEmpty()): ?>
                      <button class="dropdown-item roster-email-button" type="button" data-bs-toggle="modal" data-bs-target="#roster-email-<?php echo e($eventRegion->id); ?>" data-target-type="unlinked_imported" data-recipient="<?php echo e($unlinkedImportedRecipients->count()); ?> unlinked imported player email(s)" data-recipient-hash="<?php echo e(hash('sha256', $unlinkedImportedRecipients->pluck('email')->toJson())); ?>"><i class="ti ti-user-question me-2"></i>Email unlinked / not registered</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($linkedUnpaidRecipients->isNotEmpty()): ?>
                      <button class="dropdown-item roster-email-button" type="button" data-bs-toggle="modal" data-bs-target="#roster-email-<?php echo e($eventRegion->id); ?>" data-target-type="linked_unpaid" data-recipient="<?php echo e($linkedUnpaidRecipients->count()); ?> linked player email(s) still not registered/paid" data-recipient-hash="<?php echo e(hash('sha256', $linkedUnpaidRecipients->pluck('email')->toJson())); ?>"><i class="ti ti-credit-card-off me-2"></i>Email linked, not registered / paid</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($allLinkedImportedRecipients->isNotEmpty()): ?>
                      <button class="dropdown-item roster-email-button" type="button" data-bs-toggle="modal" data-bs-target="#roster-email-<?php echo e($eventRegion->id); ?>" data-target-type="linked_all" data-recipient="<?php echo e($allLinkedImportedRecipients->count()); ?> linked player email(s) in <?php echo e($eventRegion->region?->region_name); ?>" data-recipient-hash="<?php echo e(hash('sha256', $allLinkedImportedRecipients->pluck('email')->toJson())); ?>"><i class="ti ti-users me-2"></i>Email all linked players</button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventRegion->region?->usesOnlineClothingOrders()): ?>
                      <div class="dropdown-divider"></div>
                      <div class="region-action-status" data-clothing-status><?php echo e($eventRegion->region->clothing_order ? 'Clothing ordering open' : 'Clothing ordering closed'); ?></div>
                      <form method="POST" action="<?php echo e(route('backend.region.clothing.toggle', $eventRegion->region_id)); ?>" class="clothing-order-form">
                        <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                        <button type="submit" class="dropdown-item clothing-order-toggle" data-catalogue-ready="<?php echo e($clothingCatalogueReady ? '1' : '0'); ?>" <?php if(! $eventRegion->region->clothing_order && ! $clothingCatalogueReady): echo 'disabled'; endif; ?> <?php if(! $eventRegion->region->clothing_order && ! $clothingCatalogueReady): ?> title="Finish clothing setup before opening orders" <?php endif; ?>><i class="ti ti-<?php echo e($eventRegion->region->clothing_order ? 'lock' : 'shopping-cart'); ?> me-2"></i><?php echo e($eventRegion->region->clothing_order ? 'Close ordering' : 'Open ordering'); ?></button>
                      </form>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div>
                </div>
              </div>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($regionTeams->isNotEmpty()): ?>
              <div class="row g-3 mb-3">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regionTeams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $regionTeam): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <?php ($teamInvitations = $activeImport?->invitations?->where('team_id', $regionTeam->id)->sortBy('queue_position') ?? collect()); ?>
                  <?php ($teamSelected = $teamInvitations->whereIn('status', [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, \App\Models\TeamSelectionInvitation::PAID_CONFIRMED])); ?>
                  <?php ($teamOpenPlaceCount = max(0, (int) $regionTeam->num_team_members - $teamSelected->count())); ?>
                  <?php ($teamReserves = $teamInvitations->where('status', \App\Models\TeamSelectionInvitation::RESERVE)); ?>
                  <?php ($eligibleTeamReserves = $teamReserves->filter(fn($reserve) => $recipientEmailFor($reserve))); ?>
                  <?php ($openRosterRanks = (int) $regionTeam->num_team_members > 0 ? collect(range(1, (int) $regionTeam->num_team_members))->reject(fn($rank) => $teamSelected->contains(fn($selected) => (int) $selected->roster_rank === $rank))->values() : collect()); ?>
                  <?php ($openRosterRanks = $openRosterRanks->filter(fn($rank) => $regionTeam->team_players->where('rank', $rank)->count() < 2 && !$regionTeam->team_players->contains(fn($slot) => (int) $slot->rank === $rank && ((int) $slot->player_id !== 0 || (int) $slot->pay_status !== 0)))->values()); ?>
                  <?php ($importedRoster = $regionTeam->team_players_no_profile->sortBy('rank')->values()); ?>
                  <?php ($linkedImportedCount = $importedRoster->whereNotNull('player_profile')->count()); ?>
                  <div class="col-12">
                    <div class="card regional-team-card" data-team-card-id="<?php echo e($regionTeam->id); ?>">
                      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2" data-team-workspace-header data-team-workspace-target="#team-workspace-<?php echo e($regionTeam->id); ?>">
                        <div>
                          <h6 class="mb-1"><?php echo e($regionTeam->name); ?></h6>
                          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeImport): ?>
                            <span class="text-muted small" data-team-roster-summary><?php echo e($teamSelected->count()); ?> selected · <?php echo e($teamReserves->count()); ?> reserves · <?php echo e($regionTeam->num_team_members); ?> configured places</span>
                          <?php else: ?>
                            <span class="text-muted small"><?php echo e($importedRoster->count()); ?> roster players · <?php echo e($linkedImportedCount); ?> linked · <?php echo e($importedRoster->count() - $linkedImportedCount); ?> unlinked · <?php echo e($regionTeam->num_team_members); ?> configured places</span>
                          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center team-workspace-actions">
                          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeImport): ?><span class="badge bg-label-danger <?php echo e($teamOpenPlaceCount > 0 ? '' : 'd-none'); ?>" data-team-open-places><?php echo e($teamOpenPlaceCount); ?> open <?php echo e(\Illuminate\Support\Str::plural('place', $teamOpenPlaceCount)); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                          <span class="badge <?php echo e($regionTeam->published ? 'bg-label-success' : 'bg-label-secondary'); ?>" data-team-publication-status><?php echo e($regionTeam->published ? 'Published' : 'Not published'); ?></span>
                          <button class="btn btn-sm btn-primary team-workspace-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#team-workspace-<?php echo e($regionTeam->id); ?>" aria-controls="team-workspace-<?php echo e($regionTeam->id); ?>" aria-expanded="false"><i class="ti ti-eye me-1"></i><span>Show team</span></button>
                          <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions for <?php echo e($regionTeam->name); ?>"><i class="ti ti-dots-vertical"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                              <button type="button" class="dropdown-item team-publication-button" data-url="<?php echo e(route('backend.team-selection.teams.publication.update', [$event, $eventRegion, $regionTeam])); ?>" data-team-id="<?php echo e($regionTeam->id); ?>" data-published="<?php echo e($regionTeam->published ? '1' : '0'); ?>"><i class="ti ti-<?php echo e($regionTeam->published ? 'world-off' : 'world-upload'); ?> me-2"></i><span><?php echo e($regionTeam->published ? 'Unpublish' : 'Publish'); ?></span></button>
                              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeImport ? $teamSelected->isNotEmpty() : $importedRoster->isNotEmpty()): ?><button class="dropdown-item roster-email-button" type="button" data-bs-toggle="modal" data-bs-target="#roster-email-<?php echo e($eventRegion->id); ?>" data-target-type="team" data-team-id="<?php echo e($regionTeam->id); ?>" data-recipient="<?php echo e($activeImport ? $teamSelected->count() : $importedRoster->count()); ?> players in <?php echo e($regionTeam->name); ?>"><i class="ti ti-mail me-2"></i>Email team</button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                              <button class="dropdown-item team-settings-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#team-settings-<?php echo e($regionTeam->id); ?>" aria-controls="team-settings-<?php echo e($regionTeam->id); ?>" aria-expanded="false"><i class="ti ti-settings me-2"></i><span>Team settings</span></button>
                            </div>
                          </div>
                        </div>
                      </div>
                      <div class="collapse" id="team-settings-<?php echo e($regionTeam->id); ?>">
                        <div class="card-body border-bottom">
                          <form method="POST" action="<?php echo e(route('backend.team-selection.teams.update', [$event, $eventRegion, $regionTeam])); ?>" class="row g-2 align-items-end"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                            <input type="hidden" name="settings_team_id" value="<?php echo e($regionTeam->id); ?>">
                            <div class="col-md-5"><label class="form-label">Team name</label><input name="name" value="<?php echo e($regionTeam->name); ?>" class="form-control" maxlength="255" required></div>
                            <div class="col-md-2"><label class="form-label">Players in team</label><input type="number" name="num_team_members" value="<?php echo e((int) old('settings_team_id') === (int) $regionTeam->id ? old('num_team_members', $regionTeam->num_team_members) : $regionTeam->num_team_members); ?>" class="form-control" min="1" max="50" required></div>
                            <div class="col-md-3"><input type="hidden" name="published" value="0"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="published" value="1" id="published-team-<?php echo e($regionTeam->id); ?>" <?php if($regionTeam->published): echo 'checked'; endif; ?>><label class="form-check-label" for="published-team-<?php echo e($regionTeam->id); ?>">Published for registration</label></div></div>
                            <div class="col-md-2 d-grid"><button class="btn btn-primary">Save team</button></div>
                            <div class="col-12 form-text">Increasing the number adds open player places. Reducing it moves the highest unpaid selected players into the reserve queue; accepted or paid players remain protected.</div>
                          </form>
                        </div>
                      </div>
                      <div class="collapse" id="team-workspace-<?php echo e($regionTeam->id); ?>">
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeImport): ?>
                        <ul class="nav nav-tabs px-3 pt-3" role="tablist">
                          <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#team-players-<?php echo e($regionTeam->id); ?>" type="button"><i class="ti ti-users me-1"></i>Players</button></li>
                          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#team-order-<?php echo e($regionTeam->id); ?>" type="button"><i class="ti ti-list-numbers me-1"></i>Player order</button></li>
                        </ul>
                        <div class="tab-content p-0">
                        <div class="tab-pane fade show active" id="team-players-<?php echo e($regionTeam->id); ?>">
                        <form method="POST" action="<?php echo e(route('backend.team-selection.players.add', [$event, $activeImport, $regionTeam])); ?>" class="row g-2 align-items-end p-3 border-bottom" data-add-team-player-form>
                          <?php echo csrf_field(); ?>
                          <input type="hidden" name="add_team_id" value="<?php echo e($regionTeam->id); ?>">
                          <div class="col-lg-5">
                            <label class="form-label" for="add-player-<?php echo e($regionTeam->id); ?>">Add an existing system player profile</label>
                            <select id="add-player-<?php echo e($regionTeam->id); ?>" name="player_id" class="form-select team-player-select" data-placeholder="Search player name, email or cell…" data-search-url="<?php echo e(route('backend.team-selection.players.search', [$event, $activeImport, $regionTeam])); ?>" required><option value=""></option></select>
                          </div>
                          <div class="col-lg-5">
                            <label class="form-label" for="add-player-reason-<?php echo e($regionTeam->id); ?>">Reason</label>
                            <input id="add-player-reason-<?php echo e($regionTeam->id); ?>" type="text" name="reason" class="form-control" maxlength="1000" placeholder="Why this player is being added" required>
                          </div>
                          <div class="col-lg-2 d-grid"><button class="btn btn-outline-primary" data-add-team-player-submit><i class="ti ti-user-plus me-1"></i><span>Add as reserve</span></button></div>
                          <div class="col-12 form-text">System player profiles are shown. Player-profile email is used first, followed by a parent or linked-account email. The player is appended to the reserve queue; the active roster and published ranking snapshot stay unchanged.</div>
                        </form>
                        <?php ($teamCustomEmailRecipients = $teamInvitations->filter(fn($invitation) => $customEmailRecipients->contains('id', $invitation->id))); ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeImport->status === 'sent'): ?>
                          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-2 border-bottom bg-light" data-custom-email-team-toolbar="<?php echo e($regionTeam->id); ?>">
                            <label class="form-check mb-0"><input class="form-check-input" type="checkbox" data-custom-email-check-all="<?php echo e($regionTeam->id); ?>" <?php if($teamCustomEmailRecipients->isEmpty()): echo 'disabled'; endif; ?>><span class="form-check-label">Check all eligible players</span></label>
                            <button type="button" class="btn btn-sm btn-primary" data-custom-email-team-button="<?php echo e($regionTeam->id); ?>" data-bs-toggle="modal" data-bs-target="#custom-player-email-modal-<?php echo e($activeImport->id); ?>" disabled><i class="ti ti-mail-edit me-1"></i><span>Email checked players</span></button>
                          </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <div class="table-responsive team-player-table" data-mobile-player-cards>
                          <table class="table table-sm align-middle mb-0">
                            <thead><tr><th><span class="visually-hidden">Select</span></th><th>Rank</th><th>Player</th><th>Contact</th><th>Ranking</th><th>Selection / payment</th><th>Email</th><th>Regional action</th></tr></thead>
                            <tbody data-team-invitations>
                              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $teamInvitations->sortBy(fn ($invitation) => $invitation->status === \App\Models\TeamSelectionInvitation::DECLINED ? 1 : 0); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $invitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <?php ($recipientEmail = $recipientEmailFor($invitation)); ?>
                                <?php ($rawContactEmails = $rawContactEmailsFor($invitation)); ?>
                                <?php ($delivery = $invitation->emailLogs->sortByDesc('id')->first()); ?>
                                <?php ($isReserve = $invitation->status === \App\Models\TeamSelectionInvitation::RESERVE); ?>
                                <?php ($isInactive = in_array($invitation->status, [\App\Models\TeamSelectionInvitation::DECLINED, \App\Models\TeamSelectionInvitation::WITHDRAWN], true) || (!$isReserve && !$invitation->roster_rank)); ?>
                                <?php ($hasActiveReplacement = $teamInvitations->contains(fn($candidate) => (int) $candidate->promoted_from_id === (int) $invitation->id && in_array($candidate->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, \App\Models\TeamSelectionInvitation::PAID_CONFIRMED], true))); ?>
                                <?php ($openVacancy = $isInactive && $invitation->vacated_roster_rank && !$hasActiveReplacement); ?>
                                <?php ($awaitingRestoredInvitation = $invitation->status === \App\Models\TeamSelectionInvitation::INVITED && $invitation->declined_at && !$invitation->invited_at); ?>
                                <?php ($awaitingActivatedInvitation = $invitation->status === \App\Models\TeamSelectionInvitation::INVITED && !$invitation->invited_at && data_get($invitation->snapshot_json, 'activation.pending_manual_invitation')); ?>
                                <?php ($canSelectCustomInvitation = $activeImport->status === 'sent' && in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, \App\Models\TeamSelectionInvitation::PAID_CONFIRMED], true) && $invitation->roster_rank && (bool) $recipientEmail); ?>
                                <?php ($rankLabel = $isReserve ? 'Reserve '.$invitation->queue_position : ($isInactive ? ($invitation->status === \App\Models\TeamSelectionInvitation::DECLINED ? 'Declined' : ($invitation->status === \App\Models\TeamSelectionInvitation::WITHDRAWN ? 'Withdrawn' : 'Removed')) : 'Rank '.$invitation->roster_rank)); ?>
                                <?php ($statusTone = $isInactive ? 'danger' : ($invitation->status === \App\Models\TeamSelectionInvitation::PAID_CONFIRMED ? 'success' : ($isReserve ? 'warning' : 'info'))); ?>
                                <tr data-order-player-id="<?php echo e($invitation->id); ?>" data-invitation-player-id="<?php echo e($invitation->player_id); ?>" class="<?php echo e($isReserve ? 'reserve-row' : ''); ?>">
                                  <td data-label="Select"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canSelectCustomInvitation): ?><input class="form-check-input" type="checkbox" name="invitation_ids[]" value="<?php echo e($invitation->id); ?>" form="custom-player-email-form-<?php echo e($activeImport->id); ?>" data-custom-email-player="<?php echo e($regionTeam->id); ?>" aria-label="Select <?php echo e($invitation->player?->full_name ?: 'player'); ?> for a custom email"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                  <td data-label="Rank"><span class="badge <?php echo e($isInactive ? 'bg-label-danger' : ($isReserve ? 'bg-label-warning' : 'bg-label-primary')); ?>"><?php echo e($rankLabel); ?></span></td>
                                  <td data-label="Player"><strong><?php echo e($invitation->player?->full_name ?: 'Missing player'); ?></strong><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$invitation->player?->profile_complete): ?><div class="small text-warning">Profile incomplete</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                  <td data-label="Contact">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recipientEmail): ?>
                                      <div><?php echo e($recipientEmail); ?></div>
                                    <?php elseif($rawContactEmails->isNotEmpty()): ?>
                                      <div class="text-warning">Profile/account email invalid</div>
                                      <div class="small text-muted"><?php echo e($rawContactEmails->first()); ?></div>
                                    <?php else: ?>
                                      <div>Email required</div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <div class="small text-muted"><?php echo e($invitation->player?->cellNr ?: 'No cell number'); ?></div>
                                  </td>
                                  <td data-label="Ranking"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($invitation->snapshot_json, 'selection_source') === 'manual_system_profile'): ?><strong>Manual addition</strong><div class="small text-muted">Not in ranking snapshot</div><?php else: ?><strong>#<?php echo e($invitation->ranking_position); ?></strong><div class="small text-muted"><?php echo e(number_format((float)$invitation->total_points, 2)); ?> pts</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                  <td data-label="Selection / payment">
                                    <span class="badge bg-label-<?php echo e($statusTone); ?>"><?php echo e(str($invitation->status)->replace('_',' ')->title()); ?></span>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invitation->status === \App\Models\TeamSelectionInvitation::PAID_CONFIRMED): ?><div class="small text-muted mt-1"><?php echo e($invitation->order?->collection_status === 'paid_privately' ? 'Manual / private collection' : ($invitation->order?->payfast_paid ? ($invitation->order->wallet_debited ? 'PayFast + wallet payment' : 'PayFast payment') : ($invitation->order?->wallet_debited ? 'Wallet payment' : 'Payment evidence unavailable'))); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($invitation->decline_method === 'system_primary_team_promotion'): ?><div class="small text-info mt-1"><?php echo e($invitation->decline_reason); ?></div><?php else: ?><div class="small text-muted mt-1">Read only</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!($isRegionalPreview ?? false) && auth()->user()->hasRole('super-user') && $invitation->status === \App\Models\TeamSelectionInvitation::PAID_CONFIRMED): ?>
                                      <?php echo $__env->make('backend.team-selection._invitation-payment-transfer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                  </td>
                                  <td data-label="Email">
                                    <div><?php echo e($awaitingRestoredInvitation ? 'Restored — invitation not sent' : ($awaitingActivatedInvitation ? 'Pending invitation — not sent' : ($delivery ? $delivery->delivery_status_label : 'Not sent'))); ?></div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($delivery?->accepted_at): ?><small class="d-block text-muted"><?php echo e($delivery->accepted_at->format('d M Y H:i:s')); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($delivery?->transport_message_id): ?><small class="d-block text-muted text-break">Message ID: <?php echo e($delivery->transport_message_id); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeImport->status === 'sent' && !$isReserve): ?>
                                      <div class="d-flex flex-wrap gap-1 mt-1">
                                        <a class="btn btn-xs btn-outline-secondary" target="_blank" href="<?php echo e(route('backend.team-selection.invitations.email.view', [$event, $activeImport, $invitation])); ?>">View email</a>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isTeam() && $awaitingActivatedInvitation && !$awaitingRestoredInvitation && $invitation->roster_rank && $recipientEmail): ?>
                                          <form method="POST" action="<?php echo e(route('backend.team-selection.invitations.email.resend', [$event, $activeImport, $invitation])); ?>" data-mail-launch data-email-invitation-player="<?php echo e($invitation->player_id); ?>" data-email-player-name="<?php echo e($invitation->player?->full_name); ?>" data-email-team="<?php echo e($regionTeam->id); ?>"><?php echo csrf_field(); ?><button class="btn btn-xs btn-primary" title="Review this player's invitation before sending">Send invite</button></form>
                                        <?php elseif(!$awaitingRestoredInvitation && !$awaitingActivatedInvitation && in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true)): ?>
                                          <form method="POST" action="<?php echo e(route('backend.team-selection.invitations.email.resend', [$event, $activeImport, $invitation])); ?>" data-mail-launch data-email-invitation-player="<?php echo e($invitation->player_id); ?>" data-email-player-name="<?php echo e($invitation->player?->full_name); ?>" data-email-team="<?php echo e($regionTeam->id); ?>"><?php echo csrf_field(); ?><button class="btn btn-xs btn-outline-primary">Resend</button></form>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                      </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                  </td>
                                  <td data-label="Regional action">
                                    <?php ($reserveActivationIndex = $isReserve ? $teamReserves->values()->search(fn($candidate) => (int) $candidate->id === (int) $invitation->id) : false); ?>
                                    <?php ($reserveActivationRank = $isReserve ? $openRosterRanks->first() : null); ?>
                                    <?php ($canMarkPaidPrivately = !$isReserve && in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true)); ?>
                                    <?php ($canUndoPrivatePayment = !($isRegionalPreview ?? false) && auth()->user()->hasRole('super-user') && $invitation->status === \App\Models\TeamSelectionInvitation::PAID_CONFIRMED && $invitation->order?->collection_status === 'paid_privately' && !$invitation->order->wallet_debited && !$invitation->order->payfast_paid && !$invitation->order->payfast_handed_off_at && !$invitation->order->payfast_pf_payment_id && !$invitation->order->payfast_raw_data && (float) $invitation->order->wallet_reserved === 0.0 && !$invitation->order->withdrawn_at && !$invitation->order->hasRefund()); ?>
                                    <?php ($canManagePaidPayment = !($isRegionalPreview ?? false) && auth()->user()->hasRole('super-user') && $invitation->status === \App\Models\TeamSelectionInvitation::PAID_CONFIRMED); ?>
                                    <?php ($hasRegionalActions = (bool) $reserveActivationRank || (in_array($invitation->status, [\App\Models\TeamSelectionInvitation::DECLINED, \App\Models\TeamSelectionInvitation::WITHDRAWN], true) && $invitation->vacated_roster_rank) || ($recipientEmail && (!$isReserve || $event->isTeam())) || $canMarkPaidPrivately || $canUndoPrivatePayment || $canManagePaidPayment || $openVacancy); ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($hasRegionalActions): ?>
                                    <div class="dropdown">
                                      <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Regional actions for <?php echo e($invitation->player?->full_name ?: 'player'); ?>">
                                        Actions
                                      </button>
                                      <div class="dropdown-menu dropdown-menu-end p-1 <?php echo e((int) old('undo_private_invitation_id') === (int) $invitation->id || (int) old('cash_refund_invitation_id') === (int) $invitation->id ? 'show' : ''); ?>" style="min-width:18rem;max-width:calc(100vw - 2rem);max-height:calc(100vh - 8rem);overflow-y:auto;white-space:normal;">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->isTeam() && $recipientEmail): ?>
                                      <form method="POST" action="<?php echo e(route('backend.team-selection.invitations.email.resend', [$event, $activeImport, $invitation])); ?>" data-mail-launch><?php echo csrf_field(); ?><input type="hidden" name="custom_email" value="1"><button class="dropdown-item">Custom email</button></form>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManagePaidPayment): ?>
                                      <div class="px-2 pt-1 small text-muted">Payment management · <?php echo e($invitation->order_id ? 'Order #'.$invitation->order_id : 'No attached order'); ?></div>
                                      <div class="px-2 pb-2 small"><?php echo e($invitation->order?->collection_status === 'paid_privately' ? 'Manual / private collection' : ($invitation->order?->payfast_paid ? ($invitation->order->wallet_debited ? 'PayFast + wallet payment' : 'PayFast payment') : ($invitation->order?->wallet_debited ? 'Wallet payment' : 'Payment evidence unavailable'))); ?><br>Original payer: <?php echo e($invitation->order?->user?->name ?: 'Payer unavailable'); ?></div>
                                      <button class="dropdown-item text-primary" type="button" data-super-admin-only data-show-payment-transfer="invitation-payment-transfer-<?php echo e($invitation->id); ?>">Move payment to another player</button>
                                      <?php echo $__env->make('backend.team-selection._cash-refund-action', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($awaitingRestoredInvitation): ?>
                                      <form method="POST" action="<?php echo e(route('backend.team-selection.invitations.email.send-restored', [$event, $activeImport, $invitation])); ?>" data-mail-launch data-email-invitation-player="<?php echo e($invitation->player_id); ?>" data-email-player-name="<?php echo e($invitation->player?->full_name); ?>" data-email-team="<?php echo e($regionTeam->id); ?>"><?php echo csrf_field(); ?><button class="dropdown-item text-success">Send invitation</button></form>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canMarkPaidPrivately): ?>
                                      <form method="POST" action="<?php echo e(route('backend.team-selection.invitations.mark-paid-privately', [$event, $activeImport, $invitation])); ?>" onsubmit="return confirm('Confirm that payment was collected privately for this player? This will mark the team place paid, cancel any pending checkout, and will NOT be recorded as a PayFast reconciliation. No email will be sent.');"><?php echo csrf_field(); ?><button class="dropdown-item text-primary">Mark paid privately (not reconciled)</button></form>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canUndoPrivatePayment): ?>
                                      <details class="p-2" data-super-admin-only <?php if((int) old('undo_private_invitation_id') === (int) $invitation->id): ?> open <?php endif; ?>><summary class="text-warning">Mark as unpaid (private payment)</summary>
                                        <form method="POST" action="<?php echo e(route('backend.team-selection.invitations.undo-private-payment', [$event, $activeImport, $invitation])); ?>" class="mt-2">
                                          <?php echo csrf_field(); ?>
                                          <input type="hidden" name="undo_private_invitation_id" value="<?php echo e($invitation->id); ?>">
                                          <input type="hidden" name="expected_order_id" value="<?php echo e($invitation->order_id); ?>"><input type="hidden" name="expected_player_id" value="<?php echo e($invitation->player_id); ?>"><input type="hidden" name="expected_roster_rank" value="<?php echo e($invitation->roster_rank); ?>">
                                          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) old('undo_private_invitation_id') === (int) $invitation->id && $errors->any()): ?><div class="small text-danger mb-2"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                          <label class="form-label small" for="undo-private-reason-<?php echo e($invitation->id); ?>">Correction reason</label><input id="undo-private-reason-<?php echo e($invitation->id); ?>" name="reason" type="text" class="form-control form-control-sm mb-2" maxlength="1000" value="<?php echo e((int) old('undo_private_invitation_id') === (int) $invitation->id ? old('reason') : ''); ?>" required>
                                          <label class="form-check small"><input type="checkbox" class="form-check-input" name="confirm_unpaid" value="1" required><span class="form-check-label">Correct this manual collection mark; no money will be refunded.</span></label>
                                          <button class="btn btn-sm btn-outline-warning mt-2">Confirm mark as unpaid</button>
                                        </form>
                                      </details>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isReserve && $reserveActivationRank): ?>
                                      <?php echo $__env->make('backend.team-selection._open-position-action', ['restorePosition' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($invitation->status, [\App\Models\TeamSelectionInvitation::DECLINED, \App\Models\TeamSelectionInvitation::WITHDRAWN], true) && $invitation->vacated_roster_rank): ?>
                                      <?php echo $__env->make('backend.team-selection._open-position-action', ['restorePosition' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                                      <form method="POST" action="<?php echo e(route('backend.team-selection.invitations.restore', [$event, $activeImport, $invitation])); ?>" onsubmit="return confirm('<?php echo e($invitation->status === \App\Models\TeamSelectionInvitation::WITHDRAWN ? 'Restore this withdrawn player' : 'Restore this player'); ?> at Rank <?php echo e($invitation->vacated_roster_rank); ?>? Active players at and below that rank will move down. <?php echo e($invitation->status === \App\Models\TeamSelectionInvitation::WITHDRAWN ? 'Their old payment and withdrawal history stays unchanged, and they must register and pay again through a fresh order. ' : ''); ?>No email will be sent.');"><?php echo csrf_field(); ?><button class="dropdown-item text-primary">Restore at Rank <?php echo e($invitation->vacated_roster_rank); ?></button></form>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recipientEmail && !$isReserve): ?><button class="dropdown-item text-success roster-email-button" type="button" data-bs-toggle="modal" data-bs-target="#roster-email-<?php echo e($eventRegion->id); ?>" data-target-type="player" data-team-id="<?php echo e($regionTeam->id); ?>" data-invitation-id="<?php echo e($invitation->id); ?>" data-recipient="<?php echo e($invitation->player?->full_name); ?> · <?php echo e($recipientEmail); ?>"><i class="ti ti-mail me-1" aria-hidden="true"></i>Email player</button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$isReserve && in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true)): ?>
                                      <?php ($unavailableFormOpen = (int) old('unavailable_invitation_id') === (int) $invitation->id); ?>
                                      <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#unavailable-player-<?php echo e($invitation->id); ?>">Mark unavailable / release place</button>
                                      <div class="modal fade" id="unavailable-player-<?php echo e($invitation->id); ?>" tabindex="-1" aria-labelledby="unavailable-player-title-<?php echo e($invitation->id); ?>" aria-hidden="true" data-replacement-modal <?php if($unavailableFormOpen): ?> data-reopen <?php endif; ?>>
                                        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
                                          <div class="modal-header"><h5 class="modal-title" id="unavailable-player-title-<?php echo e($invitation->id); ?>">Mark unavailable — <?php echo e($invitation->player?->full_name); ?></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                                          <div class="modal-body">
                                            <p><?php echo e($regionTeam->name); ?> · Rank <span data-current-roster-rank><?php echo e($invitation->roster_rank); ?></span></p>
                                            <p>The unpaid invitation will be declined and this place will stay vacant. No reserve will be promoted and no email will be sent. Any unpaid wallet reservation will be released.</p>
                                            <form method="POST" action="<?php echo e(route('backend.team-selection.invitations.unavailable', [$event, $activeImport, $invitation])); ?>">
                                              <?php echo csrf_field(); ?>
                                              <input type="hidden" name="unavailable_invitation_id" value="<?php echo e($invitation->id); ?>">
                                              <input type="hidden" name="expected_team_id" value="<?php echo e($invitation->team_id); ?>">
                                              <input type="hidden" name="expected_player_id" value="<?php echo e($invitation->player_id); ?>">
                                              <input type="hidden" name="expected_roster_rank" value="<?php echo e($invitation->roster_rank); ?>">
                                              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($unavailableFormOpen && $errors->any()): ?><div class="alert alert-danger" role="alert"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($message); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                              <label class="form-label" for="unavailable-reason-<?php echo e($invitation->id); ?>">Reason</label>
                                              <textarea id="unavailable-reason-<?php echo e($invitation->id); ?>" name="reason" class="form-control mb-3" rows="3" maxlength="1000" required><?php echo e($unavailableFormOpen ? old('reason') : 'Player not available.'); ?></textarea>
                                              <div class="form-check mb-3"><input type="checkbox" class="form-check-input" id="confirm-unavailable-<?php echo e($invitation->id); ?>" name="confirm_unavailable" value="1" required><label class="form-check-label" for="confirm-unavailable-<?php echo e($invitation->id); ?>">I confirm this player is unavailable and the place should be left vacant.</label></div>
                                              <button class="btn btn-danger w-100">Mark unavailable / release place</button>
                                            </form>
                                          </div>
                                        </div></div>
                                      </div>
                                      <?php ($replacementFormOpen = (int) old('replacement_invitation_id') === (int) $invitation->id); ?>
                                      <?php ($replacementMode = $replacementFormOpen ? old('replacement_mode', 'next_reserve') : ($eligibleTeamReserves->isNotEmpty() ? 'next_reserve' : 'custom_profile')); ?>
                                      <?php ($replacementMode = $replacementMode === 'next_reserve' && $eligibleTeamReserves->isEmpty() ? 'custom_profile' : $replacementMode); ?>
                                      <button type="button" class="dropdown-item text-warning" data-bs-toggle="modal" data-bs-target="#replace-player-<?php echo e($invitation->id); ?>">Change player</button>
                                      <div class="modal fade" id="replace-player-<?php echo e($invitation->id); ?>" tabindex="-1" aria-labelledby="replace-player-title-<?php echo e($invitation->id); ?>" aria-hidden="true" data-replacement-modal <?php if($replacementFormOpen): ?> data-reopen <?php endif; ?>>
                                        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                          <div class="modal-content">
                                            <div class="modal-header">
                                              <h5 class="modal-title" id="replace-player-title-<?php echo e($invitation->id); ?>">Change player — <?php echo e($invitation->player?->full_name); ?></h5>
                                              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                        <form method="POST" action="<?php echo e(route('backend.team-selection.invitations.replace', [$event, $activeImport, $invitation])); ?>"
                                              class="mt-2 replacement-player-form" data-replacement-player-form
                                              onsubmit="return confirm('Replace this unpaid player? The roster change is audited and cannot be undone.');">
                                          <?php echo csrf_field(); ?>
                                          <input type="hidden" name="replacement_invitation_id" value="<?php echo e($invitation->id); ?>">
                                          <label class="form-label small mb-1" for="replacement-mode-<?php echo e($invitation->id); ?>">Replacement source</label>
                                          <select id="replacement-mode-<?php echo e($invitation->id); ?>" name="replacement_mode"
                                                  class="form-select form-select-sm mb-2 replacement-mode-select" data-replacement-mode>
                                            <option value="next_reserve" <?php if($eligibleTeamReserves->isEmpty()): echo 'disabled'; endif; ?> <?php if($replacementMode === 'next_reserve'): echo 'selected'; endif; ?>>
                                              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eligibleTeamReserves->isNotEmpty()): ?> Next reserve — <?php echo e($eligibleTeamReserves->first()->player?->full_name); ?> <?php else: ?> No eligible reserve available <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </option>
                                            <option value="custom_profile" <?php if($replacementMode === 'custom_profile'): echo 'selected'; endif; ?>>Choose a Cape Tennis player profile</option>
                                          </select>
                                          <div class="<?php echo \Illuminate\Support\Arr::toCssClasses(['mb-2', 'd-none' => $replacementMode !== 'custom_profile']); ?>" data-custom-replacement-profile>
                                            <label class="form-label small mb-1" for="replacement-player-<?php echo e($invitation->id); ?>">Player profile</label>
                                            <select id="replacement-player-<?php echo e($invitation->id); ?>" name="replacement_player_id"
                                                    class="form-select team-player-select replacement-profile-select"
                                                    data-placeholder="Search player name, email or cell…"
                                                    data-search-url="<?php echo e(route('backend.team-selection.players.search', [$event, $activeImport, $regionTeam])); ?>"
                                                    <?php if($replacementMode !== 'custom_profile'): echo 'disabled'; endif; ?> <?php if($replacementMode === 'custom_profile'): echo 'required'; endif; ?>>
                                              <option value=""></option>
                                            </select>
                                            <div class="form-text">Any Cape Tennis player profile can be selected; no parent or linked account is required. If a valid contact email is available, it is used for a sent campaign. Existing players below this place move up, and the replacement joins the final active roster place.</div>
                                          </div>
                                          <label class="form-label small mb-1" for="replacement-reason-<?php echo e($invitation->id); ?>">Reason</label>
                                          <input id="replacement-reason-<?php echo e($invitation->id); ?>" type="text" name="reason"
                                                 class="form-control form-control-sm mb-2" maxlength="1000"
                                                 value="Player not available." placeholder="Required reason" required>
                                          <button class="btn btn-sm btn-warning w-100">Confirm replacement</button>
                                        </form>
                                            </div>
                                          </div>
                                        </div>
                                      </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($openVacancy && $eligibleTeamReserves->isNotEmpty()): ?>
                                      <form method="POST" action="<?php echo e(route('backend.team-selection.invitations.promote-reserve', [$event, $activeImport, $invitation])); ?>" onsubmit="return confirm('Invite the next reserve now? Their own response and payment deadlines will start now.');"><?php echo csrf_field(); ?><button class="dropdown-item text-warning">Invite next reserve</button></form>
                                    <?php elseif($openVacancy): ?>
                                      <span class="text-warning small">Vacancy open · no eligible reserve. Add or link a reserve below.</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$openVacancy && !$recipientEmail && ($isReserve || !in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true))): ?>
                                      <span class="text-muted small">No action available</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                      </div>
                                    </div>
                                    <?php else: ?>
                                      <span class="text-muted small">No action available</span>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                  </td>
                                </tr>
                              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr data-empty-team-invitations><td colspan="8" data-mobile-full class="text-center text-muted py-4">No ranked players have been imported for this team yet.</td></tr>
                              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>
                          </table>
                        </div>
                        </div>
                        <div class="tab-pane fade" id="team-order-<?php echo e($regionTeam->id); ?>">
                          <div class="p-3 border-bottom small text-muted">Change the playing order without changing the selected players, their payment state, or the original ranking snapshot. Every move is audited.</div>
                          <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                              <thead><tr><th>Roster rank</th><th>Player</th><th>Published ranking</th><th>Selection status</th><th>Move</th></tr></thead>
                              <tbody class="roster-order-sortable" data-reorder-url="<?php echo e(route('backend.team-selection.teams.order', [$event, $activeImport, $regionTeam])); ?>" data-reorder-field="invitation_ids">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $teamSelected->sortBy('roster_rank'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $orderedInvitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                  <tr draggable="true" data-order-id="<?php echo e($orderedInvitation->id); ?>">
                                    <td><span class="badge bg-label-primary">Rank <?php echo e($orderedInvitation->roster_rank); ?></span></td>
                                    <td><span class="drag-handle me-2" title="Drag to reorder"><i class="ti ti-grip-vertical"></i></span><strong><?php echo e($orderedInvitation->player?->full_name); ?></strong></td>
                                    <td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(data_get($orderedInvitation->snapshot_json, 'selection_source') === 'manual_system_profile'): ?>Manual addition <span class="text-muted">· not in ranking snapshot</span><?php else: ?>#<?php echo e($orderedInvitation->ranking_position); ?> <span class="text-muted">· <?php echo e(number_format((float)$orderedInvitation->total_points, 2)); ?> pts</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
                                    <td><?php echo e(str($orderedInvitation->status)->replace('_',' ')->title()); ?></td>
                                    <td><div class="d-flex gap-1">
                                      <form method="POST" action="<?php echo e(route('backend.team-selection.invitations.move', [$event, $activeImport, $orderedInvitation])); ?>"><?php echo csrf_field(); ?><input type="hidden" name="direction" value="up"><button class="btn btn-sm btn-outline-primary" title="Move up" aria-label="Move <?php echo e($orderedInvitation->player?->full_name); ?> up" <?php if($loop->first): echo 'disabled'; endif; ?>><i class="ti ti-arrow-up"></i></button></form>
                                      <form method="POST" action="<?php echo e(route('backend.team-selection.invitations.move', [$event, $activeImport, $orderedInvitation])); ?>"><?php echo csrf_field(); ?><input type="hidden" name="direction" value="down"><button class="btn btn-sm btn-outline-primary" title="Move down" aria-label="Move <?php echo e($orderedInvitation->player?->full_name); ?> down" <?php if($loop->last): echo 'disabled'; endif; ?>><i class="ti ti-arrow-down"></i></button></form>
                                    </div></td>
                                  </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                        </div>
                      <?php else: ?>
                        <?php echo $__env->make('backend.team-selection._imported-roster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
            <?php else: ?>
              <div class="alert alert-warning">No teams have been added for this region yet. <button type="button" class="btn btn-sm btn-outline-primary ms-2" data-open-region-task="setup">Set up teams</button></div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>

              <div class="tab-pane fade region-task-panel" id="region-<?php echo e($eventRegion->id); ?>-invitations" role="tabpanel" aria-labelledby="region-<?php echo e($eventRegion->id); ?>-invitations-tab" tabindex="0">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$activeImport): ?>
                  <div class="alert alert-info">Import players from your region's published ranking. Review the players before importing; invitations are prepared separately.</div>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sourceReady && $regionTeams->isNotEmpty()): ?>
                    <a class="btn btn-primary" href="<?php echo e(route('backend.team-selection.preview', [$event, $source])); ?>"><i class="ti ti-file-import me-1"></i>Import players</a>
                  <?php else: ?>
                    <p class="text-muted small">First choose a published ranking series and create the teams.</p>
                    <button type="button" class="btn btn-outline-primary" data-open-region-task="setup">Set up teams for import</button>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php else: ?>
                  <?php ($unansweredInvitations = $activeImport->invitations->filter(fn ($invitation) => $invitation->status === \App\Models\TeamSelectionInvitation::INVITED && $invitation->invited_at && !$invitation->accepted_at)); ?>
                  <h6 class="mb-1">Player invitations</h6>
                  <p class="text-muted small">Accept opens registration and payment. Decline asks for confirmation and records the response.</p>
                  <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="badge bg-label-warning">Awaiting response: <?php echo e($unansweredInvitations->count()); ?></span>
                    <span class="badge bg-label-primary">Payment pending: <?php echo e($activeImport->invitations->where('status', 'accepted_pending_payment')->count()); ?></span>
                    <span class="badge bg-label-success">Registered: <?php echo e($activeImport->invitations->where('status', 'paid_confirmed')->count()); ?></span>
                    <span class="badge bg-label-secondary">Declined: <?php echo e($activeImport->invitations->where('status', 'declined')->count()); ?></span>
                  </div>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeImport->status === 'sent' && $canManageRegion): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEventManager): ?><button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#roster-email-<?php echo e($eventRegion->id); ?>" data-target-type="filtered" data-unanswered-invitations="1" data-recipient="Players awaiting a response in this region" <?php if($unansweredInvitations->isEmpty()): echo 'disabled'; endif; ?>>Email players awaiting response</button><?php else: ?><a class="btn btn-primary mb-3" href="<?php echo e(route('backend.event-communications.index', [$event, 'compose_scope' => 'region', 'region_id' => $eventRegion->region_id, 'compose_filter' => 'not_registered'])); ?>">Email unregistered players</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($unansweredInvitations->isEmpty()): ?><p class="small text-muted">No sent invitations are awaiting a response.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <?php elseif($activeImport->status !== 'sent'): ?>
                    <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#prepare-invitations-<?php echo e($activeImport->id); ?>">Prepare and send invitations</button>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <details class="mb-3"><summary>View player responses</summary><div class="table-responsive mt-2"><table class="table table-sm"><thead><tr><th>Player</th><th>Response</th></tr></thead><tbody>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $activeImport->invitations->where('status', '!=', 'reserve'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $responseInvitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr><td><?php echo e($responseInvitation->player?->full_name ?: 'Player'); ?></td><td><?php echo e(match ($responseInvitation->status) { 'invited' => $responseInvitation->invited_at ? 'Awaiting response' : 'Not yet sent', 'accepted_pending_payment' => 'Payment pending', 'paid_confirmed' => 'Registered', default => ucfirst(str_replace('_', ' ', $responseInvitation->status)) }); ?></td></tr>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </tbody></table></div></details>
                  <details class="border rounded p-3"><summary>Advanced settings and delivery details</summary><div class="mt-3">
                  <div class="regional-readonly rounded p-2 mb-3 small"><strong>Ranking snapshot:</strong> <code><?php echo e($activeImport->ranking_run_id); ?></code> · locked to preserve the imported selection record.</div>
                  <form method="POST" action="<?php echo e(route('backend.team-selection.replacement-mode.update', [$event, $activeImport])); ?>" class="border rounded p-3"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                    <div class="row g-3 align-items-end">
                      <div class="col-lg-8"><label class="form-label">Reserve replacement</label><select name="replacement_mode" class="form-select"><option value="automatic" <?php if($activeImport->auto_replacement_enabled): echo 'selected'; endif; ?>>Automatic</option><option value="manual" <?php if(!$activeImport->auto_replacement_enabled): echo 'selected'; endif; ?>>Manual approval</option></select></div>
                      <div class="col-lg-4 d-grid"><button class="btn btn-outline-primary">Save setting</button></div>
                      <div class="col-12 form-text">Automatic invites the next eligible reserve immediately. Manual leaves a visible vacancy with an <strong>Invite next reserve</strong> button. A replacement keeps the campaign deadline when it is still more than 24 hours away; otherwise they receive 24 hours from invitation, capped before the event starts.</div>
                    </div>
                  </form>
                  <?php ($emailLogs = $activeImport->invitations->flatMap->emailLogs); ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeImport->status === 'sent'): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingActivatedInvitations->isNotEmpty()): ?>
                      <div class="alert alert-warning mt-3 mb-3">
                        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
                          <div><strong><?php echo e($pendingActivatedInvitations->count()); ?> newly activated invitation(s) pending.</strong><div class="small">No email was sent during the roster changes. Review the exact recipients, then confirm one bulk send using the saved campaign and deadlines.</div></div>
                          <span class="badge bg-label-warning"><?php echo e($pendingActivatedRecipients->count()); ?> ready to send</span>
                        </div>
                        <details class="mt-2"><summary>Review <?php echo e($pendingActivatedRecipients->count()); ?> exact recipient(s)</summary><div class="small text-muted mt-2"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $pendingActivatedRecipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pendingInvitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><div><?php echo e($pendingInvitation->player?->full_name ?: 'Player'); ?> · <?php echo e($recipientEmailFor($pendingInvitation)); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> No pending player currently has a valid email address. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></details>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingActivatedInvitations->count() > $pendingActivatedRecipients->count()): ?><div class="small text-danger mt-2"><?php echo e($pendingActivatedInvitations->count() - $pendingActivatedRecipients->count()); ?> pending player(s) have no valid email and will remain pending.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <div class="mt-3 form-text">Use <strong>Email checked players</strong> above a team table to compose a custom email.</div>
                        <form method="POST" action="<?php echo e(route('backend.team-selection.invitations.email.send-pending-activated', [$event, $activeImport])); ?>" class="mt-3" data-mail-launch><?php echo csrf_field(); ?>
                          <input type="hidden" name="recipient_hash" value="<?php echo e($pendingActivatedRecipientHash); ?>"><input type="hidden" name="recipient_count" value="<?php echo e($pendingActivatedRecipients->count()); ?>">
                          <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="confirm_recipients" value="1" id="confirm-pending-activated-<?php echo e($activeImport->id); ?>" required><label class="form-check-label" for="confirm-pending-activated-<?php echo e($activeImport->id); ?>">I reviewed and confirm these <?php echo e($pendingActivatedRecipients->count()); ?> exact recipient(s).</label></div>
                          <button class="btn btn-sm btn-success" <?php if($pendingActivatedRecipients->isEmpty()): echo 'disabled'; endif; ?>>Write email to <?php echo e($pendingActivatedRecipients->count()); ?> pending players</button>
                        </form>
                      </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($customEmailRecipients->isNotEmpty()): ?>
                      <div class="modal fade" id="custom-player-email-modal-<?php echo e($activeImport->id); ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg modal-dialog-centered">
                          <form id="custom-player-email-form-<?php echo e($activeImport->id); ?>" method="POST" action="<?php echo e(route('backend.team-selection.invitations.email.custom-preview', [$event, $activeImport])); ?>" class="modal-content" data-mail-compose><?php echo csrf_field(); ?>
                            <div class="modal-header"><div><h5 class="modal-title">Email checked players</h5><div class="small text-muted">Only checked, eligible active players in this team will be included.</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body">
                              <?php echo $__env->make('backend.partials.email-sender-fields', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                              <div class="mb-3"><label class="form-label" for="custom-invitation-subject-<?php echo e($activeImport->id); ?>">Subject</label><input id="custom-invitation-subject-<?php echo e($activeImport->id); ?>" class="form-control" name="email_subject" maxlength="255" value="<?php echo e(old('email_subject', '')); ?>" required></div>
                              <div><label class="form-label" for="custom-invitation-message-<?php echo e($activeImport->id); ?>">Message</label><textarea id="custom-invitation-message-<?php echo e($activeImport->id); ?>" class="form-control" name="email_message" rows="8" maxlength="10000" required><?php echo e(old('email_message', '')); ?></textarea><div class="form-text">Write your own message. This custom email does not send an invitation, change registration or include clothing information. The saved invitation campaign is retained.</div></div>
                            </div>
                            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Review exact recipients and email</button></div>
                          </form>
                        </div>
                      </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3"><span class="badge bg-label-secondary">Email queued: <?php echo e($emailLogs->where('status','queued')->count()); ?></span><span class="badge bg-label-success">Mail server accepted: <?php echo e($emailLogs->where('evidence_status','server_accepted')->whereNotNull('accepted_at')->count()); ?></span><span class="badge bg-label-danger">Failed: <?php echo e($emailLogs->where('status','failed')->count()); ?></span><span class="badge bg-label-warning">Skipped: <?php echo e($emailLogs->where('status','skipped')->count()); ?></span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($emailLogs->where('status','failed')->isNotEmpty()): ?><form method="POST" action="<?php echo e(route('backend.team-selection.emails.retry', [$event, $activeImport])); ?>" data-mail-launch><?php echo csrf_field(); ?><button class="btn btn-sm btn-outline-danger">Retry failed emails</button></form><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                    <form method="POST" action="<?php echo e(route('backend.team-selection.deadlines.extend', [$event, $activeImport])); ?>" class="row g-2 align-items-end mt-2"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                      <div class="col-md-3"><label class="form-label">Response deadline</label><input type="datetime-local" name="response_deadline" value="<?php echo e($activeImport->response_deadline?->format('Y-m-d\\TH:i')); ?>" class="form-control" required></div>
                      <div class="col-md-3"><label class="form-label">Payment deadline</label><input type="datetime-local" name="payment_deadline" value="<?php echo e($activeImport->payment_deadline?->format('Y-m-d\\TH:i')); ?>" class="form-control" required></div>
                      <div class="col-md-3"><label class="form-label">Last reserve promotion</label><input type="datetime-local" name="replacement_payment_deadline" value="<?php echo e(($activeImport->replacement_payment_deadline ?: $activeImport->payment_deadline)?->format('Y-m-d\\TH:i')); ?>" class="form-control" required><div class="form-text">A promoted reserve may receive their own later deadline, capped before the event.</div></div>
                      <div class="col-md-3 d-grid"><button class="btn btn-outline-primary">Update deadlines</button></div>
                    </form>
                  <?php else: ?>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3"><button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#prepare-invitations-<?php echo e($activeImport->id); ?>"><i class="ti ti-mail-cog me-1"></i>Prepare invitations</button><span class="text-muted small">Review the message, one registration deadline and exact recipients before sending.</span></div>
                    <form method="POST" action="<?php echo e(route('backend.team-selection.restart', [$event, $activeImport])); ?>" class="mt-3" onsubmit="return confirm('Remove this unsent import and clear its generated roster places?');"><?php echo csrf_field(); ?><button class="btn btn-sm btn-outline-danger">Restart draft import</button></form>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div></details>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>

              <div class="tab-pane fade region-task-panel" id="region-<?php echo e($eventRegion->id); ?>-setup" role="tabpanel" aria-labelledby="region-<?php echo e($eventRegion->id); ?>-setup-tab" tabindex="0">
                <div class="mb-3"><h6 class="mb-1">Ranking and team setup</h6><p class="text-muted small mb-0">Link the regional ranking source and create its event categories and teams.</p></div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$source): ?>
              <form method="POST" action="<?php echo e(route('backend.team-selection.link', [$event, $eventRegion])); ?>" class="row g-2 align-items-end"><?php echo csrf_field(); ?>
                <div class="col-lg-7"><label class="form-label">Ranking series</label><select name="series_id" class="form-select" required><option value="">Choose <?php echo e($event->start_date?->format('Y')); ?> series…</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $series; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($item->id); ?>"><?php echo e($item->name); ?><?php echo e($readySeriesIds->contains($item->id) ? ' · published and ready' : ' · not published'); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
                <div class="col-sm-5 col-lg-2"><label class="form-label">Reserves per team</label><input type="number" name="reserve_count" min="0" max="20" value="2" class="form-control" required></div>
                <div class="col-sm-7 col-lg-3 d-grid"><button class="btn btn-primary">Link ranking series</button></div>
              </form>
              <div class="form-text">Choose the regional series that will supply the official player rankings.</div>
            <?php else: ?>
              <div class="border rounded p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div><small class="text-muted d-block">Linked ranking series</small><strong><?php echo e($source->series?->name); ?></strong><div class="small text-muted"><?php echo e($source->reserve_count); ?> reserves per team</div></div>
                <span class="badge bg-label-<?php echo e($sourceReady ? 'success' : 'warning'); ?>"><?php echo e($sourceReady ? 'Published and ready' : 'Ranking not published'); ?></span>
              </div>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$sourceReady): ?>
                <div class="alert alert-warning mb-0" role="alert">
                  <div class="d-flex gap-3 align-items-start"><i class="ti ti-alert-triangle fs-3 mt-1"></i><div class="flex-grow-1"><h6 class="alert-heading mb-1">Team setup is waiting for a published ranking</h6><p class="mb-2">The linked series does not have a current published ranking. Categories, teams and player imports are intentionally unavailable so this event cannot be built from draft or unreviewed positions.</p><div class="small mb-3"><strong>Next step:</strong> open the ranking, resolve any audit or tie issues, mark it reviewed, and publish it. Then return here to create the event teams.</div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEventManager): ?><a class="btn btn-warning" href="<?php echo e(route('ranking.series.list', $source->series)); ?>"><i class="ti ti-trophy me-1"></i>Open ranking workflow</a><?php else: ?><span class="fw-semibold">Ask the event administrator to publish this ranking.</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></div>
                </div>
              <?php elseif(!$activeImport): ?>
                <div class="alert alert-success"><strong>Ranking ready.</strong> The ranking is published. Create the event teams from its categories, then review the player import.</div>
                <div class="d-flex flex-wrap gap-2">
                  <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#ranking-category-setup-<?php echo e($source->id); ?>"><i class="ti ti-category-plus me-1"></i><?php echo e($regionTeams->isEmpty() ? 'Create categories & teams' : 'Review categories & teams'); ?></button>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($regionTeams->isNotEmpty()): ?><a class="btn btn-outline-primary" href="<?php echo e(route('backend.team-selection.preview', [$event, $source])); ?>"><i class="ti ti-download me-1"></i>Review ranked-player import</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
              <?php else: ?>
                <div class="alert alert-success mb-0"><strong>Setup complete.</strong> The published ranking snapshot has already been imported. Continue in Invitations or Teams &amp; players.</div>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>

              <div class="tab-pane fade region-task-panel" id="region-<?php echo e($eventRegion->id); ?>-messages" role="tabpanel" aria-labelledby="region-<?php echo e($eventRegion->id); ?>-messages-tab" tabindex="0">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
              <div><h6 class="mb-1"><?php echo e($usesRegionalClothing ? 'Messages & clothing' : 'Messages'); ?></h6><p class="text-muted small mb-0"><?php echo e($usesRegionalClothing ? 'Contact selected players and manage the regional clothing workflow.' : 'Contact selected players in this region.'); ?></p></div>
              <div class="d-flex flex-wrap gap-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManageRegion): ?>
                  <a href="<?php echo e(route('backend.regional-registration-mail.compose', ['event' => $event, 'region_id' => $eventRegion->region_id])); ?>" class="btn btn-primary"><i class="ti ti-mail me-1"></i>Registration open email</a>
                  <button class="btn btn-outline-warning" type="button" data-bs-toggle="modal" data-bs-target="#final-team-reminders-<?php echo e($eventRegion->id); ?>" data-reminder-open-kind="registration_clothing"><i class="ti ti-user-exclamation me-1"></i>Registration reminder</button>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($usesRegionalClothing): ?><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#final-team-reminders-<?php echo e($eventRegion->id); ?>" data-reminder-open-kind="incomplete_clothing"><i class="ti ti-shirt me-1"></i>Incomplete clothing reminder</button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($usesRegionalClothing): ?>
                  <a href="<?php echo e($isEventManager ? route('backend.region.clothing.edit', ['region' => $eventRegion->region_id, 'event_id' => $event->id]) : route('backend.event.clothing.index', [$event, 'region_id' => $eventRegion->region_id])); ?>" class="btn btn-outline-primary"><i class="ti ti-shirt me-1"></i>Clothing setup</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
            </div>
            <h6>Regional announcements</h6>
            <p class="text-muted small">Visible only to this region’s invited players. Choose email to save the announcement and open Communications, where you can review all nominated players or another regional audience before sending.</p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventRegion->announcements->isNotEmpty()): ?><ul class="announcement-list" aria-label="Regional announcements"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventRegion->announcements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $announcement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php ($announcementLogs = $announcement->emailLogs); ?>
              <li class="announcement-row"><div class="d-flex align-items-center justify-content-between gap-2"><strong><?php echo e($announcement->title); ?></strong><form method="POST" action="<?php echo e(route('backend.team-selection.announcements.destroy', [$event, $eventRegion, $announcement])); ?>" onsubmit="return confirm('Hide this announcement from the regional portal? Previously sent email cannot be recalled.');"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?><button class="btn btn-sm btn-outline-danger">Hide</button></form></div><div class="d-flex flex-wrap gap-2 align-items-center text-muted small mt-1"><span><?php echo e($announcement->created_at->format('d M Y H:i')); ?><?php echo e($announcement->emailed_at ? ' · email queued' : ' · portal only'); ?></span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($announcementLogs->isNotEmpty()): ?><span class="badge bg-label-secondary">Queued <?php echo e($announcementLogs->where('status','queued')->count()); ?></span><span class="badge bg-label-success">Mail server accepted <?php echo e($announcementLogs->where('evidence_status','server_accepted')->whereNotNull('accepted_at')->count()); ?></span><span class="badge bg-label-danger">Failed <?php echo e($announcementLogs->where('status','failed')->count()); ?></span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($announcementLogs->where('status','failed')->isNotEmpty()): ?><form method="POST" action="<?php echo e(route('backend.team-selection.announcements.retry', [$event, $eventRegion, $announcement])); ?>"><?php echo csrf_field(); ?><button class="btn btn-sm btn-outline-danger">Review failed emails</button></form><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div>
                <details class="announcement-message"><summary>Read message</summary><div class="small"><?php echo $announcement->message; ?></div></details>
              </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventRegion->announcements->isNotEmpty()): ?></ul><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <form method="POST" action="<?php echo e(route('backend.team-selection.announcements.store', [$event, $eventRegion])); ?>" class="row g-2"><?php echo csrf_field(); ?>
              <div class="col-md-4"><label class="form-label">Title</label><input name="title" class="form-control" maxlength="255" required></div>
              <div class="col-md-8"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="2" maxlength="20000" required></textarea></div>
              <input type="hidden" name="recipient_hash" value="<?php echo e(hash('sha256', $regionAnnouncementRecipients->toJson())); ?>">
              <div class="col-12"><details><summary>Review <?php echo e($regionAnnouncementRecipients->count()); ?> exact email recipient(s)</summary><div class="small text-muted mt-1"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $regionAnnouncementRecipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $email): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><div><?php echo e($email); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> No active selected players currently have a valid email address. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></details></div>
              <div class="col-12 d-flex flex-wrap gap-3 align-items-center"><div><div class="form-check"><input type="hidden" name="send_email" value="0"><input class="form-check-input" type="checkbox" name="send_email" value="1" id="send-region-announcement-<?php echo e($eventRegion->id); ?>"><label class="form-check-label" for="send-region-announcement-<?php echo e($eventRegion->id); ?>">Open email review after publishing</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_recipients" value="1" id="confirm-region-announcement-<?php echo e($eventRegion->id); ?>"><label class="form-check-label" for="confirm-region-announcement-<?php echo e($eventRegion->id); ?>">I reviewed and confirm this exact recipient list</label></div></div><button class="btn btn-outline-primary ms-auto">Publish regional announcement</button></div>
            </form>
              </div>
            </div>
          </div>
        </div>
      </div>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeImport?->status === 'draft'): ?>
        <div class="modal fade" id="prepare-invitations-<?php echo e($activeImport->id); ?>" tabindex="-1" aria-labelledby="prepare-invitations-title-<?php echo e($activeImport->id); ?>" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable"><form method="POST" action="<?php echo e(route('backend.team-selection.email.preview', [$event, $activeImport])); ?>" class="modal-content" data-mail-compose><?php echo csrf_field(); ?>
            <input type="hidden" name="selection_import_id" value="<?php echo e($activeImport->id); ?>">
            <div class="modal-header"><div><h5 class="modal-title" id="prepare-invitations-title-<?php echo e($activeImport->id); ?>">Prepare regional invitations</h5><div class="text-muted small"><?php echo e($eventRegion->region?->region_name); ?> · <?php echo e($activeImport->invitations->where('status','invited')->count()); ?> selected recipients</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <?php echo $__env->make('backend.partials.email-sender-fields', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
              <div class="alert alert-info"><strong>Preview required.</strong> Open the actual sample email before sending. The exact message, event details and deadlines<?php echo e($usesRegionalClothing ? ', including clothing prices,' : ''); ?> are snapshotted for audit and failed-email retries. Any change requires another preview.</div>
              <div class="row g-3">
                <div class="col-12"><label class="form-label">Email subject</label><input type="text" name="email_subject" maxlength="180" class="form-control" value="<?php echo e(old('email_subject', 'Platteland team invitation: '.$event->name)); ?>" required></div>
                <div class="col-12"><label class="form-label">Invitation message</label><textarea name="email_message" rows="4" maxlength="10000" class="form-control" required><?php echo e(old('email_message', 'You have been selected to represent your region. Please review the event information and respond before the deadline.')); ?></textarea><div class="form-text">This message appears near the top of every invitation.</div></div>
                <div class="col-12"><label class="form-label">Information shown on the player invitation page</label><textarea name="event_information" rows="7" maxlength="20000" class="form-control"><?php echo e(old('event_information', $defaultInvitationEventInformation)); ?></textarea><div class="form-text">HTML from the event page is converted into readable paragraphs and bullet points. Review venues, arrival times, accommodation and team instructions before previewing the email.</div></div>
                <div class="col-md-6"><label class="form-label">Registration deadline</label><input type="datetime-local" name="registration_deadline" value="<?php echo e(old('registration_deadline', $event->registrationClosesAt()?->endOfDay()->format('Y-m-d\\TH:i'))); ?>" class="form-control" required><div class="form-text">One cutoff for responding, registering and paying. Reserve invitations use the same cutoff.</div></div>
                <div class="col-md-6"><div class="alert alert-light border mb-0 h-100"><strong>One send action.</strong><br><span class="small text-muted"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isEventManager): ?> Confirming the send opens event registration and publishes the selected teams automatically. <?php else: ?> The event manager must open registration first; this send publishes the selected teams. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span></div></div>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($usesRegionalClothing): ?><div class="col-12"><input type="hidden" name="include_clothing" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="include_clothing" value="1" id="include-clothing-<?php echo e($activeImport->id); ?>" <?php if(old('include_clothing', $clothingAvailable)): echo 'checked'; endif; ?> <?php if(!$clothingAvailable): echo 'disabled'; endif; ?>><label class="form-check-label" for="include-clothing-<?php echo e($activeImport->id); ?>">Include optional regional clothing items, sizes, prices and ordering steps</label></div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$clothingAvailable): ?><div class="form-text text-warning">Complete this region's clothing items, sizes and approved prices, then open clothing ordering to enable this option.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><?php else: ?><input type="hidden" name="include_clothing" value="0"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
              <hr><div class="row g-2"><div class="col-sm-4"><div class="border rounded p-3"><small class="text-muted d-block">Invitations</small><strong><?php echo e($activeImport->invitations->where('status','invited')->count()); ?></strong></div></div><div class="col-sm-4"><div class="border rounded p-3"><small class="text-muted d-block">Reserves held back</small><strong><?php echo e($activeImport->invitations->where('status','reserve')->count()); ?></strong></div></div><div class="col-sm-4"><div class="border rounded p-3"><small class="text-muted d-block">Missing email</small><strong><?php echo e($activeImport->invitations->filter(fn($i) => !$recipientEmailFor($i))->count()); ?></strong></div></div></div>
              <details class="border rounded p-3 mt-3"><summary class="fw-semibold">Review exact invitation recipients</summary><div class="table-responsive mt-3"><table class="table table-sm mb-0"><thead><tr><th>Player</th><th>Email used</th></tr></thead><tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $activeImport->invitations->where('status','invited')->sortBy('queue_position'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $recipientInvitation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><tr><td><?php echo e($recipientInvitation->player?->full_name); ?></td><td><?php echo e($recipientEmailFor($recipientInvitation) ?: 'Skipped — no email available'); ?></td></tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody></table></div></details>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Preview email</button></div>
</form></div>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <div class="modal fade" id="roster-email-<?php echo e($eventRegion->id); ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered"><form method="POST" action="<?php echo e(route('backend.team-selection.roster-email.send', [$event, $eventRegion])); ?>" class="modal-content" data-mail-compose><?php echo csrf_field(); ?>
          <input type="hidden" name="target_type" value="team" data-roster-email-target>
          <input type="hidden" name="team_id" data-roster-email-team>
          <input type="hidden" name="invitation_id" data-roster-email-invitation>
          <input type="hidden" name="slot_id" data-roster-email-slot>
          <input type="hidden" name="recipient_hash" data-roster-email-hash>
          <div class="modal-header"><div><h5 class="modal-title">Email selected roster</h5><div class="small text-muted" data-roster-email-recipient></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <?php echo $__env->make('backend.partials.email-sender-fields', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <div class="border rounded p-3 mb-3 d-none" data-roster-filter-panel data-preview-url="<?php echo e(route('backend.team-selection.roster-email.preview', [$event, $eventRegion])); ?>">
              <h6 class="mb-1">Choose players</h6>
              <p class="small text-muted mb-3">Filters are combined. Preview the exact recipient list before the email can be queued.</p>
              <div class="row g-3">
                <div class="col-12"><fieldset><legend class="form-label mb-2">Age groups</legend><div class="row g-2"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regionTeams->filter(fn($team) => $team->category_event_id)->unique('category_event_id')->sortBy(fn($team) => $team->category?->category?->name); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $filterTeam): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div class="col-sm-6 col-lg-4"><label class="form-check border rounded p-2 h-100"><input class="form-check-input ms-0 me-2" type="checkbox" name="category_event_ids[]" value="<?php echo e($filterTeam->category_event_id); ?>" data-roster-filter-input disabled><span class="form-check-label"><?php echo e($filterTeam->category?->category?->name ?: $filterTeam->name); ?></span></label></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></fieldset><div class="form-text">Select one or more groups, for example U13 Girls and U12 Girls.</div></div>
                <div class="col-md-6"><label class="form-label">Gender</label><select class="form-select" name="gender" data-roster-filter-input disabled required><option value="any">All genders</option><option value="girls">Girls</option><option value="boys">Boys</option></select></div>
                <div class="col-md-6"><label class="form-label">Player status</label><select class="form-select" name="audience_status" data-roster-filter-input disabled required><option value="active">All active selected players</option><option value="entered">Entered / paid</option><option value="not_entered">Selected but not entered / paid</option><option value="invited">Invitation sent</option><option value="not_invited">Not yet invited (reserves or pending send)</option><option value="accepted">Accepted (paid or payment pending)</option><option value="not_accepted">Invited but not accepted</option><option value="declined">Declined</option><option value="withdrawn">Withdrawn</option></select></div>
              </div>
              <button class="btn btn-outline-primary mt-3" type="button" data-roster-filter-preview><i class="ti ti-list-check me-1"></i>Preview recipients</button>
              <div class="alert alert-secondary mt-3 mb-0 d-none" data-roster-filter-result></div>
              <div class="mt-3 d-none" data-roster-filter-review><strong class="small">Exact recipients</strong><div class="small text-muted mt-1" data-roster-filter-list></div></div>
            </div>
            <div class="mb-3"><label class="form-label">Subject</label><input class="form-control" name="subject" maxlength="180" required></div>
            <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="7" maxlength="20000" required></textarea></div>
            <details class="mb-3 d-none" data-roster-region-review><summary>Review all <?php echo e($regionRosterEmailRecipients->count()); ?> exact regional recipient(s)</summary><div class="small text-muted mt-2"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $regionRosterEmailRecipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $recipient): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($recipient['name'] ?: 'Player'); ?> · <?php echo e($recipient['email']); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></details>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['unlinked_imported' => $unlinkedImportedRecipients, 'linked_unpaid' => $linkedUnpaidRecipients, 'linked_all' => $allLinkedImportedRecipients]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cohortKey => $cohortRecipients): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <details class="mb-3 d-none" data-roster-cohort-review="<?php echo e($cohortKey); ?>"><summary>Review all <?php echo e($cohortRecipients->count()); ?> exact recipient(s)</summary><div class="small text-muted mt-2"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $cohortRecipients; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $recipient): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><div><?php echo e($recipient['name'] ?: 'Player'); ?> · <?php echo e($recipient['email']); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></details>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_recipients" value="1" id="confirm-roster-email-<?php echo e($eventRegion->id); ?>" required><label class="form-check-label" for="confirm-roster-email-<?php echo e($eventRegion->id); ?>">I confirm the recipient details above are correct</label></div>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="ti ti-send me-1"></i>Preview email</button></div>
        </form></div>
      </div>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canManageRegion): ?>
        <div class="modal fade" id="reimport-roster-<?php echo e($eventRegion->id); ?>" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <form class="modal-content roster-contact-import-form" method="POST" enctype="multipart/form-data" action="<?php echo e(route('backend.team-selection.imported-contacts.enrich', [$event, $eventRegion])); ?>">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="confirmed" value="0" data-import-confirmed>
              <input type="hidden" name="preview_fingerprint" value="" data-import-fingerprint>
              <input type="hidden" name="expected_players" value="<?php echo e(max(1, (int) ($regionTeams->first()?->num_team_members ?: 8))); ?>">
              <input type="hidden" name="team_prefix" value="<?php echo e($eventRegion->region?->short_name ?: $eventRegion->region?->region_name); ?>">
              <div class="modal-header"><div><h5 class="modal-title">Re-import roster names and contacts</h5><div class="small text-muted"><?php echo e($eventRegion->region?->region_name); ?></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
              <div class="modal-body">
                <div class="alert alert-info">Upload the original workbook. This action can only add an email to a blank imported roster email field after one unique name match. It cannot create, delete, rename or reorder players, and cannot change links, DOBs, phones or payment state.</div>
                <label class="form-label">Excel workbook</label><input class="form-control" type="file" name="file" accept=".xls,.xlsx,.csv" required data-import-file>
                <div class="alert alert-danger d-none mt-3" data-import-errors></div>
                <div class="table-responsive d-none mt-3" data-import-preview><table class="table table-sm align-middle"><thead><tr><th>Existing player</th><th>Email to add</th></tr></thead><tbody></tbody></table></div>
                <div class="alert alert-warning d-none mt-3" data-import-anomalies><strong>Review required — these rows will be skipped.</strong><ul class="mb-2 mt-1"></ul><div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_anomalies" value="1" id="confirm-import-anomalies-<?php echo e($eventRegion->id); ?>"><label class="form-check-label" for="confirm-import-anomalies-<?php echo e($eventRegion->id); ?>">I have reviewed these exceptions and understand they will not be changed</label></div></div>
              </div>
              <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" data-import-submit>Preview workbook</button></div>
            </form>
          </div>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sourceReady && $source && !$activeImport && $categorySetup): ?>
        <?php ($setupRows = $categorySetup['rows']); ?>
        <?php ($missingSetupRows = $setupRows->reject(fn($row) => $row['ready'])); ?>
        <div class="modal fade" id="ranking-category-setup-<?php echo e($source->id); ?>" tabindex="-1" aria-labelledby="ranking-category-setup-title-<?php echo e($source->id); ?>" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title" id="ranking-category-setup-title-<?php echo e($source->id); ?>">Create teams from ranking categories</h5>
                  <div class="text-muted small"><?php echo e($eventRegion->region?->region_name); ?> · <?php echo e($source->series?->name); ?></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="alert alert-info">
                  Select the categories this region will enter. Existing event teams are preserved; only missing categories, team links and empty roster places are created.
                </div>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($setupRows->isEmpty()): ?>
                  <div class="alert alert-warning mb-0">This series has no ranking categories yet. Add its ranking lists first.</div>
                <?php else: ?>
                  <form id="ranking-category-form-<?php echo e($source->id); ?>" method="POST" action="<?php echo e(route('backend.team-selection.teams.create', [$event, $source])); ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="setup_source" value="<?php echo e($source->id); ?>">
                    <div class="row g-2 align-items-end mb-3">
                      <div class="col-sm-6 col-md-4">
                        <label class="form-label" for="default-team-size-<?php echo e($source->id); ?>">Default team size</label>
                        <input type="number" class="form-control default-team-size" id="default-team-size-<?php echo e($source->id); ?>" value="8" min="1" max="50" inputmode="numeric">
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
                          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $setupRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rowIndex => $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php ($ready = $row['ready']); ?>
                            <tr>
                              <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ready): ?>
                                  <i class="ti ti-circle-check text-success" aria-label="Ready"></i>
                                <?php else: ?>
                                  <input type="hidden" name="categories[<?php echo e($rowIndex); ?>][selected]" value="0">
                                  <input class="form-check-input" type="checkbox" name="categories[<?php echo e($rowIndex); ?>][selected]" value="1" checked aria-label="Create <?php echo e($row['category_name']); ?> team">
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                              </td>
                              <td><strong><?php echo e($row['category_name']); ?></strong></td>
                              <td><?php echo e($categorySetup['published_ready'] ? $row['ranked_count'] : 'Pending publication'); ?></td>
                              <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ready): ?>
                                  <span class="badge bg-label-success">Team ready</span>
                                <?php elseif($row['team']): ?>
                                  <span class="badge bg-label-info">Existing team will be linked</span>
                                <?php elseif($row['event_category']): ?>
                                  <span class="badge bg-label-warning">Category exists · team missing</span>
                                <?php else: ?>
                                  <span class="badge bg-label-secondary">New category &amp; team</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                              </td>
                              <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ready): ?>
                                  <?php echo e($row['team']->name); ?>

                                <?php else: ?>
                                  <input type="hidden" name="categories[<?php echo e($rowIndex); ?>][ranking_list_id]" value="<?php echo e($row['ranking_list_id']); ?>">
                                  <input type="text" class="form-control" name="categories[<?php echo e($rowIndex); ?>][team_name]" value="<?php echo e(old("categories.$rowIndex.team_name", $row['team']?->name ?: $row['suggested_team_name'])); ?>" required maxlength="255">
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                              </td>
                              <td>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ready): ?>
                                  <?php echo e($row['team']->num_team_members); ?>

                                <?php else: ?>
                                  <input type="number" class="form-control team-size-input" name="categories[<?php echo e($rowIndex); ?>][num_players]" value="<?php echo e(old("categories.$rowIndex.num_players", $row['team']?->num_team_members ?: 8)); ?>" min="1" max="50" inputmode="numeric" aria-label="Team size for <?php echo e($row['category_name']); ?>" required>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                              </td>
                            </tr>
                          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($missingSetupRows->isNotEmpty()): ?>
                  <button type="submit" form="ranking-category-form-<?php echo e($source->id); ?>" class="btn btn-primary">Create selected teams</button>
                <?php elseif($categorySetup['published_ready']): ?>
                  <a class="btn btn-primary" href="<?php echo e(route('backend.team-selection.preview', [$event, $source])); ?>">Continue to ranked-player preview</a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</div>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\team-selection\_workspace-content.blade.php ENDPATH**/ ?>