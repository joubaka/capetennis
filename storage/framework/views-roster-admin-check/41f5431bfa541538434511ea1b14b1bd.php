<?php
  $publishedDayLabels = app(\App\Services\Scheduling\SchedulePublicationService::class)->publicDrawDayLabels($event);
?>
      
      <div class="card d-block d-md-none mb-4">
        <div class="card-body">

          <?php
            $mobileUser = auth()->user();
            $canViewUnpublished = $mobileUser && (
              (method_exists($mobileUser, 'isConvenorForEvent') && $mobileUser->isConvenorForEvent($event->id)) ||
              (method_exists($mobileUser, 'is_convenor') && $mobileUser->is_convenor($event->id)) ||
              (method_exists($mobileUser, 'hasRole') && ($mobileUser->hasRole('admin') || $mobileUser->hasRole('super-user')))
            );
          ?>

          
          <h6 class="fw-bold mb-2">Published draws</h6>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $eventDraws->where('published', true)
              ->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $typeName => $draws): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>

            <div class="d-flex flex-column gap-2 mt-1">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="<?php echo e(route('public.roundrobin.show', $draw->id)); ?>"
                   class="btn btn-sm btn-<?php echo e($draw->draw_types?->btn_color ?? 'primary'); ?> w-100">
                  <?php echo e($draw->drawName); ?>

                  <span style="white-space: normal; line-height: 1.4;" class="badge <?php echo e($publishedDayLabels->has($draw->id) ? 'bg-label-light' : 'bg-label-secondary'); ?> ms-1">
                    <?php echo e($publishedDayLabels->has($draw->id) ? 'Times available · '.$publishedDayLabels->get($draw->id) : 'Times to follow'); ?>

                  </span>
                </a>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="alert alert-info"><strong>Draws are being finalised.</strong> Match times may be published separately.</div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewUnpublished): ?>
            <?php echo $__env->make('frontend.event.partials.interpro-admin-drawlist', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewUnpublished && $eventDraws->where('published', false)->count()): ?>
            <h6 class="fw-bold text-danger mt-4">Unpublished Draws</h6>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventDraws->where('published', false)
                ->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $typeName => $draws): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

              <div class="fw-bold mt-2"><?php echo e($typeName); ?></div>

              <div class="d-flex flex-column gap-2 mt-1">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <a href="<?php echo e(route('public.roundrobin.show', $draw->id)); ?>"
                     class="btn btn-sm btn-outline-<?php echo e($draw->draw_types?->btn_color ?? 'secondary'); ?> w-100">
                    <?php echo e($draw->drawName); ?>

                    <span class="badge bg-danger ms-1">Not published</span>
                  </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </div>

            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewUnpublished && !empty($fixturesPerVenueGrouped)): ?>
            <h6 class="fw-bold mt-4 mb-2">Quick Links per Venue</h6>

            <div class="d-flex flex-column gap-2">

              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $fixturesPerVenueGrouped; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $venueName => $fixtures): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                  $venueId   = optional($fixtures->first()->venue)->id;
                  $firstDate = optional($fixtures->first()->scheduled_at)?->toDateString();
                ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($venueId && $firstDate): ?>

                  <a href="<?php echo e(route('fixtures.venue', [
                      'event_id' => $event->id,
                      'venue_id' => $venueId
                  ])); ?>"
                     class="btn btn-sm btn-outline-primary w-100">
                    <?php echo e($venueName); ?> Fixtures
                  </a>

                  <a href="<?php echo e(route('fixtures.order', [
                      'eventId' => $event->id,
                      'venueId' => $venueId,
                      'date'    => $firstDate
                  ])); ?>"
                     class="btn btn-sm btn-outline-success w-100">
                    Order of Play – <?php echo e($venueName); ?>

                  </a>

                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </div>
      </div>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\interpro-draws-mobile.blade.php ENDPATH**/ ?>