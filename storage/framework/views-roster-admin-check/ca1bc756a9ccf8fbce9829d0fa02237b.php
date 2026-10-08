<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->announcements->isNotEmpty()): ?>
  <div class="card event-section-card mb-4">
    <div class="card-body event-card-padding p-4">
      <div class="event-section-heading mb-4">
        <span class="event-section-icon" aria-hidden="true">
          <svg class="event-section-icon-svg" viewBox="0 0 24 24" focusable="false">
            <path d="M4 14h3l9 4V6l-9 4H4v4Zm3 0 2 5h3l-2-5m9-5a4 4 0 0 1 0 6" />
          </svg>
        </span>
        <div>
          <h5 class="mb-1">Latest announcements</h5>
          <p class="text-muted mb-0">Updates published by the event organiser.</p>
        </div>
      </div>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->announcements; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $announcement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="alert alert-primary mb-3">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(filled($announcement->title)): ?>
            <h6 class="alert-heading"><?php echo e($announcement->title); ?></h6>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <div class="event-information-content"><?php echo $announcement->message; ?></div>
          <div class="small text-muted mt-2">
            <i class="ti ti-clock me-1"></i><?php echo e(optional($announcement->created_at)->timezone(config('app.timezone'))->format('d M Y, H:i')); ?>

          </div>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\event-announcements.blade.php ENDPATH**/ ?>