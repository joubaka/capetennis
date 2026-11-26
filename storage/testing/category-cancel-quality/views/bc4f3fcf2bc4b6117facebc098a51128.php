<?php
  $canViewNominationCounts = auth()->check()
      && (auth()->user()->hasRole('super-user') || auth()->user()->is_event_admin($event->id));
  $publishedDayLabels = app(\App\Services\Scheduling\SchedulePublicationService::class)->publicDrawDayLabels($event);
  $publicNominationKey = static fn ($nomination) => [
      mb_strtolower(trim($nomination->player?->name ?? $nomination->nominee_name ?? '')),
      mb_strtolower(trim($nomination->player?->surname ?? $nomination->nominee_surname ?? '')),
      (int) $nomination->id,
  ];
?>
<style>
    .cavaliers-registered-nominee {
        background-color: #eaf6ed !important;
        border-color: #15803d !important;
        color: #14532d !important;
    }
    .cavaliers-registered-nominee .cavaliers-registered-badge {
        background-color: #14532d;
        color: #fff;
    }
</style>
<div class="row">

    <div class="col-xl-8 col-lg-7 col-md-7 ">
        <!-- Activity Timeline -->

        <!--/ Activity Timeline -->
        <div class="row">
            <!-- Connections -->
            <div class="col-lg-12 col-xl-12 ">
                <?php echo $__env->make('frontend.event.partials.event-announcements', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php echo $__env->make('frontend.event.partials.event-information', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                <div class="card p-4 mt-4 ">
                    <h5 class="pb-1 mb-4">Nominations</h5>
                    <p class="small text-muted">Unregistered players first, then registered players. Each group is alphabetical by first name.</p>
                    <div class="row">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php
                            $nominations = $eventCategory->nominations->where('event_id', $event->id)
                                ->sort(static fn ($left, $right) => [
                                    isset($nomRegisteredLookup[$left->player_id.'-'.$eventCategory->id]) ? 1 : 0,
                                    ...$publicNominationKey($left),
                                ] <=> [
                                    isset($nomRegisteredLookup[$right->player_id.'-'.$eventCategory->id]) ? 1 : 0,
                                    ...$publicNominationKey($right),
                                ]);
                        ?>
                        <div class="col-md-6 col-sm-12">
                            <div class="card shadow-none bg-transparent border border-primary m-1 p-2">
                                <div class="card-header">
                                    <h3><?php echo e($eventCategory->category->name); ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventCategory->nominations_published == 1 && $canViewNominationCounts): ?>
                                        (<?php echo e($nominations->count()); ?>)
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </h3>
                                </div>
                                <ul class="list-group list-group-flush">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventCategory->nominations_published == 1): ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $nominations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $nomination): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php $nomineeRegistered = isset($nomRegisteredLookup[$nomination->player_id.'-'.$eventCategory->id]); ?>
                                    <li class="list-group-item<?php echo e($nomineeRegistered ? ' cavaliers-registered-nominee' : ''); ?>"><?php echo e($nomination->display_name); ?>

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($provenance = $nominationProvenance[$eventCategory->id][$nomination->id] ?? null): ?>
                                        <small class="badge rounded-pill bg-label-primary text-wrap ms-1">(<?php echo e($provenance['region']); ?> · <?php echo e($provenance['source']); ?>)</small>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($nomineeRegistered): ?>
                                        <span class="badge cavaliers-registered-badge ms-1">Registered</span>
                                    <?php elseif(($cavaliersRegistrationOpen ?? false) && $nomination->player_id && !$eventCategory->isLocked()): ?>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('register.register', ['id' => $event->id, 'nomination' => $nomination->id])); ?>">Register</a>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guest() || ($cavaliersCanManageNominations ?? false) || in_array((int) $nomination->player_id, $cavaliersDeclinePlayerIds ?? [], true)): ?>
                                                <a class="btn btn-sm btn-outline-danger" href="<?php echo e(route('cavaliers.nominations.decline', [$event, $eventCategory, $nomination->id])); ?>">Decline</a>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    </li>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    <?php else: ?>
                                    <p class="badge bg-label-warning">Not Published</p>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </ul>

                            </div>


                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    </div>

                </div>



            </div>
            <!--/ Connections -->
            <!-- Teams -->
            <div class="col-lg-12 col-xl-6">

            </div>
            <!--/ Teams -->
        </div>

    </div>
    <div class="col-xl-4 col-lg-5 col-md-5 ">
        <?php
        $wallet = 0;
        ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($wallet == 1): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($userRegistrations->count() > 0 ): ?>


        <div class="card shadow-none border border-success mb-3">
            <div class="card-header bg-label-success ">You have players entered in this event!</div>
            <div class="card-body mt-5">

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $userRegistrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $registration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <p><?php echo e($registration->registration->players[0]->name); ?> <?php echo e($registration->registration->players[0]->surname); ?> - <?php echo e($registration->categoryEvent->category->name); ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if( $signUp == 'open' && $event->eventType == 6): ?>
                    <span class="btn btn-label-danger border border-danger btn-sm withDrawPlayer " data-id="<?php echo e($registration->id); ?>">

                        Withdraw player

                    </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                </p>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>


        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php else: ?>

        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php
  $formatWithdrawalDeadline = optional($event->withdrawal_deadline)->format('d M Y');
