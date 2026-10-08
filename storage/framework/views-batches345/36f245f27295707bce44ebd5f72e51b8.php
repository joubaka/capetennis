<div class="mb-3 gap-3">
  <div>
    <span id="publishResults" data-event_id="<?php echo e($event->id); ?>"
      class="mb-2 align-middle btn btn-<?php echo e($event->results_published ? 'danger' : 'success'); ?> btn-sm">
      <?php echo e($event->results_published ? 'Unpublish Results' : 'Publish Results'); ?>

    </span>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->series): ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->series->rankType->type == 'position' || $event->series->rankType->type == 'overberg'): ?>
        <?php echo $__env->make('backend.adminPage._includes.position_type', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php else: ?>
        <?php echo $__env->make('backend.adminPage._includes.participation_type', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php elseif($event->eventType != 7): ?>
      <?php echo $__env->make('backend.adminPage._includes.position_type', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\results.blade.php ENDPATH**/ ?>