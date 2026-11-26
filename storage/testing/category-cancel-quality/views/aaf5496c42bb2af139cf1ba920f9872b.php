<?php ($configData = Helper::appClasses()); ?>

<?php $__env->startSection('title', 'Player detail · private membership preview'); ?>
<?php $__env->startSection('content'); ?>
<div class="mp-preview">
<?php echo $__env->make('backend.player-performance.membership-preview.chrome', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedPlayer): ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(str_starts_with($selected, 'u10 ')): ?><p class="mp-note">U10 ball type and court size are unverified. The recorded cohort does not establish compatible playing formats.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo $__env->make('backend.player-performance.membership-preview.player-evidence', ['player' => $selectedPlayer], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<p class="mp-note mt-3">The model limits one event's total influence and gives older evidence less weight. Finishing order and trial references provide separate context. This page does not calculate a rating change for each match. Recorded cohort labels do not establish current-season age eligibility.</p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($evidence['reason']): ?><div class="alert alert-warning" role="status"><?php echo e($evidence['reason']); ?> Saved estimates above are not a live assessment of changed results.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($evidence['available']): ?>
<p class="mp-subtle">Verified saved-source record: <?php echo e($evidence['players'][$selectedPlayer['id']]['wins']); ?> wins · <?php echo e($evidence['players'][$selectedPlayer['id']]['losses']); ?> losses.</p>
<?php echo $__env->make('backend.player-performance.membership-preview.match-history', ['historyName' => $selectedPlayer['name']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo $__env->make('backend.player-performance.membership-preview.footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\player-performance\membership-preview\detail.blade.php ENDPATH**/ ?>