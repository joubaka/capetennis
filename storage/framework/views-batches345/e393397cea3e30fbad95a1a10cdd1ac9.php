<?php $__env->startSection('title', 'Choose draw format — '.$draw->drawName); ?>
<?php $__env->startSection('content'); ?>
<?php echo $__env->make('backend.draw.partials.workspace-header', ['workspaceContext' => 'settings'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('backend.draw.partials.workspace-links', ['workspaceTab' => 'settings'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="mx-auto" style="max-width:900px">
  <p class="text-primary mb-1">Step 1 · Draw format</p>
  <h3 class="mb-2">How should this draw start?</h3>
  <p class="text-muted mb-4"><?php echo e($draw->drawName); ?> · Choose the format first. Player placement and format-specific settings come next.</p>
  <details class="alert alert-info mb-4">
    <summary class="fw-semibold" style="cursor:pointer">Help me choose a format</summary>
    <div class="small mt-3">
      <p class="mb-2"><strong>Most tournaments:</strong> choose Round robin → playoffs so everyone plays a group stage before the knockout rounds.</p>
      <p class="mb-2"><strong>Small field or league:</strong> choose Round robin only when final standings should decide the result.</p>
      <p class="mb-2"><strong>Fast knockout:</strong> choose Playoffs only; a player may have only one match.</p>
      <p class="mb-0"><strong>Placement matches:</strong> choose Monrad. Custom Monrad is an advanced option for placing players into different starting rounds.</p>
    </div>
  </details>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger" role="alert"><?php echo e($errors->first()); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->locked || $draw->published || $draw->oop_published): ?>
    <div class="alert alert-warning" role="alert">
      Unlock the draw and unpublish both the draw and schedule before changing its format.
    </div>
  <?php elseif($resetSummary['results'] > 0): ?>
    <div class="alert alert-danger" role="alert">
      This draw has <?php echo e($resetSummary['results']); ?> recorded result<?php echo e($resetSummary['results'] === 1 ? '' : 's'); ?> and cannot be reset from this page.
    </div>
  <?php elseif($resetSummary['has_format_state']): ?>
    <div class="alert alert-warning" role="alert">
      <strong>Changing format resets this draw.</strong>
      It will permanently remove <?php echo e($resetSummary['fixtures']); ?> generated match<?php echo e($resetSummary['fixtures'] === 1 ? '' : 'es'); ?>,
      <?php echo e($resetSummary['scheduled_times']); ?> scheduled time<?php echo e($resetSummary['scheduled_times'] === 1 ? '' : 's'); ?>, and current group or starting-position placements.
      The draw, venue allocation and <?php echo e($resetSummary['roster_entries']); ?> roster entr<?php echo e($resetSummary['roster_entries'] === 1 ? 'y' : 'ies'); ?> remain available for rebuilding.
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <form method="POST" action="<?php echo e(route('draw.setup.store', $draw)); ?>" id="draw-format-form"
    data-current-workflow="<?php echo e($draw->settings?->workflow); ?>" data-has-format-state="<?php echo e($resetSummary['has_format_state'] ? '1' : '0'); ?>">
    <?php echo csrf_field(); ?>
    <fieldset <?php if($draw->locked || $draw->published || $draw->oop_published || $resetSummary['results'] > 0): echo 'disabled'; endif; ?>>
      <legend class="visually-hidden">Draw format</legend>
      <div class="row g-3 mb-4">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $options; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => [$label, $description]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="col-12 col-md-6">
            <label class="card h-100 p-4 d-flex flex-row gap-3" style="cursor:pointer">
              <input type="radio" name="workflow" value="<?php echo e($value); ?>" class="form-check-input flex-shrink-0" required
                <?php if(old('workflow', $draw->settings?->workflow) === $value): echo 'checked'; endif; ?>>
              <span>
                <strong class="d-flex align-items-center gap-2 mb-2">
                  <?php echo e($label); ?>

                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($value === 'round_robin_playoffs'): ?><span class="badge bg-label-primary">Recommended</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($value === 'custom_monrad'): ?><span class="badge bg-label-secondary">Advanced</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </strong>
                <span class="text-muted d-block"><?php echo e($description); ?></span>
                <small class="d-block mt-2">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($value === 'round_robin'): ?> Best when every player should meet everyone in their group.
                  <?php elseif($value === 'round_robin_playoffs'): ?> Best for a full tournament with group play and a championship finish.
                  <?php elseif($value === 'playoffs'): ?> Fastest format, but eliminated players do not continue.
                  <?php elseif($value === 'monrad'): ?> Best when finishing positions and additional matches matter.
                  <?php else: ?> Use when seeded players must enter in different rounds.
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </small>
              </span>
            </label>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resetSummary['has_format_state']): ?>
        <label class="form-check border rounded p-3 mb-3" for="confirm-format-reset">
          <input class="form-check-input ms-0 me-2" type="checkbox" name="reset_existing" value="1" id="confirm-format-reset">
          <span class="form-check-label"><strong>I understand this removes the current fixtures and scheduled times.</strong></span>
        </label>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <button class="btn btn-primary" type="submit"><?php echo e($resetSummary['has_format_state'] ? 'Change format and reset draw' : 'Continue to setup →'); ?></button>
    </fieldset>
  </form>
  <p class="text-muted small mt-3">Recorded results are always protected. A confirmed reset keeps the draw and eligible roster, but removes generated fixtures and timetable entries.</p>
</div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($resetSummary['has_format_state']): ?>
<script>
document.getElementById('draw-format-form')?.addEventListener('submit', event => {
  const form = event.currentTarget;
  const selected = form.querySelector('input[name="workflow"]:checked')?.value;
  if (!selected || selected === form.dataset.currentWorkflow) return;
  const confirmation = document.getElementById('confirm-format-reset');
  if (!confirmation?.checked) {
    event.preventDefault();
    confirmation?.focus();
    confirmation?.setCustomValidity('Confirm the draw reset before changing format.');
    confirmation?.reportValidity();
    return;
  }
  confirmation.setCustomValidity('');
  if (!window.confirm('Change draw format? This permanently removes the current fixtures, scheduled times, and group or starting-position placements. Recorded results are protected.')) {
    event.preventDefault();
  }
});
document.getElementById('confirm-format-reset')?.addEventListener('change', event => event.currentTarget.setCustomValidity(''));
</script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\setup.blade.php ENDPATH**/ ?>