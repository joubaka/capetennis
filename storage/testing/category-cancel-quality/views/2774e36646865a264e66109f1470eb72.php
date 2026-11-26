<li class="ctr-player-card" data-ctr-player data-entry-id="<?php echo e($row['entry_id']); ?>" data-player-id="<?php echo e($row['player_id'] ?? ''); ?>" data-category-id="<?php echo e($row['category_id']); ?>" data-component="<?php echo e($row['component'] ?? ''); ?>" data-score="<?php echo e($row['score'] ?? ''); ?>" data-order="<?php echo e($row['order'] ?? $row['entry_id']); ?>" draggable="true">
  <div class="ctr-player-title"><span class="ctr-drag-handle" aria-hidden="true">⠿</span><strong><?php echo e($row['name']); ?></strong></div>
  <div class="ctr-player-rating">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['score'] !== null): ?>
      <span class="ctr-score">CTR <?php echo e(number_format($row['score'], 1)); ?></span><span><?php echo e($row['confidence']); ?></span>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['historical_connection']): ?><span>Historical connection</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php else: ?>
      <span class="ctr-unrated"><?php echo e($row['rating_label'] ?? 'CTR unavailable'); ?></span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['band'] ?? null): ?><span><?php echo e($row['band']); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['issue']): ?><p class="ctr-player-issue"><?php echo e($row['issue']); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="ctr-player-move">
    <label class="visually-hidden" for="ctr-entry-<?php echo e($row['entry_id']); ?>">Move <?php echo e($row['name']); ?> to group</label>
    <select id="ctr-entry-<?php echo e($row['entry_id']); ?>" name="placements[<?php echo e($row['entry_id']); ?>]" class="form-select form-select-sm" data-placement data-saved-group="<?php echo e($row['group']); ?>">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $groupNumbers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $groupNumber): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($groupNumber); ?>" <?php if($groupNumber === $displayGroup): echo 'selected'; endif; ?>><?php echo e($groupNumber ? 'Group '.$groupNumber : 'Needs placement'); ?></option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </select>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-move-player aria-label="Move <?php echo e($row['name']); ?> to selected group">Move</button>
  </div>
</li>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\draw\ctr-player-card.blade.php ENDPATH**/ ?>