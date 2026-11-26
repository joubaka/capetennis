<div class="row">
  <div class="col-xl-8 col-lg-7 col-md-7">
    <?php echo $__env->make('frontend.event.partials.event-announcements', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('frontend.event.partials.event-information', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </div>

  <div class="col-xl-4 col-lg-5 col-md-5">
    <?php echo $__env->make('frontend.event.partials.event-about', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('frontend.event.partials.event-documents', [
      'documentsReadOnly' => true,
      'documentCardClass' => 'event-section-card',
      'documentTitle' => 'Event documents',
      'documentDescription' => 'Downloads supplied by the organiser.',
      'documentEmptyMessage' => 'No event documents are available yet.',
      'documentNewWindow' => false,
    ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
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
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\eventTypes\default.blade.php ENDPATH**/ ?>