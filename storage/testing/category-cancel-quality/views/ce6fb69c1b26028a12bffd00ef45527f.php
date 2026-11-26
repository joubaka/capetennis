
<?php $__env->startSection('title', 'Team standings – '.$draw->drawName); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl">
  <h1 class="h4"><?php echo e($draw->drawName); ?>: team standings</h1>
  <?php echo $__env->make('backend.team-draw.partials.organiser-decisions', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('frontend.fixtures.partials.live-results-status', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <div data-live-results="admin-draw-standings">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(app(\App\Services\TeamRoundRobinMatrixService::class)->supports($draw)): ?>
    <?php echo $__env->make('backend.team-draw.partials.round-robin-matrix', ['matrix' => app(\App\Services\TeamRoundRobinMatrixService::class)->forDraw($draw, $rows)], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($draw->team_draw_selection['competition']['phase'] ?? null) === 'playoff'): ?>
    <?php echo $__env->make('frontend.fixtures.partials.team-placement-results', ['placementSections' => [['draw' => $draw, 'category' => $draw->team_draw_selection['competition']['group'], 'age' => $draw->team_draw_selection['competition']['group'], 'gender' => 'mixed', 'results' => app(\App\Services\TeamPlacementResultsService::class)->forDraw($draw, false)]]], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php elseif($draw->team_scoring_rules['undecided'] ?? false): ?>
    <div class="alert alert-info">Scoring is to be decided. Confirm scoring before entering results or qualifying teams.</div>
  <?php else: ?>
  <p>Completed rubbers contribute points. Team ties count as played once every rubber is complete. Teams tied on all configured criteria share a rank.</p>
  <div class="card"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>Rank</th><th>Team</th><th>Played</th><th>Won</th><th>Drawn</th><th>Lost</th><th>Match points</th><th>Tie points</th><th>Total points</th><th>Rubbers</th><th>Sets</th><th>Games</th></tr></thead>
    <tbody><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $rows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><tr>
      <td><?php echo e($row['rank']); ?></td><th><?php echo e($row['name']); ?></th><td><?php echo e($row['played']); ?></td><td><?php echo e($row['wins']); ?></td><td><?php echo e($row['draws']); ?></td><td><?php echo e($row['losses']); ?></td><td data-match-points><?php echo e($row['match_points']); ?></td><td data-tie-points><?php echo e($row['tie_points']); ?></td><td><?php echo e($row['points']); ?></td>
      <td><?php echo e($row['rubber_wins']); ?>–<?php echo e($row['rubber_losses']); ?></td><td><?php echo e($row['sets_for']); ?>–<?php echo e($row['sets_against']); ?></td><td><?php echo e($row['games_for']); ?>–<?php echo e($row['games_against']); ?></td>
    </tr><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="12">No team ties have been generated.</td></tr><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></tbody>
  </table></div></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</div>
<?php echo $__env->make('frontend.fixtures.partials.live-results-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\team-draw\standings.blade.php ENDPATH**/ ?>