<?php ($recentMatches = collect($liveResults)); ?>
<section class="record-sheet record-live-results" aria-labelledby="live-results-heading" data-my-tennis-live-results="<?php echo e(route('my.tennis.record.live', $recordPlayer)); ?>" <?php if($liveResultsPrefetch ?? false): ?> hidden <?php endif; ?>>
  <div class="record-section-heading">
    <h2 id="live-results-heading" tabindex="-1">Latest results</h2>
    <span class="record-note">Past seven days</span>
  </div>
  <p class="record-note mb-2">Recently posted or updated completed scores · separate from your saved season totals.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $recentMatches->take(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <?php echo $__env->make('frontend.my-tennis.record-live-result-row', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <p class="mb-0">No completed scores have been posted for this player in the past seven days.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recentMatches->count() > 3): ?>
    <details id="recent-results-more" class="recent-results-more" data-recent-results-more>
      <summary>Show all recent results <span class="record-note">(<?php echo e($recentMatches->count() - 3); ?> more)</span></summary>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $recentMatches->skip(3); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php echo $__env->make('frontend.my-tennis.record-live-result-row', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </details>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\record-live-results.blade.php ENDPATH**/ ?>