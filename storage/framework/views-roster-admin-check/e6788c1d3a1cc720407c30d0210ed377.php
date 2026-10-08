<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(
  $sDate ||
  $eDate ||
  $formatEntryLine ||
  $formatWithdrawalLine ||
  $event->entryFee !== null ||
  $event->eventCategories->isNotEmpty() ||
  $event->venue_notes ||
  (isset($mastersBatch) && $mastersBatch->response_deadline) ||
  (isset($mastersBatch) && $mastersBatch->payment_deadline) ||
  $event->organizer ||
  $event->email
): ?>
  <div class="card event-section-card mb-4">
    <div class="card-body">
      <small class="card-text text-uppercase">About</small>

      <ul class="list-unstyled mb-4 mt-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sDate): ?>
          <li class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            <span class="fw-bold">Start Date:</span>
            <span class="badge bg-label-success"><?php echo e($sDate); ?></span>
          </li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eDate): ?>
          <li class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
            <span class="fw-bold">End Date:</span>
            <span class="badge bg-label-success"><?php echo e($eDate); ?></span>
          </li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <li class="d-flex align-items-center flex-wrap gap-2 mb-3">
          <i class="ti ti-users" aria-hidden="true"></i>
          <span class="fw-bold">Confirmed entries:</span>
          <span class="badge bg-label-info"><?php echo e(number_format($entryCount ?? 0)); ?></span>
        </li>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($formatEntryLine): ?>
          <li class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <i class="ti ti-check" aria-hidden="true"></i>
            <span class="fw-bold">Entry deadline:</span>
            <span class="badge bg-label-warning"><?php echo e($formatEntryLine); ?></span>
          </li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($formatWithdrawalLine): ?>
          <li class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <i class="ti ti-x" aria-hidden="true"></i>
            <span class="fw-bold">Withdrawal deadline:</span>
            <span class="badge bg-label-danger"><?php echo e($formatWithdrawalLine); ?></span>
          </li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($mastersBatch) && $mastersBatch->response_deadline): ?>
          <li class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <i class="ti ti-mail-forward" aria-hidden="true"></i>
            <span class="fw-bold">Response deadline:</span>
            <span class="badge bg-label-warning"><?php echo e($mastersBatch->response_deadline->format('d M Y H:i')); ?></span>
          </li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($mastersBatch) && $mastersBatch->payment_deadline): ?>
          <li class="d-flex align-items-center flex-wrap gap-2 mb-3">
            <i class="ti ti-credit-card" aria-hidden="true"></i>
            <span class="fw-bold">Payment deadline:</span>
            <span class="badge bg-label-warning"><?php echo e($mastersBatch->payment_deadline->format('d M Y H:i')); ?></span>
          </li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->venue_notes): ?>
          <li class="d-flex align-items-start gap-2 mb-3">
            <i class="ti ti-map-pin mt-1" aria-hidden="true"></i>
            <span><strong>Venue:</strong> <?php echo e($event->venue_notes); ?></span>
          </li>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->entryFee !== null || $event->eventCategories->contains(fn ($categoryEvent) => $categoryEvent->entry_fee !== null)): ?>
          <?php echo $__env->make('frontend.event.partials.entry-fees', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </ul>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->organizer || $event->email): ?>
        <small class="card-text text-uppercase">Contact</small>
        <ul class="list-unstyled mb-0 mt-3">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->organizer): ?>
            <li class="d-flex align-items-start gap-2 mb-3">
              <i class="ti ti-phone-call mt-1" aria-hidden="true"></i>
              <span><strong>Organizer:</strong> <?php echo e($event->organizer); ?></span>
            </li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->email): ?>
            <li class="d-flex align-items-start gap-2 mb-0">
              <i class="ti ti-mail mt-1" aria-hidden="true"></i>
              <span class="text-break"><strong>Email:</strong> <a href="mailto:<?php echo e($event->email); ?>"><?php echo e($event->email); ?></a></span>
            </li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ul>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\event-about.blade.php ENDPATH**/ ?>