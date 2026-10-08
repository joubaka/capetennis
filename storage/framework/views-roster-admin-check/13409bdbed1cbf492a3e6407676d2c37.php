<?php $__env->startSection('title', 'Series'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">

  
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Series</h4>
    <a href="<?php echo e(route('series.create')); ?>" class="btn btn-primary">
      Create Series
    </a>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>Name</th>
            <th>Events</th>
            <th>Status</th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $series; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
              <td>
                <strong><?php echo e($s->name); ?></strong>
              </td>
              <td>
                <?php echo e($s->events_count); ?>

              </td>
              <td>
                <span class="badge bg-<?php echo e($s->active ? 'success' : 'secondary'); ?>">
                  <?php echo e($s->active ? 'Active' : 'Inactive'); ?>

                </span>
              </td>
              <td class="text-end">
                <a href="<?php echo e(route('series.show', $s)); ?>"
                   class="btn btn-sm btn-outline-primary">
                  View
                </a>
                <a href="<?php echo e(route('series.events', $s)); ?>"
                   class="btn btn-sm btn-outline-secondary">
                  Events
                </a>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="4" class="text-center text-muted py-3">
                No series created yet
              </td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\series\series-index.blade.php ENDPATH**/ ?>