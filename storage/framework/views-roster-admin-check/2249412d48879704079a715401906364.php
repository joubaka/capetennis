<?php $__env->startSection('title', 'Team standings – '.$draw->drawName); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl">
  <h1 class="h4"><?php echo e($draw->drawName); ?>: team standings</h1>
  <div class="d-flex flex-wrap gap-2 mb-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->event->standings_published): ?><a class="btn btn-outline-primary" style="min-height:44px" href="<?php echo e(route('frontend.events.standings', $draw->event)); ?>">Full event standings</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <a class="btn btn-outline-secondary" style="min-height:44px" href="<?php echo e(route('frontend.fixtures.index', $draw)); ?>">View match results</a>
  </div>
  <p>Completed matches contribute points, match wins, sets and games. Team ties count as played once every required match is complete. Teams tied on all configured criteria share a rank.</p>
  <?php echo $__env->make('frontend.fixtures.partials.live-results-status', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <div data-live-results="draw-standings">
  <div class="card"><div class="table-responsive" tabindex="0" role="region" aria-label="Standings table; scroll sideways for all statistics"><table class="table align-middle mb-0 text-nowrap">
    <thead><tr><th>Rank</th><th>Team / region</th><th>Ties played</th><th>Ties won</th><th>Ties drawn</th><th>Ties lost</th><th>Points</th><th>Matches W–L</th><th>Sets F–A</th><th>Games F–A</th></tr></thead>
    <tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr>
      <td><?php echo e($row['rank']); ?></td><th><?php echo e($row['name']); ?></th><td><?php echo e($row['played']); ?></td><td><?php echo e($row['wins']); ?></td><td><?php echo e($row['draws']); ?></td><td><?php echo e($row['losses']); ?></td><td><?php echo e($row['points']); ?></td>
      <td><?php echo e($row['rubber_wins']); ?>–<?php echo e($row['rubber_losses']); ?></td><td><?php echo e($row['sets_for']); ?>–<?php echo e($row['sets_against']); ?></td><td><?php echo e($row['games_for']); ?>–<?php echo e($row['games_against']); ?></td>
    </tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="10">No published team ties or legacy match results are available.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody>
  </table></div></div>
  <p class="text-muted mt-2">Legacy matches count towards match wins and scores, but do not count as completed team ties.</p>
  </div>
</div>
<?php echo $__env->make('frontend.fixtures.partials.live-results-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\fixtures\team-standings.blade.php ENDPATH**/ ?>