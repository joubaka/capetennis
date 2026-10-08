<div class="mb-3 gap-3">
  <div>
    <h4>Nominations</h4>

    <!-- Actions Dropdown -->
    <div class="dropdown mb-3">
      <button class="btn p-0" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <span class="btn btn-primary btn-sm">
          <i class="ti ti-dots-vertical ti-sm text-white"></i> Actions
        </span>
      </button>
      <div class="dropdown-menu dropdown-menu-end" aria-labelledby="salesByCountryTabs">
        <a class="dropdown-item createEmailButton"
           href="javascript:void(0);"
           data-bs-target="#createEmail"
           data-bs-toggle="modal"
           data-totype="event"
           onclick="changeRecipants('nominations','<?php echo e($event->id); ?>')">
          Send e-mail to all nominated players in event
        </a>
      </div>
    </div>

    <!-- Category Loop -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $event->eventCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $eventCategory): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <div class="card shadow-none bg-transparent border border-primary mb-5">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h3 class="mb-0"><?php echo e($eventCategory->category->name); ?></h3>

          <div class="d-flex gap-2">
            <!-- ✅ Fixed: pass ID not object -->
          <button type="button"
        class="btn btn-success btn-sm openNominateModal"
        data-bs-toggle="modal"
        data-bs-target="#nominatePlayerModal"
        data-categoryeventid="<?php echo e($eventCategory->id); ?>">
  <i class="ti ti-user-plus me-1"></i> Nominate Player
</button>


            <button class="nominationPublish btn btn-sm btn-<?php echo e($eventCategory->nominations_published ? 'danger' : 'success'); ?>"
                    data-id="<?php echo e($eventCategory->id); ?>">
              <?php echo e($eventCategory->nominations_published ? 'Unpublish' : 'Publish'); ?> list
            </button>
          </div>
        </div>

        <!-- Table -->
        <div class="table-responsive text-nowrap mb-4">
          <table class="table table-bordered nomination-table" id="nomination-table-<?php echo e($eventCategory->id); ?>">
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
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $eventCategory->nominations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $nomination): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr data-nominationid="<?php echo e($nomination->id); ?>">
                  <td><strong><?php echo e($k + 1); ?></strong></td>
                  <td><?php echo e($nomination->player->getFullNameAttribute()); ?> <?php $info = $playerInfo[$nomination->player->id] ?? null; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($info): ?>
  <small class="text-muted"><?php echo e($info['region']); ?> | Rank <?php echo e($info['rank']); ?></small>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</td>
                  <td><?php echo e($nomination->player->email); ?></td>
                  <td><span class="badge bg-label-primary me-1"><?php echo e($nomination->player->cellNr); ?></span></td>
                  <td>
                    <span class="btn btn-sm btn-secondary sendEmail"
                          data-bs-target="#createEmail"
                          data-bs-toggle="modal"
                          data-email="<?php echo e($nomination->player->email); ?>"
                          data-totype="one">
                      <i class="ti ti-pencil me-1"></i>Email Player
                    </span>

                    <!-- ✅ Fixed: correct attribute names -->
                    <span class="btn btn-sm btn-danger nomination-remove"
                          data-id="<?php echo e($nomination->id); ?>"
                          data-player="<?php echo e($nomination->player->getFullNameAttribute()); ?>"
                          data-categoryeventid="<?php echo e($eventCategory->id); ?>">
                      <i class="ti ti-trash me-1"></i>Remove
                    </span>
                  </td>
                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5" class="text-center text-muted">No nominations yet</td></tr>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\nominations\view.blade.php ENDPATH**/ ?>