<tr class="reserve-row" data-team-invitation-row="<?php echo e($invitation->id); ?>">
  <td><span class="badge bg-label-warning">Reserve <?php echo e($invitation->queue_position); ?></span></td>
  <td>
    <strong><?php echo e($invitation->player?->full_name ?: 'Missing player'); ?></strong>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $invitation->player?->profile_complete): ?><div class="small text-warning">Profile incomplete</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </td>
  <td>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recipientEmail): ?>
      <div><?php echo e($recipientEmail); ?></div>
    <?php elseif($rawContactEmails->isNotEmpty()): ?>
      <div class="text-warning">Profile/account email invalid</div>
      <div class="small text-muted"><?php echo e($rawContactEmails->first()); ?></div>
    <?php else: ?>
      <div>Email required</div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="small text-muted"><?php echo e($invitation->player?->cellNr ?: 'No cell number'); ?></div>
  </td>
  <td><strong>Manual addition</strong><div class="small text-muted">Not in ranking snapshot</div></td>
  <td><span class="badge bg-label-warning">Reserve</span><div class="small text-muted mt-1">Read only</div></td>
  <td><div>Not sent</div></td>
  <td>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($openRosterRanks->isNotEmpty()): ?>
      <div class="dropdown">
        <button class="btn btn-sm btn-icon btn-outline-secondary dropdown-toggle hide-arrow" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Regional actions for <?php echo e($invitation->player?->full_name ?: 'player'); ?>">
          <i class="ti ti-dots-vertical" aria-hidden="true"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end p-1" style="min-width:15rem;">
          <?php echo $__env->make('backend.team-selection._open-position-action', ['restorePosition' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
      </div>
    <?php else: ?>
      <span class="text-muted small">No action available</span>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </td>
</tr>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-selection\_added-reserve-row.blade.php ENDPATH**/ ?>