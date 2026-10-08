

<?php $__env->startSection('title', $event->name . ' – Fixtures HQ'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
<style>
  .fixture-card {
    border-left: 3px solid #696cff;
  }
  .fixture-row:hover {
    background-color: rgba(105,108,255,.05);
  }
  .badge-completed {
    background-color: #28c76f;
  }
  .badge-pending {
    background-color: #ff9f43;
  }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">

  <?php echo $__env->make('backend.event.partials.header', ['event' => $event], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  
  <div class="row g-3 mb-4">
    <div class="col-md-4">
      <div class="card">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="avatar avatar-md bg-label-primary rounded">
            <i class="ti ti-list-check ti-md"></i>
          </div>
          <div>
            <h6 class="mb-0">Total Fixtures</h6>
            <span class="fw-semibold fs-5"><?php echo e($stats['totalFixtures']); ?></span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="avatar avatar-md bg-label-success rounded">
            <i class="ti ti-check ti-md"></i>
          </div>
          <div>
            <h6 class="mb-0">Completed</h6>
            <span class="fw-semibold fs-5 text-success"><?php echo e($stats['completedFixtures']); ?></span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="card">
        <div class="card-body d-flex align-items-center gap-3">
          <div class="avatar avatar-md bg-label-warning rounded">
            <i class="ti ti-clock ti-md"></i>
          </div>
          <div>
            <h6 class="mb-0">Pending</h6>
            <span class="fw-semibold fs-5 text-warning"><?php echo e($stats['pendingFixtures']); ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  
  <div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
      <h5 class="mb-0">
        <i class="ti ti-tournament me-2"></i>
        Draws & Fixtures
      </h5>
    </div>

    <div class="card-body">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $draws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $draw): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="card fixture-card mb-3">
          <div class="card-header d-flex align-items-center justify-content-between py-2">
            <div>
              <h6 class="mb-0">
                <?php echo e($draw->drawName); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->categoryEvent?->category): ?>
                  <span class="badge bg-label-primary ms-2"><?php echo e($draw->categoryEvent->category->name); ?></span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </h6>
              <small class="text-muted">
                <?php echo e($draw->draw_fixtures_count); ?> fixtures
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->groups->count() > 0): ?>
                  • <?php echo e($draw->groups->count()); ?> groups
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </small>
            </div>
            <div class="d-flex gap-2">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->locked): ?>
                <span class="badge bg-label-success">
                  <i class="ti ti-lock me-1"></i> Locked
                </span>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <a href="<?php echo e(route('frontend.fixtures.index', $draw)); ?>" 
                 class="btn btn-sm btn-outline-primary"
                 target="_blank">
                <i class="ti ti-eye me-1"></i> View
              </a>
            </div>
          </div>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->drawFixtures->count() > 0): ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th style="width: 50px;">#</th>
                    <th>Player 1</th>
                    <th>Player 2</th>
                    <th style="width: 120px;">Score</th>
                    <th style="width: 100px;">Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $draw->drawFixtures->take(5); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fixture): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="fixture-row">
                      <td class="text-muted"><?php echo e($fixture->match_nr ?? $loop->iteration); ?></td>
                      <td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->registration1?->players->first()): ?>
                          <?php echo e($fixture->registration1->players->first()->full_name ?? $fixture->registration1->players->first()->name); ?>

                        <?php else: ?>
                          <span class="text-muted">TBD</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </td>
                      <td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->registration2?->players->first()): ?>
                          <?php echo e($fixture->registration2->players->first()->full_name ?? $fixture->registration2->players->first()->name); ?>

                        <?php else: ?>
                          <span class="text-muted">TBD</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </td>
                      <td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->fixtureResults->count() > 0): ?>
                          <span class="fw-semibold"><?php echo e($fixture->score); ?></span>
                        <?php else: ?>
                          <span class="text-muted">–</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </td>
                      <td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fixture->winner_registration): ?>
                          <span class="badge badge-completed text-white">Completed</span>
                        <?php else: ?>
                          <span class="badge badge-pending text-white">Pending</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
              </table>
            </div>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($draw->drawFixtures->count() > 5): ?>
              <div class="card-footer text-center py-2">
                <a href="<?php echo e(route('frontend.fixtures.index', $draw)); ?>" class="text-primary">
                  View all <?php echo e($draw->drawFixtures->count()); ?> fixtures
                  <i class="ti ti-arrow-right ms-1"></i>
                </a>
              </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php else: ?>
            <div class="card-body text-muted text-center py-4">
              <i class="ti ti-calendar-off ti-lg mb-2 d-block"></i>
              No fixtures generated yet
            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="text-center py-5 text-muted">
          <i class="ti ti-tournament ti-xl mb-3 d-block"></i>
          <h6>No Draws Created</h6>
          <p class="mb-3">Create draws from the Categories & Entries page to generate fixtures.</p>
          <a href="<?php echo e(route('admin.events.entries.new', $event)); ?>" class="btn btn-primary">
            <i class="ti ti-plus me-1"></i> Manage Categories & Entries
          </a>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\individual\fixtures\hq.blade.php ENDPATH**/ ?>