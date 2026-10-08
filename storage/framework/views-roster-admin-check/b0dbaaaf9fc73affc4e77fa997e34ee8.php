<form id="replacePlayerForm">
  <?php echo csrf_field(); ?>

  <input type="hidden" name="pivot_id" value="<?php echo e($slot->id); ?>">
  <input type="hidden" name="team_id" value="<?php echo e($teamId); ?>">

  <div class="mb-3">
    <label class="form-label">
      Replace player at position <?php echo e($rank); ?>

    </label>

 <select name="player_id"
        class="form-select select2ReplacePlayer"
        required>

      <option value="">— Select player —</option>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <option value="<?php echo e($player->id); ?>">
          <?php echo e($player->name); ?> <?php echo e($player->surname); ?>

        </option>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </select>
  </div>

  <div class="d-flex justify-content-end">
    <button type="submit" class="btn btn-primary">
      Replace Player
    </button>
  </div>
</form>


<?php /**PATH C:\wamp64\www\ct\resources\views\backend\team\partials\replace-player-form.blade.php ENDPATH**/ ?>