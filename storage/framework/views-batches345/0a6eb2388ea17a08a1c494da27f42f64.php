<div class="card ">



    <div class="row mt-4">
        <div class="card-body m-4 ">
            <!-- Navigation -->
            <div class="col-lg-12 col-md-12 col-12 mb-md-0 mb-3">
                <div class="d-flex justify-content-between flex-column mb-2 mb-md-0">
                    <div class="row">

                        <ul class="nav nav-pills mb-3">
                            <li class="nav-item">
                                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#payment">
                                    <i class="ti ti-credit-card me-1 ti-sm"></i>
                                    <span class="align-middle fw-semibold">Entries</span>
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link " data-bs-toggle="tab" data-bs-target="#results">
                                    <i class="ti ti-credit-card me-1 ti-sm"></i>
                                    <span class="align-middle fw-semibold">Results</span>
                                </button>
                            </li>


                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('super-user')): ?>
                            <li class="nav-item">
                                <button class="nav-link " data-bs-toggle="tab" data-bs-target="#transactions">
                                    <i class="ti ti-credit-card me-1 ti-sm"></i>
                                    <span class="align-middle fw-semibold">Transactions</span>
                                </button>
                            </li>

                            <?php endif; ?>

                            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('super-user')): ?>
                            <li class="nav-item">
                                <button class="nav-link " data-bs-toggle="tab" data-bs-target="#draws">
                                    <i class="ti ti-credit-card me-1 ti-sm"></i>
                                    <span class="align-middle fw-semibold">Draws</span>
                                </button>
                            </li>

                            <?php endif; ?>
                        </ul>

                        <div class="d-none d-md-block">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /Navigation -->

        <!-- FAQ's -->
        <div class="col-lg-112 col-md-12 col-12">
            <div class="tab-content py-0">
                <div class="tab-pane fade show active" id="payment" role="tabpanel">
                    <div class="d-flex mb-3 gap-3">

                        <div>
                            <h4 class="mb-0 mt-4">
                                <span class="align-middle">Entries</span>
                            </h4>
                            <small>Player entered in <?php echo e($event->name); ?></small>
                        </div>
                    </div>

                    <div class="col-md-12 col-lg-12 col-xl-12 mb-4">
                        <div class=" h-100  shadow-none bg-transparent ">
                            <div class=" d-flex justify-content-between pb-2 mb-1">

                                <div class="dropdown">
                                    <button class="btn p-0" type="button" id="salesByCountryTabs" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <span class="btn btn-primary btn-sm"> <i class="ti ti-dots-vertical ti-sm text-white"></i> Actions</span>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="salesByCountryTabs">
                                        <a class="dropdown-item createEmailButton" href="javascript:void(0);" data-bs-target="#createEmail" data-bs-toggle="modal" data-totype="event" onclick="changeRecipants('event','<?php echo e($event->id); ?>')">Send e-mail to all players in event</a>
                                        <a class="dropdown-item" href="<?php echo e(route('export.registrations',$event->id)); ?>" data-event="<?php echo e($event->id); ?>">Export entry list</a>

                                    </div>
                                </div>
                            </div>
                            <div class="shadow-none bg-transparent">
                                <div class="nav-align-top">
                                    <ul class="nav nav-tabs nav-fill" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-new" aria-controls="navs-justified-new" aria-selected="true">Confirmed</button>
                                        </li>
                                        <li class=" nav-item" role="presentation">
                                            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-link-preparing" aria-controls="navs-justified-link-preparing" aria-selected="false" tabindex="-1">Withdrawals</button>
                                        </li>

                                    </ul>
                                    <div class="tab-content pb-0">
                                        <div class="tab-pane fade active show" id="navs-justified-new" role="tabpanel">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $categories): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                            <div class="card shadow-none bg-transparent border border-primary mb-5 ">
                                                <div class="card-header">
                                                    <h3><?php echo e($categories->category->name); ?></h3><button type="button" data-bs-target="#addPlayerToCategory" data-bs-toggle="modal" data-categoryeventid="<?php echo e($categories->id); ?>" class="btn btn-success btn-sm addPlayerC">Add Player</button>
                                                </div>

                                                <div class="table-responsive text-nowrap mb-4">
                                                    <table class="table table-bordered">
                                                        <thead>
                                                            <tr>
                                                                <th>#</th>
                                                                <th>Name</th>
                                                                <th>Email</th>
                                                                <th>Contact</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categories->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $registration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                                                            <tr>
                                                                <td> <strong><?php echo e($k+1); ?></strong></td>
                                                                <td><?php echo e($registration->players[0]->name); ?> <?php echo e($registration->players[0]->surname); ?></td>
                                                                <td>
                                                                    <?php echo e($registration->players[0]->email); ?>

                                                                </td>
                                                                <td><span class="badge bg-label-primary me-1"><?php echo e($registration->players[0]->cellNr); ?></span></td>
                                                                <td>
                                                                    <span class="btn btn-sm btn-secondary sendEmail" data-bs-target="#createEmail" data-bs-toggle="modal" data-email=" <?php echo e($registration->players[0]->email); ?>" data-totype="one"><i class="ti ti-pencil me-1"></i>Email Player</span>
                                                                    <span class="btn btn-sm btn-danger withdrawButton" data-categoryEventId="<?php echo e($categories->id); ?>" data-player="<?php echo e($registration->players[0]->name); ?> <?php echo e($registration->players[0]->surname); ?>" data-registrationId="<?php echo e($registration->id); ?>"><i class="ti ti-trash me-1"></i>Withdraw</span>

                                                                </td>
                                                            </tr>
                                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>



                                            </div>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                        </div>

                                        <div class="tab-pane fade " id="navs-justified-link-preparing" role="tabpanel">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $categories): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <div class="card shadow-none bg-transparent border border-primary mb-5 ">
                                                <div class="card-header">
                                                    <h3><?php echo e($categories->category->name); ?></h3>
                                                </div>

                                                <div class="table-responsive text-nowrap mb-4">
                                                    <table class="table table-bordered">
                                                        <thead>
                                                            <tr>
                                                                <th>#</th>
                                                                <th>Name</th>
                                                                <th>Email</th>
                                                                <th>Contact</th>
                                                                <th>Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categories->withdrawals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $withdrawal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                            <tr>
                                                                <td> <strong><?php echo e($k+1); ?></strong></td>
                                                                <td><?php echo e($withdrawal->registration->players[0]->name); ?> <?php echo e($withdrawal->registration->players[0]->surname); ?></td>
                                                                <td> <?php echo e($withdrawal->registration->players[0]->email); ?>

                                                                </td>
                                                                <td><span class="badge bg-label-primary me-1"><?php echo e($withdrawal->registration->players[0]->cellNr); ?> </span></td>
                                                                <td>
                                                                    <span class="btn btn-sm btn-secondary sendEmail" data-bs-target="#createEmail" data-bs-toggle="modal" data-email=" <?php echo e($withdrawal->registration->players[0]->email); ?>" data-totype="one"><i class="ti ti-pencil me-1"></i>Email Player</span>


                                                                </td>
                                                            </tr>
                                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>



                                            </div>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                </div>
                <div class="tab-pane fade show " id="results" role="tabpanel">
                    <div class=" mb-3 gap-3">

                        <div>

                            <span id="publishResults" data-event_id="<?php echo e($event->id); ?>" class="mb-2 align-middle btn btn-<?php echo e($event->results_published == 1 ? 'danger':'success'); ?> btn-sm"><?php echo e($event->results_published == 1 ? 'Unpublish Results':'Publish Results'); ?></span>


                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->series): ?>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->series->rankType->type == 'position' || $event->series->rankType->type == 'overberg'): ?>
                            <?php echo $__env->make('backend.adminPage._includes.position_type', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                            <?php else: ?>
                            <?php echo $__env->make('backend.adminPage._includes.participation_type', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <?php elseif($event->eventType == 7): ?>


                            <?php else: ?>
                            <?php echo $__env->make('backend.adminPage._includes.position_type', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </div>
                    </div>



                </div>
                <div class="tab-pane fade show " id="transactions" role="tabpanel">

                    <div class=" mb-3 gap-3">
                        <table id="transactionTable" class="table">
                            <thead>
                                <th>Nr</th>
                                <th>Date</th>
                                <th>Type</th>
                                <th>User</th>
                                <th>Items</th>


                                <th>Gross</th>
                                <th>Payfast Fee</th>
                                <th>Cape Tennis Fee</th>
                                <th>Nett</th>
                            </thead>
                            <tfoot>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                    <td>Totals</td>
                                    <td>Totals</td>
                                    <td>Totals</td>
                                </tr>
                            </tfoot>
                            <tbody>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr>
                                    <td><?php echo e($transaction->pf_payment_id ? $transaction->pf_payment_id:''); ?></td>
                                    <td><?php echo e($transaction->created_at->format('j F, Y')); ?></td>
                                    <td><?php echo e($transaction->transaction_type); ?></td>
                                    <td> <?php echo e($transaction->user->name); ?></td>
                                    <td>
                                        <ul>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transaction->order): ?>
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $transaction->order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $value): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>


                                            <li class="d-flex mb-3 pb-1 align-items-center border p-2 ">

                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                    <div class="me-2">
                                                        <h6 class="mb-0"><?php echo e($value->player->name); ?> <?php echo e($value->player->surname); ?></h6>
                                                        <small class="text-muted d-block"><?php echo e($value->category_event->category->name); ?></small>
                                                    </div>
                                                    <div class="user-progress d-flex align-items-center gap-1">
                                                        <h6 class="mb-0 text-secondary">R<?php echo e($value->item_price); ?></h6>
                                                    </div>
                                                </div>
                                            </li>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            <?php else: ?>
                                            <li class="d-flex mb-3 pb-1 align-items-center border p-2">

                                                <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
                                                    <div class="me-2">
                                                        <h6 class="mb-0"><?php echo e($transaction->custom_str2); ?> </h6>
                                                        <small class="text-muted d-block"><?php echo e($transaction->category_event->category->name); ?></small>
                                                    </div>
                                                    <div class="user-progress d-flex align-items-center gap-1">
                                                        <h6 class="mb-0 text-secondary">R<?php echo e($transaction->amount_gross); ?></h6>
                                                    </div>
                                                </div>
                                            </li>
                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


                                        </ul>
                                        <ul class="p-0 m-0">


                                        </ul>



                                    </td>

                                    <td class="h6 mb-0 text-primary"><?php echo e($transaction->amount_gross); ?></td>
                                    <td class="h6 mb-0 text-danger"><?php echo e($transaction->transaction_type == 'Withdrawal' ? ($transaction->amount_fee*-1):$transaction->amount_fee); ?></td>


                                    <td class="h6 mb-0 text-danger">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transaction->order): ?>
                                        <?php echo (($transaction->order->items->count()*-10)); ?>


                                        <?php else: ?>
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transaction->transaction_type == 'Withdrawal'): ?>
                                        10
                                        <?php else: ?>
                                        -10
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


                                    </td>
                                    <td class="h6 mb-0 text-warning">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transaction->order): ?>

                                        <?php echo ($transaction->amount_gross - (($transaction->order->items->count()*($transaction->cape_tennis_fee)) - $transaction->amount_fee)); ?>


                                        <?php else: ?>

                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transaction->transaction_type == 'Withdrawal'): ?>
                                        <?php echo e($transaction->amount_net); ?>

                                        <?php else: ?>
                                        <?php echo e($transaction->amount_net - 10); ?>

                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>




                                    </td>
                                </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>

                        </table>
                    </div>


                </div>
                <div class="tab-pane fade show " id="draws" role="tabpanel">

                    <div class=" mb-3 gap-3">
                        <div class="card">
                            <div class="card-header"></div>
                            <div class="card-body">
                                <button class=" mb-1 btn btn-success">Create Draw</button>


                                <div class="row">
                                    <div class="col-md-4 col-12 mb-3 mb-md-0">
                                        <div class="list-group">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <a class="list-group-item list-group-item-action" id="list-settings-list" data-bs-toggle="list" href="#draw-<?php echo e($draw->id); ?>"><?php echo e($draw->drawName); ?></a>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-8 col-12">
                                        <div class="tab-content">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <div class="tab-pane fade show" id="draw-<?php echo e($draw->id); ?>">
                                                <div class="col-12 col-xl-12 col-md-12">
                                                    <div class="card h-100">
                                                        <div class="card-header d-flex align-items-center justify-content-between">
                                                            <h5 class="card-title m-0 me-2">Draw Details</h5>
                                                            <div class="dropdown">
                                                                <button class="btn p-0" type="button" id="topCourses" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                    <i class="ti ti-dots-vertical"></i>
                                                                </button>
                                                                <div class="dropdown-menu dropdown-menu-end" aria-labelledby="topCourses">
                                                                    <a class="dropdown-item" href="javascript:void(0);">Refresh</a>
                                                                    <a class="dropdown-item" href="javascript:void(0);">Download</a>
                                                                    <a class="dropdown-item" href="javascript:void(0);">View All</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="card-body">
                                                            <ul class="list-unstyled mb-0">
                                                                <li class="d-flex mb-4 pb-1 align-items-center mt-2">
                                                                    <div class="avatar flex-shrink-0 me-3">
                                                                        <span class="avatar-initial rounded bg-label-info"><i class=" ti-md"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-align-box-right-middle">
                                                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                                                    <path d="M15 15h2" />
                                                                                    <path d="M3 5a2 2 0 0 1 2 -2h14a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-14z" />
                                                                                    <path d="M11 12h6" />
                                                                                    <path d="M13 9h4" />
                                                                                </svg></i></span>
                                                                    </div>
                                                                    <div class="row w-100 align-items-center">
                                                                        <div class="col-sm-8 col-lg-12 col-xxl-8 mb-1 mb-sm-0 mb-lg-1 mb-xxl-0">
                                                                            <p class="mb-0 fw-medium">Draw Type</p>
                                                                        </div>
                                                                        <div class="col-sm-4 col-lg-12 col-xxl-4 d-flex justify-content-sm-end justify-content-md-start justify-content-xxl-end">
                                                                            <div class="badge bg-label-secondary"><?php echo e($draw->draw_types->drawTypeName); ?></div>
                                                                        </div>
                                                                        <div class="btn btn-success btn-sm col-3 m-1">Edit Draw Type</div>
                                                                    </div>
                                                                </li>
                                                                <li class="d-flex mb-4 pb-1 align-items-center">
                                                                    <div class="avatar flex-shrink-0 me-3">
                                                                        <span class="avatar-initial rounded bg-label-success"><i class=" ti-md">
                                                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-ball-tennis">
                                                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                                                    <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                                                                    <path d="M6 5.3a9 9 0 0 1 0 13.4" />
                                                                                    <path d="M18 5.3a9 9 0 0 0 0 13.4" />
                                                                                </svg>

                                                                            </i></span>
                                                                    </div>
                                                                    <div class="row w-100 align-items-center">
                                                                        <div class="col-sm-8 col-lg-12 col-xxl-8 mb-1 mb-sm-0 mb-lg-1 mb-xxl-0">
                                                                            <p class="mb-0 fw-medium">Published</p>
                                                                        </div>
                                                                        <div class="col-sm-4 col-lg-12 col-xxl-4 d-flex justify-content-sm-end justify-content-md-start justify-content-xxl-end">
                                                                            <div class="badge bg-label-secondary"><?php echo e($draw->published == 1 ? 'Published':'Not Published'); ?></div>
                                                                        </div>
                                                                    </div>
                                                                </li>
                                                                <li class="d-flex mb-4 pb-1 align-items-center">
                                                                    <div class="avatar flex-shrink-0 me-3">
                                                                        <span class="avatar-initial rounded bg-label-warning"><i class=" ti-md"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-lock">
                                                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                                                    <path d="M5 13a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-6z" />
                                                                                    <path d="M11 16a1 1 0 1 0 2 0a1 1 0 0 0 -2 0" />
                                                                                    <path d="M8 11v-4a4 4 0 1 1 8 0v4" />
                                                                                </svg></i></span>
                                                                    </div>
                                                                    <div class="row w-100 align-items-center">
                                                                        <div class="col-sm-8 col-lg-12 col-xxl-8 mb-1 mb-sm-0 mb-lg-1 mb-xxl-0">
                                                                            <p class="mb-0 fw-medium">Locked</p>
                                                                        </div>
                                                                        <div class="col-sm-4 col-lg-12 col-xxl-4 d-flex justify-content-sm-end justify-content-md-start justify-content-xxl-end">
                                                                            <div class="badge bg-label-secondary"><?php echo e($draw->locked == 1 ? 'Locked':'Open'); ?></div>
                                                                        </div>
                                                                    </div>
                                                                </li>
                                                                <li class="d-flex mb-4 pb-1 align-items-center">
                                                                    <div class="avatar flex-shrink-0 me-3">
                                                                        <span class="avatar-initial rounded bg-label-primary"><i class=" ti-md"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clipboard">
                                                                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                                                    <path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" />
                                                                                    <path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" />
                                                                                </svg></i></span>
                                                                    </div>
                                                                    <div class="row w-100 align-items-center">
                                                                        <div class="col-sm-8 col-lg-12 col-xxl-8 mb-1 mb-sm-0 mb-lg-1 mb-xxl-0">
                                                                            <p class="mb-0 fw-medium">Players in draw</p>
                                                                        </div>
                                                                        <div class="col-sm-4 col-lg-12 col-xxl-4 d-flex justify-content-sm-end justify-content-md-start justify-content-xxl-end">
                                                                            <div class="badge bg-label-secondary"><?php echo e($draw->registrations->count()); ?></div>
                                                                        </div>
                                                                        <div class="btn btn-success btn-sm col-3 m-1" data-bs-toggle="modal" data-bs-target="#add-registrations-modal">Add Players to draw</div>
                                                                    </div>
                                                                </li>
                                                                <div class="card border border-primary mb-4">
                                                                    <div class="card-body ">
                                                                        <li class="d-flex mb-4 pb-1 align-items-center">
                                                                            <div class="avatar flex-shrink-0 me-3">
                                                                                <span class="avatar-initial rounded bg-label-primary"><i class=" ti-md"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-clipboard">
                                                                                            <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                                                            <path d="M9 5h-2a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-12a2 2 0 0 0 -2 -2h-2" />
                                                                                            <path d="M9 3m0 2a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v0a2 2 0 0 1 -2 2h-2a2 2 0 0 1 -2 -2z" />
                                                                                        </svg></i></span>
                                                                            </div>
                                                                            <div class="row w-100 align-items-center">
                                                                                <div class="col-sm-8 col-lg-12 col-xxl-8 mb-1 mb-sm-0 mb-lg-1 mb-xxl-0">
                                                                                    <p class="mb-0 fw-medium">Groups</p>
                                                                                </div>
                                                                                <div class="col-sm-4 col-lg-12 col-xxl-4 d-flex justify-content-sm-end justify-content-md-start justify-content-xxl-end">
                                                                                    <div class="badge bg-label-secondary"><?php echo e($draw->groups->count()); ?></div>
                                                                                </div>
                                                                                <div class="btn btn-success btn-sm col-3 ms-1 ">Configure Groups</div>

                                                                            </div>

                                                                        </li>


                                                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draw->groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                        <div class=" col-2 m-1 badge bg-primary pt-2 pb-2">Group <?php echo e($key+1); ?></div>

                                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                                    </div>
                                                                </div>


                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                        </div>
                                    </div>
                                </div>


                            </div>
                        </div>
                    </div>


                </div>
            </div>
        </div>
        <!-- /FAQ's -->
    </div>





