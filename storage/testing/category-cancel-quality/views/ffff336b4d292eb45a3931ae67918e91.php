
<?php $__env->startSection('title', $event->name.' – '.$age.' standings'); ?>
<?php $__env->startSection('content'); ?>
<div class="container py-4">
  <h1 class="h4"><?php echo e($event->name); ?> — <?php echo e($age); ?> standings</h1>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($standings === null): ?>
    <p role="status">Standings will be available after the last match in this age group has been processed.</p>
    <a class="btn btn-outline-primary" style="min-height:44px" href="<?php echo e(request()->url()); ?>">Check again</a>
  <?php else: ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($standings['mixedRules']): ?>
      <p>Draws use different scoring rules. Totals are shown without a combined ranking.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="table-responsive">
      <table class="table"><thead><tr><th>Rank</th><th>Region / team</th><th>Played</th><th>Wins</th><th>Match points</th><th>Tie points</th><th>Total points</th></tr></thead><tbody>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $standings['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr><td><?php echo e($row['rank'] ?? '—'); ?></td><td><?php echo e($row['name']); ?></td><td><?php echo e($row['played']); ?></td><td><?php echo e($row['wins']); ?></td><td data-match-points><?php echo e($row['match_points']); ?></td><td data-tie-points><?php echo e($row['tie_points']); ?></td><td><?php echo e($row['points']); ?></td></tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="7">No standings available for this age group.</td></tr>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </tbody></table>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\fixtures\convener-standings.blade.php ENDPATH**/ ?>