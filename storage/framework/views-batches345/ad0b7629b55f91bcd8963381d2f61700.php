<style>
    .select2-container {
        z-index: 100000;
    }
</style>
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
                                    <span class="align-middle fw-semibold">Nominations</span>
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
                            <li class="nav-item" role="presentation">
                                <a href="<?php echo e(route('headOffice.show',$event->id)); ?>" type="button" class="nav-link"><i class="tf-icons ti ti-message-dots ti-xs me-1"></i>Dashboard </a>
                            </li>

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
                    <?php echo $__env->make('backend.nominations.view', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>



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
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($transactions)): ?>
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
      <small class="text-muted d-block"><?php echo e($value->category_event?->category?->name ?? '—'); ?></small>
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
      <h6 class="mb-0"><?php echo e($transaction->custom_str2); ?></h6>
      <small class="text-muted d-block"><?php echo e($transaction->category_event?->category?->name ?? '—'); ?></small>
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
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>

                        </table>
                    </div>


                </div>
            </div>
        </div>
        <!-- /FAQ's -->
    </div>





</div>
<?php echo $__env->make('backend.adminPage.admin_show.tabs.modals.nominationModal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\cavaliers_trials_show.blade.php ENDPATH**/ ?>