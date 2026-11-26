<?php ($configData = Helper::appClasses()); ?>

<?php $__env->startSection('title', 'Player directory · membership preview'); ?>
<?php $__env->startSection('content'); ?>
<div class="mp-preview">
<?php echo $__env->make('backend.player-performance.membership-preview.chrome', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('backend.player-performance.membership-preview.overview', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<p class="mp-note">A direct result within 24 calendar months allows a player onto this preview leaderboard, including low-confidence estimates. Provisional scores do not establish precise rankings; more varied evidence can improve their context.</p>
<?php echo $__env->make('backend.player-performance.membership-preview.filters', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="mp-grid">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
<article class="mp-card">
    <div class="mp-topline"><span class="mp-eyebrow"><?php echo e($player['cohort_label']); ?></span><span class="mp-chip <?php if($player['established']): ?> mp-chip-active <?php endif; ?>"><?php echo e($player['status']); ?></span></div>
    <h2 class="mt-3 mb-1"><?php echo e($player['name']); ?></h2>
    <p class="mp-subtle mb-1">Comparison group <?php echo e($player['component']); ?></p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['score'] !== null): ?><p class="mp-subtle mb-3">Saved estimate as of <?php echo e($as_of); ?> · <?php echo e($player['confidence']); ?> evidence confidence</p>
    <div class="mp-score"><?php echo e(number_format($player['score'], 1)); ?> <small>/ 100</small></div>
    <?php else: ?><p class="mp-subtle">No direct played-match evidence. A numeric rating is withheld.</p><div class="mp-score">— <small>Unrated</small></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="mp-counts"><div><strong><?php echo e($player['played']); ?></strong><span>singles matches</span></div><div><strong><?php echo e($player['events']); ?></strong><span><?php echo e(Str::plural('event', $player['events'])); ?></span></div><div><strong><?php echo e($player['opponents']); ?></strong><span>opponents</span></div></div>
    <p class="mp-subtle">Recorded in the last 365 days. Last match: <?php echo e($player['last_match'] ?? 'unavailable'); ?>.</p>
    <?php echo $__env->make('backend.player-performance.membership-preview.profile-freshness', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$player['established']): ?><div class="mp-note"><strong><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player['status'] === 'Inactive'): ?>Inactive evidence <?php elseif($player['status'] === 'Unrated'): ?>Direct results needed <?php else: ?> Building evidence · included on leaderboard <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></strong><br><?php echo e(ucfirst(implode('; ', $player['needs']))); ?>.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <details><summary>How to read this estimate</summary><p class="mp-subtle">Compare within <?php echo e($player['cohort_label']); ?> and the same connected group. Confidence describes the amount and freshness of evidence, not guaranteed accuracy. One match can change nearby ordering.</p></details>
    <a class="mp-btn mt-2" href="<?php echo e(route('backend.player-performance.membership-preview.detail', ['player' => $player['id'], 'cohort' => $player['cohort']])); ?>">View results &amp; evidence</a>
</article>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
<div class="mp-card"><h2>No player cards available</h2><p class="mp-subtle mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reason): ?>Cards will appear when the saved data can be safely displayed.<?php else: ?> No saved profiles match these filters or page.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p></div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<p class="mp-subtle mt-3"><?php echo e($players->total()); ?> profiles match these filters · up to 25 per page.</p>
<?php echo e($players->links('pagination::bootstrap-5')); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($unavailable_count && !$reason): ?><p class="mp-subtle mt-3"><?php echo e($unavailable_count); ?> of the <?php echo e($total_count); ?> saved cohort profiles have invalid or unavailable evidence and are withheld.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo $__env->make('backend.player-performance.membership-preview.rules', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('backend.player-performance.membership-preview.footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\player-performance\membership-preview\directory.blade.php ENDPATH**/ ?>