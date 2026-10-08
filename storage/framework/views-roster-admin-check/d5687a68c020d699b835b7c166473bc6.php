<div class="mb-3">
  <div class="d-flex justify-content-between mb-4">
    <div>
      <button class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#generateDrawModal">
        <i class="fas fa-plus"></i> Create Draw
      </button>
      <button class="btn btn-danger"><i class="fas fa-sort"></i> Change Draw Order</button>
    </div>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categoryEvent->draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="border rounded p-3 mb-4 bg-white shadow-sm">
        <div class="d-flex justify-content-between">
          <div>
            <h5 class="mb-2"><?php echo e($draw->drawName); ?></h5>
            <div class="mb-2">
              <span class="badge bg-warning">Individual</span>
              <span class="badge bg-primary">Tennis (Singles)</span>
              <span class="badge bg-danger"><?php echo e($draw->registrations_count ?? '0'); ?> players</span>
              <span class="badge bg-dark"><?php echo e($draw->gender ?? 'Mixed'); ?></span>
              <span class="badge bg-<?php echo e($draw->locked ? 'warning' : 'info'); ?>">
                <?php echo e($draw->locked ? '🔒 Locked' : '🔓 Unlocked'); ?>

              </span>
            </div>
            <div class="text-primary small fw-bold mb-1">
              <?php echo e($draw->completion_percent ?? '0%'); ?> Complete
            </div>
            <div class="progress" style="height: 6px; max-width: 300px;">
              <div class="progress-bar bg-primary" role="progressbar"
                   style="width: <?php echo e($draw->completion_percent ?? '0%'); ?>;"></div>
            </div>
          </div>

          <div class="d-flex align-items-start gap-2 flex-wrap">
            <a href="<?php echo e(route('category.manage', $draw->category_event_id)); ?>" class="btn btn-sm btn-warning">
              <i class="fas fa-cog"></i> Settings
            </a>
            <a href="#" class="btn btn-sm btn-orange"><i class="fas fa-users"></i> Players</a>
            <a href="<?php echo e(route('draws.show', $draw->id)); ?>" target="_blank" class="btn btn-sm btn-success">
              <i class="fas fa-eye"></i> View Draw
            </a>
            <form method="POST" action="<?php echo e(route('draws.destroy', $draw->id)); ?>" onsubmit="return confirm('Delete this draw?')">
              <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
              <button class="btn btn-sm btn-danger" type="submit"><i class="fas fa-trash-alt"></i></button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\tournament_admin.blade.php ENDPATH**/ ?>