<?php $__env->startSection('title', 'Edit Team Rubber'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div><h4 class="mb-1">Edit rubber #<?php echo e($team_fixture->id); ?></h4>
      <p class="text-muted mb-0"><?php echo e($team_fixture->home_side_name ?? 'Home'); ?> vs <?php echo e($team_fixture->away_side_name ?? 'Away'); ?></p></div>
    <a href="<?php echo e(url()->previous()); ?>" class="btn btn-outline-secondary">Back</a>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?><div class="alert alert-danger"><ul class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <form method="POST" action="<?php echo e(route('backend.team-fixtures.update', $team_fixture)); ?>">
    <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
    <input type="hidden" name="participant_revision" value="<?php echo e(app(\App\Services\TeamParticipantHistoryService::class)->revision($team_fixture)); ?>">
    <div class="card mb-3"><div class="card-header">Schedule</div><div class="card-body row g-3">
      <div class="col-md-4"><label class="form-label" for="scheduled_at">Time</label><input class="form-control" type="datetime-local" id="scheduled_at" name="scheduled_at" value="<?php echo e(old('scheduled_at', $team_fixture->scheduled_at?->format('Y-m-d\TH:i'))); ?>"></div>
      <div class="col-md-4"><label class="form-label" for="venue_id">Venue</label><select class="form-select" id="venue_id" name="venue_id"><option value="">Unassigned</option><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($venue->id); ?>" <?php if(old('venue_id', $team_fixture->venue_id) == $venue->id): echo 'selected'; endif; ?>><?php echo e($venue->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></select></div>
      <div class="col-md-2"><label class="form-label" for="court_label">Court</label><input class="form-control" id="court_label" name="court_label" value="<?php echo e(old('court_label', $team_fixture->court_label)); ?>"></div>
      <div class="col-md-2"><label class="form-label" for="duration_min">Minutes</label><input class="form-control" type="number" min="10" max="480" id="duration_min" name="duration_min" value="<?php echo e(old('duration_min', $team_fixture->duration_min)); ?>"></div>
    </div></div>
    <div class="card"><div class="card-header">Score</div><div class="card-body row g-3">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php for($set = 1; $set <= 3; $set++): ?>
        <?php ($result = $team_fixture->fixtureResults->firstWhere('set_nr', $set)); ?>
        <div class="col-md-4"><label class="form-label">Set <?php echo e($set); ?></label><div class="input-group"><input class="form-control" type="number" min="0" name="set<?php echo e($set); ?>_home" value="<?php echo e(old('set'.$set.'_home', $result?->team1_score)); ?>"><span class="input-group-text">–</span><input class="form-control" type="number" min="0" name="set<?php echo e($set); ?>_away" value="<?php echo e(old('set'.$set.'_away', $result?->team2_score)); ?>"></div></div>
      <?php endfor; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <div class="col-12 text-end"><button class="btn btn-primary" type="submit">Save changes</button></div>
    </div></div>
  </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-fixtures\edit.blade.php ENDPATH**/ ?>