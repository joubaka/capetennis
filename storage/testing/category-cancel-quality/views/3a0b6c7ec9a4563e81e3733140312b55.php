<?php ($reserveAdd = $reserveAdd ?? false); ?>
<details class="mt-3 roster-player-editor"><summary class="btn btn-outline-primary"><?php echo e($reserveAdd ? 'Add reserve player' : 'Add player'); ?></summary>
<form method="POST" action="<?php echo e(route('backend.team.addPlayers')); ?>" class="mt-3 roster-player-form" data-team-roster-change>
  <?php echo csrf_field(); ?><input type="hidden" name="team_id" value="<?php echo e($team->id); ?>"><input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reserveAdd): ?><input type="hidden" name="reserve" value="1"><p class="small text-muted">Add an unpaid reserve after the configured team places. Existing players and team size stay unchanged.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php echo $__env->make('backend.adminPage.admin_show._roster-player-inputs', ['profileFieldName' => $team->noProfile ? 'player_id' : 'player_ids[]'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <button class="btn btn-primary"><?php echo e($reserveAdd ? 'Add reserve player' : 'Add player'); ?></button>
</form></details>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\_roster-add.blade.php ENDPATH**/ ?>