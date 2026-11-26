<?php ($configData = Helper::appClasses()); ?>

<?php $__env->startSection('title', 'Active leaderboard · membership preview'); ?>
<?php $__env->startSection('content'); ?>
<div class="mp-preview">
<?php echo $__env->make('backend.player-performance.membership-preview.chrome', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('backend.player-performance.membership-preview.overview', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<p class="mp-note">Private positions use the saved cohort and connected comparison group. They are not official selection ranks. Current age eligibility, playing conditions and these proposed activity criteria have not been verified for competition use. Equal displayed ratings share a position; filtering and paging do not renumber positions.</p>
<?php echo $__env->make('backend.player-performance.membership-preview.filters', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activePlayers->isEmpty()): ?>
<section class="mp-empty"><div class="mp-empty-icon" aria-hidden="true">—</div><h2>No active players on this page</h2><p class="mp-subtle"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reason): ?>An active leaderboard cannot be assessed from the available saved data.<?php elseif($selected && ($cohort_summary[$selected]['active'] ?? 0) === 0): ?>No saved <?php echo e($cohorts[$selected]); ?> profiles currently meet all proposed active criteria. Their other evidence remains in the directory without an active position.<?php elseif($active_count === 0): ?>No displayable saved profiles currently meet all proposed criteria. Other evidence remains in the directory without an active position.<?php else: ?> No active players match these filters or page.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p><a class="mp-btn" href="<?php echo e(route('backend.player-performance.membership-preview.directory', array_filter(['cohort' => $selected]))); ?>">Explore the player directory</a></section>
<?php else: ?>
<?php echo $__env->make('backend.player-performance.membership-preview.active-groups', ['activeRows' => $activePlayers->items()], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<p class="mp-subtle mt-3"><?php echo e($activePlayers->total()); ?> active profiles match these filters · up to 25 per page.</p>
<?php echo e($activePlayers->links('pagination::bootstrap-5')); ?>

<?php echo $__env->make('backend.player-performance.membership-preview.rules', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('backend.player-performance.membership-preview.footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\player-performance\membership-preview\leaderboard.blade.php ENDPATH**/ ?>