

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">

  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h4 class="mb-1"><i class="ti ti-alert-triangle me-2 text-danger"></i>Orphaned Registrations</h4>
      <p class="text-muted mb-0">Paid orders where registration records were not created — likely due to an ITN processing failure.</p>
    </div>
    <a href="<?php echo e(route('backend.superadmin.index')); ?>" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-arrow-left me-1"></i>Back to Super Admin
    </a>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
    <div class="alert alert-success alert-dismissible fade show">
      <i class="ti ti-check me-2"></i><?php echo e(session('success')); ?>

      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="ti ti-x me-2"></i><?php echo e(session('error')); ?>

      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('warning')): ?>
    <div class="alert alert-warning alert-dismissible fade show">
      <i class="ti ti-alert-triangle me-2"></i><?php echo e(session('warning')); ?>

      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <div class="alert alert-danger alert-dismissible fade show">
      <i class="ti ti-x me-2"></i>
      <ul class="mb-0">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </ul>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($sandboxOrphans->isNotEmpty()): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2 mb-3">
      <i class="ti ti-flask" style="font-size:1.4rem;"></i>
      <div>
        <strong><?php echo e($sandboxOrphans->count()); ?> sandbox/test order(s) detected.</strong>
        These were paid via the PayFast sandbox and should never create real registrations.
        You can safely delete all traces of these test records below.
      </div>
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $sandboxOrphans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $orphan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="card mb-3 border-warning">
        <div class="card-header bg-label-warning d-flex justify-content-between align-items-center">
          <div>
            <span class="badge bg-warning text-dark me-2"><i class="ti ti-flask me-1"></i>SANDBOX</span>
            <strong>Order #<?php echo e($orphan->order->id); ?></strong>
            <span class="text-muted ms-2 small">PF Ref: <?php echo e($orphan->pf_payment_id ?? '—'); ?></span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">By: <?php echo e(optional($orphan->order->user)->name ?? 'Unknown'); ?></span>
            <span class="fw-bold">R<?php echo e(number_format($orphan->total, 2)); ?></span>
            <form method="POST" action="<?php echo e(route('superadmin.orphans.purge', $orphan->order)); ?>"
                  onsubmit="return confirm('Permanently delete ALL test data for order #<?php echo e($orphan->order->id); ?>?\n\nThis will remove:\n- The order & order items\n- The registration(s)\n- The player_registrations pivot rows\n- Any category_event_registrations rows\n- The sandbox transaction record\n\nThis cannot be undone.')">
              <?php echo csrf_field(); ?>
              <?php echo method_field('DELETE'); ?>
              <button class="btn btn-warning btn-sm text-dark">
                <i class="ti ti-trash me-1"></i>Delete Test Data
              </button>
            </form>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr><th>Player</th><th>Event</th><th>Category</th><th>Price</th><th>CER</th></tr>
            </thead>
            <tbody>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $orphan->order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                  $isMissing = $orphan->missing_items->contains('id', $item->id);
                  $player    = $item->player;
                  $event     = optional($item->category_event)->event;
                  $cat       = optional(optional($item->category_event)->category)->name ?? '—';
                ?>
                <tr>
                  <td><?php echo e(optional($player)->name); ?> <?php echo e(optional($player)->surname); ?></td>
                  <td><small><?php echo e(optional($event)->name ?? '—'); ?></small></td>
                  <td><?php echo e($cat); ?></td>
                  <td>R<?php echo e(number_format($item->item_price, 2)); ?></td>
                  <td>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isMissing): ?>
                      <span class="badge bg-secondary">No CER (expected)</span>
                    <?php else: ?>
                      <span class="badge bg-warning text-dark">CER exists (test data)</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <hr class="my-4">
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($orphans->isEmpty()): ?>
    <div class="card">
      <div class="card-body text-center py-5">
        <i class="ti ti-circle-check text-success" style="font-size:3rem;"></i>
        <h5 class="mt-3 text-success">All real paid orders have complete registration records.</h5>
        <p class="text-muted">No orphaned registrations found.</p>
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert-danger">
      <i class="ti ti-alert-circle me-2"></i>
      <strong><?php echo e($orphans->count()); ?> real order(s)</strong> have missing registration records.
      Each represents a player who paid but was not registered. Use <strong>Repair</strong> to fix.
    </div>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $orphans; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $orphan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="card mb-4 border-danger">
        <div class="card-header bg-label-danger d-flex justify-content-between align-items-center">
          <div>
            <strong>Order #<?php echo e($orphan->order->id); ?></strong>
            <span class="badge bg-success ms-2">Paid</span>
            <span class="text-muted ms-2 small">PF Ref: <?php echo e($orphan->pf_payment_id ?? '—'); ?></span>
          </div>
          <div class="d-flex align-items-center gap-2">
            <span class="text-muted small">By: <?php echo e(optional($orphan->order->user)->name ?? 'Unknown'); ?></span>
            <span class="fw-bold text-success">R<?php echo e(number_format($orphan->total, 2)); ?></span>
            <form method="POST" action="<?php echo e(route('superadmin.orphans.repair', $orphan->order)); ?>"
                  onsubmit="return confirm('Repair <?php echo e($orphan->missing_items->count()); ?> missing registration(s) for order #<?php echo e($orphan->order->id); ?>?')">
              <?php echo csrf_field(); ?>
              <button class="btn btn-danger btn-sm">
                <i class="ti ti-tool me-1"></i>Repair (<?php echo e($orphan->missing_items->count()); ?> missing)
              </button>
            </form>
            <form method="POST" action="<?php echo e(route('superadmin.orphans.delete-real', $orphan->order)); ?>"
                  onsubmit="return confirm('DELETE order #<?php echo e($orphan->order->id); ?> permanently?\n\nThis will remove:\n- The order & order items\n- The registration(s) & player links\n\nThe PayFast transaction record (PF: <?php echo e($orphan->pf_payment_id); ?>) will be KEPT for financial audit.\n\nThis cannot be undone.')">
              <?php echo csrf_field(); ?>
              <?php echo method_field('DELETE'); ?>
              <button class="btn btn-outline-secondary btn-sm">
                <i class="ti ti-trash me-1"></i>Delete
              </button>
            </form>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr><th>Player</th><th>Event</th><th>Category</th><th>Price</th><th>CER Status</th></tr>
            </thead>
            <tbody>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $orphan->order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                  $isMissing = $orphan->missing_items->contains('id', $item->id);
                  $player    = $item->player;
                  $event     = optional($item->category_event)->event;
                  $cat       = optional(optional($item->category_event)->category)->name ?? '—';
                ?>
                <tr class="<?php echo e($isMissing ? 'table-danger' : ''); ?>">
                  <td><?php echo e(optional($player)->name); ?> <?php echo e(optional($player)->surname); ?></td>
                  <td><small><?php echo e(optional($event)->name ?? '—'); ?></small></td>
                  <td><?php echo e($cat); ?></td>
                  <td>R<?php echo e(number_format($item->item_price, 2)); ?></td>
                  <td>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isMissing): ?>
                      <span class="badge bg-danger"><i class="ti ti-x me-1"></i>Missing</span>
                    <?php else: ?>
                      <span class="badge bg-success"><i class="ti ti-check me-1"></i>OK</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\superadmin\orphaned-registrations.blade.php ENDPATH**/ ?>