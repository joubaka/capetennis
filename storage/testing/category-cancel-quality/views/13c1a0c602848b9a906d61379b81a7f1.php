<?php
  $snapshot = $ratingSnapshot ?? \App\Services\Performance\PlayerRatingHistoryDisplay::snapshot($ratingHistory ?? []);
  $snapshotPoints = $snapshot['points'];
  $snapshotLatest = $snapshotPoints ? $snapshotPoints[array_key_last($snapshotPoints)] : null;
  $expandedTrend = $expandedTrend ?? false;
  $chartX = fn ($index) => $expandedTrend ? 38 + $index * 422 / 11 : 10 + $index * 260 / 11;
  $chartY = fn ($score) => $expandedTrend ? 114 - $score : 80 - $score * .7;
  $snapshotChanged = false;
  foreach ($snapshotPoints as $i => $savedPoint) {
    $before = $snapshotPoints[$i - 1] ?? null;
    if ($before && $before['index'] + 1 === $savedPoint['index'] && $before['group'] !== $savedPoint['group']) $snapshotChanged = true;
  }
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$snapshotLatest && ($compactUnavailable ?? false)): ?>
<p class="ctr-rating-status small mb-0" role="status" data-rating-snapshot data-current-rating-summary>
  <?php echo e($snapshot['reason'] || $snapshot['stale'] ? 'Rating update pending.' : 'No rating yet.'); ?>

</p>
<?php else: ?>
<section class="ct-rating-snapshot" aria-label="Latest saved rating summary" data-rating-snapshot data-current-rating-summary>
  <h3>Latest saved rating</h3>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($snapshotLatest): ?>
    <div class="ct-rating-snapshot-score">
      <?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['size' => 'small','stage' => $snapshot['stage'],'overall' => empty($snapshot['stage']),'score' => $snapshotLatest['score'],'local' => $snapshotLatest['connection_status'] === 'disconnected']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['size' => 'small','stage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($snapshot['stage']),'overall' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(empty($snapshot['stage'])),'score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($snapshotLatest['score']),'local' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($snapshotLatest['connection_status'] === 'disconnected')]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $attributes = $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $component = $__componentOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
      <p><?php echo e($snapshot['label']); ?><br><?php echo e($snapshotLatest['confidence_label']); ?> confidence<br><time datetime="<?php echo e($snapshotLatest['date']); ?>"><?php echo e($snapshotLatest['date']); ?></time></p>
    </div>
    <figure class="ct-rating-snapshot-trend">
      <svg viewBox="<?php echo e($expandedTrend ? '0 0 480 145' : '0 0 280 90'); ?>" role="img" aria-label="Saved monthly rating trend on a fixed zero to one hundred scale" data-rating-mini-trend>
        <title>Available saved rating trend · 0–100</title><desc>Missing months remain gaps. Dashed links indicate changed comparison groups and are not like-for-like progress.</desc>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expandedTrend): ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = [100, 50, 0]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tick): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <line x1="38" y1="<?php echo e($chartY($tick)); ?>" x2="460" y2="<?php echo e($chartY($tick)); ?>" stroke="#70869b" />
            <text x="34" y="<?php echo e($chartY($tick) + 5); ?>" fill="#e0ebf5" font-size="18" text-anchor="end"><?php echo e($tick); ?></text>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $snapshotPoints; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $savedPoint): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($loop->last || ($loop->first && $snapshotLatest['index'] - $savedPoint['index'] >= 4)): ?>
              <text x="<?php echo e($chartX($savedPoint['index'])); ?>" y="138" fill="#e0ebf5" font-size="18" text-anchor="<?php echo e($loop->last && $savedPoint['index'] >= 3 ? 'end' : 'start'); ?>"><?php echo e(\Carbon\CarbonImmutable::parse($savedPoint['date'])->format('M Y')); ?></text>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php else: ?>
          <line x1="10" y1="80" x2="270" y2="80" stroke="#ffffff25" />
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $snapshotPoints; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $savedPoint): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php ($before = $snapshotPoints[$loop->index - 1] ?? null); ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($before && $before['index'] + 1 === $savedPoint['index'] && $before['group'] !== null && $savedPoint['group'] !== null): ?>
            <line x1="<?php echo e($chartX($before['index'])); ?>" y1="<?php echo e($chartY($before['score'])); ?>" x2="<?php echo e($chartX($savedPoint['index'])); ?>" y2="<?php echo e($chartY($savedPoint['score'])); ?>" stroke="#c4e4ff" stroke-width="2" <?php if($before['group'] !== $savedPoint['group']): ?> stroke-dasharray="4 4" data-snapshot-group-change <?php endif; ?> />
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <circle cx="<?php echo e($chartX($savedPoint['index'])); ?>" cy="<?php echo e($chartY($savedPoint['score'])); ?>" r="<?php echo e($loop->last ? ($expandedTrend ? 6 : 4) : ($expandedTrend ? 3.5 : 2.5)); ?>" fill="<?php echo e($loop->last ? '#ff9f43' : '#d8edff'); ?>" data-snapshot-point><title><?php echo e($savedPoint['date']); ?> · <?php echo e(number_format($savedPoint['score'], 1)); ?> CTR</title></circle>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </svg>
      <figcaption>Ratings are out of 100. Gaps show months without a saved rating.<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($snapshotChanged): ?> A dashed line shows a change in the group used for comparison.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></figcaption>
    </figure>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($snapshotLatest['evidence_basis'] === 'finish_only'): ?><p>Low confidence · final placings only</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($snapshotLatest['connection_status'] === 'disconnected'): ?><p>Provisional within-group estimate; not calibrated against other groups.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($snapshotLatest['historical_connection']): ?><p>Low confidence — historical connection. Link dated <?php echo e($snapshotLatest['connection_reference_date'] ?? 'unknown'); ?>.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php else: ?>
    <p>No rating yet. A rating needs enough suitable results.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($snapshot['reason']): ?><p role="status"><?php echo e($snapshot['reason']); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$expandedTrend): ?>
    <p class="ct-rating-snapshot-note">Provisional estimate, not an official ranking.<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($snapshot['as_of']): ?> Saved through <?php echo e($snapshot['as_of']); ?>.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($snapshot['stale']): ?> An update is pending.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
  <?php elseif($snapshot['stale']): ?>
    <p class="ct-rating-snapshot-note">An update is pending.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\rating-snapshot.blade.php ENDPATH**/ ?>