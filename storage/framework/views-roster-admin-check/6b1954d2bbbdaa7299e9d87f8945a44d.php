<?php $__env->startSection('title', 'Paid Hoodie Orders'); ?>

<?php $__env->startSection('content'); ?>
<div class="container mt-4">
    <h2>Paid Hoodie Orders</h2>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($orders->isEmpty()): ?>
        <p>No paid orders found.</p>
    <?php else: ?>
      <table class="table table-bordered table-striped mt-3">
    <thead class="table-dark">
        <tr>
            <th>Customer</th>
            <th>Email</th>
            <th>Items Ordered</th>
            <th>Total</th>
            <th>Order Date</th>
        </tr>
    </thead>
    <tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr>
            <td><?php echo e($order->user_text); ?></td>
            <td><?php echo e($order->email_text); ?></td>
            <td>
                <ul class="mb-0">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <li>
                            <?php echo e($item->itemType->item_type_name ?? '—'); ?> (<?php echo e($item->size->size ?? '-'); ?>) - R<?php echo e(number_format($item->itemType->price ?? 0, 2)); ?>

                        </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </ul>
            </td>
            <td>
                R<?php echo e(number_format($order->items->sum(fn($item) => $item->itemType->price ?? 0), 2)); ?>

            </td>
            <td><?php echo e($order->created_at->format('Y-m-d H:i')); ?></td>
        </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </tbody>
    <tfoot class="table-light">
        <tr>
            <th colspan="3" class="text-end">Grand Total:</th>
            <th>
                R<?php echo e(number_format($orders->sum(fn($order) => $order->items->sum(fn($item) => $item->itemType->price ?? 0)), 2)); ?>

            </th>
            <th></th>
        </tr>
    </tfoot>
</table>

    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\orders\paid_orders.blade.php ENDPATH**/ ?>