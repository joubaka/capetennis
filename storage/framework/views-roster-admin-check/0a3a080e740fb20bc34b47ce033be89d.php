

<?php $__env->startSection('title', 'My Refunds'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mt-4" style="max-width: 860px;">

  <h4 class="mb-4">My Withdrawal & Refund Status</h4>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
    <div class="alert alert-success"><?php echo e(session('success')); ?></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registrations->isEmpty()): ?>
    <div class="card">
      <div class="card-body text-center text-muted py-5">
        <i class="ti ti-inbox fs-2 mb-2 d-block"></i>
        No withdrawals found.
      </div>
    </div>
  <?php else: ?>
    <div class="card shadow-sm">
      <div class="card-body p-0">
        <table class="table table-bordered mb-0">
          <thead class="table-light">
            <tr>
              <th>Event</th>
              <th>Category</th>
              <th>Player(s)</th>
              <th>Withdrawn</th>
              <th>Refund Method</th>
              <th>Refund Status</th>
              <th>Amount</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php
                $eventName    = optional($reg->categoryEvent?->event)->name ?? '—';
                $categoryName = optional($reg->categoryEvent?->category)->name ?? '—';
                $playerNames  = $reg->players->map(fn($p) => trim($p->name . ' ' . $p->surname))->implode(', ') ?: '—';
                $statusBadge  = match($reg->refund_status) {
                  'completed'   => ['bg-success',  'Completed'],
                  'pending'     => ['bg-warning text-dark', 'Pending'],
                  'not_refunded' => ['bg-secondary', 'No Refund'],
                  default        => ['bg-light text-muted border', $reg->refund_status ?? 'Not set'],
                };
              ?>
              <tr>
                <td><?php echo e($eventName); ?></td>
                <td><?php echo e($categoryName); ?></td>
                <td><?php echo e($playerNames); ?></td>
                <td><?php echo e($reg->withdrawn_at?->format('d M Y') ?? '—'); ?></td>
                <td><?php echo e($reg->refund_method ? ucfirst($reg->refund_method) : '—'); ?></td>
                <td>
                  <span class="badge <?php echo e($statusBadge[0]); ?>"><?php echo e($statusBadge[1]); ?></span>
                </td>
                <td>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reg->refund_net > 0): ?>
                    R<?php echo e(number_format($reg->refund_net, 2)); ?>

                  <?php else: ?>
                    —
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
                <td>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reg->canRequestRefund()): ?>
                    <a href="<?php echo e(route('registrations.refund.choose', $reg)); ?>"
                       class="btn btn-sm btn-primary">
                      Choose Refund
                    </a>
                  <?php elseif($reg->refund_status === 'pending' && $reg->refund_method === 'bank'): ?>
                    <span class="text-muted small">Awaiting bank transfer</span>
                  <?php elseif($reg->refund_status === 'completed'): ?>
                    <span class="text-success small">
                      <i class="ti ti-check me-1"></i>Done
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reg->refunded_at): ?>
                        · <?php echo e($reg->refunded_at->format('d M Y')); ?>

                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </span>
                  <?php else: ?>
                    <span class="text-muted small">—</span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <p class="text-muted small mt-3">
      For bank refunds still showing as <strong>Pending</strong>, our team processes these manually. If your refund has not arrived within 5 business days, please contact
      <a href="mailto:support@capetennis.co.za">support@capetennis.co.za</a>.
    </p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\registrations\refund-status.blade.php ENDPATH**/ ?>