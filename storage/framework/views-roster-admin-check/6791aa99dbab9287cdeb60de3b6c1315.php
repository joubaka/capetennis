<?php($publishedDayLabels = app(\App\Services\Scheduling\SchedulePublicationService::class)->publicDrawDayLabels($event))

<div class="card mb-4">
  <div class="card-header">
    <small class="card-text text-uppercase">Draws and Order of Play</small>
  </div>

  <div class="card-body">

    {{-- ✅ Published Draws --}}
    <div class="mb-3">
      <h6 class="fw-bold">Published Draws</h6>

      @forelse($eventDraws->where('published', true)
          ->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other') as $typeName => $draws)

      

        <div class="d-flex flex-wrap gap-2">
          @foreach($draws as $draw)
            <a href="{{ route('public.roundrobin.show', $draw->id) }}"
               class="btn btn-sm btn-{{ $draw->draw_types?->btn_color ?? 'secondary' }}">
              {{ $draw->drawName }}
              <span style="white-space: normal; line-height: 1.4;" class="badge {{ $publishedDayLabels->has($draw->id) ? 'bg-label-light' : 'bg-label-secondary' }} ms-1">
                {{ $publishedDayLabels->has($draw->id) ? 'Times available · '.$publishedDayLabels->get($draw->id) : 'Times to follow' }}
              </span>
            </a>
          @endforeach
        </div>

      @empty
        <div class="alert alert-info m-0"><strong>Draws are being finalised.</strong> They will appear here when released; match times may follow later.</div>
      @endforelse
    </div>


    {{-- 🚧 Unpublished Draws (Admins only) --}}
    @php $isAdmin = $canPreviewUnpublishedDraws ?? (auth()->check() && $eventDraws->contains(fn($draw) => auth()->user()->can('view', $draw))); ?>

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventDraws->where('published', false)->count()): ?>
      <div class="mt-4">
        <h6 class="fw-bold text-danger">Unpublished Draws</h6>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventDraws->where('published', false)
            ->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $typeName => $draws): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

          <h6 class="mt-3"><?php echo e($typeName); ?></h6>

          <div class="d-flex flex-wrap gap-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isAdmin): ?>
                
                <a href="<?php echo e(route('frontend.fixtures.index', $draw->id)); ?>"
                   class="btn btn-sm btn-outline-<?php echo e($draw->draw_types?->btn_color ?? 'secondary'); ?>">
                  <?php echo e($draw->drawName); ?>

                  <span class="badge bg-danger ms-1">Not published</span>
                </a>
              <?php else: ?>
                
                <span class="btn btn-sm btn-light disabled">
                  <?php echo e($draw->drawName); ?>

                  <span class="badge bg-danger ms-1">Not published</span>
                </span>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>

        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
      <?php $isAdmin = auth()->check() && in_array(auth()->id(), [1764, 584, 585]); ?>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isAdmin && !empty($fixturesPerVenueGrouped)): ?>
        <div class="mt-4">
          <h6 class="fw-bold mb-2">Quick Links per Venue</h6>

          <div class="d-flex flex-column gap-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fixturesPerVenueGrouped; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venueName => $fixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php
                $venueId = optional($fixtures->first()->venue)->id;
                $firstDate = optional($fixtures->first()->scheduled_at)?->toDateString();
              ?>

              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($venueId && $firstDate): ?>
                <div class="d-flex flex-wrap gap-2">
                  <a href="<?php echo e(route('fixtures.venue', ['event_id' => $event->id, 'venue_id' => $venueId])); ?>"
                     class="btn btn-sm btn-outline-primary">
                    <?php echo e($venueName); ?> Fixtures
                  </a>

                  <a href="<?php echo e(route('fixtures.order', ['eventId' => $event->id, 'venueId' => $venueId, 'date' => $firstDate])); ?>"
                     class="btn btn-sm btn-outline-success">
                    Order of Play
                  </a>
                </div>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\individual-draw.blade.php ENDPATH**/ ?>