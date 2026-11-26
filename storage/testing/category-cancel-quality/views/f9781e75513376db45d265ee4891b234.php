<?php $__env->startSection('title', 'Confirm nomination decline'); ?>
<?php $__env->startSection('content'); ?>
<div class="card mx-auto" style="max-width:42rem"><div class="card-body">
<h3>Decline nomination?</h3><p><strong><?php echo e($review['name']); ?></strong> · <?php echo e($categoryEvent->category->name); ?></p><p><?php echo e($event->name); ?></p>
<p>Confirming removes this player from the public nomination list and records their decline for the event manager. This does not cancel a checkout, withdraw a paid entry or request a refund.</p>
<form method="POST" action="<?php echo e(route('cavaliers.nominations.decline.confirm', [$event, $categoryEvent, $review['nomination_id']])); ?>"><?php echo csrf_field(); ?>
<input type="hidden" name="token" value="<?php echo e($token); ?>"><label class="d-flex gap-2 mb-3"><input type="checkbox" name="confirmed" value="1" required> I confirm that this player declines the nomination.</label>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><p class="text-danger"><?php echo e($errors->first()); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<div class="d-flex flex-wrap gap-2"><a href="<?php echo e(route('events.show', $event)); ?>" class="btn btn-outline-secondary">Keep nomination</a><button type="submit" class="btn btn-danger">Confirm decline</button></div>
</form></div></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\cavaliers-decline.blade.php ENDPATH**/ ?>