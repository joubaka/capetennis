<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if((int) $event->eventType !== 3): ?>
  <?php echo $__env->make('backend.adminPage.admin_show.tabs.player-order-legacy', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php else: ?>
<div class="tab-pane fade" id="tab-order" role="tabpanel" aria-labelledby="order-tab">
  <p class="alert alert-info">Set player order before generating fixtures. Ranking-managed teams use selection order. Generated fixtures lock reordering to preserve match lineups, times and results.</p>
  <label for="order-region" class="form-label">Region</label>
  <select id="order-region" class="form-select mb-3" data-order-region>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($region->id); ?>"><?php echo e($region->region_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </select>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $event->regions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $region): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <section id="order-region-<?php echo e($region->id); ?>" data-roster-panel="order" data-region-id="<?php echo e($region->id); ?>" <?php if($k !== 0): ?> hidden <?php endif; ?>><div class="p-4" role="status">Select a region to load player order.</div></section>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?> <div class="alert alert-light border">No regions have been added.</div> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\player-order.blade.php ENDPATH**/ ?>