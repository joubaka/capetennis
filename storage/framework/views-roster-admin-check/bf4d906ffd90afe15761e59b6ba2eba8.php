<div class="card mb-4"><div class="card-body">
    <h2 class="h5">Cape Tennis Performance Score · secondary finish/match summary</h2>
    <p class="h3"><?php echo e($performance['headline'] ? number_format($performance['headline']['score'], 1).'/100' : 'Unrated'); ?></p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($performance['headline']): ?>
        <p>Provisional singles · <?php echo e($performance['headline']['cohort']); ?> (<?php echo e($performance['headline']['band']); ?>) · <?php echo e($performance['headline']['finish_count']); ?> finishes / <?php echo e($performance['headline']['match_count']); ?> matches across <?php echo e($performance['headline']['count']); ?> events · Latest: <?php echo e($performance['headline']['last_played']); ?></p>
    <?php else: ?>
        <p>No eligible published singles results. Doubles, where available, are shown separately.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <p>Private, uncalibrated pilot v2 · all published history, weighted toward recent results. Tournament finishes and completed matches contribute. Qualification is not assessed.</p>
    <a href="<?php echo e(route('backend.player-performance.show', $player->id)); ?>">View rating evidence and other cohorts</a>
</div></div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\player-performance\card.blade.php ENDPATH**/ ?>