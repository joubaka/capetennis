<p>Masters invitation update: <strong><?php echo e($action); ?></strong></p>
<p>Event: <?php echo e($invitation->batch?->event?->name ?? 'Masters event'); ?></p>
<p>Age group: <?php echo e($invitation->categoryEvent?->category?->name ?? 'Unknown'); ?></p>
<p>Player: <?php echo e($invitation->player?->full_name ?? $invitation->player_id); ?></p>
<p>Ranking position: <?php echo e($invitation->ranking_position); ?></p>
<p>Invitation status: <?php echo e($invitation->status); ?></p>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($replacement): ?>
  <p>Replacement: <?php echo e($replacement->player?->full_name ?? $replacement->player_id); ?> (ranking position <?php echo e($replacement->ranking_position); ?>)</p>
  <p>Replacement status: <?php echo e($replacement->status); ?></p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\emails\masters\admin-update.blade.php ENDPATH**/ ?>