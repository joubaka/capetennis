<div class="row">

    <div class="col-xl-8 col-lg-7 col-md-7 ">
        <!-- Activity Timeline -->

        <!--/ Activity Timeline -->
        <div class="row">
            <!-- Connections -->
            <div class="col-lg-12 col-xl-12 ">
                <?php echo $__env->make('frontend.event.partials.event-information', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php echo $__env->make('frontend.event.partials.event-announcements', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>





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
        <?php echo $__env->make('frontend.event.partials.event-about', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <div class="card mb-4">
            <div class="card-header"> <small class="card-text text-uppercase">Documents</small>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->guest()): ?>

                <?php else: ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->is_admin($event->id)->count() > 0 || Auth::user()->id == 584 ): ?>
                <div class="btn btn-success btn-sm float-right" data-bs-target='#addFileModal' data-bs-toggle='modal'>Upload .PDF</div>
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
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::user()->id == $event->admin): ?>
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


        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(is_null($event->series)): ?>
        <?php else: ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->series->leaderboard_publishied == 1): ?>
        <div class="card mb-4">
            <div class="card-header"> <small class="card-text text-uppercase">Series</small></div>
            <div class="card-body">

                <a href="<?php echo e(route('frontend.ranking.show',$event->series->id)); ?>" class="btn bg-label-success btn-sm"><?php echo e($event->series->name); ?> Ranking list</a>

            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->results_published == 1): ?>

        <div class="card mb-4">
            <div class="card-header"> <small class="card-text text-uppercase">Results</small></div>
            <div class="card-body">

                <a href="<?php echo e(route('result.show',$event->id)); ?>" class="btn bg-label-success btn-sm">Results here</a>

            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <div class="card mb-4">
            <div class="card-header"> <small class="card-text text-uppercase">Meals</small></div>
            <div class="card-body">


                <ul class="list-group">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $user->orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <?php echo e($item->product->product_name); ?> 
                        <span class="badge bg-primary"><?php echo e($item->nrOf); ?></span>
                    </li>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php else: ?>
                    <div class="badge bg-label-warning">
                    Log in to see orders!</div>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </ul>




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

                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registration->order_item): ?>

                                    <?php echo e($event->eventType->id == 9 ?  '+'. $registration->order_item->parent:''); ?>


                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

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
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\eventTypes\parentChildDoubles.blade.php ENDPATH**/ ?>