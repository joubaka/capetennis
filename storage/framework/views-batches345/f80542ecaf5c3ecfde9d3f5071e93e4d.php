<?php
  $publishedDayLabels = app(\App\Services\Scheduling\SchedulePublicationService::class)->publicDrawDayLabels($event);
?>
 
      <div class="d-none d-md-block">

        <div class="card mb-4">
          <div class="card-header">
            <small class="card-text text-uppercase">Draws and Order of Play</small>
          </div>

          <div class="card-body">

            <?php
              $deskUser = auth()->user();
              $canScoreEvent = $canScoreEvent ?? ($deskUser && $deskUser->can('event.score', $event));
              $canViewUnpublished = $deskUser && (
                (method_exists($deskUser, 'isConvenorForEvent') && $deskUser->isConvenorForEvent($event->id)) ||
                (method_exists($deskUser, 'is_convenor') && $deskUser->is_convenor($event->id)) ||
                (method_exists($deskUser, 'hasRole') && ($deskUser->hasRole('admin') || $deskUser->hasRole('super-user')))
              );
            ?>

            
            <div class="mb-3">
              <h6 class="fw-bold">Published draws</h6>

              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $eventDraws->where('published', true)
                  ->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $typeName => $draws): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>

                <div class="d-flex flex-wrap gap-2 mt-1">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="d-flex align-items-center gap-1">
                      <a href="<?php echo e(route('public.roundrobin.show', $draw->id)); ?>"
                         class="btn btn-sm btn-<?php echo e($draw->draw_types?->btn_color ?? 'secondary'); ?>">
                        <?php echo e($draw->drawName); ?>

                        <span style="white-space: normal; line-height: 1.4;" class="badge <?php echo e($publishedDayLabels->has($draw->id) ? 'bg-label-light' : 'bg-label-secondary'); ?> ms-1">
                          <?php echo e($publishedDayLabels->has($draw->id) ? 'Times available · '.$publishedDayLabels->get($draw->id) : 'Times to follow'); ?>

                        </span>
                      </a>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canScoreEvent): ?>
                        <a href="<?php echo e(route('frontend.scoring.workspace', ['event' => $event, 'draw' => $draw->id])); ?>"
                           class="btn btn-sm btn-light border"
                           title="Score <?php echo e($draw->drawName); ?>">
                          <i class="ti ti-scoreboard me-1" aria-hidden="true"></i>Score
                          <span class="visually-hidden"> <?php echo e($draw->drawName); ?></span>
                        </a>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>

              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <div class="alert alert-info m-0"><strong>Draws are being finalised.</strong> Match times may be published separately.</div>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewUnpublished): ?>
              <?php echo $__env->make('frontend.event.partials.interpro-admin-drawlist', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewUnpublished && $eventDraws->where('published', false)->count()): ?>
              <div class="mt-4">
                <h6 class="fw-bold text-danger">Unpublished Draws</h6>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventDraws->where('published', false)
                    ->groupBy(fn($d) => $d->draw_types?->drawTypeName ?? 'Other'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $typeName => $draws): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                  <h6 class="mt-3"><?php echo e($typeName); ?></h6>

                  <div class="d-flex flex-wrap gap-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <a href="<?php echo e(route('public.roundrobin.show', $draw->id)); ?>"
                         class="btn btn-sm btn-outline-<?php echo e($draw->draw_types?->btn_color ?? 'secondary'); ?>">
                        <?php echo e($draw->drawName); ?>

                        <span class="badge bg-danger ms-1">Unpublished</span>
                      </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div>

                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

              </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewUnpublished && !empty($fixturesPerVenueGrouped)): ?>
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
                        <a href="<?php echo e(route('fixtures.venue', [
                            'event_id' => $event->id,
                            'venue_id' => $venueId
                        ])); ?>"
                           class="btn btn-sm btn-outline-primary">
                          <?php echo e($venueName); ?> Fixtures
                        </a>

                        <a href="<?php echo e(route('fixtures.order', [
                            'eventId' => $event->id,
                            'venueId' => $venueId,
                            'date'    => $firstDate
                        ])); ?>"
                           class="btn btn-sm btn-outline-success">
                          Order of Play
                        </a>
                      </div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                </div>

              </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          </div>
        </div>

      </div>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\interpro-draws-desktop.blade.php ENDPATH**/ ?>