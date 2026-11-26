<?php
  $rosterOnly = $rosterOnly ?? false;
  $occupied = $region->teams->sum(fn ($team) => $team->workspaceSlots->filter(fn ($slot) => (int) $slot->player_id > 0 || $slot->noProfile)->count());
  $unpaid = $region->teams->sum(fn ($team) => $team->workspaceSlots->filter(fn ($slot) => ((int) $slot->player_id > 0 || $slot->noProfile) && (int) $slot->pay_status !== 1)->count());
  $reserves = $region->teams->sum(fn ($team) => $team->workspaceSlots->where('selected_team_reserve', true)->count() + ($teamSelectionInvitations ?? collect())->get($team->id, collect())->where('status', \App\Models\TeamSelectionInvitation::RESERVE)->whereNotIn('player_id', $team->workspaceSlots->where('selected_team_reserve', true)->pluck('player_id'))->count());
?>
<div class="roster-region-header">
  <div class="d-flex flex-wrap align-items-baseline gap-2"><h2 class="h6 mb-0"><?php echo e($region->region_name); ?></h2><p class="small text-muted mb-0"><?php echo e($region->teams->count()); ?> teams · <?php echo e($occupied); ?> occupied places <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($rosterOnly)): ?>· <?php echo e($unpaid); ?> unpaid · <?php echo e($reserves); ?> reserves
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</p></div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($rosterOnly)): ?>
  <div class="dropdown roster-region-tools">
    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-label="Region tools for <?php echo e($region->region_name); ?>">Region tools</button>
    <div class="dropdown-menu dropdown-menu-end">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(app(\App\Services\TeamSelection\RegionManagerAccessService::class)->managedRegionIds(auth()->user(), $event)->contains((int) $region->id)): ?>
      <a class="dropdown-item" href="<?php echo e(route('backend.team-selection.region-profile-matches.index', [$event, $region])); ?>">Check profiles for whole region</a>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <h3 class="dropdown-header">Send email</h3>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($regionalScoped ?? false): ?>
      <a class="dropdown-item" href="<?php echo e(route('backend.event-communications.index', [$event, 'compose_scope' => 'region', 'region_id' => $region->id])); ?>">Players in this region</a>
      <a class="dropdown-item" href="<?php echo e(route('backend.event-communications.index', [$event, 'compose_scope' => 'region', 'region_id' => $region->id, 'compose_filter' => 'not_registered'])); ?>">Unpaid players in this region</a>
    <?php else: ?>
      <button type="button" class="dropdown-item emailRegionBtn" data-regionid="<?php echo e($region->id); ?>" data-regionname="<?php echo e($region->region_name); ?>">Players in this region</button>
      <button type="button" class="dropdown-item emailUnpaidRegionBtn" data-regionid="<?php echo e($region->id); ?>" data-regionname="<?php echo e($region->region_name); ?>">Unpaid players in this region</button>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <h3 class="dropdown-header">Clothing</h3>
    <a class="dropdown-item" href="<?php echo e(($regionalScoped ?? false) ? route('backend.event.clothing.index', [$event, 'region_id' => $region->id]) : route('backend.region.clothing.edit', ['region' => $region->id, 'event_id' => $event->id])); ?>">Clothing setup</a>
    <a class="dropdown-item" href="<?php echo e(route('backend.region.clothing.orders', ['region' => $region->id, 'event_id' => $event->id])); ?>">Clothing orders</a>
    </div>
  </div>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div>
