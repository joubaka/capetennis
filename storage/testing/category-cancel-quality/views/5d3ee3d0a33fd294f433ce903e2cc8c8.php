<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $event->eventType !== 3 && !($rosterOnly ?? false) && !($centralTeamWorkspace ?? false)): ?>
  <?php echo $__env->make('backend.adminPage.admin_show.tabs.players-legacy', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php else: ?>
<?php ($regionsInEvent = $event->regions ?? collect()); ?>
<div class="tab-pane fade show active" id="tab-players" role="tabpanel" aria-labelledby="players-tab">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $regionsInEvent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <section id="players-region-<?php echo e($region->id); ?>" data-roster-panel="players" data-region-id="<?php echo e($region->id); ?>" <?php if($rosterOnly ?? false): ?>data-loaded="true"
<?php endif; ?>
 <?php if($k !== 0): ?> hidden
<?php endif; ?>
>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(((int) $event->eventType === 3 || ($centralTeamWorkspace ?? false)) && !($rosterOnly ?? false)): ?><div class="roster-panel-placeholder p-4" role="status">Select a region to load its roster.</div>
      <?php else: ?> <?php echo $__env->make('backend.adminPage.admin_show.tabs.players-region', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    </section>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <div class="alert alert-light border mt-3">No regions have been added to this event.</div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\players.blade.php ENDPATH**/ ?>