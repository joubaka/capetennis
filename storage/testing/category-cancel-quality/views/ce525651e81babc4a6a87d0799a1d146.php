<?php
  $modalSlotType = $team->noProfile && $rosterSlot->noProfile ? 'imported' : 'profile';
  $modalId = 'roster-player-actions-'.$modalSlotType.'-'.$team->id.'-'.$identity->id;
  $modalRowKey = (string) ($rosterSlot->id ?? 'imported-'.$rosterSlot->noProfile?->id);
?>
<button type="button" class="btn btn-sm btn-outline-secondary roster-icon-control" <?php if($superAdminOnly ?? false): ?> data-super-admin-only <?php endif; ?> data-bs-toggle="modal" data-bs-target="#<?php echo e($modalId); ?>" data-roster-actions-target="#<?php echo e($modalId); ?>" title="Player actions" aria-label="Player actions for <?php echo e($name); ?>"><i class="ti ti-dots" aria-hidden="true"></i></button>
<div class="modal fade roster-player-actions-modal" id="<?php echo e($modalId); ?>" tabindex="-1" aria-labelledby="<?php echo e($modalId); ?>-title" aria-describedby="<?php echo e($modalId); ?>-description" aria-hidden="true"
     <?php if($superAdminOnly ?? false): ?> data-super-admin-only <?php endif; ?> data-roster-actions-modal data-team-id="<?php echo e($team->id); ?>" data-slot-id="<?php echo e($identity->id); ?>" data-slot-type="<?php echo e($modalSlotType); ?>"
     data-region-id="<?php echo e($team->region_id); ?>" data-event-id="<?php echo e($event->id); ?>" data-roster-row-key="<?php echo e($modalRowKey); ?>">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="small text-muted">Player actions</div>
          <h5 class="modal-title" id="<?php echo e($modalId); ?>-title"><?php echo e($name); ?></h5>
          <p class="small text-body mb-0 mt-1 text-break" id="<?php echo e($modalId); ?>-description">
            <?php echo e($team->name); ?> <span class="text-muted">#<?php echo e($team->id); ?></span> · Rank <span data-current-roster-rank><?php echo e($identity->rank); ?></span>
          </p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body roster-player-actions-body d-grid gap-2"><?php echo e($slot); ?></div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Back to roster</button></div>
    </div>
  </div>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\_roster-player-modal.blade.php ENDPATH**/ ?>