<p class="small text-muted" data-roster-match-count role="status" aria-live="polite"></p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $region->teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
  <?php
    $invitations = ($teamSelectionInvitations ?? collect())->get($team->id, collect());
    $rankingManaged = $invitations->isNotEmpty();
    $teamReserves = $invitations->where('status', \App\Models\TeamSelectionInvitation::RESERVE)->sortBy('queue_position');
    $reserveCount = $team->workspaceSlots->where('selected_team_reserve', true)->count() + $teamReserves->whereNotIn('player_id', $team->workspaceSlots->where('selected_team_reserve', true)->pluck('player_id'))->count();
    $slots = $team->workspaceSlots;
    $occupiedSlots = $slots->filter(fn ($slot) => (int) $slot->player_id > 0 || $slot->noProfile);
    $fixtureHistory = app(\App\Services\TeamRosterService::class)->isLocked($event, $team);
    $automaticRosterChanges = !$rankingManaged && app(\App\Services\TeamRosterService::class)->canUpdateRosterAutomatically($event);
    $rosterLocked = $fixtureHistory && !$automaticRosterChanges;
    $automaticPlayingOrder = $rosterLocked && !$rankingManaged && app(\App\Services\TeamRosterService::class)->canUpdateOrderAutomatically($event);
    $rosterCanEdit = $rosterOnly || auth()->user()->can('team.players.manage', $team);
  ?>
  <details class="roster-team" data-roster-team="<?php echo e($team->id); ?>" data-category="<?php echo e($team->category_event_id); ?>" data-publication="<?php echo e($team->published ? 'published' : 'draft'); ?>" data-team-name="<?php echo e($team->name); ?>">
    <summary class="roster-team-summary">
      <span class="roster-team-title"><strong><?php echo e($team->name); ?></strong><span class="small text-muted"><?php echo e($team->category?->category?->name ?? 'Category not set'); ?> · #<?php echo e($team->id); ?> · <?php echo e($occupiedSlots->count()); ?>/<?php echo e($team->num_team_members); ?> places <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($rosterOnly)): ?>· <?php echo e($occupiedSlots->where('pay_status', '!=', 1)->count()); ?> unpaid · <?php echo e($reserveCount); ?> reserves
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</span></span>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rosterOnly): ?><span class="roster-disclosure" aria-hidden="true">⌄</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($rosterOnly)): ?>
      <span class="d-flex flex-wrap gap-2 align-items-center"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingManaged): ?><span class="badge bg-label-info">Ranking-managed</span>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<span class="badge <?php echo e($team->published ? 'bg-label-success' : 'bg-label-secondary'); ?>" data-team-publication="<?php echo e($team->id); ?>"><?php echo e($team->published ? 'Published' : 'Unpublished'); ?></span><span class="roster-disclosure" aria-hidden="true">⌄</span></span>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </summary>
    <div class="roster-team-body">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixtureHistory): ?><p class="text-muted"><?php echo e($automaticRosterChanges ? 'Roster changes update upcoming fixtures automatically. Started matches and results keep their original players. Imported players with started match history require the competition replacement workflow.' : 'This competition has fixture history. Ask the event administrator to change this player list.'); ?></p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($rosterOnly)): ?>
      <div class="roster-team-tools">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->noProfile && app(\App\Services\TeamSelection\RegionManagerAccessService::class)->canManageTeam(auth()->user(), $team, $event)): ?>
            <a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('backend.team-selection.profile-matches.index', [$event, $team])); ?>">Check existing profiles</a>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team.players.manage', $team)): ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($regionalScoped ?? false): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('backend.event-communications.index', [$event, 'compose_scope' => 'team', 'team_id' => $team->id])); ?>">Send team email</a><?php else: ?><button type="button" class="btn btn-sm btn-outline-secondary emailTeamBtn" data-teamid="<?php echo e($team->id); ?>" data-teamname="<?php echo e($team->name); ?>">Send team email</button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$rosterLocked && !$rankingManaged && $event->isTeam() && !$event->isInterprovincialTrials() && $team->num_team_members > 0 && $rosterCanEdit): ?>
          <?php echo $__env->make('backend.adminPage.admin_show._roster-add', ['reserveAdd' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <div class="table-responsive roster-compact-table"><table class="table align-middle team-player-table mb-0">
        <thead><tr><th scope="col">Rank</th><th scope="col" data-roster-player-column>Player</th><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($rosterOnly)): ?><th scope="col" data-roster-contact-column>Contact</th><th scope="col" data-roster-invitation-column>Invitation</th><th scope="col" data-roster-payment-column>Payment</th>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<th scope="col">Actions</th></tr></thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $slots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
            <?php
              $player = $team->noProfile && (int) $slot->noProfile?->player_profile > 0 ? $slot->noProfile->profile : ((int) $slot->player_id > 0 ? $slot->player : null);
              $np = $player ? null : $slot->noProfile;
              $name = $player ? trim($player->name.' '.$player->surname) : ($np ? trim($np->name.' '.$np->surname) : 'Empty place');
              $email = $player?->email ?: $np?->email;
              $cell = $player?->cellNr ?: $np?->cell_nr;
              $whatsAppUrl = \App\Support\PhoneContact::whatsAppUrl($cell);
              $paid = (int) $slot->pay_status === 1;
            ?>
            <?php if($rosterOnly && !$player && !$np) continue; ?>
            <tr data-roster-row="<?php echo e($slot->id ?? 'imported-'.$slot->noProfile?->id); ?>" data-roster-player-id="<?php echo e($player?->id); ?>" data-roster-imported-id="<?php echo e($slot->noProfile?->id); ?>" data-occupied="<?php echo e(($player || $np) ? 'true' : 'false'); ?>" data-search="<?php echo e($rosterOnly ? $name : $name.' '.$email.' '.$cell); ?>" <?php if (! ($rosterOnly)): ?>data-payment="<?php echo e(($player || $np) ? ($paid ? 'paid' : 'unpaid') : 'vacant'); ?>" data-profile="<?php echo e($player ? 'linked' : ($np ? 'imported' : 'vacant')); ?>"
<?php endif; ?>
>
              <td data-label="Rank"><span class="badge bg-label-primary"><?php echo e($slot->rank); ?></span><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$rosterOnly && $slot->selected_team_reserve): ?><span class="badge bg-label-warning ms-1">Reserve</span>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</td>
              <td data-label="Player">
                <strong><?php echo e($name); ?></strong>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($rosterOnly)): ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player): ?>
                    <?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal91914c4a33538de48225a297c93a4e0d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-rating','data' => ['playerId' => $player->id,'context' => $team->category]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-rating'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['player-id' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($player->id),'context' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($team->category)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $attributes = $__attributesOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__attributesOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal91914c4a33538de48225a297c93a4e0d)): ?>
