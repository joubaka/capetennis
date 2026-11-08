<?php
  $workspaceUrl = route('backend.draw.roundrobin.show', $draw);
  $flexibleWorkspace = $draw->usesFlexibleMonrad();
  $workspacePublicationSurface = $workspaceSurface ?? ($flexibleWorkspace ? 'flexible' : 'standalone');
  $workspaceHasDayPublication = in_array($workspaceSurface ?? '', ['roundrobin', 'flexible'], true) && auth()->user()?->can('event.manage', $draw->event);
  $workspaceDraws = $draw->event->draws->where('event_id', $draw->event_id)->sort(fn ($left, $right) => strnatcasecmp($left->drawName, $right->drawName) ?: $left->id <=> $right->id)->values();
?>
<?php if (isset($component)) { $__componentOriginal6ccefb989a1afce853acb3cdbc40307e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6ccefb989a1afce853acb3cdbc40307e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.backend.page-header','data' => ['title' => $draw->drawName,'subtitle' => $draw->event->name,'eyebrow' => 'Draw workspace','icon' => 'ti-tournament','class' => 'draw-workspace-header','dataBackendWide' => true,'dataWorkspaceContext' => ''.e($workspaceContext ?? '').'']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backend.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw->drawName),'subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($draw->event->name),'eyebrow' => 'Draw workspace','icon' => 'ti-tournament','class' => 'draw-workspace-header','data-backend-wide' => true,'data-workspace-context' => ''.e($workspaceContext ?? '').'']); ?>
   <?php $__env->slot('meta', null, []); ?> 
    <span class="badge <?php echo e($draw->published ? 'bg-label-success' : 'bg-label-secondary'); ?>" data-workspace-draw-status>Draw: <?php echo e($draw->published ? 'published' : 'unpublished'); ?></span>
    <span class="badge <?php echo e($draw->oop_published ? 'bg-label-success' : 'bg-label-secondary'); ?>" data-workspace-schedule-status>Schedule: <?php echo e($draw->oop_published ? 'published' : 'unpublished'); ?></span>
    <span class="badge bg-label-warning" data-workspace-lock-status <?php if(!$draw->locked): ?> hidden <?php endif; ?>>Locked</span>
    <span class="small text-muted" role="status" data-share-status></span>
   <?php $__env->endSlot(); ?>
   <?php $__env->slot('actions', null, []); ?> 
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $draw->event)): ?>
        <a class="btn btn-sm btn-primary" data-draw-next-schedule <?php if(!$draw->isTeamDraw() && !$draw->drawFixtures()->exists()): ?> hidden <?php endif; ?> href="<?php echo e(route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]])); ?>">Schedule matches</a>
      <?php endif; ?>
      <?php echo $__env->make('backend.draw.partials.publication-controls', ['publicationDraw' => $draw, 'publicationSurface' => $workspacePublicationSurface, 'publicationDropdown' => true, 'publicationByDay' => $workspaceHasDayPublication], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <details class="draw-workspace-tools" data-individual-draw-tools><summary class="btn btn-sm btn-outline-secondary">More tools</summary><div class="draw-workspace-tools-panel">
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('replaceAlternate', $draw)): ?>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('draw.alternate.show', $draw)); ?>">Replace with alternate</a>
      <?php endif; ?>
      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.manage', $draw->event)): ?><a data-draw-guide-open class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('draw.guide.show', [$draw->event_id, $draw])); ?>">Review setup</a><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($rankingReference)): ?>
        <div class="dropdown draw-ranking-reference">
          <button class="btn btn-sm btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
            <i class="ti ti-list-numbers me-1" aria-hidden="true"></i><?php echo e($rankingReference['category']->name); ?> rankings
          </button>
          <div class="dropdown-menu dropdown-menu-end draw-ranking-menu p-0">
            <div class="draw-ranking-heading">
              <div>
                <span class="draw-ranking-eyebrow">Series ranking reference</span>
                <h2><?php echo e($rankingReference['category']->name); ?></h2>
                <p><?php echo e($rankingReference['series']->name); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingReference['series']->year): ?> · <?php echo e($rankingReference['series']->year); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
              </div>
              <span class="badge bg-label-<?php echo e($rankingReference['status'] === 'published' ? 'success' : ($rankingReference['status'] === 'reviewed' ? 'info' : ($rankingReference['status'] === 'calculated' ? 'warning' : 'secondary'))); ?>">
                <?php echo e($rankingReference['status'] ? ucfirst($rankingReference['status']) : 'No ranking run'); ?>

              </span>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($rankingReference['rows']->isNotEmpty()): ?>
              <div class="draw-ranking-scroll">
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead><tr><th scope="col">Rank</th><th scope="col">Player</th><th scope="col" class="text-end">Points</th></tr></thead>
                  <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $rankingReference['rows']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ranking): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <tr>
                        <td class="draw-ranking-position"><?php echo e($ranking->rank_position); ?></td>
                        <td><?php echo e($ranking->player?->full_name ?? 'Unknown player'); ?></td>
                        <td class="text-end fw-semibold"><?php echo e(number_format($ranking->total_points)); ?></td>
                      </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="draw-ranking-empty">No ranking entries are available for this category in the active series run.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <div class="draw-ranking-footer">
              <span>Reference only · current <?php echo e($rankingReference['status'] ?? 'unavailable'); ?> run</span>
              <a href="<?php echo e($rankingReference['url']); ?>" target="_blank" rel="noopener">Open full rankings</a>
            </div>
          </div>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($workspaceSurface ?? '') === 'roundrobin'): ?>
        <button class="btn btn-sm btn-outline-primary" type="button" id="rr-open-print">Print</button>
      <?php else: ?>
        <a class="btn btn-sm btn-outline-primary" href="<?php echo e($workspaceUrl); ?>#print">Print</a>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <a class="btn btn-sm btn-outline-secondary" href="<?php echo e($flexibleWorkspace ? route('public.flexible-monrad.show', $draw) : route('public.roundrobin.show', $draw)); ?>" target="_blank" rel="noopener" data-workspace-public data-workspace-draw-published="<?php echo e($draw->published ? '1' : '0'); ?>" data-workspace-schedule-published="<?php echo e($draw->oop_published ? '1' : '0'); ?>" <?php if(!$draw->published && !$draw->oop_published): ?> hidden <?php endif; ?>><?php echo e($draw->published ? 'Public view' : 'Preview times'); ?></a>
      <button class="btn btn-sm btn-outline-secondary" type="button" data-share-draw="<?php echo e(route('public.roundrobin.show', $draw)); ?>" <?php if(!$draw->published): echo 'disabled'; endif; ?> title="<?php echo e($draw->published ? 'Share public draw link' : 'Publish the draw before sharing'); ?>">Share</button>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($workspaceSurface ?? '') === 'roundrobin'): ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('lockToggle', $draw)): ?>
          <button type="button" class="btn btn-sm <?php echo e($draw->locked ? 'btn-danger' : 'btn-outline-secondary'); ?>" id="btn-toggle-lock"><span id="lock-label"><?php echo e($draw->locked ? 'Unlock' : 'Lock'); ?></span></button>
        <?php endif; ?>
        <span id="badge-locked" class="d-none" hidden></span><span id="badge-published" class="d-none" hidden></span>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($flexibleWorkspace): ?>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('lockToggle', $draw)): ?>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-workspace-lock="<?php echo e(route('backend.draw.toggle-lock', $draw)); ?>"><?php echo e($draw->locked ? 'Unlock draw' : 'Lock draw'); ?></button>
        <?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div></details>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($workspaceDraws->count() > 1): ?>
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">Switch draw</button>
          <ul class="dropdown-menu dropdown-menu-end">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $workspaceDraws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $otherDraw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <li><a class="dropdown-item <?php echo e($otherDraw->id === $draw->id ? 'active' : ''); ?>" href="<?php echo e(route('backend.draw.roundrobin.show', $otherDraw)); ?>" data-workspace-switch><?php echo e($otherDraw->drawName); ?></a></li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </ul>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route('headOffice.show', $draw->event_id)); ?>">Back to draws</a>
   <?php $__env->endSlot(); ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6ccefb989a1afce853acb3cdbc40307e)): ?>