</div>
<!-- Modal -->
<div class="modal fade" id="add-registrations-modal" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Add Players to draw</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">


                <div class="col-12 p-4">

                    <div class="switches-stacked">
                        <label class="switch">
                            <input type="radio" class="switch-input" name="switches-stacked-radio" checked />
                            <span class="switch-toggle-slider">
                                <span class="switch-on"></span>
                                <span class="switch-off"></span>
                            </span>
                            <span class="switch-label">Add all players in category</span>
                        </label>
                        <div class="ms-5 col-sm mb-3">

                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="form-check mt-3 ">
                                <input name="default-radio-1" class="form-check-input" type="radio" value="" id="defaultRadio1" />
                                <label class="form-check-label" for="defaultRadio1">
                                    <?php echo e($category->name); ?>

                                </label>
                            </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


                        </div>
                    </div>
                    <label class="switch">
                        <input type="radio" class="switch-input" name="switches-stacked-radio" />
                        <span class="switch-toggle-slider">
                            <span class="switch-on"></span>
                            <span class="switch-off"></span>
                        </span>
                        <span class="switch-label">Add players from event</span>
                    </label>
                    <div class="mb-3 mt-3">

                        <select id="select2Multiple" class="select2 form-select" multiple>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $registration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($registration->id); ?>"><?php echo e($registration->registration->players[0]->name); ?> <?php echo e($registration->registration->players[0]->surname); ?></option>

                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                        </select>
                    </div>
                </div>
            </div>
           <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="button" class="btn btn-primary">Add Players</button>
        </div>   
        </div>
      
    </div>
</div>

<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\overbergTrialsAdmin.blade.php ENDPATH**/ ?>