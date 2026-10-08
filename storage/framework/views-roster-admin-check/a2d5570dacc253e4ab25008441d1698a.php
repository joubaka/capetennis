<?php $__env->startSection('title', 'Create Team Rubber'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="mb-1">Create Team Rubber</h4>
      <p class="text-muted mb-0">The rubber will be attached to a team tie in the selected draw.</p>
    </div>
    <a href="<?php echo e(url()->previous()); ?>" class="btn btn-outline-secondary">Back</a>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <div class="alert alert-danger"><ul class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="card">
    <div class="card-body">
      <form method="POST" action="<?php echo e(route('backend.team-fixtures.store')); ?>" class="row g-3">
        <?php echo csrf_field(); ?>
        <div class="col-md-6">
          <label class="form-label" for="draw_id">Draw</label>
          <select class="form-select" id="draw_id" name="draw_id" required>
            <option value="">Select draw</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($draw->id); ?>" <?php if(old('draw_id') == $draw->id): echo 'selected'; endif; ?>><?php echo e($draw->drawName); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="round_nr">Round</label>
          <input class="form-control" id="round_nr" name="round_nr" type="number" min="1" value="<?php echo e(old('round_nr', 1)); ?>" required>
        </div>
        <div class="col-md-3">
          <label class="form-label" for="tie_nr">Tie</label>
          <input class="form-control" id="tie_nr" name="tie_nr" type="number" min="1" value="<?php echo e(old('tie_nr', 1)); ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="home_team_id">Home team</label>
          <select class="form-select" id="home_team_id" name="home_team_id" required>
            <option value="">Select team</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($team->id); ?>" <?php if(old('home_team_id') == $team->id): echo 'selected'; endif; ?>><?php echo e($team->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="away_team_id">Away team</label>
          <select class="form-select" id="away_team_id" name="away_team_id" required>
            <option value="">Select team</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $teams; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $team): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($team->id); ?>" <?php if(old('away_team_id') == $team->id): echo 'selected'; endif; ?>><?php echo e($team->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="fixture_type">Rubber type</label>
          <select class="form-select" id="fixture_type" name="fixture_type">
            <option value="1">Singles</option><option value="2">Doubles</option>
            <option value="3">Mixed doubles</option><option value="4">Reverse singles</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="scheduled_at">Scheduled time</label>
          <input class="form-control" id="scheduled_at" name="scheduled_at" type="datetime-local" value="<?php echo e(old('scheduled_at')); ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="venue_id">Venue</label>
          <select class="form-select" id="venue_id" name="venue_id"><option value="">Unscheduled</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($venue->id); ?>" <?php if(old('venue_id') == $venue->id): echo 'selected'; endif; ?>><?php echo e($venue->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>
        <div class="col-12 text-end"><button class="btn btn-primary" type="submit">Create rubber</button></div>
      </form>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-fixtures\create.blade.php ENDPATH**/ ?>