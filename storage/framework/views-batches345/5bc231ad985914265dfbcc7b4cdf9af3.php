
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($drawPublicationSummary['total'] ?? 0) > 0): ?>
  <div id="event-draws-match-times" class="card event-draws-card mb-4" tabindex="-1">
    <div class="card-header event-draws-header">
      <span class="event-draws-header-icon" aria-hidden="true"><i class="ti ti-tournament"></i></span>
      <div>
        <h5 class="mb-1">Draws and match times</h5>
        <p class="text-muted small mb-0">Open a draw to see the bracket, venue and published match times.</p>
      </div>
    </div>
    <div class="card-body">
      <?php echo $__env->make('frontend.event.partials._venue-scoring', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventDraws->isEmpty()): ?>
        <div class="alert alert-info mb-0" role="status">
          <div class="fw-semibold">The draws are being finalised.</div>
          <div class="small">They will appear here as soon as the organiser publishes them. Match times and venues may follow later.</div>
        </div>
      <?php else: ?>
        <div class="event-draw-grid">
          <?php
            $sortedDraws = $eventDraws->sortBy([
              ['published', 'desc'],
              [fn($d) => $d->draw_types?->ageCategory ?? $d->drawName ?? '', 'asc'],
              ['drawName', 'asc'],
            ]);
            $canScoreEvent = $canScoreEvent ?? (auth()->check() && auth()->user()->can('event.score', $event));
          ?>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $sortedDraws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
              $firstSchedule = $draw->order_of_play->whereNotNull('time')->sortBy('time')->first();
              $drawVenueNames = $draw->venues->pluck('name')->filter()->unique()->values();
              $publicDrawUrl = $draw->usesFlexibleMonrad()
                ? route('public.flexible-monrad.show', $draw)
                : route('public.roundrobin.show', $draw);
              $canViewDraw = isset($canViewDrawById) ? $canViewDrawById->get($draw->id, false) : (auth()->check() && auth()->user()->can('view', $draw));
            ?>

            <article class="event-draw-card">
              <div>
                <div class="event-draw-card-heading">
                  <div class="event-draw-name"><?php echo e($draw->drawName ?? 'Draw #'.$draw->id); ?></div>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->published): ?>
                    <span class="event-draw-state"><?php echo e($draw->scheduleIsPublished() ? 'Draw & times live' : 'Draw live'); ?></span>
                  <?php elseif($canViewDraw): ?>
                    <span class="event-draw-state">Draft</span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <div class="event-draw-meta">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($drawVenueNames->isNotEmpty()): ?>
                    <span><i class="ti ti-map-pin" aria-hidden="true"></i><span><?php echo e($drawVenueNames->join(', ')); ?></span></span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->scheduleIsPublished() && $firstSchedule?->time): ?>
                    <span><i class="ti ti-clock" aria-hidden="true"></i><span>First match <?php echo e(\Carbon\Carbon::parse($firstSchedule->time)->format('H:i')); ?></span></span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
              </div>

              <div class="event-draw-actions">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->published): ?>
                  <a href="<?php echo e($publicDrawUrl); ?>#draw" class="btn btn-sm btn-outline-primary">
                    <i class="ti ti-tournament me-1" aria-hidden="true"></i>View draw
                  </a>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->scheduleIsPublished()): ?>
                    <a href="<?php echo e($publicDrawUrl); ?>#schedule" class="btn btn-sm btn-success">
                      <i class="ti ti-clock me-1" aria-hidden="true"></i>View schedule
                    </a>
                  <?php else: ?>
                    <span class="badge bg-label-secondary align-self-center">Times to follow</span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canScoreEvent): ?>
                    <a href="<?php echo e(route('frontend.scoring.workspace', ['event' => $event, 'draw' => $draw->id])); ?>"
                       class="btn btn-sm btn-light border event-draw-score"
                       title="Score <?php echo e($draw->drawName); ?>">
                      <span>Score</span>
                      <span class="visually-hidden"> <?php echo e($draw->drawName); ?></span>
                    </a>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php elseif($canViewDraw): ?>
                  <a href="<?php echo e($publicDrawUrl); ?>#draw" class="btn btn-sm btn-outline-secondary">
                    <i class="ti ti-tournament me-1" aria-hidden="true"></i>Preview draw
                  </a>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>
            </article>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\event-draws.blade.php ENDPATH**/ ?>