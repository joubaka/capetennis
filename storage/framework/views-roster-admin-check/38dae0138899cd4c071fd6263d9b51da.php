<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clothing Orders</title>
    <style>
        body { font-family: sans-serif; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h2>My Clothing Orders</h2>
    <table>
        <thead>
            <tr>
                <th>Order #</th>
                <th>Date</th>
                <th>Player</th>
                <th>Item</th>
                <th>Size</th>
                <th>Team</th>
                <th>Qty</th>
                <th>Unit</th>
                <th>Total</th>
                <th>Payfast Id</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $clothings; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->pay_status == 1): ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <tr>
                      <td><?php echo e($order->id); ?></td>
                      <td><?php echo e(optional($item->created_at)->format('d M Y')); ?></td>
                      <td>
                          <span class="badge bg-label-primary me-1">
                              <?php echo e(optional($order->player)->getFullNameAttribute()); ?>

                          </span>
                      </td>
                      <td><?php echo e($item->item_name ?: optional($item->itemType)->item_type_name); ?></td>
                      <td><?php echo e($item->size_name ?: optional($item->size)->size); ?></td>
                      <td><?php echo e(optional($order->team)->name); ?></td>
                      <td><?php echo e($item->qty ?: 1); ?></td>
                      <td>R<?php echo e(number_format((float) $item->price, 2)); ?></td>
                      <td>R<?php echo e(number_format((float) $item->line_total, 2)); ?></td>
                      <td><?php echo e($order->pf_id); ?></td>
                      <td><span class="badge bg-label-success">Paid</span></td>
                  </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\clothing\clothing-order-pdf.blade.php ENDPATH**/ ?>