<?php
  $featureDescriptions = [
    'record' => ['Your season. Your monthly tennis report.', 'Review matches, wins, events and recent form in your monthly report. Compare your recorded seasons.'],
    'opponents' => ['Opponent Insights', 'Revisit recorded meetings, season records and recent encounters. Past results do not predict the next match.'],
    'pathway' => ['Tennis pathway', 'Explore ball-level progress and tournament options with your coach. Suggestions depend on suitable results and do not guarantee entry.'],
    'events' => ['Past-event ratings', 'Explore retrospective estimates at eligible event completion dates. These use published evidence available at each cutoff; they are not ratings originally published at the time.'],
    'rating' => ['Player rating', 'Follow your monthly CTR history and explore the results behind it. Ratings may be provisional or unavailable.'],
  ];
  $featureQuery = $previewFeature ?? request()->query('feature', 'rating');
  $requestedFeature = is_string($featureQuery) && array_key_exists($featureQuery, $featureDescriptions) ? $featureQuery : 'rating';
  $previewHeading = ($showSample ?? false) ? 'h2' : 'h3';
  $samplePanels = [
      'rating' => ['Orange Ball CTR' => '48.2 / 100', 'Confidence' => 'Medium', 'Last snapshot' => 'October'],
      'record' => ['Month' => 'Example month', 'Recorded matches' => '6', 'Record' => '4 wins · 2 losses', 'Events' => '2'],
      'opponents' => ['Opponent' => 'Sample player', 'Recorded meetings' => '3', 'Match record' => '2 wins · 1 loss'],
      'pathway' => ['Current ball level' => 'Orange Ball', 'Event type to explore' => 'Junior development events'],
      'events' => ['Event' => 'Sample junior tournament', 'Estimated CTR' => '46.8 / 100'],
  ];
?>
<section class="plus-feature-explanations" aria-label="Explore Player Plus features" data-plus-feature-explanations>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $featureDescriptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $featureKey => [$featureTitle, $featureDescription]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <article id="plus-feature-<?php echo e($featureKey); ?>" class="plus-feature-explanation" tabindex="-1" data-plus-feature-panel="<?php echo e($featureKey); ?>" <?php if($requestedFeature !== $featureKey): ?> hidden <?php endif; ?>>
      <span class="small fw-semibold">Player Plus preview · <?php echo e($ratingPlayer->full_name); ?></span>
      <<?php echo e($previewHeading); ?>><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($featureKey, ['rating', 'events'], true)): ?><?php if (isset($component)) { $__componentOriginalb7701658b742c1480bd9cae408300c9d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7701658b742c1480bd9cae408300c9d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-mark','data' => ['class' => 'me-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'me-2']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $attributes = $__attributesOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__attributesOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $component = $__componentOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__componentOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php echo e($featureTitle); ?></<?php echo e($previewHeading); ?>>
      <p><?php echo e($featureDescription); ?></p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($showSample ?? false) && $requestedFeature === $featureKey): ?>
        <div class="plus-sample" data-plus-sample>
          <strong>Fictional example · not this player’s results</strong>
          <p class="small mb-0">Sample values only, not a personal assessment.</p>
          <dl>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $samplePanels[$featureKey]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <div><dt><?php echo e($label); ?></dt><dd><?php echo e($value); ?></dd></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </dl>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($featureKey === 'rating'): ?>
          <p><a href="<?php echo e(route('my.tennis.feature-preview', ['player' => $ratingPlayer, 'feature' => 'events'])); ?>">Preview past-event ratings</a></p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <p class="small">Essential published tournament information remains free.</p>
      <a class="<?php echo \Illuminate\Support\Arr::toCssClasses(['btn', 'btn-primary' => $showSample ?? false, 'plus-cta' => !($showSample ?? false)]); ?>" href="<?php echo e(app(\App\Services\Performance\PlayerRatingAccess::class)->comingSoon() ? route('ctr.index', ['player' => $ratingPlayer->id]) : route('player-plus.player', $ratingPlayer)); ?>">View Plus for <?php echo e($ratingPlayer->name); ?> →</a>
    </article>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</section>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\feature-preview.blade.php ENDPATH**/ ?>