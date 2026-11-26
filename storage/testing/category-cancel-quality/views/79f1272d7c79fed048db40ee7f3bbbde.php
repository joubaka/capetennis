<!DOCTYPE html>
<html>
<head>
    <title>Transactions PDF</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ccc; padding: 5px; text-align: center; }
        th { background-color: #f5f5f5; }
        .refund-row td { background-color: #fff4f4; }
        ul { margin: 0; padding-left: 15px; font-size: 10px; }
    </style>
</head>
<body>
    <h2>Transactions - <?php echo e($event->name); ?></h2>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $receiptSections; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $section): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <h3><?php echo e($key === 'clothing' ? 'Regional clothing funds — payable to convenors' : 'Registration reconciliation'); ?></h3>
    <table>
      <thead>
        <tr>
          <th>Region</th>
          <th>Received</th>
          <th>Fees deducted (net)</th>
          <th>Completed refunds</th>
          <th>Pending refunds</th>
          <th><?php echo e($key === 'clothing' ? 'Net payable to convenor' : 'Registration net income'); ?></th>
        </tr>
      </thead>
      <tbody>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $section['groups']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region => $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <td><?php echo e($region); ?></td>
          <td>R<?php echo e(number_format($group['totals']['gross'], 2)); ?></td>
          <td>R<?php echo e(number_format(abs($group['rows']->reject(fn ($row) => $row->type === 'withdrawal' || ($row->type === 'refund' && ($row->refund_status ?? '') !== 'completed'))->sum(fn ($row) => ($row->fee ?? 0) + ($row->capeFee ?? 0))), 2)); ?></td>
          <td>R<?php echo e(number_format($group['totals']['completed_refunds'], 2)); ?></td>
          <td>R<?php echo e(number_format($group['totals']['pending_refunds'], 2)); ?></td>
          <td>R<?php echo e(number_format($group['totals']['net'], 2)); ?></td>
        </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="6">No transactions.</td></tr>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </tbody>
    </table>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($key === 'registrations'): ?>
      <p>Registration payouts: R<?php echo e(number_format($totalPayouts, 2)); ?>. Registration balance: R<?php echo e(number_format($section['totals']['net'] - $totalPayouts, 2)); ?>.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <p>Clothing funds are excluded from registration income and payouts. Pending refunds do not reduce funds until completed.</p>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Date</th>
                <th>Type</th>
                <th>Participant / Items</th>
                <th>Method</th>
                <th>Gross</th>
                <th>PayFast Fee</th>
                <th>Cape Tennis Fee</th>
                <th>Net</th>

            </tr>
        </thead>
        <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ledger; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php

                $isRefund = $t->type === 'refund';
            ?>
            <tr class="<?php echo e($isRefund ? 'refund-row' : ''); ?>">
                <td><?php echo e($t->pf_payment_id ?? '-'); ?></td>
                <td><?php echo e(\Carbon\Carbon::parse($t->created_at)->format('d M Y')); ?></td>
                <td><?php echo e($t->type === 'clothing_payment' ? 'Clothing received' : ($t->type === 'refund' ? (($t->subtype ?? '') === 'clothing_refund' ? 'Clothing refund' : 'Registration refund') : ($t->type === 'payment' ? 'Registration received' : ucfirst($t->type)))); ?></td>
                <td style="text-align: left;">
                    <strong><?php echo e($t->player ?? '-'); ?></strong>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$isRefund && isset($t->order) && $t->order && $t->order->items && $t->order->items->count()): ?>
                        <ul>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $t->order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($t->type === 'clothing_payment'): ?>
                                    <?php echo e($item->item_name ?? optional($item->itemType)->item_type_name ?? 'Clothing'); ?>

                                    (<?php echo e($item->size_name ?? optional($item->size)->size ?? '-'); ?>, qty <?php echo e($item->qty ?: 1); ?>)
                                    &mdash; R<?php echo e(number_format($item->line_total ?? (($item->price ?? 0) * ($item->qty ?: 1)), 2)); ?>

                                <?php else: ?>
                                    <?php echo e($item->player->name ?? ''); ?> <?php echo e($item->player->surname ?? ''); ?>

                                    (<?php echo e(optional(optional($item->category_event)->category)->name ?? '-'); ?>)
                                    &mdash; R<?php echo e(number_format($item->item_price ?? 0, 2)); ?>

                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </ul>
                    <?php elseif($isRefund && isset($t->category)): ?>
                        <ul><li><?php echo e($t->category ?? '-'); ?></li></ul>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
                <td><?php echo e($t->method ?? '-'); ?></td>
                <td><?php echo e(in_array($t->type, ['refund', 'payout']) ? '-' : ''); ?> R<?php echo e(number_format(abs($t->gross), 2)); ?></td>
                <td>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($t->fee != 0): ?>
                        <?php echo e($t->fee > 0 ? '+' : '-'); ?> R<?php echo e(number_format(abs($t->fee), 2)); ?>

                    <?php else: ?> &mdash;
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
                <td>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($t->capeFee != 0): ?>
                        <?php echo e($t->capeFee > 0 ? '+' : '-'); ?> R<?php echo e(number_format(abs($t->capeFee), 2)); ?>

                    <?php else: ?> &mdash;
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
                <td><?php echo e($t->net < 0 ? '-' : ''); ?> R<?php echo e(number_format(abs($t->net), 2)); ?></td>

            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>

    </table>
</body>
</html>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\pdf\transactions.blade.php ENDPATH**/ ?>