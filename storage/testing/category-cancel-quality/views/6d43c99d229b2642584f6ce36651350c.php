<?php $__env->startSection('title', $event->name . ' – Withdrawals'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xl">
  <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div><h1 class="h4"><?php echo e($event->name); ?> — Withdrawals</h1><p class="text-muted mb-0"><?php echo e(number_format($withdrawals->total())); ?> withdrawn entry records. Refund status is separate from withdrawal.</p></div>
    <a class="btn btn-outline-secondary" href="<?php echo e(route('admin.events.finances', $event)); ?>">Back to finances</a>
  </div>
  <div class="card"><div class="table-responsive"><table class="table table-sm">
    <thead><tr><th>Player(s)</th><th>Division</th><th>Withdrawn</th><th>Refund status</th><th>Record</th></tr></thead>
    <tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $withdrawals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $withdrawal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <tr><td><?php echo e($withdrawal->players ?: 'Player unavailable'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($withdrawal->team)): ?><small class="d-block text-muted"><?php echo e($withdrawal->team); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td><td><?php echo e($withdrawal->division ?? 'Unnamed division'); ?></td><td><?php echo e($withdrawal->withdrawn_at ? \Illuminate\Support\Carbon::parse($withdrawal->withdrawn_at)->timezone(config('app.timezone'))->format('d M Y H:i') : 'Date unavailable'); ?></td><td><?php echo e($withdrawal->refund_label); ?></td><td><?php echo e($withdrawal->source); ?> #<?php echo e($withdrawal->id); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($withdrawal->archived): ?><small class="d-block text-muted">Archived withdrawal</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td></tr>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="5">No participant withdrawals recorded.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody>
  </table></div><div class="card-body"><?php echo e($withdrawals->links()); ?></div></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\event\finance-withdrawals.blade.php ENDPATH**/ ?>