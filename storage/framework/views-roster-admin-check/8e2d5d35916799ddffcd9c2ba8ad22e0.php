
<?php
  // show only paid orders (remove ->filter(...) if you want all)
  $ordersToShow = $myClothingOrders->filter(fn($o) => (int)$o->pay_status === 1);

  // precompute a grand total if prices are available
  $grandTotal = 0;
  foreach ($ordersToShow as $order) {
    $orderTotal = is_numeric($order->total ?? null)
      ? (float) $order->total
      : (float) $order->items->sum(fn($it) => ($it->qty ?? 1) * (float) $it->price);
    $grandTotal += $orderTotal;
    $order->computed_total = $orderTotal; // attach for display
  }
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ordersToShow->isNotEmpty()): ?>
  <div class="card mb-4">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
      <div class="d-flex align-items-center gap-2">
        <h5 class="m-0">My Clothing Orders</h5>
        <span class="badge bg-label-primary"><?php echo e($ordersToShow->count()); ?></span>
      </div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($grandTotal > 0): ?>
        <div class="text-muted small">
          <span class="me-2">Total paid:</span>
          <span class="fw-semibold">R <?php echo e(number_format($grandTotal, 2)); ?></span>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>

    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="w-25">Team / Region</th>
              <th>Items</th>
              <th class="text-end">Total</th>
              <th class="text-center">Status</th>
            </tr>
          </thead>
          <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ordersToShow; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
              $teamName   = optional($order->team)->name;
              $regionName = optional(optional($order->team)->region)->region_name;
              $lines = [];

              foreach ($order->items as $it) {
                $typeName = $it->item_name ?: (optional($it->itemType)->item_type_name ?? 'Item');
                $sizeName = $it->size_name ?: optional($it->size)->size;
                $qty      = $it->qty ?? 1;

                $lines[] = [
                  'name' => $typeName,
                  'size' => $sizeName,
                  'qty'  => $qty,
                ];
              }
            ?>

            <tr>
              <td class="align-middle">
                <div class="fw-medium text-wrap"><?php echo e($teamName); ?></div>
                <small class="text-muted text-wrap d-block"><?php echo e($regionName); ?></small>
                <small class="text-muted">Ref: <span class="badge bg-label-secondary"><?php echo e($order->player->full_name); ?></span></small>
              </td>

              <td class="align-middle">
                <ul class="list-unstyled mb-0">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $lines; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $line): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="mb-1 text-wrap">
                      <span class="fw-medium"><?php echo e($line['name']); ?></span>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($line['size']): ?>
                        <span class="badge bg-label-secondary ms-1"><?php echo e($line['size']); ?></span>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      <span class="text-muted ms-1">×<?php echo e($line['qty']); ?></span>
                    </li>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </ul>
              </td>

              <td class="text-end align-middle">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($order->computed_total ?? 0) > 0): ?>
                  <span class="fw-semibold">R<?php echo e(number_format($order->computed_total, 2)); ?></span>
                <?php elseif(!empty($order->total_amount)): ?>
                  <span class="fw-semibold">R<?php echo e(number_format($order->total_amount, 2)); ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>

              <td class="text-center align-middle">
                <span class="badge <?php echo e((int)$order->pay_status === 1 ? 'bg-label-success' : 'bg-label-danger'); ?>">
                  <?php echo e((int)$order->pay_status === 1 ? 'Paid' : 'X'); ?>

                </span>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\_my_clothing_orders.blade.php ENDPATH**/ ?>