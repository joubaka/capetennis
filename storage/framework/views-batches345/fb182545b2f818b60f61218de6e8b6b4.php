<style>
  .select2-container {
    z-index: 100000;
  }
  .draggable-player {
    cursor: grab;
    transition: 0.2s;
  }
  .draggable-player:hover {
    background-color: #f8f9fa;
    transform: scale(1.02);
  }
  .sortable-team .list-group-item {
    cursor: grab;
  }
</style>

<div class="card">


  <div class="row mt-4">
    <div class="card-body m-4">
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
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#results">
                  <i class="ti ti-list-details me-1 ti-sm"></i>
                  <span class="align-middle fw-semibold">Results</span>
                </button>
              </li>
              <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#teams">
                  <i class="ti ti-users me-1 ti-sm"></i>
                  <span class="align-middle fw-semibold">Teams</span>
                </button>
              </li>
              <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#transactions">
                  <i class="ti ti-currency-rand me-1 ti-sm"></i>
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
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="col-lg-12 col-md-12 col-12">
      <div class="tab-content py-0">

        
        <div class="tab-pane fade show active" id="payment" role="tabpanel">
          <?php echo $__env->make('backend.adminPage.admin_show.tabs.entries', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>

        
        <div class="tab-pane fade" id="results" role="tabpanel">
          <?php echo $__env->make('backend.adminPage.admin_show.tabs.results', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>

        
        <div class="tab-pane fade" id="teams" role="tabpanel">
          <?php echo $__env->make('backend.adminPage.admin_show.tabs.teams', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>

        
        <div class="tab-pane fade" id="transactions" role="tabpanel">
          <?php echo $__env->make('backend.adminPage.admin_show.tabs.transactions', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>

        
        <div class="tab-pane fade" id="tournament-admin" role="tabpanel">
          <?php echo $__env->make('backend.adminPage.admin_show.tabs.tournament_admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php echo $__env->make('backend.adminPage.admin_show.tabs.modals.generateDrawOptionsModal', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>





<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\schools.blade.php ENDPATH**/ ?>