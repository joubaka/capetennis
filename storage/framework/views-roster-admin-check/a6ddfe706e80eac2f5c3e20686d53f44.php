<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event.score', $event)): ?>
  <?php if (! $__env->hasRenderedOnce('1b772202-8e19-4b18-9d46-ac1d49a9405f')): $__env->markAsRenderedOnce('1b772202-8e19-4b18-9d46-ac1d49a9405f'); ?>
    <style>
      .event-venue-scoring { padding: 16px; border: 1px solid #d5e1ef; border-radius: 10px; background: #f5f8fc; color: #173f7a; }
      .event-venue-scoring-intro { margin-bottom: 14px; }
      .event-venue-scoring-intro p { color: #52647a; line-height: 1.5; }
      .event-venue-scoring-list { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 250px), 1fr)); gap: 10px; }
      .event-venue-scoring-link.btn { display: flex; align-items: center; gap: 10px; min-width: 0; min-height: 48px; padding: 12px; border: 1px solid #b9cde5; border-radius: 8px; background: #fff; color: #173f7a; text-align: left; white-space: normal; font-weight: 600; line-height: 1.4; }
      .event-venue-scoring-name { flex: 1; min-width: 0; overflow-wrap: anywhere; }
      .event-venue-scoring-link > .ti { flex-shrink: 0; font-size: 1.125rem; }
      .event-venue-scoring-count { flex-shrink: 0; min-width: 32px; padding: 5px 8px; border-radius: 5px; background: #e8eff8; color: #173f7a; text-align: center; font-size: .8125rem; }
      .event-venue-scoring-link.btn:hover, .event-venue-scoring-link.btn:focus-visible { border-color: #173f7a; background: #173f7a; color: #fff; }
      .event-venue-scoring-link.btn:focus-visible { outline: 3px solid #6b9cd3; outline-offset: 3px; }
    </style>
  <?php endif; ?>
  <section class="event-venue-scoring mb-4" aria-label="Score fixtures by venue">
      <div class="event-venue-scoring-intro">
        <h6 class="mb-1 fw-bold">
          <i class="ti ti-scoreboard me-1" aria-hidden="true"></i> Score fixtures by venue
        </h6>
        <p class="small mb-0">Choose a venue to see its fixture queue and enter or correct scores.</p>
      </div>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($scoringVenues ?? collect())->isNotEmpty()): ?>
        <div class="event-venue-scoring-list">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $scoringVenues; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $scoringVenue): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <a href="<?php echo e(route('frontend.scoring.workspace', ['event' => $event, 'schedule_source' => 'published', 'venue' => $scoringVenue->id])); ?>"
               class="btn event-venue-scoring-link">
              <i class="ti ti-map-pin" aria-hidden="true"></i>
              <span class="event-venue-scoring-name"><?php echo e($scoringVenue->name); ?></span>
              <span class="event-venue-scoring-count"><?php echo e($scoringVenue->fixture_count); ?><span class="visually-hidden"> fixtures</span></span>
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
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\_venue-scoring.blade.php ENDPATH**/ ?>