<?php $component = $__componentOriginal91914c4a33538de48225a297c93a4e0d; ?>
<?php unset($__componentOriginal91914c4a33538de48225a297c93a4e0d); ?>
<?php endif; ?>
                  <?php else: ?>
                    <span class="badge bg-label-warning ms-1"><?php echo e($np ? 'Unlinked profile' : 'Vacant'); ?></span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($rosterOnly)): ?>
              <td data-label="Contact" <?php if(!$email && !$cell): ?>class="roster-contact-empty"<?php endif; ?>>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($email || $cell): ?>
                  <div class="roster-contact">
                    <div class="roster-contact-links">
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($email): ?><a href="mailto:<?php echo e($email); ?>" title="<?php echo e($email); ?>"><?php echo e($email); ?></a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cell): ?><a href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', $cell)); ?>" title="<?php echo e($cell); ?>"><?php echo e($cell); ?></a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($whatsAppUrl): ?><a class="roster-whatsapp" href="<?php echo e($whatsAppUrl); ?>" title="WhatsApp" target="_blank" rel="noopener noreferrer" aria-label="Open WhatsApp for <?php echo e($name); ?> (opens in a new tab)"><i class="ti ti-brand-whatsapp" aria-hidden="true"></i></a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div>
                <?php else: ?>
                  <span class="text-muted" aria-label="No contact details captured">—</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td data-label="Invitation">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player && ($selectionInvitation = $invitations->firstWhere('player_id', $player->id))): ?>
                  <span class="badge bg-label-info"><?php echo e(\Illuminate\Support\Str::headline($selectionInvitation->status)); ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td data-label="Payment"><span class="badge <?php echo e($paid ? 'bg-label-success' : 'bg-label-warning'); ?>"><?php echo e($paid ? 'Paid' : (($player || $np) ? 'Unpaid' : '—')); ?></span></td>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

              <td data-label="Actions"><div class="roster-row-actions">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rosterCanEdit && !$rosterLocked): ?><?php echo $__env->make('backend.adminPage.admin_show._roster-actions', ['compactRoster' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rosterCanEdit && $automaticPlayingOrder && ($player || $np)): ?>
                <?php echo $__env->make('backend.adminPage.admin_show._roster-order-buttons', ['compactRoster' => true, 'identity' => $team->noProfile ? $slot->noProfile : $slot], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php echo $__env->make('backend.adminPage.admin_show._roster-name-form', ['identity' => $team->noProfile ? $slot->noProfile : $slot], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$rosterLocked && !$rankingManaged && $slot->selected_team_reserve && $slot->rank > 0 && $slot->rank <= $team->num_team_members && auth()->user()->can('team.players.manage', $team)): ?>
                <form method="POST" action="<?php echo e(route('backend.team.addPlayers')); ?>" data-team-roster-change>
                  <?php echo csrf_field(); ?><input type="hidden" name="team_id" value="<?php echo e($team->id); ?>"><input type="hidden" name="event_id" value="<?php echo e($event->id); ?>"><input type="hidden" name="confirm_main_slot_id" value="<?php echo e($team->noProfile ? $np?->id ?? $slot->noProfile?->id : $slot->id); ?>"><input type="hidden" name="expected_rank" value="<?php echo e($slot->rank); ?>">
                  <button class="btn btn-sm btn-outline-primary">Confirm main place</button>
                </form>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$player && !$np && !$rosterLocked && !$rankingManaged && $event->isTeam() && !$event->isInterprovincialTrials() && $slot->rank <= $team->num_team_members && auth()->user()->can('team.players.manage', $team)): ?>
                <?php echo $__env->make('backend.adminPage.admin_show._roster-fill', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rosterLocked && !$rosterOnly && $slot->exists && $player && $paid): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (\Illuminate\Support\Facades\Blade::check('role', 'super-user')): ?>
                  <?php $__env->startComponent('backend.adminPage.admin_show._roster-player-modal', ['team' => $team, 'rosterSlot' => $slot, 'identity' => $slot->noProfile ?? $slot, 'name' => $name, 'event' => $event, 'superAdminOnly' => true]); ?>
                    <?php echo $__env->make('backend.adminPage.admin_show._roster-withdrawal-action', ['slot' => $slot], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                  <?php echo $__env->renderComponent(); ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($rosterOnly)): ?>
              <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team.players.manage', $team)): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player): ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($regionalScoped ?? false): ?>
                    <a class="btn btn-sm btn-outline-secondary roster-icon-control" href="<?php echo e(route('backend.event-communications.index', [$event, 'compose_scope' => 'individual', 'individual_key' => 'player:'.$player->id])); ?>" title="Send email" aria-label="Send email to <?php echo e($name); ?>"><i class="ti ti-mail" aria-hidden="true"></i></a>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary roster-icon-control emailPlayer" data-playerid="<?php echo e($player->id); ?>" data-name="<?php echo e($name); ?>" title="Send email" aria-label="Send email to <?php echo e($name); ?>"><i class="ti ti-mail" aria-hidden="true"></i></button>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php else: ?>
                  <span class="small text-muted <?php echo e($np ? 'roster-imported-action-hint' : ''); ?>"><?php echo e($np ? 'Manage imported player in selection' : 'No player assigned'); ?></span>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<?php endif; ?>


<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

              </div></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?> <tr><td colspan="<?php echo e($rosterOnly ? 3 : 6); ?>" class="text-muted">No roster places have been created.</td></tr> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table></div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rosterCanEdit && !$rosterLocked): ?><?php echo $__env->make('backend.adminPage.admin_show._roster-add', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingManaged && !$rosterOnly): ?>
        <details class="roster-reserves mt-3"><summary>Reserve queue (<?php echo e($teamReserves->count()); ?>)</summary><p class="small text-muted mt-2">Reserves enter the active roster, draws, exports and emails to players in the team or region only after promotion.</p>
          <ol class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $teamReserves; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reserve): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?><li><?php echo e($reserve->player?->full_name ?? 'Missing player'); ?> <span class="text-muted">· Ranking #<?php echo e($reserve->ranking_position ?? '—'); ?></span></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?><li class="list-unstyled">No reserves remain.</li><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ol>
        </details>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </div>
  </details>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <div class="alert alert-light border">No teams in this region.</div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($region->teams->isNotEmpty()): ?><div class="alert alert-light border" data-roster-empty hidden>No players or teams match these filters. Clear filters to see the region roster.</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\players-region.blade.php ENDPATH**/ ?>