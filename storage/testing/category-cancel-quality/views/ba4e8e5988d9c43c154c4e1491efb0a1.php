<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $matchPage['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<article class="record-row">
  <time datetime="<?php echo e($match['date']); ?>"><?php echo e($match['date']); ?></time>
  <div>
    <?php echo $__env->make('frontend.my-tennis.record-player-name', ['name' => $match['opponent'], 'rating' => $matchRatings[$match['opponent_id'] ?? 0] ?? null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <p><?php echo e($match['event']); ?></p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($match['date_basis'] === 'event start proxy'): ?><small class="record-note">Estimated date</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <div class="record-score"><span><?php echo e($match['won'] ? 'Win' : 'Loss'); ?></span><br><span><?php echo e($match['score']); ?></span></div>
</article>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo $__env->make('frontend.my-tennis.record-pagination', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\record-matches.blade.php ENDPATH**/ ?>