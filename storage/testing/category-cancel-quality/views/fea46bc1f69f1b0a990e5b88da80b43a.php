<?php
  $months = $record['monthly'] ?? [];
  $largest = max([0, ...array_values(array_map(fn ($month) => $month['wins'] + $month['losses'], $months))]);
?>
<section class="overview-chart" aria-labelledby="record-monthly-heading" data-match-activity>
  <h3 id="record-monthly-heading">Match activity</h3>
  <p class="record-note mb-0">Wins and losses · <?php echo e($record['year'] ?: 'Latest 12 saved months'); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($record['year'] === '' && $months): ?> · <?php echo e(reset($months)['label']); ?> – <?php echo e(end($months)['label']); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($largest > 0): ?>
    <ul class="record-month-chart" aria-label="Monthly match activity">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $months; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $month): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php ($count = $month['wins'] + $month['losses']); ?>
        <li>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($count): ?><a class="record-month-link" href="<?php echo e(request()->fullUrlWithQuery(['record_month' => $key, 'page' => null])); ?>#match-results" aria-label="<?php echo e($month['label']); ?>: <?php echo e($month['wins']); ?> wins, <?php echo e($month['losses']); ?> losses. View saved matches" <?php if(($record['record_month'] ?? '') === $key): ?> aria-current="true" <?php endif; ?>>
          <?php else: ?><div class="record-month-link" aria-label="<?php echo e($month['label']); ?>: no saved matches"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <span class="record-month-count" aria-hidden="true"><?php echo e($count); ?></span>
            <span class="record-month-column" aria-hidden="true"><span class="record-month-bars">
              <span style="height:<?php echo e(100 * $month['losses'] / $largest); ?>%;background:#f4a340"></span>
              <span style="height:<?php echo e(100 * $month['wins'] / $largest); ?>%;background:#145c8d"></span>
            </span></span>
            <small aria-hidden="true"><?php echo e(substr($month['label'], 0, 3)); ?></small>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($count): ?></a><?php else: ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </li>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </ul>
    <p class="record-note mt-2 mb-0"><span class="record-key" style="background:#145c8d"></span> Wins <span class="record-key ms-2" style="background:#f4a340"></span> Losses <span class="ms-2">Select a month to view matches.</span></p>
    <details>
      <summary>Monthly counts and match links</summary>
      <div class="table-responsive"><table class="table table-sm mb-0">
        <caption class="visually-hidden">Recorded wins and losses by month</caption>
        <thead><tr><th scope="col">Month</th><th scope="col">Wins</th><th scope="col">Losses</th></tr></thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $months; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $month): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr><th scope="row"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($month['wins'] || $month['losses']): ?><a href="<?php echo e(request()->fullUrlWithQuery(['record_month' => $key, 'page' => null])); ?>#match-results" <?php if(($record['record_month'] ?? '') === $key): ?> aria-current="true" <?php endif; ?>><?php echo e($month['label']); ?></a><?php else: ?><?php echo e($month['label']); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></th><td><?php echo e($month['wins']); ?></td><td><?php echo e($month['losses']); ?></td></tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table></div>
    </details>
  <?php else: ?>
    <p class="record-note mt-3 mb-0" role="status">No saved match activity in this chart’s time window.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\record-monthly.blade.php ENDPATH**/ ?>