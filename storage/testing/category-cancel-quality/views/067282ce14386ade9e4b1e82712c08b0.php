<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$pendingMove && $pathway['current_stage'] === 'standard' && $pathway['currentCompetition'] && $pathway['competition_bands_available']): ?>
  <section class="pathway-progress-panel" aria-labelledby="pathway-progress-heading">
    <h3 class="h6" id="pathway-progress-heading">Your Green/Yellow rating in perspective</h3>
    <p class="small">Latest saved CTR: <strong><?php echo e(number_format($pathway['score'], 1)); ?></strong> · <?php echo e($pathway['presentation']['status']); ?>. This is a guidance band, not a percentage of the pathway completed.</p>
    <div class="pathway-band" role="img" aria-label="Saved Green/Yellow CTR <?php echo e(number_format($pathway['score'], 1)); ?>. Wilson B at or below <?php echo e($pathway['wilsonCutoff']); ?>, Wilson A above <?php echo e($pathway['wilsonCutoff']); ?> through <?php echo e($pathway['tsaCutoff']); ?>, TSA above <?php echo e($pathway['tsaCutoff']); ?>.">
      <span class="pathway-band-marker" style="left:<?php echo e(max(0, min(100, $pathway['score']))); ?>%"><?php echo e(number_format($pathway['score'], 1)); ?> ↓</span>
      <div class="pathway-band-track" aria-hidden="true"><span style="width:<?php echo e($pathway['wilsonCutoff']); ?>%"></span><span style="width:<?php echo e($pathway['tsaCutoff'] - $pathway['wilsonCutoff']); ?>%"></span><span style="width:<?php echo e(100 - $pathway['tsaCutoff']); ?>%"></span></div>
      <div class="pathway-band-labels" aria-hidden="true"><span>Wilson B · ≤<?php echo e($pathway['wilsonCutoff']); ?></span><span>Wilson A · ><?php echo e($pathway['wilsonCutoff']); ?>–<?php echo e($pathway['tsaCutoff']); ?></span><span>TSA · ><?php echo e($pathway['tsaCutoff']); ?></span></div>
    </div>
  </section>
<?php elseif(!$pendingMove && in_array($pathway['current_stage'], ['red', 'orange'], true) && $pathway['target']): ?>
  <section class="pathway-progress-panel" aria-labelledby="pathway-progress-heading">
    <h3 class="h6" id="pathway-progress-heading">Your <?php echo e($pathway['current_stage_label']); ?> targets</h3>
    <p class="small"><?php echo e($pathway['assessment_status']); ?>. Discuss your next step with your coach. These targets use only this ball level’s evidence.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pathway['assessed']): ?>
      <label for="pathway-rating-target"><?php echo e($pathway['ctr_label']); ?>: <?php echo e(number_format($pathway['score'], 1)); ?> / <?php echo e($pathway['target']['ctr']); ?></label>
      <progress class="pathway-meter" id="pathway-rating-target" value="<?php echo e(min($pathway['score'], $pathway['target']['ctr'])); ?>" max="<?php echo e($pathway['target']['ctr']); ?>"><?php echo e(number_format($pathway['score'], 1)); ?></progress>
      <label for="pathway-event-target">Published <?php echo e($pathway['current_stage_label']); ?> events: <?php echo e($pathway['ownEvents']); ?> / <?php echo e($pathway['target']['events']); ?></label>
      <progress class="pathway-meter" id="pathway-event-target" value="<?php echo e(min($pathway['ownEvents'], $pathway['target']['events'])); ?>" max="<?php echo e($pathway['target']['events']); ?>"><?php echo e($pathway['ownEvents']); ?></progress>
    <?php else: ?>
      <p class="small mb-0">Current progress is unavailable until comparable, up-to-date rating and event evidence can be assessed. Saved values are shown separately below.</p>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </section>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\pathway-progress.blade.php ENDPATH**/ ?>