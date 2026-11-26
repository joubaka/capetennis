<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $event->eventType !== 3 && !($rosterOnly ?? false) && !($centralTeamWorkspace ?? false)): ?>
  <?php echo $__env->make('backend.adminPage.admin_show.tabs.player-order-legacy', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php else: ?>
<div class="tab-pane fade" id="tab-order" role="tabpanel" aria-labelledby="order-tab">
  <div class="roster-toolbar order-filter-bar mb-2">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <label class="visually-hidden" for="order-team-search">Find a team</label>
      <input type="search" id="order-team-search" class="form-control" placeholder="Find a team" data-order-search>
      <button type="button" class="btn btn-outline-secondary" data-order-clear>Clear search</button>
    </div>
    <p class="small text-muted mb-0"><?php echo e(($isRegionalPreview ?? false) ? 'Preview controls do not save changes.' : (($rosterOnly ?? false) ? 'Move players up or down within their team.' : 'Moves save immediately. Ranking-managed teams use selection order. Fixture locks appear beside each team.')); ?></p>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $event->regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <section id="order-region-<?php echo e($region->id); ?>" data-roster-panel="order" data-region-id="<?php echo e($region->id); ?>" <?php if($rosterOnly ?? false): ?>data-loaded="true"
<?php endif; ?>
 <?php if($k !== 0): ?> hidden
<?php endif; ?>
>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rosterOnly ?? false): ?>
        <?php ($orderLocked = $region->teams->contains(fn ($team) => app(\App\Services\TeamRosterService::class)->isLocked($event, $team))); ?>
        <?php echo $__env->make('backend.adminPage.admin_show.tabs.order-region', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php else: ?><div class="p-4" role="status">Select a region to load player order.</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </section>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <div class="alert alert-light border">No regions have been added.</div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\player-order.blade.php ENDPATH**/ ?>