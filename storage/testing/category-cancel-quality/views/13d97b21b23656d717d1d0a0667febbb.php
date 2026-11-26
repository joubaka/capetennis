<?php ($pendingMove = isset($pathway['move']) && $pathway['move']['score'] === null ? $pathway['move'] : null); ?>
<section id="player-development-pathway" class="ctr-pathway mt-3" aria-labelledby="ctr-pathway-heading" data-player-pathway>
  <link rel="stylesheet" href="<?php echo e(asset('assets/css/player-pathway.css')); ?>?v=<?php echo e(filemtime(public_path('assets/css/player-pathway.css'))); ?>">
  <header class="pathway-hero"><span class="pathway-eyebrow">Cape Tennis · Your game, your opportunities</span><h2 id="ctr-pathway-heading">Your Tennis Pathway</h2><p>Find your place. Explore your next challenge. Discover opportunities to play.</p><p class="h4 text-white mb-2"><?php echo e($pathway['presentation']['stage']); ?></p><p class="small"><?php echo e($pathway['presentation']['status']); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pathway['metrics']['date'] ?? null): ?> · Saved <?php echo e($pathway['metrics']['date']); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pathway['presentation']['event_stage']): ?><a class="btn" href="#pathway-upcoming-events">Explore matching events <span aria-hidden="true">→</span></a><?php else: ?><a class="btn" href="#pathway-level-map">Explore tournament levels <span aria-hidden="true">→</span></a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></header>
  <?php echo $__env->make('frontend.my-tennis.pathway-journey', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <div class="pathway-summary">
    <div class="pathway-next-step">
      <h3 class="h6 mb-1">Your tournament stage</h3>
      <p class="h5 mb-2" data-pathway-applicable-stage><?php echo e($pathway['presentation']['stage']); ?></p>
      <p class="small fw-semibold mb-2" role="status" data-pathway-stage-state="<?php echo e($pathway['presentation']['state']); ?>"><?php echo e($pathway['presentation']['status']); ?></p>
      <h4 class="h6 mb-1">Why this stage?</h4>
      <p class="small text-muted mb-2"><?php echo e($pathway['presentation']['why']); ?></p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$pendingMove && in_array($pathway['current_stage'], ['red', 'orange'], true) && $pathway['target']): ?>
        <p class="small mb-2" data-pathway-target-progress>
          <?php echo e($pathway['ctr_label']); ?>: <strong><?php echo e(number_format($pathway['score'], 1)); ?> / <?php echo e(number_format($pathway['target']['ctr'], 1)); ?></strong> ·
          Published <?php echo e($pathway['current_stage_label']); ?> events: <strong><?php echo e($pathway['ownEvents'] ?? 'Unknown'); ?> / <?php echo e($pathway['target']['events']); ?></strong>.
          <?php echo e($pathway['assessment_status']); ?>.
        </p>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <h4 class="h6 mb-1">Suggested next step</h4>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingMove): ?>
        <p class="small mb-3">Play eligible published singles at <?php echo e(\App\Services\Performance\BallRatingModel::LABELS[$pendingMove['to']]); ?> level to establish the next CTR.</p>
      <?php else: ?>
        <ul class="small ps-3 mb-3">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $pathway['next_steps']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($step); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </ul>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pathway['presentation']['event_stage']): ?>
        <a class="btn btn-sm btn-primary pathway-browse" href="#pathway-upcoming-events">View matching events</a>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <a class="btn btn-sm btn-outline-primary pathway-browse" href="<?php echo e(route('home')); ?>">Browse all events</a>
    </div>
    <div class="pathway-current-level">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingMove): ?>
        <h3 class="h6">Latest rated ball level</h3>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pathway['badge']['stage']): ?>
          <div class="pathway-score" data-pathway-top-badge><?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['stage' => $pathway['badge']['stage'],'score' => $pathway['badge']['score'],'local' => $pathway['badge']['local']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['stage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($pathway['badge']['stage']),'score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($pathway['badge']['score']),'local' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($pathway['badge']['local'])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $attributes = $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $component = $__componentOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?></div>
        <?php else: ?>
          <p class="small mb-2">No saved rating from an earlier ball level.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <p class="small mb-0"><?php echo e(\App\Services\Performance\BallRatingModel::LABELS[$pendingMove['to']]); ?> · CTR pending for the recorded move.</p>
      <?php else: ?>
      <h3 class="h6">Ball level: <?php echo e($pathway['current_stage'] === 'standard' ? 'Green/yellow ball' : $pathway['current_stage_label']); ?></h3>
      <div class="pathway-score" data-pathway-top-badge><?php if (isset($component)) { $__componentOriginalbe486b4b2948917b8d6df3788e886b6d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-badge','data' => ['stage' => $pathway['badge']['stage'],'label' => $pathway['badge']['stage'] === 'standard' ? 'Green/yellow ball' : null,'score' => $pathway['badge']['score'],'local' => $pathway['badge']['local']]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['stage' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($pathway['badge']['stage']),'label' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($pathway['badge']['stage'] === 'standard' ? 'Green/yellow ball' : null),'score' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($pathway['badge']['score']),'local' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($pathway['badge']['local'])]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $attributes = $__attributesOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__attributesOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d)): ?>
<?php $component = $__componentOriginalbe486b4b2948917b8d6df3788e886b6d; ?>
<?php unset($__componentOriginalbe486b4b2948917b8d6df3788e886b6d); ?>
<?php endif; ?></div>
      <p class="small text-muted mt-2 mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pathway['metrics']['date'] ?? null): ?> Latest saved ball-level rating · <?php echo e($pathway['metrics']['date']); ?> <?php else: ?> No saved ball-level rating available. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pathway['metrics']['stale'] ?? false): ?><p class="small text-muted mt-2 mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pathway['assessment_available'] ?? false): ?> Assessment uses the latest saved rating shown above while the nightly update is due. <?php else: ?> Saved rating awaiting an update; progress is not assessed. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>
  <?php echo $__env->make('frontend.my-tennis.pathway-progress', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('frontend.my-tennis.pathway-move', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('frontend.my-tennis.pathway-events', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <details class="pathway-evidence-details mt-3"><summary>How your pathway is assessed · saved evidence</summary>
  <section class="mt-3" aria-labelledby="pathway-ball-evidence-heading">
    <h3 class="h6 mb-1" id="pathway-ball-evidence-heading">Your saved evidence by ball level</h3>
    <p class="small text-muted mb-0">Counts cover rating-eligible published singles in the <?php echo e(\App\Services\Performance\AllAgeRatingModel::WINDOW_DAYS); ?> days ending <?php echo e($pathway['metrics']['date'] ?? 'on the saved rating date'); ?>.</p>
    <div class="pathway-stages">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = ['red' => '#ed2029', 'orange' => '#ed7100', 'standard' => '#12765a']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ball => $ink): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php ($progress = $pathway['stageProgress'][$ball] ?? []); ?>
        <?php ($stagePending = ($pendingMove['to'] ?? null) === $ball); ?>
        <div class="pathway-stage" style="--stage-ink:<?php echo e($ink); ?>" data-pathway-ball-evidence="<?php echo e($ball); ?>">
          <h4 class="h6 mb-2"><?php echo e(\App\Services\Performance\BallRatingModel::LABELS[$ball]); ?></h4>
          <p class="small mb-1">Saved CTR <strong><?php echo e($stagePending ? 'Pending' : (isset($progress['score']) ? number_format($progress['score'], 1) : 'Unavailable')); ?></strong><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$stagePending && ($progress['local'] ?? false)): ?> · Within-group only <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
          <p class="small mb-1">Played singles <strong><?php echo e($progress['matches'] ?? 'Unavailable'); ?></strong></p>
          <p class="small mb-0">Confidence <strong><?php echo e(!$stagePending && isset($progress['score']) ? ($progress['confidence_label'] ?? 'Unavailable') : 'Unavailable'); ?></strong></p>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </section>
  </details>
</section>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\pathway.blade.php ENDPATH**/ ?>