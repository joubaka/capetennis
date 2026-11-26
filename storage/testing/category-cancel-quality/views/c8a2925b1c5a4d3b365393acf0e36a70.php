<?php
  $ball = in_array($infoStage, ['red', 'orange'], true) ? $infoStage : 'standard';
  $label = ['red' => 'Red ball', 'orange' => 'Orange ball', 'wilson-b' => 'Wilson B', 'wilson-a' => 'Wilson A', 'tsa' => 'TSA tournaments'][$infoStage];
  $progress = $pathway['stageProgress'][$ball] ?? [];
  $movePending = ($pathway['move']['to'] ?? null) === $ball && $pathway['move']['score'] === null;
?>
<div data-pathway-stage-details="<?php echo e($infoStage); ?>">
  <h5 class="h6"><?php echo e($label); ?> · <?php echo e($ratingPlayer->full_name); ?></h5>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ball !== 'standard'): ?>
    <p>Enjoy singles events at <?php echo e($label); ?> level and build your experience. Each ball level has its own CTR, so your progress here comes from results at this level.</p>
    <h6 class="small fw-bold">Events to explore</h6>
    <ul class="ps-3"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Services\Performance\PlayerPathwayService::EVENT_OPTIONS[$ball]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(str_contains($option, 'TSA')): ?><a href="https://www.satennis.co.za/" target="_blank" rel="noopener noreferrer"><?php echo e($option); ?> <span class="visually-hidden">(opens in a new tab)</span></a><?php else: ?><?php echo e($option); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul>
    <?php ($target = $progress['target'] ?? ($pathway['benchmarks'][$ball === 'red' ? 0 : 1]['target'] ?? null)); ?>
    <p><strong>Ready to explore the next level?</strong> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($target): ?> <?php echo e(number_format($target['ctr'], 0)); ?> CTR and <?php echo e($target['events']); ?> published <?php echo e($label); ?> events. <?php else: ?> Benchmark awaiting configuration. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> Chat with your coach when both targets are met using your current rating and event history at this level. Several matches at the same event count as one event.</p>
    <p><strong>Your progress so far:</strong> <?php echo e($movePending ? 'CTR pending for the recorded move' : (isset($progress['score']) ? number_format($progress['score'], 1).' CTR' : 'Rating unavailable')); ?> · <?php echo e($progress['events'] ?? 'Unknown'); ?> published events. <?php echo e($movePending ? 'Not assessed' : ($progress['status'] ?? 'Not assessed')); ?></p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($progress['reason'] ?? null): ?><p><?php echo e($progress['reason']); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <p>These targets look at your latest <?php echo e(\App\Services\Performance\AllAgeRatingModel::WINDOW_DAYS); ?> days of results. Progress can change as that window moves, and playing at a higher level does not complete earlier stages.</p>
  <?php elseif(!$pathway['competition_bands_available']): ?>
    <p>Tournament rating bands are awaiting configuration. Ask Cape Tennis to review the guide; check event requirements with the organiser.</p>
  <?php elseif($infoStage === 'tsa'): ?>
    <p>Thinking about TSA tournaments? Chat with your coach about which events would suit you, then check their age, entry and nomination requirements.</p>
    <p>The Cape Tennis guide suggests exploring TSA tournaments with a Green/Yellow CTR above <?php echo e(\App\Services\Performance\PlayerPathwayService::formatCutoff($pathway['tsaCutoff'])); ?>. A CTR of exactly <?php echo e(\App\Services\Performance\PlayerPathwayService::formatCutoff($pathway['tsaCutoff'])); ?> remains in the Wilson A band. Review suitability with your coach and the organiser.</p>
    <p>CTR is not a TSA entry minimum. Check each event’s current entry, ranking and selection requirements.</p>
    <h6 class="small fw-bold">Junior tournament guide</h6>
    <p class="small text-muted">Formats vary by event. Check the current TSA calendar and the event’s SportyHQ listing for formats, age groups, dates, sponsors, ranking points and entry requirements.</p>
    <dl>
      <dt>Rainbow Kidz</dt>
      <dd>Under-10 introduction using red, orange and green balls on smaller courts. Usually 1–2 days, with no ranking points.</dd>
      <dt>Seasonal tournaments</dt>
      <dd>Summer, Autumn, Winter and Spring events, from U12 to Open. An option for a first TSA tournament.</dd>
      <dt>Mini Series</dt>
      <dd>Series 1–6, from U12 to Open, typically over 3 days. Platinum, Gold, Silver and Bronze groups are arranged by ranking; points depend on finishing position.</dd>
      <dt>Grand Prix</dt>
      <dd>U12 to Open, usually 4–5 days, mainly in a single Platinum draw.</dd>
      <dt>Masters</dt>
      <dd>Invitation events, with qualification through the Mini Series race rankings.</dd>
      <dt>Super 8</dt>
      <dd>Usually 5–6 days, aimed at experienced players, mainly in Platinum draws with higher ranking points.</dd>
      <dt>Junior Nationals</dt>
      <dd>The national junior championship, competing for a national junior title.</dd>
    </dl>
    <h6 class="small fw-bold">Planning your time</h6>
    <p>Allow roughly 2–6 days, depending on the event and draw: around 2–3 days for a 16-player draw, 4–5 days for a 32-player draw, and 6 days for longer events with 32-, 64- or 128-player draws. Confirm the actual schedule before making travel plans.</p>
    <a href="https://www.satennis.co.za/" target="_blank" rel="noopener noreferrer">SA Tennis · TSA events <span class="visually-hidden">(opens in a new tab)</span></a>
  <?php else: ?>
    <p>Wilson A and B use the same Green/Yellow rating and event history. The Cape Tennis guide uses CTR <?php echo e(\App\Services\Performance\PlayerPathwayService::formatCutoff($pathway['wilsonCutoff'])); ?> or below for Wilson B, above <?php echo e(\App\Services\Performance\PlayerPathwayService::formatCutoff($pathway['wilsonCutoff'])); ?> through <?php echo e(\App\Services\Performance\PlayerPathwayService::formatCutoff($pathway['tsaCutoff'])); ?> for Wilson A, and above <?php echo e(\App\Services\Performance\PlayerPathwayService::formatCutoff($pathway['tsaCutoff'])); ?> for TSA tournaments.</p>
    <p>These bands are Cape Tennis guidance, not official qualification or selection rules. Check division suitability with your coach and the organiser.</p>
    <h6 class="small fw-bold">Events to explore</h6>
    <ul class="ps-3"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = \App\Services\Performance\PlayerPathwayService::EVENT_OPTIONS['standard']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $option): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($option); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($ball === 'standard'): ?><p><strong>Your Green/Yellow progress:</strong> <?php echo e($movePending ? 'CTR pending for the recorded move' : (isset($progress['score']) ? number_format($progress['score'], 1).' CTR' : 'Rating unavailable')); ?> · <?php echo e($progress['events'] ?? 'Unknown'); ?> published events. <?php echo e($movePending ? '' : ($progress['reason'] ?? '')); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$movePending && $pathway['current_stage'] === $ball): ?>
    <p><strong><?php echo e($pathway['presentation']['status']); ?></strong> · <?php echo e($pathway['reason']); ?></p>
    <h6 class="small fw-bold">What’s next?</h6><ul class="ps-3"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $pathway['next_steps']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($step); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <p class="text-muted mb-0">This is a guide to your next step. Each event or team still has its own entry and selection process.</p>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\pathway-info.blade.php ENDPATH**/ ?>