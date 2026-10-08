<div class="row">
  <div class="col-xl-8 col-lg-7 col-md-7">
    <?php echo $__env->make('frontend.event.partials.event-information', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('frontend.event.partials.event-announcements', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </div>

  <div class="col-xl-4 col-lg-5 col-md-5">
    <?php echo $__env->make('frontend.event.partials.event-about', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <div class="card event-section-card mb-4">
      <div class="card-header">
        <h5 class="mb-1">Event documents</h5>
        <p class="text-muted small mb-0">Downloads supplied by the organiser.</p>
      </div>
      <div class="card-body">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $event->files; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <div class="event-document mb-2">
            <a class="d-flex align-items-center gap-2 text-break"
               href="<?php echo e(route('events.documents.show', [$event, $file])); ?>">
              <i class="ti ti-file-description fs-4 text-primary"></i>
              <span><?php echo e($file->name); ?></span>
            </a>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <div class="text-center py-3">
            <i class="ti ti-file-off fs-2 text-muted"></i>
            <p class="text-muted small mb-0 mt-2">No event documents are available yet.</p>
          </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->results_published == 1): ?>
      <div class="card event-section-card mb-4">
        <div class="card-header"><small class="text-uppercase">Results</small></div>
        <div class="card-body">
          <a href="<?php echo e(route('events.results', $event->id)); ?>" class="btn bg-label-success btn-sm">
            <i class="ti ti-trophy me-1"></i> View Results
          </a>
        </div>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\eventTypes\default.blade.php ENDPATH**/ ?>