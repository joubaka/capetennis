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
                                    <span class="align-middle fw-semibold">Results</span>
                                </button>
                            </li>



                            <li class="nav-item">
                                <button class="nav-link " data-bs-toggle="tab" data-bs-target="#transactions">
                                    <i class="ti ti-credit-card me-1 ti-sm"></i>
                                    <span class="align-middle fw-semibold">Transactions</span>
                                </button>
                            </li>

                           <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()->id == 584): ?>
        <li class="nav-item">
            <a href="<?php echo e(route('event.admin.main', $event->id)); ?>" class="nav-link">
                <i class="ti ti-trophy me-1 ti-sm"></i>
                <span class="align-middle fw-semibold">Tournament Admin</span>
            </a>
        </li>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>





                        </ul>

                        <div class="d-none d-md-block">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-12 col-md-12 col-12">
            <div class="tab-content py-0">
                <!-- registrations -->
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
                                    <button class="btn p-0" type="button" id="salesByCountryTabs"
                                        data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <span class="btn btn-primary btn-sm"> <i
                                                class="ti ti-dots-vertical ti-sm text-white"></i> Actions</span>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end" aria-labelledby="salesByCountryTabs">
                                        <a class="dropdown-item createEmailButton" href="javascript:void(0);"
                                            data-bs-target="#createEmail" data-bs-toggle="modal" data-totype="event"
                                            onclick="changeRecipants('event','<?php echo e($event->id); ?>')">Send e-mail to all
                                            players in event</a>
                                        <a class="dropdown-item" href="<?php echo e(route('export.registrations', $event->id)); ?>"
                                            data-event="<?php echo e($event->id); ?>">Export entry list</a>

                                    </div>
                                </div>
                            </div>
                            <div class="shadow-none bg-transparent">
                                <div class="nav-align-top">
                                    <ul class="nav nav-tabs nav-fill" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button type="button" class="nav-link active"
                                                role="tab" data-bs-toggle="tab" data-bs-target="#navs-justified-new"
                                                aria-controls="navs-justified-new"
                                                aria-selected="true">Confirmed</button>
                                        </li>
                                        <li class=" nav-item" role="presentation">
                                            <button type="button" class="nav-link"
                                                role="tab" data-bs-toggle="tab"
                                                data-bs-target="#navs-justified-link-preparing"
                                                aria-controls="navs-justified-link-preparing" aria-selected="false"
                                                tabindex="-1">Withdrawals</button>
                                        </li>

                                    </ul>
                                    <div class="tab-content pb-0">
                                        <div class="tab-pane fade active show" id="navs-justified-new" role="tabpanel">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $categories): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <div
                                                    class="card shadow-none bg-transparent border border-primary mb-5 ">
                                                    <div class="card-header">
                                                        <h3><?php echo e($categories->category->name); ?></h3><button type="button"
                                                            data-bs-target="#addPlayerToCategory" data-bs-toggle="modal"
                                                            data-categoryeventid="<?php echo e($categories->id); ?>"
                                                            class="btn btn-success btn-sm addPlayerC">Add
                                                            Player</button>
                                                        <a class="btn btn-sm btn-primary createEmailButton"
                                                            href="javascript:void(0);" data-bs-target="#createEmail"
                                                            data-bs-toggle="modal" data-totype="event"
                                                            onclick="changeRecipants('category','<?php echo e($event->id); ?>')">Send
                                                            e-mail </a>

                                                    </div>

                                                    <div class="table-responsive text-nowrap mb-4">
                                                        <table class="table table-bordered">
                                                            <thead>
                                                                <tr>
                                                                    <th>#</th>
                                                                    <th>Name</th>
                                                                    <th>Email</th>
                                                                    <th>Contact</th>
                                                                    <th>Payment note</th>
                                                                    <th>Actions</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categories->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $registration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                                    <tr>
                                                                        <td> <strong><?php echo e($k + 1); ?></strong></td>
                                                                        <td><?php echo e($registration->players[0]->name); ?>

                                                                            <?php echo e($registration->players[0]->surname); ?>

                                                                        </td>
                                                                        <td>
                                                                            <?php echo e($registration->players[0]->email); ?>

                                                                        </td>
                                                                        <td><span
                                                                                class="badge bg-label-primary me-1"><?php echo e($registration->players[0]->cellNr); ?></span>
                                                                        </td>
                                                                        <td>
                                                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($registration->pivot->admin_payment_status, ['unpaid', 'paid'], true)): ?>
                                                                                <?php $adminCollectionPaid = $registration->pivot->admin_payment_status === 'paid'; ?>
                                                                                <span class="badge <?php echo e($adminCollectionPaid ? 'bg-success' : 'bg-warning text-dark'); ?>">
                                                                                    Private collection <?php echo e($adminCollectionPaid ? 'noted paid' : 'not marked paid'); ?> (not reconciled)
                                                                                </span>
                                                                                <form method="POST"
                                                                                      action="<?php echo e(route('admin.entry.admin-payment-status', $registration->pivot->id)); ?>"
                                                                                      class="mt-1">
                                                                                    <?php echo csrf_field(); ?>
                                                                                    <?php echo method_field('PATCH'); ?>
                                                                                    <input type="hidden" name="paid" value="<?php echo e($adminCollectionPaid ? '0' : '1'); ?>">
                                                                                    <button type="submit" class="btn btn-xs <?php echo e($adminCollectionPaid ? 'btn-outline-warning' : 'btn-outline-success'); ?>">
                                                                                        <?php echo e($adminCollectionPaid ? 'Mark note unpaid' : 'Mark note paid'); ?>

                                                                                    </button>
                                                                                </form>
                                                                            <?php else: ?>
                                                                                <span class="badge <?php echo e((int) $registration->pivot->payment_status_id === 1 ? 'bg-success' : 'bg-warning text-dark'); ?>">
                                                                                    <?php echo e((int) $registration->pivot->payment_status_id === 1 ? 'Paid' : 'Unpaid'); ?>

                                                                                </span>
                                                                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                                        </td>
                                                                        <td>
                                                                            <span
                                                                                class="btn btn-sm btn-secondary sendEmail"
                                                                                data-bs-target="#createEmail"
                                                                                data-bs-toggle="modal"
                                                                                data-email=" <?php echo e($registration->players[0]->email); ?>"
                                                                                data-totype="one"><i
                                                                                    class="ti ti-pencil me-1"></i>Email
                                                                                Player</span>
       <button 
  class="btn btn-sm btn-danger withdraw-player-btn"
  data-id="<?php echo e($registration->id); ?>"
  data-categoryevent="<?php echo e($registration->categoryEvents->first()->id ?? ($categoryEvent->id ?? '')); ?>"
  data-name="<?php echo e($registration->players[0]->name); ?> <?php echo e($registration->players[0]->surname); ?>">
  <i class="ti ti-user-x me-1"></i> Withdraw
