<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($team->noProfile && $identity && (int) $identity->player_profile === 0): ?>
  <details>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compactRoster ?? false): ?>
      <summary class="roster-action-choice">
        <span>
          <strong class="d-block">Correct the player’s name</strong>
          <span class="small text-body">Fix a typo and keep the same player in this spot.</span>
        </span>
        <i class="ti ti-chevron-down" aria-hidden="true"></i>
      </summary>
    <?php else: ?>
      <summary class="btn btn-sm btn-outline-secondary">Change name</summary>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <form method="POST" action="<?php echo e(route('backend.team.editPlayerName')); ?>" class="mt-2" data-team-roster-change>
      <?php echo csrf_field(); ?>
      <input type="hidden" name="team_id" value="<?php echo e($team->id); ?>"><input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">
      <input type="hidden" name="slot_id" value="<?php echo e($identity->id); ?>"><input type="hidden" name="expected_rank" value="<?php echo e($identity->rank); ?>">
      <input type="hidden" name="expected_player_id" value="0">
      <input type="hidden" name="expected_name" value="<?php echo e($identity->name); ?>"><input type="hidden" name="expected_surname" value="<?php echo e($identity->surname); ?>">
      <label class="form-label w-100">First name<input class="form-control" name="name" value="<?php echo e($identity->name); ?>" required maxlength="100"></label>
      <label class="form-label w-100">Surname<input class="form-control" name="surname" value="<?php echo e($identity->surname); ?>" required maxlength="100"></label>
      <button class="btn btn-primary">Save name</button>
    </form>
  </details>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\_roster-name-form.blade.php ENDPATH**/ ?>