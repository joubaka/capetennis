<li class="list-group-item <?php echo e($currentPlayer ? 'bg-light' : ''); ?>">
    <div class="d-flex justify-content-between align-items-baseline gap-2 mb-1">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($profileUrl): ?>
            <a class="text-break" href="<?php echo e($profileUrl); ?>"><?php echo e($name); ?></a>
        <?php else: ?>
            <strong class="text-break"><?php echo e($name); ?> <span class="small fw-normal">(this player)</span></strong>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <span class="fw-semibold text-nowrap"><?php echo e($score === null ? 'Withheld' : number_format($score, 1).'/100'); ?></span>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($score !== null): ?>
        <div class="progress" style="height:.5rem" aria-hidden="true">
            <div class="progress-bar <?php echo e($currentPlayer ? 'bg-primary' : 'bg-info'); ?>" style="width:<?php echo e(max(0, min(100, (float) $score))); ?>%"></div>
        </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</li>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\player-performance\partials\evidence-score-bar.blade.php ENDPATH**/ ?>