?>

        <?php echo $__env->make('frontend.event.partials.event-about', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php echo $__env->make('frontend.event.partials.event-documents', ['documentNewWindow' => false], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <!--        <div class="card mb-4">
            <div class="card-header"> <small class="card-text text-uppercase">Photos</small>


            </div>
            <div class="card-body">

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($event->sell->id)): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->sell->type == 'Photos'): ?>

                <a class="btn btn-secondary btn-sm" href="<?php echo e(route('frontend.event.photos',$event->id)); ?>">Photos</a>

                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            </div>
        </div> -->

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->results_published == 1): ?>

        <div class="card mb-4">
            <div class="card-header"> <small class="card-text text-uppercase">Results</small></div>
            <div class="card-body">

                <a href="<?php echo e(route('result.show',$event->id)); ?>" class="btn bg-label-success btn-sm">Results here</a>

            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div id="event-draws-match-times" class="card mb-4" tabindex="-1" data-event-draw-section aria-label="Published draws">
            <div class="card-header"> <small class="card-text text-uppercase">Draws</small></div>
            <div class="card-body">
           
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventDraws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="col m-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->published == 1): ?>
                    <a href="<?php echo e(route('frontend.showDraw',$draw->id)); ?>" class="btn btn-sm btn-success">
                      <?php echo e($draw->drawName); ?>

                      <span style="white-space: normal; line-height: 1.4;" class="badge <?php echo e($publishedDayLabels->has($draw->id) ? 'bg-label-light' : 'bg-label-secondary'); ?> ms-1">
                        <?php echo e($publishedDayLabels->has($draw->id) ? 'Times available · '.$publishedDayLabels->get($draw->id) : 'Times to follow'); ?>

                      </span>
                    </a>
                    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('view', $draw)): ?>
                    <a href="<?php echo e(route('frontend.bracket.fixtures',$draw->id)); ?>" class="btn btn-sm btn-primary">Fixtures</a>
                    <?php endif; ?>
                    <?php else: ?>
                    <div class="badge bg-danger"><?php echo e($draw->drawName); ?> - Not published</div>

                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


            </div>
        </div>
        <div class="card mb-4">
            <div class="card-body">
                <small class="card-text text-uppercase">Players</small><br>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventcategories): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="card p-2 mb-2">

                    <?php
                      $paidRegs = $eventcategories->categoryEventRegistrations->where('payment_status_id', 1)->where('status', '!=', 'withdrawn');
                    ?>
                    <h3 class="badge bg-label-primary"><?php echo e($eventcategories->category->name); ?> (<?php echo e($paidRegs->count()); ?>) </h3>
                    <div class="list-group list-group-flush">

                        <div class="demo-inline-spacing ">
                            <div class="list-group list-group-flush">

                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $paidRegs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cereg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php $registration = $cereg->registration; ?>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registration && $registration->players->count()): ?>

                                <a href="javascript:void(0);" class="list-group-item list-group-item-action"> <?php echo e($registration->players[0]->name); ?> <?php echo e($registration->players[0]->surname); ?>



                                </a>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </div>
                        </div>


                    </div>





                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
        </div>
    </div>

</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\eventTypes\cavaliers_trials.blade.php ENDPATH**/ ?>