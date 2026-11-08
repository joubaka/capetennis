<?php
  $navigationDraws = $event->draws->sort(fn ($left, $right) => strnatcasecmp($left->drawName, $right->drawName) ?: $left->id <=> $right->id)->values();
  $selectedNavigationDraws = $guideSelection->where('event_id', $event->id)->values();
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($selectedNavigationDraws->isNotEmpty()): ?>
  <section class="card mb-3" aria-label="Draw navigation and publication">
    <div class="card-body d-grid gap-3">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $selectedNavigationDraws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $publicationDraw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
          $navigationIndex = $navigationDraws->search(fn ($item) => $item->id === $publicationDraw->id);
          $navigationPrevious = $navigationIndex > 0 ? $navigationDraws[$navigationIndex - 1] : null;
          $navigationNext = $navigationIndex !== false && $navigationIndex < $navigationDraws->count() - 1 ? $navigationDraws[$navigationIndex + 1] : null;
          $navigationQuery = !empty($planningDate) ? ['date' => $planningDate] : [];
        ?>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
          <div><h2 class="h6 mb-1"><?php echo e($publicationDraw->drawName); ?></h2><span class="small text-muted">Draw <?php echo e($navigationIndex + 1); ?> of <?php echo e($navigationDraws->count()); ?></span></div>
          <nav class="d-flex flex-wrap gap-2" aria-label="<?php echo e($publicationDraw->drawName); ?> navigation">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($navigationPrevious): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('backend.event-venue-schedule.index', ['event' => $event->id, 'draw_ids' => [$navigationPrevious->id]] + $navigationQuery)); ?>" title="<?php echo e($navigationPrevious->drawName); ?>">Previous draw</a><?php else: ?><span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true">Previous draw</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('backend.draw.roundrobin.show', $publicationDraw)); ?>#schedule">Open draw</a>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($navigationNext): ?><a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('backend.event-venue-schedule.index', ['event' => $event->id, 'draw_ids' => [$navigationNext->id]] + $navigationQuery)); ?>" title="<?php echo e($navigationNext->drawName); ?>">Next draw</a><?php else: ?><span class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true">Next draw</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </nav>
        </div>
        <?php echo $__env->make('backend.draw.partials.publication-controls', ['publicationDraw' => $publicationDraw, 'publicationSurface' => 'standalone'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <p class="small text-muted mb-0">Only saved match times can be published. Unsaved suggestions remain private.</p>
    </div>
  </section>
  <?php if (! $__env->hasRenderedOnce('79685abe-58a2-450f-bfa3-a08899440506')): $__env->markAsRenderedOnce('79685abe-58a2-450f-bfa3-a08899440506'); ?>
    <script src="<?php echo e(asset('js/draw-workspace-navigation.js')); ?>?v=<?php echo e(filemtime(public_path('js/draw-workspace-navigation.js'))); ?>" defer></script>
  <?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views/backend/schedule/partials/draw-controls.blade.php ENDPATH**/ ?>