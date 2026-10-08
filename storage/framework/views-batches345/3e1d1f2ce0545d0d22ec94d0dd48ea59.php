<?php
  $occupied = $region->teams->sum(fn ($team) => $team->workspaceSlots->filter(fn ($slot) => (int) $slot->player_id > 0 || $slot->noProfile)->count());
  $unpaid = $region->teams->sum(fn ($team) => $team->workspaceSlots->filter(fn ($slot) => ((int) $slot->player_id > 0 || $slot->noProfile) && (int) $slot->pay_status !== 1)->count());
  $reserves = ($teamSelectionInvitations ?? collect())->only($region->teams->modelKeys())->flatten(1)->where('status', \App\Models\TeamSelectionInvitation::RESERVE)->count();
?>
<div class="roster-region-header">
  <div><h2 class="h5 mb-1"><?php echo e($region->region_name); ?></h2><p class="text-muted mb-0"><?php echo e($region->teams->count()); ?> teams · <?php echo e($occupied); ?> occupied places · <?php echo e($unpaid); ?> unpaid · <?php echo e($reserves); ?> reserves</p></div>
  <div class="d-flex flex-wrap gap-2"><div class="dropdown"><button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Send Emails</button><div class="dropdown-menu dropdown-menu-end">
    <button type="button" class="dropdown-item emailRegionBtn" data-regionid="<?php echo e($region->id); ?>" data-regionname="<?php echo e($region->region_name); ?>">Players in this region</button>
    <button type="button" class="dropdown-item emailUnpaidRegionBtn" data-regionid="<?php echo e($region->id); ?>" data-regionname="<?php echo e($region->region_name); ?>">Unpaid players in this region</button>
  </div></div>
          <div class="dropdown"><button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-label="Clothing for <?php echo e($region->region_name); ?>">Clothing</button><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="<?php echo e(route('backend.region.clothing.edit', ['region' => $region->id, 'event_id' => $event->id])); ?>">Clothing setup</a><a class="dropdown-item" href="<?php echo e(route('backend.region.clothing.orders', ['region' => $region->id, 'event_id' => $event->id])); ?>">Clothing orders</a></div></div>
  </div>