<?php $attributes = $__attributesOriginal6ccefb989a1afce853acb3cdbc40307e; ?>
<?php unset($__attributesOriginal6ccefb989a1afce853acb3cdbc40307e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6ccefb989a1afce853acb3cdbc40307e)): ?>
<?php $component = $__componentOriginal6ccefb989a1afce853acb3cdbc40307e; ?>
<?php unset($__componentOriginal6ccefb989a1afce853acb3cdbc40307e); ?>
<?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($workspaceHasDayPublication): ?>
  <?php echo $__env->make('backend.schedule.partials.draw-day-publication', ['publicationDraw' => $draw, 'workspaceDayPublication' => true], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if (! $__env->hasRenderedOnce('8756f370-7f31-48cf-9058-0d5d7e6f3685')): $__env->markAsRenderedOnce('8756f370-7f31-48cf-9058-0d5d7e6f3685'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('css/draw-workspace-navigation.css')); ?>?v=<?php echo e(filemtime(public_path('css/draw-workspace-navigation.css'))); ?>">
  <script src="<?php echo e(asset('js/draw-workspace-navigation.js')); ?>?v=<?php echo e(filemtime(public_path('js/draw-workspace-navigation.js'))); ?>" defer></script>
<?php endif; ?>

<?php echo $__env->make('backend.draw.partials.guide-modal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\wamp64\www\ct\resources\views/backend/draw/partials/workspace-header.blade.php ENDPATH**/ ?>