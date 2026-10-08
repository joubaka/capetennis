
<?php $__env->startSection('title', 'Team ties – '.$draw->drawName); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl">
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><?php echo e($draw->drawName); ?>: team ties</h1>
    <div class="d-flex flex-wrap gap-2">
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team-fixture.schedule', $draw)): ?><a class="btn btn-outline-primary" href="<?php echo e(route('backend.team-schedule.page', $draw)); ?>">Schedule rubbers</a><?php endif; ?>
      <a class="btn btn-outline-primary" href="<?php echo e(route('backend.team-draw.standings', $draw)); ?>">Standings</a>
    </div>
  </div>
  <p>Review the players for each rubber, validate the complete tie, then publish it before entering scores. Draw publication controls public visibility separately.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->locked || $draw->published): ?><div class="alert alert-info">This draw is protected. Tie validation and publication changes are unavailable.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div id="team-tie-message" class="d-none" role="status" aria-live="polite"></div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $ties->groupBy('round_nr'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $roundNumber => $roundTies): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <h2 class="h5 mt-4">Round <?php echo e($roundNumber); ?></h2>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $roundTies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tie): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php ($protected = $draw->locked || $draw->published || $tie->isLocked() || $tie->published_at || $tie->winner_team_id); ?>
      <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div><h3 class="h6 mb-1"><?php echo e($tie->home_side_name ?? 'Home'); ?> vs <?php echo e($tie->away_side_name ?? 'Away'); ?></h3>
            <span class="badge bg-label-secondary"><?php echo e(ucfirst($tie->status)); ?></span>
          </div>
          <div class="d-flex flex-wrap gap-2">
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('validateTie', $tie)): ?>
              <button type="button" class="btn btn-sm btn-outline-primary team-tie-action" data-url="<?php echo e(route('team-draw.ties.validate', $tie)); ?>" <?php if($protected || $tie->status === 'validated'): echo 'disabled'; endif; ?>>Validate tie</button>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('publishTie', $tie)): ?>
              <button type="button" class="btn btn-sm btn-primary team-tie-action" data-url="<?php echo e(route('team-draw.ties.publish', $tie)); ?>" <?php if($protected || $tie->status !== 'validated'): echo 'disabled'; endif; ?>>Publish tie</button>
            <?php endif; ?>
          </div>
        </div>
        <div class="table-responsive"><table class="table align-middle mb-0">
          <thead><tr><th>Rubber</th><th>Match</th><th>Schedule</th><th>Actions</th></tr></thead>
          <tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $tie->rubbers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rubber): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
            <tr><td><?php echo e($rubber->rubber_sequence); ?></td><td><?php echo e($rubber->rubber_name ?? ucwords(str_replace('_', ' ', $rubber->rubber_code ?? 'Match'))); ?></td>
              <td><?php echo e($rubber->scheduled_at?->format('d M H:i') ?? 'Unscheduled'); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rubber->court_label): ?><span class="text-muted"><?php echo e($rubber->court_label); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td>
              <td><div class="d-flex flex-wrap gap-2">
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team-fixture.view', $rubber)): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('backend.team-fixtures.show', $rubber)); ?>">Review players</a><?php endif; ?>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team-fixture.saveScore', $rubber)): ?><a class="btn btn-sm btn-primary" href="<?php echo e(route('frontend.fixtures.enter-scores', $draw)); ?>">Enter scores</a><?php endif; ?>
                <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('team-fixture.update', $rubber)): ?><a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('backend.team-fixtures.edit', $rubber)); ?>">Edit schedule</a><?php endif; ?>
              </div></td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?><tr><td colspan="4">No rubbers have been generated for this tie.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody>
        </table></div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><div class="alert alert-info">No team ties have been generated.</div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>
<?php $__env->startSection('page-script'); ?>
<script src="<?php echo e(asset('js/team-tie-operations.js')); ?>?v=<?php echo e(filemtime(public_path('js/team-tie-operations.js'))); ?>"></script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\team-draw\operations.blade.php ENDPATH**/ ?>