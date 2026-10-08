

<?php $__env->startSection('title', 'Event Details'); ?>

<?php $__env->startSection('vendor-style'); ?>

<?php $__env->stopSection(); ?>

<!-- Page -->
<?php $__env->startSection('page-style'); ?>

<?php $__env->stopSection(); ?>


<?php $__env->startSection('vendor-script'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="container-xxl py-3 py-md-4">
<a href="<?php echo e(url()->previous()); ?>" class="btn btn-warning mb-3">Back</a>
<div class="table-responsive d-none d-md-block">
    <table class="table">
        <thead>
  <tr>
    <th>Player</th>
    <th>Item</th>
    <th class="text-center">Qty</th>
    <th class="text-end">Unit Price</th>
    <th class="text-end">Line Total</th>
  </tr>
</thead>

       <tbody class="table-border-bottom-0">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <tr>
    <td>
      <span class="badge bg-label-primary">
        <?php echo e($item->order->player->getFullNameAttribute()); ?>

      </span>
    </td>

    <td>
      <?php echo e($item->item_name ?: optional($item->itemType)->item_type_name); ?>

      <span class="badge bg-label-warning ms-1">
        Size <?php echo e($item->size_name ?: optional($item->size)->size); ?>

      </span>
    </td>

    <td class="text-center">
      <?php echo e($item->qty); ?>

    </td>

    <td class="text-end">
      R<?php echo e(number_format($item->price, 2)); ?>

    </td>

    <td class="text-end fw-bold">
      R<?php echo e(number_format($item->line_total, 2)); ?>

    </td>
  </tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</tbody>

<tfoot>
  <tr class="border-top">
    <td colspan="4" class="text-end fw-bold">Total payable</td>
    <td class="text-end fw-bold">
      R<?php echo e(number_format($total, 2)); ?>

    </td>
  </tr>
</tfoot>

    </table>
</div>

<div class="d-md-none">
  <div class="d-grid gap-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <article class="card border shadow-none">
        <div class="card-body p-3">
          <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
            <div>
              <div class="small text-muted">Player</div>
              <div class="fw-semibold"><?php echo e($item->order->player->getFullNameAttribute()); ?></div>
            </div>
            <span class="badge bg-label-warning">Size <?php echo e($item->size_name ?: optional($item->size)->size); ?></span>
          </div>
          <div class="fw-medium mb-3"><?php echo e($item->item_name ?: optional($item->itemType)->item_type_name); ?></div>
          <dl class="row g-2 mb-0 small">
            <dt class="col-5 text-muted fw-normal">Quantity</dt>
            <dd class="col-7 text-end mb-0"><?php echo e($item->qty); ?></dd>
            <dt class="col-5 text-muted fw-normal">Unit price</dt>
            <dd class="col-7 text-end mb-0">R<?php echo e(number_format($item->price, 2)); ?></dd>
            <dt class="col-5 fw-semibold">Line total</dt>
            <dd class="col-7 text-end fw-bold mb-0">R<?php echo e(number_format($item->line_total, 2)); ?></dd>
          </dl>
        </div>
      </article>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <div class="d-flex justify-content-between align-items-center border-top border-bottom py-3 mt-3 fs-5">
    <strong>Total payable</strong>
    <strong>R<?php echo e(number_format($total, 2)); ?></strong>
  </div>
</div>

    <div class="d-grid d-sm-block px-0 py-4">
        <?php echo $payfast->getForm(); ?>

        <button type="submit" form="payfastForm" class="btn btn-danger btn-lg">Pay now with PayFast</button>
</div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\clothing\cart-clothing.blade.php ENDPATH**/ ?>