<div class="masters-compact-list" aria-label="Provisional Top 12 within this comparison group">
<div class="masters-compact-row masters-compact-heading"><span>Pos.</span><span>Player</span><span>Rating</span></div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $group['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div class="masters-compact-row <?php echo e($row['band'] || $row['boundary_tie'] ? 'masters-cutoff-row' : ''); ?>">
<span><?php echo e($row['position']); ?></span>
<div class="masters-compact-name"><strong><?php echo e($row['name']); ?></strong>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['boundary_tie']): ?><small class="d-block">Top 10 boundary tie — rule needed</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $row['flags']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $flag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(str_contains($flag, 'Over the division')): ?><small class="d-block">Over-age under provisional rule</small>
<?php elseif(str_contains($flag, 'Younger player')): ?><small class="d-block">Younger — playing-up rule unconfirmed</small>
<?php elseif(str_contains($flag, 'Birth date') || str_contains($flag, 'Current player profile') || str_contains($flag, 'Profile gender')): ?><small class="d-block"><?php echo e($flag); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<strong class="masters-compact-score"><?php echo e($group['converged'] && $row['score'] !== null ? number_format($row['score'], 1) : '—'); ?></strong>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\player-performance\masters-compact-list.blade.php ENDPATH**/ ?>