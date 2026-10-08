<?php $__env->startSection('title', 'Bank Details Received'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5 text-center">

      <div class="card shadow-sm border-success">
        <div class="card-body py-5">
          <div class="mb-3">
            <i class="ti ti-circle-check text-success" style="font-size: 4rem;"></i>
          </div>
          <h4 class="text-success mb-2">Bank Details Received</h4>
          <p class="text-muted mb-3">
            Thank you, <strong><?php echo e($user->name); ?></strong>! We have received your bank details for
            <?php echo e($registrations->count() === 1 ? '1 refund' : $registrations->count() . ' refunds'); ?>

            totalling <strong>R<?php echo e(number_format($registrations->sum('refund_net'), 2)); ?></strong>.
            These will be processed within <strong>1–3 business days</strong>.
          </p>
          <ul class="list-unstyled text-start small text-muted mx-auto" style="max-width:320px">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php
                $player    = $reg->players->first();
                $pName     = $player ? trim($player->name . ' ' . $player->surname) : 'Player';
                $eventName = optional($reg->categoryEvent?->event)->name ?? 'Event';
              ?>
              <li><i class="ti ti-check text-success me-1"></i> <?php echo e($pName); ?> — <?php echo e($eventName); ?> — R<?php echo e(number_format($reg->refund_net, 2)); ?></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </ul>
          <p class="small text-muted mt-3">
            Questions? Email <a href="mailto:support@capetennis.co.za">support@capetennis.co.za</a>
          </p>
        </div>
      </div>

    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\refund\bank-details-submitted.blade.php ENDPATH**/ ?>