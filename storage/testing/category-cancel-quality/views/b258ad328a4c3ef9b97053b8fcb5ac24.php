<details class="roster-player-editor">
  <summary class="btn btn-sm btn-outline-primary" aria-label="Add player to Rank <?php echo e($slot->rank); ?>">Add player</summary>
  <div class="roster-player-form mt-2">
    <p class="small fw-semibold mb-2">Fill Rank <?php echo e($slot->rank); ?></p>
    <?php ($availableReserves = $team->workspaceSlots->where('selected_team_reserve', true)->filter(fn ($candidate) => $team->noProfile ? (bool) $candidate->noProfile : (int) $candidate->player_id > 0)); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($availableReserves->isNotEmpty()): ?>
      <fieldset class="mb-3">
        <legend class="form-label">Choose a team reserve</legend>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $availableReserves; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $candidate): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php ($reserveIdentity = $team->noProfile ? $candidate->noProfile : $candidate); ?>
          <?php ($reserveName = $candidate->noProfile ? trim($candidate->noProfile->name.' '.$candidate->noProfile->surname) : $candidate->player?->full_name); ?>
          <form method="POST" action="<?php echo e(route('backend.team.addPlayers')); ?>" data-team-roster-change class="mb-2">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="team_id" value="<?php echo e($team->id); ?>"><input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">
            <input type="hidden" name="target_rank" value="<?php echo e($slot->rank); ?>"><input type="hidden" name="reserve_slot_id" value="<?php echo e($reserveIdentity->id); ?>"><input type="hidden" name="expected_reserve_rank" value="<?php echo e($candidate->rank); ?>">
            <button class="btn btn-sm btn-outline-primary w-100 text-start">Add <?php echo e($reserveName); ?></button>
          </form>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </fieldset>
      <p class="small text-muted">Or add another player:</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <form method="POST" action="<?php echo e(route('backend.team.addPlayers')); ?>" data-team-roster-change>
      <?php echo csrf_field(); ?>
      <input type="hidden" name="team_id" value="<?php echo e($team->id); ?>"><input type="hidden" name="event_id" value="<?php echo e($event->id); ?>"><input type="hidden" name="target_rank" value="<?php echo e($slot->rank); ?>">
      <?php echo $__env->make('backend.adminPage.admin_show._roster-player-inputs', ['profileFieldName' => 'player_id'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <button class="btn btn-sm btn-primary">Add to Rank <?php echo e($slot->rank); ?></button>
    </form>
  </div>
</details>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\_roster-fill.blade.php ENDPATH**/ ?>