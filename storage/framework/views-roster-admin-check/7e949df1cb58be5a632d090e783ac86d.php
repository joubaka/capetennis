<section class="card dashboard-events-card mb-4" aria-labelledby="upcoming-events-title">
  <div class="card-header dashboard-section-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
    <div>
      <h5 class="mb-1" id="upcoming-events-title">Upcoming events</h5>
      <p class="text-muted small mb-0">Published Cape Tennis events that are coming up or currently under way.</p>
    </div>
    <a class="btn btn-sm btn-outline-primary align-self-start align-self-sm-center" href="<?php echo e(route('events.index')); ?>">View all events</a>
  </div>
  <div class="card-body">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $upcomingEvents->chunk(2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventRow): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <div class="row g-3 <?php echo e($loop->last ? '' : 'mb-3'); ?>">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventRow; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="col-12 col-md-6">
            <article class="upcoming-event-card d-flex flex-column">
              <div class="d-flex flex-wrap gap-2 mb-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->eventTypeModel?->name): ?>
                  <span class="badge bg-label-primary"><?php echo e($event->eventTypeModel->name); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->series?->name): ?>
                  <span class="badge bg-label-secondary"><?php echo e($event->series->name); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
              <h5 class="mb-2"><?php echo e($event->name); ?></h5>
              <p class="text-muted small mb-3">
                <i class="ti ti-calendar me-1" aria-hidden="true"></i>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->start_date && $event->end_date && ! $event->start_date->isSameDay($event->end_date)): ?>
                  <?php echo e($event->start_date->format('d M Y')); ?> – <?php echo e($event->end_date->format('d M Y')); ?>

                <?php else: ?>
                  <?php echo e($event->start_date?->format('d M Y') ?? 'Date to be confirmed'); ?>

                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </p>
              <a class="btn btn-sm btn-primary mt-auto align-self-start" href="<?php echo e(route('events.show', $event)); ?>">View event</a>
            </article>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
      <div class="text-center py-4">
        <span class="badge bg-label-secondary p-3 mb-3"><i class="ti ti-calendar-off ti-lg" aria-hidden="true"></i></span>
        <h5>No upcoming events published</h5>
        <p class="text-muted mb-0">Check back later for newly published Cape Tennis events.</p>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</section>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\partials\upcoming-events.blade.php ENDPATH**/ ?>