<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ($placementSections ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $placementSection): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <section class="card mb-4" data-placement-results="<?php echo e($placementSection['draw']->id); ?>">
    <div class="card-header"><h2 class="h5 mb-1"><?php echo e($placementSection['draw']->drawName); ?></h2><p class="mb-0">Playoff results determine final places. These matches do not add points to the round-robin standings.</p></div>
    <div class="card-body">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $placementSection['results']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $placement): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="mb-3" style="overflow-wrap:anywhere">
          <h3 class="h6"><?php echo e($placement['label']); ?><?php echo e(empty($placement['automatic']) ? ' playoff' : ''); ?></h3>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($placement['automatic'])): ?>
            <p><strong>3:</strong> <?php echo e($placement['winner_name']); ?> — awarded without a match because only three sides qualified.</p>
          <?php else: ?>
          <p class="mb-1"><?php echo e($placement['home_name']); ?> <?php echo e($placement['home_wins']); ?>–<?php echo e($placement['away_wins']); ?> <?php echo e($placement['away_name']); ?></p>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($placement['decision_method'])): ?><p class="small">Decided by <?php echo e(['sets_games' => 'sets won, then games won', 'mixed_tiebreak' => 'a mixed-doubles deciding tie-break', 'ruling' => 'organiser decision'][$placement['decision_method']]); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($placement['deciding_score'])): ?> (<?php echo e($placement['deciding_score']['home']); ?>–<?php echo e($placement['deciding_score']['away']); ?>)<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($placement['winner_name']): ?>
            <p class="mb-0"><strong><?php echo e($placement['position']); ?>:</strong> <?php echo e($placement['winner_name']); ?><br><strong><?php echo e($placement['position'] + 1); ?>:</strong> <?php echo e($placement['loser_name']); ?></p>
          <?php elseif($placement['complete']): ?>
            <p class="mb-0 text-muted">The encounter is tied. Final places await the agreed tie-break.</p>
          <?php else: ?>
            <p class="mb-0 text-muted">Final places await completed matches.</p>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <p class="mb-0 text-muted">No placement results are available.</p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </section>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\fixtures\partials\team-placement-results.blade.php ENDPATH**/ ?>