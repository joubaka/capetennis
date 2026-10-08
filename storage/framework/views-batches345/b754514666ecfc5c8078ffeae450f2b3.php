
<?php $__env->startSection('title', 'Clothing Orders — ' . ($region->region_name ?? 'Region')); ?>
<?php $__env->startSection('content'); ?>
<?php
  $clothingFinancials = $clothingFinancials ?? [
    'received' => round($clothings->sum(fn ($order) => (float) ($order->amount_paid ?? $order->total)), 2),
    'payfast_fees' => round($clothings->sum(fn ($order) => (float) $order->payfast_fee), 2),
    'net' => round($clothings->sum(fn ($order) => (float) ($order->amount_paid ?? $order->total) - (float) $order->payfast_fee), 2),
  ];
?>
<div class="card mb-4 clothing-orders-admin">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
    <div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothingEvent ?? null): ?><p class="text-muted mb-1"><?php echo e($clothingEvent->name); ?></p><?php else: ?><p class="text-muted mb-1">All events in this region</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><h5 class="mb-0 text-uppercase">Clothing Orders — <?php echo e($region->region_name ?? ''); ?></h5></div>
    <div class="btn-group mt-2 mt-md-0">
      <a href="<?php echo e(route('backend.region.clothing.edit', array_filter(['region' => $region, 'event_id' => request()->integer('event_id') ?: null]))); ?>" class="btn btn-sm btn-outline-secondary">Back to clothing</a>
      <a href="<?php echo e(route('export.pdf.clothing.order', array_filter(['id' => $region->id, 'event_id' => request()->integer('event_id') ?: null]))); ?>" target="_blank" class="btn btn-sm btn-outline-danger">
        <i class="ti ti-file-text"></i> PDF
      </a>
      <a href="<?php echo e(route('export.excel.clothing', array_filter(['id' => $region->id, 'event_id' => request()->integer('event_id') ?: null]))); ?>" target="_blank" class="btn btn-sm btn-outline-success">
        <i class="ti ti-file-spreadsheet"></i> Excel
      </a>
    </div>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table table-striped align-middle clothing-fulfillment-table">
        <thead class="table-light">
          <tr>
            <th>Player</th>
            <th>Team</th>
            <th>Item</th>
            <th>Size</th>
            <th>Qty</th>
            <th>Status</th>
            <th>Order details</th>
          </tr>
        </thead>
        <tbody>
          <?php 
            $grandTotal = 0;
          ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $clothings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                  $price = (float) $item->price;
                  $qty = (int) ($item->qty ?: 1);
                  $lineTotal = (float) ($item->line_total ?: $price * $qty);
                ?>
                <tr>
                  <td data-label="Player"><?php echo e(optional($order->player)->name); ?></td>
                  <td data-label="Team"><?php echo e(optional($order->team)->name); ?></td>
                  <td data-label="Item"><?php echo e($item->item_name ?: optional($item->itemType)->item_type_name); ?></td>
                  <td data-label="Size"><?php echo e($item->size_name ?: optional($item->size)->size); ?></td>
                  <td data-label="Qty"><?php echo e($qty); ?></td>
                  <td data-label="Status"><span class="badge bg-label-success">Paid</span></td>
                  <td data-label="Order details"><details><summary>Details</summary><dl class="mt-2 mb-0 text-break">
                    <dt>Order</dt><dd>#<?php echo e($order->id); ?></dd>
                    <dt>Date</dt><dd><?php echo e($order->created_at->format('d-m-Y')); ?></dd>
                    <dt>Payfast ID</dt><dd><?php echo e($order->pf_id); ?></dd>
                    <dt>Unit Price</dt><dd>R<?php echo e(number_format($price, 2)); ?></dd>
                    <dt>Line Total</dt><dd>R<?php echo e(number_format($lineTotal, 2)); ?></dd>
                  </dl></details></td>
                </tr>
                <?php
                  $grandTotal += $lineTotal;
                ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-3">No clothing orders found for this region</td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>

      </table>
    </div>
    <details class="border rounded p-3 mt-3"><summary>Financial summary</summary><div class="row g-3 mt-1">
      <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Customer payments received</small><strong class="fs-5 text-success">R<?php echo e(number_format($clothingFinancials['received'], 2)); ?></strong></div></div>
      <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">PayFast fees</small><strong class="fs-5 text-warning">− R<?php echo e(number_format($clothingFinancials['payfast_fees'], 2)); ?></strong></div></div>
      <div class="col-md-4"><div class="border rounded p-3 h-100"><small class="text-muted d-block">Net clothing proceeds before supplier costs</small><strong class="fs-5">R<?php echo e(number_format($clothingFinancials['net'], 2)); ?></strong></div></div>
    </div>
    <p class="mt-3 mb-0"><strong>Totals: R<?php echo e(number_format($grandTotal, 2)); ?></strong></p>
    </details>
  </div>
</div>
<style>
.clothing-orders-admin summary { min-height:44px; cursor:pointer; align-content:center; }
.clothing-orders-admin .btn { min-height:44px; }
.clothing-orders-admin .btn-group { display:flex; flex-wrap:wrap; gap:.4rem; }
.clothing-fulfillment-table td { white-space:normal; overflow-wrap:anywhere; }
@media(max-width:767px) {
 .clothing-fulfillment-table thead { display:none; }
 .clothing-fulfillment-table tbody, .clothing-fulfillment-table tr { display:block; }
 .clothing-fulfillment-table tr { border-bottom:1px solid #d9e1eb; padding:.75rem; }
 .clothing-fulfillment-table td { display:grid; grid-template-columns:80px minmax(0,1fr); gap:.5rem; border:0; padding:.35rem 0; }
 .clothing-fulfillment-table td::before { content:attr(data-label); font-weight:600; }
 .clothing-fulfillment-table td[colspan] { display:block; }
 .clothing-fulfillment-table td[colspan]::before { display:none; }
}
</style>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\clothing\clothing-index.blade.php ENDPATH**/ ?>