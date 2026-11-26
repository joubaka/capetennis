<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.score', $event)): ?>
  <?php if (! $__env->hasRenderedOnce('5acd4fbf-901d-44d5-9d92-2b925dbd6a79')): $__env->markAsRenderedOnce('5acd4fbf-901d-44d5-9d92-2b925dbd6a79'); ?>
    <style>
      .event-venue-scoring { padding: 16px; border: 0; border-radius: 8px; background: #f2f6fb; color: #173f7a; }
      .event-venue-scoring-intro { margin-bottom: 12px; }
      .event-venue-scoring-intro p { color: #52647a; line-height: 1.5; }
      .event-venue-scoring-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 10px; }
      .event-venue-scoring-link.btn { display: flex; align-items: center; gap: 12px; min-width: 0; min-height: 48px; padding: 12px; border: 1px solid transparent; border-radius: 6px; background: #fff; color: #173f7a; text-align: left; white-space: normal; font-weight: 600; line-height: 1.5; }
      .event-venue-scoring-name { flex: 1; min-width: 0; overflow-wrap: anywhere; }
      .event-venue-scoring-link > .ti { flex-shrink: 0; font-size: 1.125rem; }
      .event-venue-scoring-link > .event-venue-scoring-arrow { font-size: 1rem; }
      .event-venue-scoring-link.btn:hover, .event-venue-scoring-link.btn:focus-visible { border-color: #173f7a; background: #173f7a; color: #fff; }
      .event-venue-scoring-link.btn:focus-visible { outline: 3px solid #6b9cd3; outline-offset: 3px; }
    </style>
  <?php endif; ?>
  <section class="event-venue-scoring mb-4" aria-label="Score fixtures by venue">
      <div class="event-venue-scoring-intro">
        <h6 class="mb-1 fw-bold">
          <i class="ti ti-clipboard-check me-1" aria-hidden="true"></i> Score fixtures by venue
        </h6>
        <p class="small mb-0">Choose a venue to enter or update match scores.</p>
      </div>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($scoringVenues ?? collect())->isNotEmpty()): ?>
        <div class="event-venue-scoring-list">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $scoringVenues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $scoringVenue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route('frontend.scoring.workspace', ['event' => $event, 'schedule_source' => 'working', 'venue' => $scoringVenue->id])); ?>"
               class="btn event-venue-scoring-link">
              <i class="ti ti-map-pin" aria-hidden="true"></i>
              <span class="event-venue-scoring-name"><?php echo e($scoringVenue->name); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($scoringVenue->today_fixture_count ?? 0) > 0): ?>
                  <span class="d-block small fw-normal mt-1"><?php echo e(\Carbon\Carbon::parse($scoringVenue->scoring_date)->format('l')); ?>: <?php echo e($scoringVenue->today_scored_count); ?> of <?php echo e($scoringVenue->today_fixture_count); ?> scored</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <span class="d-block small fw-normal"><?php echo e($scoringVenue->fixture_count); ?> scheduled total</span>
              </span>
              <i class="ti ti-chevron-right event-venue-scoring-arrow" aria-hidden="true"></i>
            </a>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      <?php else: ?>
        <a href="<?php echo e(route('frontend.scoring.workspace', $event)); ?>" class="btn event-venue-scoring-link">
          Open scoring workspace
        </a>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </section>
<?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\partials\_venue-scoring.blade.php ENDPATH**/ ?>