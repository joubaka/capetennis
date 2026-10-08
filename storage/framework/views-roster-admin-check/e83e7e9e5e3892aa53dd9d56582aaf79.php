

<?php $__env->startSection('title', $series->name); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?php echo e($series->name); ?></h4>

    <a href="<?php echo e(route('admin.series.events', $series)); ?>"
       class="btn btn-primary">
      Manage Events
    </a>
  </div>

  <div class="row g-4">

    
    <div class="col-xl-4">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Series Info</h5>
        </div>
        <div class="card-body">
          <p class="mb-2">
            <strong>Status:</strong>
            <span class="badge bg-<?php echo e($series->active ? 'success' : 'secondary'); ?>">
              <?php echo e($series->active ? 'Active' : 'Inactive'); ?>

            </span>
          </p>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($series->description): ?>
            <p class="mb-0">
              <strong>Description</strong><br>
              <?php echo e($series->description); ?>

            </p>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>

    
    <div class="col-xl-8">
      <div class="card h-100">
        <div class="card-header">
          <h5 class="mb-0">Series Stats</h5>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            <li>
              Events
              <span class="fw-semibold float-end">
                <?php echo e($series->events_count); ?>

              </span>
            </li>
          </ul>
        </div>
      </div>
    </div>

  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\series\show.blade.php ENDPATH**/ ?>