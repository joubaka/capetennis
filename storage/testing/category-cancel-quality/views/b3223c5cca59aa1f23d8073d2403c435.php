<?php
  $journeyPublic = $journeyPublic ?? false;
  $journeyCurrent = $journeyPublic ? null : (($pendingMove['to'] ?? null) ?: ($pathway['presentation']['event_stage'] ?? null));
  $journeyWilson = $journeyPublic ? config('pathway.wilson_a_above') : $pathway['wilsonCutoff'];
  $journeyTsa = $journeyPublic ? config('pathway.tsa_above') : $pathway['tsaCutoff'];
  $journeyBands = is_numeric($journeyWilson) && is_numeric($journeyTsa) && is_finite((float) $journeyWilson) && is_finite((float) $journeyTsa) && $journeyWilson >= 0 && $journeyTsa <= 100 && $journeyTsa > $journeyWilson;
  $journeyStages = [
    'red' => ['Red ball', '#bc2637', 'Build your foundations', 'Explore academy events, school leagues and Red ball opportunities.'],
    'orange' => ['Orange ball', '#b95500', 'Grow your game', 'Explore Orange ball opportunities and discuss the next ball level with your coach.'],
    'Wilson B' => ['Wilson B', '#315d8c', 'Find your competitive rhythm', 'Explore Wilson B divisions using your Green/Yellow CTR and the event’s entry requirements.'],
    'Wilson A' => ['Wilson A', '#164b7a', 'Take on your next challenge', 'Explore Wilson A divisions using your Green/Yellow CTR and the event’s entry requirements.'],
    'TSA tournaments' => ['TSA', '#66509a', 'Explore a wider circuit', 'Discuss suitable TSA tournaments with your coach and check the external organiser’s current requirements.'],
  ];
?>
<section id="pathway-level-map" class="pathway-journey" aria-label="Explore the tennis pathway" data-pathway-journey>
  <div class="pathway-journey-intro"><h3 class="h5">All levels and targets</h3><p>A world of opportunities to play. Explore a stage to see what it offers. Players can join at different stages; this is a guide, not a compulsory ladder.</p></div>
  <div class="pathway-group-labels" aria-hidden="true"><span>Ball-level development</span><span>Competition guidance · Green/Yellow ball</span></div>
  <ol class="pathway-map" aria-label="Development pathway snapshot" data-pathway-snapshot>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $journeyStages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $journeyKey => [$journeyLabel, $journeyInk, $journeyTitle, $journeyCopy]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <li style="--stage-ink:<?php echo e($journeyInk); ?>" <?php if(in_array($journeyKey, ['Wilson B', 'Wilson A', 'TSA tournaments'])): ?> data-pathway-competition="<?php echo e($journeyKey); ?>" <?php endif; ?> <?php if($journeyCurrent === $journeyKey): ?> aria-current="step" <?php endif; ?>>
      <span class="pathway-stage-family"><?php echo e(in_array($journeyKey, ['red', 'orange']) ? 'Ball development' : 'Green/Yellow guidance'); ?></span>
      <details class="pathway-explore" name="tennis-pathway-explore">
        <summary><span class="pathway-stop" aria-hidden="true"><i class="ti <?php echo e(in_array($journeyKey, ['red', 'orange']) ? 'ti-ball-tennis' : 'ti-trophy'); ?>"></i></span><strong><?php echo e($journeyLabel); ?></strong><span class="pathway-explore-label">Explore <span aria-hidden="true">+</span></span></summary>
        <div class="pathway-explore-body"><p class="fw-semibold"><?php echo e($journeyTitle); ?></p><p><?php echo e($journeyCopy); ?></p>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($journeyKey, ['red', 'orange'])): ?>
            <ul class="ps-3"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Services\Performance\PlayerPathwayService::EVENT_OPTIONS[$journeyKey]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $journeyOption): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($journeyOption); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul>
            <?php ($journeyTarget = $journeyPublic ? ($publicPathwayTargets[$journeyKey] ?? null) : ($pathway['benchmarks'][$journeyKey === 'red' ? 0 : 1]['target'] ?? null)); ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($journeyTarget): ?><p>Coach discussion targets: <?php echo e(number_format($journeyTarget['ctr'], 0)); ?> <?php echo e($journeyLabel); ?> CTR and <?php echo e($journeyTarget['events']); ?> published events at this ball level.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php elseif($journeyBands): ?>
            <p>Wilson B: CTR <?php echo e(\App\Services\Performance\PlayerPathwayService::formatCutoff($journeyWilson)); ?> or below. Wilson A: above <?php echo e(\App\Services\Performance\PlayerPathwayService::formatCutoff($journeyWilson)); ?> through <?php echo e(\App\Services\Performance\PlayerPathwayService::formatCutoff($journeyTsa)); ?>. TSA: above <?php echo e(\App\Services\Performance\PlayerPathwayService::formatCutoff($journeyTsa)); ?>. Guidance applies only to comparable Green/Yellow ratings.</p>
          <?php else: ?>
            <p>Tournament rating bands are awaiting configuration. Explore event details with your coach.</p>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($journeyKey === 'TSA tournaments'): ?>
            <a href="https://www.satennis.co.za/" target="_blank" rel="noopener noreferrer" class="pathway-stage-link">Explore TSA events <span class="visually-hidden">(opens in a new tab)</span></a>
          <?php else: ?>
            <a href="<?php echo e(route('ctr.index', ['stage' => $journeyKey])); ?>#pathway-events" class="pathway-stage-link">Explore published opportunities <span aria-hidden="true">→</span></a>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$journeyPublic): ?>
            <?php echo $__env->make('frontend.my-tennis.pathway-info-button', ['infoStage' => ['red' => 'red', 'orange' => 'orange', 'Wilson B' => 'wilson-b', 'Wilson A' => 'wilson-a', 'TSA tournaments' => 'tsa'][$journeyKey], 'infoLabel' => $journeyLabel], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </details>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($journeyCurrent === $journeyKey): ?><span class="pathway-here"><?php echo e($pendingMove ? 'Recorded move · CTR pending' : (in_array($journeyKey, ['red', 'orange']) ? 'You are here · latest saved stage' : 'Your rating stage · latest saved CTR')); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </li>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </ol>
  <section class="pathway-exploration-panel" data-pathway-exploration-panel hidden aria-label="Explored stage details">
    <h4 class="h5" data-pathway-exploring-label aria-live="polite"></h4>
    <div data-pathway-exploring-content></div>
  </section>
  <p class="small text-muted">Cape Tennis rating guide. Red, Orange and Green/Yellow ratings stay separate. These opportunities do not guarantee entry, promotion or selection. Earlier stages are not marked complete.</p>
</section>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\pathway-journey.blade.php ENDPATH**/ ?>