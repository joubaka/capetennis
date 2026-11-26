<section class="record-sheet" aria-labelledby="upcoming-heading">
  <div class="record-section-heading">
    <h2 id="upcoming-heading">Upcoming matches</h2>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$record['reason']): ?><a href="#past-opponents">Past opponents ↓</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <?php
    $personalSnapshot = ($workspace['canViewRating'] ?? false) ? ($workspace['snapshot'] ?? null) : null;
    $personalPoints = $personalSnapshot['points'] ?? [];
    $personalRating = !empty($personalSnapshot['stage']) && $personalPoints ? $personalPoints[array_key_last($personalPoints)] : null;
  ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $upcoming['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $match): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <?php ($matchTime = \Carbon\Carbon::parse($match['date'])); ?>
    <article class="record-row record-upcoming-row">
      <time datetime="<?php echo e($matchTime->toIso8601String()); ?>"><?php echo e($matchTime->format('j M Y')); ?> <strong><?php echo e($matchTime->format('H:i')); ?></strong></time>
      <div>
        <strong><?php echo e($match['event']); ?></strong>
        <p class="record-note"><?php echo e($match['draw']); ?><?php echo e($match['team'] ? ' · '.$match['team'] : ''); ?></p>
        <p class="record-note"><?php echo e($match['venue'] ?: 'Venue to be confirmed'); ?> · <?php echo e($match['court'] ? 'Court '.$match['court'] : 'Court to be confirmed'); ?></p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $match['opponents']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $opponent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
          <?php ($opponentRating = $upcoming['ratings'][$opponent['id'] ?? 0] ?? null); ?>
          <details class="record-rating-comparison">
            <summary><strong><?php echo e($opponent['name']); ?></strong></summary>
            <div class="record-rating-comparison-body">
              <h3 class="h6 record-rating-comparison-title"><?php echo e($recordPlayer->full_name); ?> vs <?php echo e($opponent['name']); ?></h3>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [
                ['name' => $recordPlayer->full_name, 'rating' => $personalRating, 'stage' => $personalSnapshot['stage'] ?? null],
                ['name' => $opponent['name'], 'rating' => $opponentRating, 'stage' => $opponentRating['stage'] ?? null],
              ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $side): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="record-rating-comparison-side">
                  <?php echo $__env->make('frontend.my-tennis.record-player-name', ['name' => $side['name'], 'rating' => $side['rating'], 'stage' => $side['stage']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($side['rating']['confidence_label'] ?? null, ['Low', 'Medium', 'High'], true)): ?>
                    <small class="record-note"><?php echo e($side['rating']['confidence_label']); ?> confidence</small>
                  <?php elseif(!$side['rating']): ?>
                    <small class="record-note">Ball rating unavailable</small>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($personalRating && $opponentRating): ?>
                <p class="record-note mt-2">These are saved singles ratings. Ratings from different ball levels or disconnected comparison groups cannot be compared directly.</p>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($opponent['id']): ?><a class="record-fixture-link mt-1" href="<?php echo e(route('my.tennis.record', ['player' => $recordPlayer->id, 'section' => 'opponents', 'opponent' => $opponent['id']])); ?>#opponent-meetings">See past meetings →</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
          </details>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
          <p class="record-note mt-2">Opponent players to be confirmed.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <a href="<?php echo e($match['url']); ?>" class="record-fixture-link"><?php echo e($match['team'] ? 'View fixtures' : 'View draw'); ?> →</a>
    </article>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <p class="mb-0" role="status">No upcoming match times have been published yet.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php echo $__env->make('frontend.my-tennis.record-pagination', ['matchPage' => $upcoming, 'pageKey' => 'upcoming_page'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</section>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\record-upcoming.blade.php ENDPATH**/ ?>