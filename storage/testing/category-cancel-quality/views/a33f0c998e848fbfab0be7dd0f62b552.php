
<?php $__env->startSection('title', 'Wallets'); ?>
<?php $__env->startSection('content'); ?>
<div class="operational-page">
<?php echo $__env->make('backend.partials.operational-controls', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<h4>Wallets</h4>
<p class="text-muted">Open a user wallet to review its balance and transaction history.</p>
<div class="card"><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>Wallet</th><th>Account</th><th>Balance</th><th>Review</th></tr></thead>
<tbody>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $wallets; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $wallet): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<tr><td>#<?php echo e($wallet->id); ?></td><td><?php echo e($wallet->payable?->name ?? 'Account unavailable'); ?></td><td>R<?php echo e(number_format($wallet->balance, 2)); ?></td>
<td><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($wallet->payable instanceof \App\Models\User): ?><a class="btn btn-outline-primary" href="<?php echo e(route('wallet.show', $wallet->payable_id)); ?>">Open wallet</a><?php else: ?><span class="text-muted">No user wallet link</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td></tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<tr><td colspan="4" class="text-center py-4">No wallets found.</td></tr>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</tbody></table></div></div>
<div class="mt-3"><?php echo e($wallets->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\wallet\wallet-index.blade.php ENDPATH**/ ?>