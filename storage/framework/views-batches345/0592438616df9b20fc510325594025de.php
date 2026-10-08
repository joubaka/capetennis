<?php
  $fieldLabels = [
    'name' => 'First name', 'surname' => 'Surname', 'dateOfBirth' => 'Date of birth',
    'gender' => 'Gender', 'email' => 'Email', 'cellNr' => 'Cellphone',
    'coach' => 'Coach', 'profile_updated_at' => 'Profile updated',
  ];
  $registrationHistoryCount = collect($analysis['impact']['registration_history'])
    ->sum(fn($columns) => collect($columns)->sum(fn($counts) => $counts['keep'] + $counts['remove']));
?>

<div class="alert alert-warning">
  <strong>Keep #<?php echo e($analysis['keep']->id); ?> and permanently remove #<?php echo e($analysis['remove']->id); ?>.</strong>
  The retained profile is the only one with linked history. This action cannot be undone from this screen.
</div>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($analysis['blockers']): ?>
  <div class="alert alert-danger mb-0">
    <strong>Quick merge blocked.</strong>
    <ul class="mb-0 mt-2"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $analysis['blockers']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $blocker): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($blocker['message']); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul>
  </div>
<?php else: ?>
  <div class="row g-3 mb-3">
    <div class="col-sm-6"><div class="border rounded p-3"><span class="text-muted small d-block">Canonical profile</span><strong>#<?php echo e($analysis['keep']->id); ?> <?php echo e($analysis['keep']->full_name); ?></strong><div class="small text-success"><?php echo e($analysis['impact']['keep']['usage_total']); ?> linked records retained</div></div></div>
    <div class="col-sm-6"><div class="border rounded p-3"><span class="text-muted small d-block">Empty duplicate</span><strong>#<?php echo e($analysis['remove']->id); ?> <?php echo e($analysis['remove']->full_name); ?></strong><div class="small text-muted">No linked history</div></div></div>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registrationHistoryCount > 0): ?>
    <div class="alert alert-info py-2"><strong>Tournament safety:</strong> <?php echo e($registrationHistoryCount); ?> registration-based references keep their registration IDs, results and ranking attribution.</div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <form method="POST" action="<?php echo e(route('superadmin.player-duplicates.merge')); ?>">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="keep_player_id" value="<?php echo e($analysis['keep']->id); ?>">
    <input type="hidden" name="remove_player_id" value="<?php echo e($analysis['remove']->id); ?>">
    <input type="hidden" name="impact_digest" value="<?php echo e($analysis['digest']); ?>">

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(collect($analysis['fields'])->contains(fn($field) => $field['different'])): ?>
      <h6 class="mb-2">Choose final profile values</h6>
      <div class="table-responsive mb-3">
        <table class="table table-sm align-middle">
          <thead><tr><th>Field</th><th>Keep current value</th><th>Use empty profile value</th></tr></thead>
          <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $analysis['fields']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => $comparison): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($comparison['different']): ?>
              <tr>
                <th><?php echo e($fieldLabels[$field] ?? ucfirst($field)); ?></th>
                <td><label class="d-flex gap-2 align-items-start"><input class="form-check-input mt-1" type="radio" name="field_sources[<?php echo e($field); ?>]" value="keep" <?php echo e($comparison['recommended'] === 'keep' ? 'checked' : ''); ?>><span><?php echo e(filled($comparison['keep']) ? $comparison['keep'] : 'Blank'); ?></span></label></td>
                <td><label class="d-flex gap-2 align-items-start"><input class="form-check-input mt-1" type="radio" name="field_sources[<?php echo e($field); ?>]" value="remove" <?php echo e($comparison['recommended'] === 'remove' ? 'checked' : ''); ?>><span><?php echo e(filled($comparison['remove']) ? $comparison['remove'] : 'Blank'); ?></span></label></td>
              </tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <div class="mb-3">
      <label class="form-label">Audit reason</label>
      <textarea name="reason" class="form-control" rows="2" minlength="10" maxlength="2000" required>Confirmed one-sided-history duplicate after matching identity details.</textarea>
    </div>
    <div class="mb-3">
      <label class="form-label">Type exactly: <code><?php echo e($analysis['confirmation_phrase']); ?></code></label>
      <input name="confirmation" class="form-control" autocomplete="off" required>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
      <span class="small text-muted">Restricted to Super Admins. Changed linked data will reject the merge.</span>
      <button class="btn btn-danger"><i class="ti ti-git-merge me-1"></i>Confirm permanent merge</button>
    </div>
  </form>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\superadmin\player-duplicate-quick-merge.blade.php ENDPATH**/ ?>