</button>




                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>



                                                </div>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                        </div>

                                        <div class="tab-pane fade " id="navs-justified-link-preparing"
                                            role="tabpanel">
                                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $categories): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <div
                                                    class="card shadow-none bg-transparent border border-primary mb-5 ">
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
                                                                        <td> <strong><?php echo e($k + 1); ?></strong></td>
                                                                        <td><?php echo e($withdrawal->registration->players[0]->name); ?>

                                                                            <?php echo e($withdrawal->registration->players[0]->surname); ?>

                                                                        </td>
                                                                        <td> <?php echo e($withdrawal->registration->players[0]->email); ?>

                                                                        </td>
                                                                        <td><span
                                                                                class="badge bg-label-primary me-1"><?php echo e($withdrawal->registration->players[0]->cellNr); ?>

                                                                            </span></td>
                                                                        <td>
                                                                            <span
                                                                                class="btn btn-sm btn-secondary sendEmail"
                                                                                data-bs-target="#createEmail"
                                                                                data-bs-toggle="modal"
                                                                                data-email=" <?php echo e($withdrawal->registration->players[0]->email); ?>"
                                                                                data-totype="one"><i
                                                                                    class="ti ti-pencil me-1"></i>Email
                                                                                Player</span>


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
                <!-- results -->
                <div class="tab-pane fade show " id="results" role="tabpanel">
                    <div class=" mb-3 gap-3">

                        <div>

                            <span id="publishResults" data-event_id="<?php echo e($event->id); ?>"
                                class="mb-2 align-middle btn btn-<?php echo e($event->results_published == 1 ? 'danger' : 'success'); ?> btn-sm"><?php echo e($event->results_published == 1 ? 'Unpublish Results' : 'Publish Results'); ?></span>


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
                <!-- transcations -->
                <div class="tab-pane fade show " id="transactions" role="tabpanel">

                    <div class="table-responsive mb-5"><a href="<?php echo e(route('transactions.pdf', $event->id)); ?>"
                            target="_blank" class="btn btn-sm btn-outline-danger mb-3">
                            Download PDF
                        </a>
                        <table id="transactionTable"
                            class="table table-sm table-bordered table-hover align-middle text-sm">
                            <thead class="table-light sticky-top">
                                <tr class="align-middle text-center">
                                    <th>Nr</th>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>User</th>
                                    <th>Items</th>
                                    <th style="min-width: 80px;">Gross</th>
                                    <th style="min-width: 80px;">Payfast Fee</th>
                                    <th style="min-width: 80px;">Cape Tennis Fee</th>
                                    <th style="min-width: 80px;">Nett</th>
                                    <th style="min-width: 80px;">Balance</th>
                                </tr>
                            </thead>

                            <tbody class="table-group-divider">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td class="text-center"><?php echo e($transaction->pf_payment_id ?? '-'); ?></td>
                                        <td><?php echo e($transaction->created_at->format('d M Y')); ?></td>
                                        <td><?php echo e($transaction->transaction_type); ?></td>
                                        <td><?php echo e($transaction->user->name ?? '-'); ?></td>

                                        
                                        <td>

                                            <div class="d-flex flex-column gap-2">
                                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transaction->order && $transaction->order->items): ?>
                                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $transaction->order->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <div
                                                            class="d-flex justify-content-between align-items-center border rounded p-2 shadow-sm bg-white">
                                                            <div>
                                                                <div class="fw-semibold"><?php echo e($item->player->name); ?>

                                                                    <?php echo e($item->player->surname); ?></div>
                                                                <small
                                                                    class="text-muted"><?php echo e($item->category_event->category->name); ?></small>
                                                            </div>
                                                            <div class="text-end">
                                                                <span class="text-secondary fw-bold">
                                                                    R<?php echo e(number_format($item->item_price ?? $event->entryFee, 2)); ?>

                                                                </span>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                                <?php else: ?>
                                                    <div
                                                        class="d-flex justify-content-between align-items-center border rounded p-2 shadow-sm bg-white">
                                                        <div>
                                                            <div class="fw-semibold"><?php echo e($transaction->custom_str2); ?>

                                                            </div>
                                                            <small
                                                                class="text-muted"><?php echo e($transaction->category_event->category->name); ?></small>
                                                        </div>
                                                        <div class="text-end">
                                                            <span class="text-secondary fw-bold">
                                                                R<?php echo e(number_format($transaction->amount_gross, 2)); ?>

                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                            </div>


                                        </td>


                                        
                                        <td class="text-center text-primary">
                                            R<?php echo e(number_format($transaction->calculated_gross, 2)); ?></td>
                                        <td class="text-center text-danger">
                                            R<?php echo e(number_format($transaction->calculated_payfast_fee, 2)); ?></td>
                                        <td class="text-center text-danger">
                                            R<?php echo e(number_format($transaction->calculated_cape_fee, 2)); ?></td>
                                        <td class="text-center text-warning">
                                            R<?php echo e(number_format($transaction->calculated_nett, 2)); ?></td>
                                        <td
                                            class="text-center fw-bold <?php echo e($transaction->calculated_balance < 0 ? 'text-danger' : 'text-success'); ?>">

                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>
                            <tfoot class="table-light border-top">
                                <tr class="fw-bold align-middle">
                                    <td colspan="5" class="text-end">Totals:</td>
                                    <td></td> <!-- Gross -->
                                    <td></td> <!-- Payfast -->
                                    <td></td> <!-- Cape -->
                                    <td></td> <!-- Nett -->
                                    <td></td> <!-- Balance -->
                                </tr>
                            </tfoot>

                        </table>
                    </div>






                </div>
                <div class="tab-pane fade show active" id="tournament-admin" role="tabpanel">
                  <div class="mb-3">
                      <div class="d-flex justify-content-between mb-4">
                          <div>
                            <button class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#generateDrawModal">
                              <i class="fas fa-plus"></i> Create Draw
                          </button>

                              <button class="btn btn-danger">
                                  <i class="fas fa-sort"></i> Change Draw Order
                              </button>
                          </div>
                      </div>

                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categoryEvent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categoryEvent->draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                              <div class="border rounded p-3 mb-4 bg-white shadow-sm">
                                  <div class="d-flex justify-content-between">
                                      <div>
                                          <h5 class="mb-2"><?php echo e($draw->drawName); ?></h5>

                                          
                                          <div class="mb-2">
                                              <span class="badge bg-warning">Individual</span>
                                              <span class="badge bg-primary">Tennis (Singles)</span>
                                              <span class="badge bg-danger"><?php echo e($draw->registrations_count ?? '0'); ?> players</span>
                                              <span class="badge bg-dark"><?php echo e($draw->gender ?? 'Mixed'); ?></span>
                                              <span class="badge bg-<?php echo e($draw->locked ? 'warning' : 'info'); ?>">
                                                  <?php echo e($draw->locked ? '🔒 Locked' : '🔓 Unlocked'); ?>

                                              </span>
                                          </div>

                                          
                                          <div class="text-primary small fw-bold mb-1">
                                              <?php echo e($draw->completion_percent ?? '0%'); ?> Complete
                                          </div>
                                          <div class="progress" style="height: 6px; max-width: 300px;">
                                              <div class="progress-bar bg-primary" role="progressbar"
                                                  style="width: <?php echo e($draw->completion_percent ?? '0%'); ?>;"></div>
                                          </div>
                                      </div>

                                      
                                      <div class="d-flex align-items-start gap-2 flex-wrap">
                                        <a href="<?php echo e(route('category.manage', $draw->category_event_id)); ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-cog"></i> Settings
                                        </a>
                                        
                                        <a href="#" class="btn btn-sm btn-orange">
                                            <i class="fas fa-users"></i> Players
                                        </a>
                                        <a href="<?php echo e(route('draws.show', $draw->id)); ?>" target="_blank" class="btn btn-sm btn-success">
                                            <i class="fas fa-eye"></i> View Draw
                                        </a>
                                        <form method="POST" action="<?php echo e(route('draws.destroy', $draw->id)); ?>" onsubmit="return confirm('Delete this draw?')">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button class="btn btn-sm btn-danger" type="submit">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>

                                  </div>
                              </div>
                          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div>
              </div>


            </div>
        </div>
        <!-- /FAQ's -->
    </div>





</div>
<?php echo $__env->make('backend.adminPage.admin_show.tabs.modals.generateDrawOptionsModal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\individual_show.blade.php ENDPATH**/ ?>