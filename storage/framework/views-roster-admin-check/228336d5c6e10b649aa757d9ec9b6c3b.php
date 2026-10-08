<?php
  $publishedDayLabels = app(\App\Services\Scheduling\SchedulePublicationService::class)->publicDrawDayLabels($event);
?>
<?php

use App\Models\EventNomination;
use App\Helpers\Fixtures;

$nominations = EventNomination::all();
?>
<div class="row">

    <div class="col-xl-8 col-lg-7 col-md-7 ">
        <!-- Activity Timeline -->

        <!--/ Activity Timeline -->
        <div class="row">
            <!-- Connections -->
            <div class="col-lg-12 col-xl-12 ">
                <?php echo $__env->make('frontend.event.partials.event-information', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php echo $__env->make('frontend.event.partials.event-announcements', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                <div class="card p-4 mt-4 ">
                    <h5 class="pb-1 mb-4">Nominations</h5>
                    <div class="row">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="col-md-6 col-sm-12">
                            <div class="card shadow-none bg-transparent border border-primary m-1 p-2">
                                <div class="card-header">
                                    <h3><?php echo e($eventCategory->category->name); ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventCategory->nominations_published == 1): ?>
                                        (<?php echo e($eventCategory->nominations->count()); ?>)
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </h3>
                                </div>
                                <ul class="list-group list-group-flush">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eventCategory->nominations_published == 1): ?>
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventCategory->nominations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $nomination): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                    <li class="list-group-item"><?php echo e($nomination->player->getFullNameAttribute()); ?>



                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($nomRegisteredLookup[$nomination->player->id])): ?>

                                        <span class=""><i class="text-success fa-solid fa-circle-check"></i></span>
                                      <?php else: ?>
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
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <small class="card-text text-uppercase">Documents</small>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->is_admin($event->id)->count() > 0 || Auth::user()->id == 584): ?>
                <form action="<?php echo e(route('file.store')); ?>" method="POST" enctype="multipart/form-data" class="mb-0">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="event_id" value="<?php echo e($event->id); ?>">
                    <label class="btn btn-success btn-sm mb-0">
                        Upload .PDF
                        <input type="file" name="myFile" class="d-none"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.csv"
                               onchange="this.form.submit()">
                    </label>
                </form>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            <div class="card-body">

                <div class="demo-inline-spacing mt-3">
                    <div class="list-group">

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->files; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=> $file): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>



                        <div class="file">
                            <div class="row">

                                <div class="col-7">
                                    <a href="<?php echo e(route('events.documents.show', [$event, $file])); ?>" class="list-group-item list-group-item-action d-flex justify-content-between">
                                        <div class="li-wrapper d-flex justify-content-start align-items-center">
                                            <div class="avatar avatar-sm me-3">
                                                <span class="avatar-initial rounded-circle bg-label-success"><?php echo e(($key+1)); ?></span>
                                            </div>
                                            <div class="list-content">
                                                <h6 class="mb-1"><?php echo e($file->name); ?></h6>

                                            </div>
                                        </div>


                                    </a>
                                </div>
                                <div class="col-4">
                                    <small>
                                        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('admin')): ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->id == $event->admin || Auth::user()->id == 584): ?>
                                        <div data-id="<?php echo e($file->id); ?>" class="btn btn-danger btn-sm deleteFileButton ml-4">Delete</div>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>


                        </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>





                    </div>
                </div>
            </div>
        </div>

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
        <div class="card mb-4">
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
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\eventTypes\cavaliers_trials.blade.php ENDPATH**/ ?>