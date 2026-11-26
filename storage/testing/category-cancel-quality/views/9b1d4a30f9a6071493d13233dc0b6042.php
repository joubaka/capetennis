<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($workspace['players']->isNotEmpty()): ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! (request()->header('X-My-Tennis-Navigation') === '1')): ?>
<?php if (! $__env->hasRenderedOnce('my-tennis-player-switch-script')): $__env->markAsRenderedOnce('my-tennis-player-switch-script'); ?>
<script defer src="<?php echo e(asset('assets/js/my-tennis-player-switch.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/my-tennis-player-switch.js'))); ?>"></script>
<?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<form method="get" action="<?php echo e(route('my.tennis')); ?>" class="ct-workspace-player-selector" data-workspace-player-selector>
  <label for="workspace-player" class="small d-block mb-1"><?php echo e(($compactSelector ?? false) ? 'Switch player' : 'View player workspace'); ?></label>
  <div class="d-flex gap-2">
    <select id="workspace-player" name="player" class="form-select" required>
      <option value="" disabled <?php if(!$workspace['related']): echo 'selected'; endif; ?>>Choose a linked player</option>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $workspace['players']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $workspaceOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($workspaceOption->id); ?>" <?php if($workspace['related'] && $workspace['player']->id === $workspaceOption->id): echo 'selected'; endif; ?>><?php echo e($workspaceOption->full_name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </select>
    <button type="submit" class="btn btn-outline-light"><?php echo e(($compactSelector ?? false) ? 'Open' : 'View'); ?></button>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($compactSelector ?? false)): ?><small>Open the selected player’s dashboard.</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</form>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\workspace-selector.blade.php ENDPATH**/ ?>