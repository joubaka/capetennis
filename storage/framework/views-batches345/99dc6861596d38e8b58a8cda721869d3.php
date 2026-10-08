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

    <?php $runningBalance = 0; ?>

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
                <th>Balance</th>
            </tr>
        </thead>
        <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $ledger; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $runningBalance = round($runningBalance + $t->net, 2);
                $isRefund = $t->type === 'refund';
            ?>
            <tr class="<?php echo e($isRefund ? 'refund-row' : ''); ?>">
                <td><?php echo e($t->pf_payment_id ?? '-'); ?></td>
                <td><?php echo e(\Carbon\Carbon::parse($t->created_at)->format('d M Y')); ?></td>
                <td><?php echo e($t->type === 'clothing_payment' ? 'Clothing received' : ucfirst($t->type)); ?></td>
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
                <td>R<?php echo e(number_format($runningBalance, 2)); ?></td>
            </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="4" style="text-align: right;">Totals:</th>
                <th></th>
                <th>R<?php echo e(number_format($totalGross, 2)); ?></th>
                <th>R<?php echo e(number_format(abs($totalPayfastFees) + ($totalWithdrawalFees ?? 0), 2)); ?></th>
                <th>R<?php echo e(number_format(abs($totalCapeTennisFees), 2)); ?></th>
                <th>R<?php echo e(number_format($netTournamentIncome, 2)); ?></th>
                <th>R<?php echo e(number_format($runningBalance, 2)); ?></th>
            </tr>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($totalWithdrawalFees ?? 0) > 0): ?>
            <tr>
                <th colspan="9" style="text-align: right; font-weight: normal;">
                    Incl. withdrawal fees retained (10%): R<?php echo e(number_format($totalWithdrawalFees, 2)); ?>

                </th>
                <th></th>
            </tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($totalPayouts > 0): ?>
            <tr>
                <th colspan="9" style="text-align: right;">
                    Payouts: - R<?php echo e(number_format($totalPayouts, 2)); ?>

                </th>
                <th></th>
            </tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tfoot>
    </table>
</body>
</html>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\pdf\transactions.blade.php ENDPATH**/ ?>