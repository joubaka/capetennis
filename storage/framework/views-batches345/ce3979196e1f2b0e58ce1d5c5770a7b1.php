<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->isTeamDraw()): ?>
  <?php ($scoring = app(\App\Services\TeamDrawScoringPublicationService::class)->state($draw)); ?>
  <span class="event-scoring-status badge bg-label-<?php echo e($scoring['ready'] ? 'success' : 'warning'); ?>" data-scoring-status data-draw-id="<?php echo e($draw->id); ?>"><?php echo e($scoring['ready'] ? 'Scoring ready' : 'Scoring not ready'); ?></span>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($statusOnly ?? false)): ?>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('publish', $draw)): ?>
      <button type="button" class="btn btn-sm btn-outline-primary" style="min-height:44px" data-enable-scoring data-draw-id="<?php echo e($draw->id); ?>" data-url="<?php echo e(route('draw.enable-scoring', $draw)); ?>" data-status-url="<?php echo e(route('draw.scoring-readiness', $draw)); ?>" <?php if(!$draw->published || $scoring['ready']): ?> hidden <?php endif; ?> <?php if($draw->locked): echo 'disabled'; endif; ?> <?php if($draw->locked): ?> title="Unlock this draw before enabling scoring." <?php endif; ?>>Enable scoring</button>
    <?php endif; ?>
    <span class="small text-muted" data-scoring-feedback data-draw-id="<?php echo e($draw->id); ?>" role="status" aria-live="polite"></span>
    <?php if (! $__env->hasRenderedOnce('1d2fc84d-f414-4cf1-8421-0f691ae2fe03')): $__env->markAsRenderedOnce('1d2fc84d-f414-4cf1-8421-0f691ae2fe03'); ?>
      <style>[data-enable-scoring][hidden] { display: none !important; }</style>
      <script src="<?php echo e(asset('js/team-draw-scoring-readiness.js')); ?>?v=<?php echo e(filemtime(public_path('js/team-draw-scoring-readiness.js'))); ?>" defer></script>
    <?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\draw\partials\scoring-readiness.blade.php ENDPATH**/ ?>