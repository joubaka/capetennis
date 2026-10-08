<div class="d-flex mb-3 gap-3">
  <div>
    <h4 class="mb-0 mt-4"><span class="align-middle">Entries</span></h4>
    <small>Players entered in <?php echo e($event->name); ?></small>
  </div>
</div>

<div class="col-md-12 col-lg-12 col-xl-12 mb-4">
  <div class="shadow-none bg-transparent h-100">
    <div class="d-flex justify-content-between pb-2 mb-1">
      <div class="dropdown">
        <button class="btn p-0" type="button" data-bs-toggle="dropdown">
          <span class="btn btn-primary btn-sm"><i class="ti ti-dots-vertical ti-sm text-white"></i> Actions</span>
        </button>
        <div class="dropdown-menu dropdown-menu-end">
          <a class="dropdown-item createEmailButton" href="javascript:void(0);"
             data-bs-target="#createEmail" data-bs-toggle="modal"
             onclick="changeRecipants('event','<?php echo e($event->id); ?>')">
            Send e-mail to all players in event
          </a>
          <a class="dropdown-item" href="<?php echo e(route('export.registrations', $event->id)); ?>">
            Export entry list
          </a>
        </div>
      </div>
    </div>

    <div class="nav-align-top">
      <ul class="nav nav-tabs nav-fill" role="tablist">
        <li class="nav-item">
          <button type="button" class="nav-link active"
                  data-bs-toggle="tab" data-bs-target="#navs-justified-new">Confirmed</button>
        </li>
        <li class="nav-item">
          <button type="button" class="nav-link"
                  data-bs-toggle="tab" data-bs-target="#navs-justified-link-preparing">Withdrawals</button>
        </li>
      </ul>

      <div class="tab-content pb-0">
        
        <div class="tab-pane fade active show" id="navs-justified-new">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categories): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="card shadow-none bg-transparent border border-primary mb-5">
              <div class="card-header">
                <h3><?php echo e($categories->category->name); ?></h3>
                <button type="button" class="btn btn-success btn-sm addPlayerC"
                        data-bs-target="#addPlayerToCategory" data-bs-toggle="modal"
                        data-categoryeventid="<?php echo e($categories->id); ?>">Add Player</button>
                <a class="btn btn-sm btn-primary createEmailButton"
                   href="javascript:void(0);" data-bs-target="#createEmail"
                   data-bs-toggle="modal"
                   onclick="changeRecipants('category','<?php echo e($categories->id); ?>')">
                   Send e-mail to category
                </a>
              </div>

              <div class="table-responsive text-nowrap mb-4">
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th>#</th><th>Name</th><th>Email</th><th>Contact</th><th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categories->registrations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $registration): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <tr>
                        <td><strong><?php echo e($k + 1); ?></strong></td>
                        <td><?php echo e($registration->players[0]->name); ?> <?php echo e($registration->players[0]->surname); ?></td>
                        <td><?php echo e($registration->players[0]->email); ?></td>
                        <td><span class="badge bg-label-primary"><?php echo e($registration->players[0]->cellNr); ?></span></td>
                        <td>
                          <span class="btn btn-sm btn-secondary sendEmail"
                                data-bs-target="#createEmail" data-bs-toggle="modal"
                                data-email="<?php echo e($registration->players[0]->email); ?>"
                                data-totype="one">
                                <i class="ti ti-pencil me-1"></i>Email
                          </span>
                          <button class="btn btn-sm btn-danger withdraw-player-btn"
                                  data-id="<?php echo e($registration->id); ?>"
                                  data-categoryevent="<?php echo e($categories->id); ?>"
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

        
        <div class="tab-pane fade" id="navs-justified-link-preparing">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $categories): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="card shadow-none bg-transparent border border-primary mb-5">
              <div class="card-header"><h3><?php echo e($categories->category->name); ?></h3></div>
              <div class="table-responsive text-nowrap mb-4">
                <table class="table table-bordered">
                  <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Contact</th><th>Actions</th></tr></thead>
                  <tbody>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $categories->withdrawals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $withdrawal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <tr>
                        <td><strong><?php echo e($k + 1); ?></strong></td>
                        <td><?php echo e($withdrawal->registration->players[0]->name); ?> <?php echo e($withdrawal->registration->players[0]->surname); ?></td>
                        <td><?php echo e($withdrawal->registration->players[0]->email); ?></td>
                        <td><span class="badge bg-label-primary"><?php echo e($withdrawal->registration->players[0]->cellNr); ?></span></td>
                        <td>
                          <span class="btn btn-sm btn-secondary sendEmail"
                                data-bs-target="#createEmail" data-bs-toggle="modal"
                                data-email="<?php echo e($withdrawal->registration->players[0]->email); ?>">
                                <i class="ti ti-pencil me-1"></i>Email
                          </span>
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
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\entries.blade.php ENDPATH**/ ?>