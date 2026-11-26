    <div class="record-row" data-recent-results-row>
      <time datetime="<?php echo e($match['recorded_at'] ? \Carbon\CarbonImmutable::parse($match['recorded_at'])->toIso8601String() : null); ?>">
        <?php echo e($match['recorded_at'] ? \Carbon\CarbonImmutable::parse($match['recorded_at'])->format('j M, H:i') : ''); ?>

      </time>
      <div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $match['opponent_players'] ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opponent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <?php echo $__env->make('frontend.my-tennis.record-player-name', [
            'name' => ($loop->first ? 'vs ' : '').$opponent['name'],
            'rating' => $pastRatings[$opponent['player_id'] ?? 0] ?? null,
          ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <strong><?php echo e($match['opponents'] ? 'vs '.$match['opponents'] : $match['draw']); ?></strong>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <p><a href="<?php echo e($match['url']); ?>"><?php echo e($match['event']); ?></a></p>
      </div>
      <div class="record-score"><?php echo e($match['outcome']); ?><br><?php echo e($match['score']); ?></div>
    </div>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\record-live-result-row.blade.php ENDPATH**/ ?>