</div>
<p class="small text-muted" data-roster-match-count role="status" aria-live="polite"></p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $region->teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
  <?php
    $invitations = ($teamSelectionInvitations ?? collect())->get($team->id, collect());
    $rankingManaged = $invitations->isNotEmpty();
    $teamReserves = $invitations->where('status', \App\Models\TeamSelectionInvitation::RESERVE)->sortBy('queue_position');
    $slots = $team->workspaceSlots;
    $occupiedSlots = $slots->filter(fn ($slot) => (int) $slot->player_id > 0 || $slot->noProfile);
  ?>
  <details class="roster-team" data-roster-team="<?php echo e($team->id); ?>" data-category="<?php echo e($team->category_event_id); ?>" data-publication="<?php echo e($team->published ? 'published' : 'draft'); ?>" data-team-name="<?php echo e($team->name); ?>" <?php if($loop->first): ?> open <?php endif; ?>>
    <summary class="roster-team-summary">
      <span class="roster-team-title"><strong><?php echo e($team->name); ?></strong><span class="small text-muted"><?php echo e($team->category?->category?->name ?? 'Category not set'); ?> · <?php echo e($occupiedSlots->count()); ?>/<?php echo e($team->num_team_members); ?> places · <?php echo e($occupiedSlots->where('pay_status', '!=', 1)->count()); ?> unpaid · <?php echo e($teamReserves->count()); ?> reserves</span></span>
      <span class="d-flex flex-wrap gap-2 align-items-center"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingManaged): ?><span class="badge bg-label-info">Ranking-managed</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><span class="badge <?php echo e($team->published ? 'bg-label-success' : 'bg-label-secondary'); ?>" data-team-publication="<?php echo e($team->id); ?>"><?php echo e($team->published ? 'Published' : 'Unpublished'); ?></span><span class="roster-disclosure" aria-hidden="true">⌄</span></span>
    </summary>
    <div class="roster-team-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <span class="small text-muted">Team #<?php echo e($team->id); ?> · Player order is shown by rank.</span>
        <div class="d-flex flex-wrap gap-2">
          <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team.players.manage', $team)): ?>
            <button type="button" class="btn btn-outline-secondary emailTeamBtn" data-teamid="<?php echo e($team->id); ?>" data-teamname="<?php echo e($team->name); ?>">Send team email</button>
          <?php endif; ?>
        </div>
      </div>
      <div class="table-responsive"><table class="table align-middle team-player-table mb-0">
        <thead><tr><th scope="col">Rank</th><th scope="col">Player</th><th scope="col">Contact</th><th scope="col">Payment</th><th scope="col">Actions</th></tr></thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $slots; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $slot): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
            <?php
              $player = (int) $slot->player_id > 0 ? $slot->player : null;
              $np = $player ? null : $slot->noProfile;
              $name = $player ? trim($player->name.' '.$player->surname) : ($np ? trim($np->name.' '.$np->surname) : 'Empty place');
              $email = $player?->email ?: $np?->email;
              $cell = $player?->cellNr ?: $np?->cell_nr;
              $whatsAppUrl = \App\Support\PhoneContact::whatsAppUrl($cell);
              $paid = (int) $slot->pay_status === 1;
            ?>
            <tr data-roster-row="<?php echo e($slot->id ?? 'imported-'.$np?->id); ?>" data-occupied="<?php echo e(($player || $np) ? 'true' : 'false'); ?>" data-search="<?php echo e($name.' '.$email.' '.$cell); ?>" data-payment="<?php echo e(($player || $np) ? ($paid ? 'paid' : 'unpaid') : 'vacant'); ?>" data-profile="<?php echo e($player ? 'linked' : ($np ? 'imported' : 'vacant')); ?>">
              <td data-label="Rank"><span class="badge bg-label-primary"><?php echo e($slot->rank); ?></span></td>
              <td data-label="Player"><strong><?php echo e($name); ?></strong><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player): ?><?php if (isset($component)) { $__componentOriginal91914c4a33538de48225a297c93a4e0d = $component; } ?>
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
<?php endif; ?><?php else: ?><span class="badge bg-label-warning ms-1"><?php echo e($np ? 'Unlinked profile' : 'Vacant'); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
              <td data-label="Contact"><div class="roster-contact">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($email): ?><div class="d-flex align-items-center gap-2"><a href="mailto:<?php echo e($email); ?>"><?php echo e($email); ?></a><button type="button" class="btn btn-outline-secondary" data-copy-contact="<?php echo e($email); ?>" aria-label="Copy email for <?php echo e($name); ?>">Copy</button></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cell): ?><div class="d-flex flex-wrap align-items-center gap-2"><a href="tel:<?php echo e(preg_replace('/[^0-9+]/', '', $cell)); ?>"><?php echo e($cell); ?></a><button type="button" class="btn btn-outline-secondary" data-copy-contact="<?php echo e($cell); ?>" aria-label="Copy cell number for <?php echo e($name); ?>">Copy</button><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($whatsAppUrl): ?><a href="<?php echo e($whatsAppUrl); ?>" class="btn btn-outline-success" target="_blank" rel="noopener noreferrer" aria-label="Open WhatsApp for <?php echo e($name); ?> (opens in a new tab)"><i class="ti ti-brand-whatsapp me-1" aria-hidden="true"></i>WhatsApp</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$email && !$cell): ?><span class="text-muted">No contact details captured.</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div></td>
              <td data-label="Payment"><span class="badge <?php echo e($paid ? 'bg-label-success' : 'bg-label-warning'); ?>"><?php echo e($paid ? 'Paid' : (($player || $np) ? 'Unpaid' : '—')); ?></span></td>
              <td data-label="Actions"><?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team.players.manage', $team)): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player): ?><button type="button" class="btn btn-outline-secondary emailPlayer" data-playerid="<?php echo e($player->id); ?>" data-name="<?php echo e($name); ?>">Send email</button><?php else: ?><span class="small text-muted"><?php echo e($np ? 'Manage imported player in selection' : 'No player assigned'); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php endif; ?></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?> <tr><td colspan="5" class="text-muted">No roster places have been created.</td></tr> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table></div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingManaged): ?>
        <details class="roster-reserves mt-3"><summary>Reserve queue (<?php echo e($teamReserves->count()); ?>)</summary><p class="small text-muted mt-2">Reserves enter the active roster, draws, exports and emails to players in the team or region only after promotion.</p>
          <ol class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $teamReserves; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reserve): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?><li><?php echo e($reserve->player?->full_name ?? 'Missing player'); ?> <span class="text-muted">· Ranking #<?php echo e($reserve->ranking_position ?? '—'); ?></span></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?><li class="list-unstyled">No reserves remain.</li><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ol>
        </details>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </details>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <div class="alert alert-light border">No teams in this region.</div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($region->teams->isNotEmpty()): ?><div class="alert alert-light border" data-roster-empty hidden>No players or teams match these filters. Clear filters to see the region roster.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\players-region.blade.php ENDPATH**/ ?>