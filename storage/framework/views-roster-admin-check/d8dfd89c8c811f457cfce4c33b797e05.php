

<?php $__env->startSection('title', 'Dashboard'); ?>


<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-buttons-bs5/buttons.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/animate-css/animate.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/toastr/toastr.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/typography.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/katex.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/quill/editor.css')); ?>" />
<?php $__env->stopSection(); ?>


<?php $__env->startSection('page-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/css/pages/page-user-view.css')); ?>">
<style>
  .card-header h3 { font-size: 1.25rem; font-weight: 600; }
  .list-unstyled li span.fw-semibold { min-width: 120px; display: inline-block; }
  .addPlayerToProfile { float: right; }
  .dashboard-tabs { border-bottom: 1px solid #e7e7ef; }
  .dashboard-tabs .nav-link { border-radius: .6rem .6rem 0 0; padding: .7rem 1rem; font-weight: 600; color: #6f6b7d; }
  .dashboard-tabs .nav-link.active { color: #7367f0; background: #f4f3ff; }
  .dashboard-events-card { border: 1px solid #ebeaf0; box-shadow: 0 .25rem 1rem rgba(47, 43, 61, .06); }
  .dashboard-section-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid #ebeaf0; }
  .dashboard-events-card .datatable-events thead th { background: #f8f8fb; color: #6f6b7d; font-size: .72rem; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap; }
  .dashboard-events-card .datatable-events tbody tr { border-bottom: 1px solid #f0eff3; }
  .dashboard-events-card .datatable-events tbody td { padding: 1rem .75rem; color: #6f6b7d; }
  .dashboard-event-name { display: inline-block; max-width: 250px; color: #4b4658; font-weight: 600; line-height: 1.3; }
  .dashboard-event-date { display: block; color: #4b4658; font-weight: 600; white-space: nowrap; }
  .dashboard-event-time { display: block; color: #a5a3ae; font-size: .75rem; margin-top: .15rem; }
  .dashboard-event-actions { display: flex; flex-wrap: wrap; gap: .4rem; min-width: 150px; }
  .dashboard-event-actions .btn { margin: 0 !important; }
  .dashboard-event-card { border: 1px solid #ebeaf0; border-radius: .75rem; padding: 1rem; height: 100%; }
  .dashboard-event-card__meta { color: #6f6b7d; font-size: .875rem; }
  .dashboard-event-card__actions { display: flex; flex-wrap: wrap; gap: .4rem; }
  .upcoming-event-card { border: 1px solid #ebeaf0; border-radius: .75rem; padding: 1rem; height: 100%; background: #fff; }
  .dashboard-event-card__actions .btn, .dashboard-tabs .nav-link { min-height: 44px; }
  .dashboard-event-card__actions .btn { display: inline-flex; align-items: center; justify-content: center; }
  .dashboard-account > summary { cursor: pointer; min-height: 44px; }
  .dashboard-account > summary::marker { color: #7367f0; }
  .dashboard-account > summary .text-muted { margin-left: .75rem; }
  .dashboard-account[open] > summary { border-color: #7367f0; }
  @media (max-width: 767.98px) {
    .dashboard-event-card__actions { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); width: 100%; }
    .dashboard-event-card__actions .dropdown > .btn { width: 100%; }
    .dashboard-event-card__actions .dropdown-item { min-height: 44px; display: flex; align-items: center; }
    .dashboard-account > summary .text-muted { display: block; margin-left: 0; }
    .dashboard-section-header { flex-wrap: wrap; }
    .dashboard-section-header { align-items: flex-start !important; gap: 1rem; }
    .dashboard-event-name { max-width: 180px; }
    .dashboard-events-card .datatable-events tbody td { padding: .8rem .6rem; }
  }
</style>
<?php $__env->stopSection(); ?>


<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/moment/moment.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/sweetalert2/sweetalert2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/cleavejs/cleave.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/cleavejs/cleave-phone.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/toastr/toastr.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/quill/katex.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/quill/quill.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php
  $showDashboardTabs = collect($tabs)->contains(true);
?>
<?php if (isset($component)) { $__componentOriginal6ccefb989a1afce853acb3cdbc40307e = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal6ccefb989a1afce853acb3cdbc40307e = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.backend.page-header','data' => ['title' => $showDashboardTabs ? 'Admin home' : 'My dashboard','eyebrow' => 'Cape Tennis','subtitle' => $showDashboardTabs ? 'Choose an event or an administration task.' : 'Your profile, players and events.','icon' => 'ti-layout-dashboard']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backend.page-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($showDashboardTabs ? 'Admin home' : 'My dashboard'),'eyebrow' => 'Cape Tennis','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($showDashboardTabs ? 'Choose an event or an administration task.' : 'Your profile, players and events.'),'icon' => 'ti-layout-dashboard']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal6ccefb989a1afce853acb3cdbc40307e)): ?>
<?php $attributes = $__attributesOriginal6ccefb989a1afce853acb3cdbc40307e; ?>
<?php unset($__attributesOriginal6ccefb989a1afce853acb3cdbc40307e); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal6ccefb989a1afce853acb3cdbc40307e)): ?>
<?php $component = $__componentOriginal6ccefb989a1afce853acb3cdbc40307e; ?>
<?php unset($__componentOriginal6ccefb989a1afce853acb3cdbc40307e); ?>
<?php endif; ?>

<input type="hidden" value="<?php echo e($user->id); ?>" id="user">

<div class="row">

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showDashboardTabs): ?>
  <div class="col-12" id="dashboard-work">
    <?php echo $__env->make('templates.adminDashboardTemplate', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <div class="col-12" id="dashboard-account">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showDashboardTabs): ?>
    <details class="dashboard-account mb-4" id="my-account" <?php if(request()->has('wallet_page')): ?> open <?php endif; ?>>
      <summary class="card p-3 mb-3"><span class="fw-semibold">My Account</span><span class="text-muted small">Profile, linked players and wallet history</span></summary>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="card mb-4">
      <div class="card-body text-center">

        <img src="<?php echo e($user->profile_photo_url ?? asset('assets/img/avatars/default.svg')); ?>"
             class="rounded-circle mb-3"
             width="100" height="100">

        <h4 class="mb-0"><?php echo e($user->userName ?? $user->name); ?></h4>
        <small class="text-muted"><?php echo e($user->email); ?></small>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($user->hasRole('super-user')): ?>
        <div class="d-flex justify-content-center mt-3">
          <div class="text-center">
            <span class="badge bg-label-primary p-2 mb-1"><i class="ti ti-shield ti-sm"></i></span>
            <p class="fw-semibold mb-0">Platform administrator</p>
            <small class="text-muted"><?php echo e($managedEventCount); ?> events available</small>
          </div>
        </div>
        <?php elseif($managedEventCount > 0): ?>
        <div class="d-flex justify-content-center mt-3">
          <div class="text-center">
            <span class="badge bg-label-primary p-2 mb-1">
              <i class="ti ti-calendar ti-sm"></i>
            </span>
            <p class="fw-semibold mb-0"><?php echo e($managedEventCount); ?></p>
            <small class="text-muted">Event administrator</small>
          </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <hr class="my-4">

        <ul class="list-unstyled text-start ps-2">
          <li class="mb-2"><span class="fw-semibold">Username:</span> <?php echo e($user->name); ?></li>
          <li class="mb-2"><span class="fw-semibold">Name:</span> <?php echo e($user->userName ?? '-'); ?></li>
          <li class="mb-2"><span class="fw-semibold">Surname:</span> <?php echo e($user->userSurname ?? '-'); ?></li>
          <li class="mb-2"><span class="fw-semibold">Email:</span> <?php echo e($user->email); ?></li>
          <li class="mb-2"><span class="fw-semibold">Contact:</span> <?php echo e($user->cell_nr ?? '-'); ?></li>

          <li class="mb-2">
            <span class="fw-semibold">Wallet Balance:</span>
            <span class="badge bg-label-success">
              R <?php echo e(number_format($user->wallet?->balance ?? 0, 2)); ?>

            </span>
          </li>
        </ul>

        <div class="d-flex justify-content-center gap-2 mt-3">
          <a href="<?php echo e(route('wallet.show', $user->id)); ?>" class="btn btn-outline-info btn-sm">
            <i class="ti ti-wallet me-1"></i> Wallet
          </a>
          <button class="btn btn-primary btn-sm"
                  data-bs-toggle="modal"
                  data-bs-target="#editUser">
            <i class="ti ti-user-edit me-1"></i> Edit Profile
          </button>
          <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('super-user')): ?>
          <a href="<?php echo e(route('settings.index')); ?>" class="btn btn-outline-secondary btn-sm">
            <i class="ti ti-settings me-1"></i> Settings
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    
    <div class="card">
      <div class="card-header d-flex justify-content-between">
        <h3 class="m-0"><i class="ti ti-users me-1"></i> Players Linked</h3>
        <button class="btn btn-sm btn-secondary addPlayerToProfile"
                data-bs-toggle="modal"
                data-bs-target="#addProfileModal">
          <i class="ti ti-link me-1"></i> Link Player
        </button>
      </div>

      <div class="card-body">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $user->players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
          <?php
            $profileStatus = $player->getProfileStatus();
            $agreementStatus = $player->hasAcceptedLatestAgreement();
          ?>
          <div class="linked-player-row mb-3 pb-3 border-bottom">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <a href="<?php echo e(route('player.profile.edit', $player)); ?>"
                   class="btn btn-sm btn-outline-primary fw-semibold"
                   title="Update player profile">
                  <?php echo e($player->name); ?> <?php echo e($player->surname); ?>

                </a>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player->isMinor()): ?>
                  <span class="badge bg-info ms-1">Minor</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <div class="text-muted small"><?php echo e($player->email); ?></div>
              </div>

              <div class="btn-group btn-group-sm">
                <button
                  class="btn btn-danger btn-sm unlink-player"
                  data-user="<?php echo e($user->id); ?>"
                  data-player="<?php echo e($player->id); ?>">
                  <i class="ti ti-trash"></i>
                </button>
              </div>
            </div>

            
            <div class="mt-2 d-flex flex-wrap gap-2">
              
              <a href="<?php echo e(route('player.profile.edit', $player)); ?>" 
                 class="badge bg-<?php echo e($profileStatus['badge']); ?> text-decoration-none"
                 title="Click to update profile">
                <i class="ti <?php echo e($profileStatus['icon']); ?> me-1"></i>
                Profile: <?php echo e(ucfirst($profileStatus['status'])); ?>

              </a>

              
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($agreementStatus): ?>
                <span class="badge bg-success">
                  <i class="ti ti-file-check me-1"></i> CoC Accepted
                </span>
              <?php else: ?>
                <a href="<?php echo e(route('agreements.show')); ?>" class="badge bg-warning text-decoration-none">
                  <i class="ti ti-file-alert me-1"></i> CoC Pending
                </a>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

              
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player->profile_updated_at): ?>
                <span class="badge bg-label-secondary" title="Last profile update">
                  <i class="ti ti-clock me-1"></i>
                  <?php echo e($player->profile_updated_at->diffForHumans()); ?>

                </span>
              <?php else: ?>
                <span class="badge bg-label-danger">
                  <i class="ti ti-alert-circle me-1"></i> Never updated
                </span>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
          <div class="alert alert-info mb-0">
            <i class="ti ti-info-circle me-1"></i> No players linked yet.
          </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </div>

    
    <div class="card mt-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="m-0"><i class="ti ti-wallet me-1"></i> Wallet Transactions</h3>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('super-user')): ?>
        <button class="btn btn-sm btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#walletTransactionModal">
          <i class="ti ti-plus me-1"></i> Credit / Debit
        </button>
        <?php endif; ?>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover table-striped mb-0">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Reference</th>
              </tr>
            </thead>
            <tbody id="walletTransactionsBody">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                  <td><?php echo e($tx->created_at->format('d M Y H:i')); ?></td>
                  <td>
                    <span class="badge <?php echo e($tx->type === 'credit' ? 'bg-success' : 'bg-danger'); ?>">
                      <?php echo e(ucfirst($tx->type)); ?>

                    </span>
                  </td>
                  <td class="fw-bold <?php echo e($tx->type === 'credit' ? 'text-success' : 'text-danger'); ?>">
                    R <?php echo e(number_format($tx->amount, 2)); ?>

                  </td>
                  <td><?php echo e($tx->meta['reference'] ?? '-'); ?></td>
                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted py-3">No transactions found.</td>
                </tr>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
          </table>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($transactions?->hasPages()): ?>
          <div class="px-3 py-3 border-top">
            <?php echo e($transactions->links('pagination::bootstrap-5')); ?>

          </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showDashboardTabs): ?>
    </details>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>

</div>


  <div class="row mt-4">
    <div class="col-12">
      <?php echo $__env->make('backend.partials.upcoming-events', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
  </div>

<div class="modal fade" id="addProfileModal">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">Link Player</h5>
      </div>

      <div class="modal-body">
        <select id="player-select" class="form-select">
          <option></option>
        </select>
      </div>

      <div class="modal-footer">
        <button class="btn btn-primary" id="linkPlayerBtn">
          Link Player
        </button>
      </div>

    </div>
  </div>
</div>


<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('super-user')): ?>
<div class="modal fade" id="walletTransactionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-wallet me-1"></i> Credit / Debit Wallet</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body">
        <input type="hidden" id="txn-idempotency-key" value="<?php echo e((string) \Illuminate\Support\Str::uuid()); ?>">
        <div class="mb-3">
          <label for="txn-type" class="form-label">Transaction Type</label>
          <select id="txn-type" class="form-select">
            <option value="credit">Credit (Add funds)</option>
            <option value="debit">Debit (Deduct funds)</option>
          </select>
        </div>
        <div class="mb-3">
          <label for="txn-amount" class="form-label">Amount (R)</label>
          <input type="number" id="txn-amount" class="form-control" step="0.01" min="0.01" placeholder="0.00" required>
        </div>
        <div class="mb-3">
          <label for="txn-reference" class="form-label">Reference (optional)</label>
          <input type="text" id="txn-reference" class="form-control" placeholder="e.g. Manual top-up">
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="submitWalletTxnBtn">
          <i class="ti ti-check me-1"></i> Submit
        </button>
      </div>

    </div>
  </div>
</div>
<?php endif; ?>

<?php echo $__env->make('_partials/_modals/modal-upgrade-plan', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->make('_partials/_modals/modal-edit-user', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('superUser')): ?>
  <?php echo $__env->make('_partials/_modals/modal-add-event', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?>
<?php $__env->stopSection(); ?>

  


<?php $__env->startSection('page-script'); ?>
<script>
'use strict';

$(function () {

  console.log('✅ Dashboard script loaded');

  const CSRF   = $('meta[name="csrf-token"]').attr('content');
  const userId = $('#user').val();
  const PLAYER_URL = APP_URL + '/backend/player';

  // ==========================================================
  // 🟦 TAB NAVIGATION FROM URL HASH OR LOCALSTORAGE
  // ==========================================================
  function activateTabFromHash() {
    var hash = window.location.hash;
    var storedTab = localStorage.getItem('dashboardTab');

    // Clear stored tab after use
    if (storedTab) {
      localStorage.removeItem('dashboardTab');
      hash = '#' + storedTab;
    }

    if (hash) {
      const target = document.getElementById(hash.slice(1));
      const account = document.getElementById('my-account');
      if (account && target && (target === account || account.contains(target) || target.classList.contains('modal'))) {
        account.open = true;
      }
      var tabId = hash.replace('#', '');
      var tabButton = $('[data-bs-target="#' + tabId + '"]');
      if (tabButton.length) {
        // Deactivate all tabs
        $('.nav-pills .nav-link').removeClass('active');
        $('.tab-pane').removeClass('show active');

        // Activate the target tab
        tabButton.addClass('active').attr('aria-selected', 'true');
        $(hash).addClass('show active');

        console.log('✅ Activated tab: ' + tabId);
      }
    }
  }

  // Run on page load
  activateTabFromHash();

  // Also handle hash changes
  $(window).on('hashchange', activateTabFromHash);

  $.ajaxSetup({
    headers: { 'X-CSRF-TOKEN': CSRF }
  });

  // ==========================================================
  // 🟦 WALLET CREDIT / DEBIT
  // ==========================================================
  $('#submitWalletTxnBtn').on('click', function () {

    const type      = $('#txn-type').val();
    const amount    = $('#txn-amount').val();
    const reference = $('#txn-reference').val();
    const idempotencyKey = $('#txn-idempotency-key').val();

    if (!amount || parseFloat(amount) <= 0) {
      toastr.warning('Please enter a valid amount');
      return;
    }

    Swal.fire({
      title: type === 'credit' ? 'Crediting wallet...' : 'Debiting wallet...',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });

    $.ajax({
      url: APP_URL + '/backend/wallet/' + userId + '/transaction',
      type: 'POST',
      data: {
        type: type,
        amount: amount,
        reference: reference,
        idempotency_key: idempotencyKey
      },
      headers: { 'Accept': 'application/json' },
      success: res => {
        Swal.close();
        toastr.success(res.message);
        $('#walletTransactionModal').modal('hide');
        $('#txn-amount').val('');
        $('#txn-reference').val('');
        location.reload();
      },
      error: xhr => {
        Swal.close();
        toastr.error(xhr.responseJSON?.message || 'Transaction failed');
      }
    });
  });

  // ==========================================================
  // 🟦 SERIES DATATABLE
  // ==========================================================
  var dtSeries = $('.datatable-series');
  if (dtSeries.length) {
    dtSeries.DataTable({
      ordering: false,
      paging: false,
      ajax: APP_URL + '/events/ajax/series',
      columns: [
        { data: 'id' },
        { data: 'name' },
        { data: null },
        { data: null },
        { data: null },
      ],
      columnDefs: [
        {
          targets: 2,
          render: function (data, type, full) {
            return '<a href="' + APP_URL + '/backend/ranking/settings/' + full.id + '" class="btn btn-sm btn-warning">Settings</a>';
          }
        },
        {
          targets: 3,
          render: function (data, type, full) {
            var btnClass = full.leaderboard_published ? 'btn-success' : 'btn-danger';
            var text = full.leaderboard_published ? 'Published' : 'Not Published';
            return '<div data-id="' + full.id + '" class="btn ' + btnClass + ' btn-sm publishLeaderboard">' + text + '</div>';
          }
        },
        {
          targets: 4,
          render: function (data, type, full) {
            return '<a href="' + APP_URL + '/backend/ranking/' + full.id + '" class="btn btn-sm btn-secondary">Show</a>';
          }
        },
      ],
      initComplete: function () {
        $(document).on('click', '.publishLeaderboard', function () {
          var id = $(this).data('id');
          var $btn = $(this);
          $.get(APP_URL + '/backend/series/publishLeaderboard/' + id, function (data) {
            if (data.leaderboard_published == 1) {
              $btn.removeClass('btn-danger').addClass('btn-success').text('Published');
            } else {
              $btn.removeClass('btn-success').addClass('btn-danger').text('Not Published');
            }
          });
        });
      }
    });
  }

  // ==========================================================
  // 🟦 USERS DATATABLE
  // ==========================================================
  var dtUsers = $('.datatable-users');
  if (dtUsers.length) {
    dtUsers.DataTable({
      ordering: true,
      pageLength: 25,
      ajax: APP_URL + '/backend/user',
      columns: [
        { data: 'id' },
        { data: 'name' },
        { data: 'email' },
        { data: null },
        { data: null },
      ],
      columnDefs: [
        {
          targets: 3,
          render: function (data, type, full) {
            if (full.roles && full.roles.length) {
              return full.roles.map(function (r) {
                return '<span class="badge bg-label-primary me-1">' + r.name + '</span>';
              }).join('');
            }
            return '<span class="text-muted">—</span>';
          }
        },
        {
          targets: 4,
          render: function (data, type, full) {
            return '<a href="' + APP_URL + '/backend/user/' + full.id + '" class="btn btn-sm btn-secondary">View</a>';
          }
        },
      ],
    });
  }

  // ==========================================================
  // 🟦 PLAYERS DATATABLE
  // ==========================================================
  var dtPlayers = $('.datatable-players');
  if (dtPlayers.length) {
    dtPlayers.DataTable({
      ordering: true,
      pageLength: 25,
      ajax: APP_URL + '/backend/player',
      columns: [
        { data: 'id' },
        { data: null },
        { data: null },
        { data: null },
        { data: null },
      ],
      columnDefs: [
        {
          targets: 1,
          render: function (data, type, full) {
            return (full.name || '') + ' ' + (full.surname || '');
          }
        },
        {
          targets: 2,
          render: function (data, type, full) {
            return '<a href="' + APP_URL + '/backend/player/profile/' + full.id + '" class="btn btn-primary btn-sm">Profile</a>';
          }
        },
        {
          targets: 3,
          render: function (data, type, full) {
            return '<a href="' + APP_URL + '/backend/player/results/' + full.id + '" class="btn btn-secondary btn-sm">Results</a>';
          }
        },
        {
          targets: 4,
          render: function (data, type, full) {
            return '<a href="' + APP_URL + '/backend/player/details/' + full.id + '" class="btn btn-info btn-sm">Details</a>';
          }
        },
      ],
    });
  }

  // ==========================================================
  // 🟦 ACTIVITY LOG DATATABLE
  // ==========================================================
  var dtActivity = $('#datatable-activity');
  if (dtActivity.length) {
    dtActivity.DataTable({
      ordering: true,
      order: [[0, 'asc']],
      pageLength: 50,
      columnDefs: [
        { targets: 4, orderable: false, searchable: false },
        { targets: 5, visible: false }
      ],
      drawCallback: function () {
        var body = this.api().table().body();
        $(body).find('[data-bs-toggle="popover"]').each(function () {
          if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
            new bootstrap.Popover(this);
          }
        });
      }
    });
  }

  // Raw activity table
  var dtActivityRawEl = $('#datatable-activity-raw');
  var dtActivityRaw = null;
  if (dtActivityRawEl.length) {
    dtActivityRaw = dtActivityRawEl.DataTable({
      ordering: true,
      order: [[0, 'desc']],
      pageLength: 50,
      columnDefs: [
        { targets: 4, orderable: false, searchable: false }
      ],
      drawCallback: function () {
        var body = this.api().table().body();
        $(body).find('[data-bs-toggle="popover"]').each(function () {
          if (typeof bootstrap !== 'undefined' && bootstrap.Popover) {
            new bootstrap.Popover(this);
          }
        });
      }
    });
  }

  // Activity filter by log name
  $('#activity-filter-log').on('change', function () {
    var val = $(this).val();
    // Filter grouped table by hidden log-names column (index 5)
    try {
      var table = $('#datatable-activity').DataTable();
      if (val) table.column(5).search(val).draw(); else table.column(5).search('').draw();
    } catch (e) {}

    // Filter raw table by log column (index 2)
    if (dtActivityRaw) {
      if (val) dtActivityRaw.column(2).search('^' + val + '$', true, false).draw(); else dtActivityRaw.column(2).search('').draw();
    }
  });

  // Toggle grouped / raw view
  $('#activity-toggle-view').on('change', function () {
    var checked = $(this).is(':checked');
    if (checked) {
      $('#datatable-activity').removeClass('d-none');
      $('#datatable-activity-raw').addClass('d-none');
    } else {
      $('#datatable-activity').addClass('d-none');
      $('#datatable-activity-raw').removeClass('d-none');
    }
  });

  // Adjust DataTable columns when Activity tab is first shown (hidden tabs issue)
  $('button[data-bs-target="#tab-activity"]').on('shown.bs.tab', function () {
    $('#datatable-activity').DataTable().columns.adjust().draw(false);
    if (dtActivityRaw) dtActivityRaw.columns.adjust().draw(false);
  });

  // ==========================================================
  // 🟦 SELECT2 (modal-safe)
  // ==========================================================
 $('#addProfileModal').on('shown.bs.modal', function () {
  if ($('#player-select').hasClass('select2-hidden-accessible')) return;

  $('#player-select').select2({
    dropdownParent: $('#addProfileModal'),
    placeholder: 'Search for a player',
    width: '100%',
    allowClear: true,
    minimumInputLength: 2,
    ajax: {
      url: <?php echo json_encode(route('player.search'), 15, 512) ?>,
      dataType: 'json',
      delay: 250,
      data: params => ({
        q: params.term || '',
        page: params.page || 1,
        format: 'select2'
      }),
      processResults: data => data,
      cache: true
    }
  });
});

$('#linkPlayerBtn').on('click', function () {

  const playerId = $('#player-select').val();

  if (!playerId) {
    toastr.warning('Select a player');
    return;
  }

  Swal.fire({
    title: 'Linking player...',
    allowOutsideClick: false,
    didOpen: () => Swal.showLoading()
  });

  $.post(
    APP_URL + '/backend/user/' + $('#user').val() + '/players',
    { player_id: playerId }
  )
  .done(res => {
    Swal.close();
    toastr.success(res.message);

    // 🔁 update UI dynamically (or reload if you prefer)
    location.reload();
  })
  .fail(xhr => {
    Swal.close();
    toastr.error(xhr.responseJSON?.message || 'Failed');
  });
});


  $('#addProfileModal').on('hidden.bs.modal', function () {
    $('#add-player-select').val(null).trigger('change');
  });

  // ==========================================================
  // 🟦 ADD PLAYER TO PROFILE
  // ==========================================================
  $('#addPlayerToProfileButton').on('click', function () {

    const playerId = $('#add-player-select').val();

    if (!playerId) {
      toastr.warning('Please select a player');
      return;
    }

    Swal.fire({
      title: 'Linking player...',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });

    $.post(
      APP_URL + '/backend/user/' + userId + '/players',
      { player_id: playerId }
    )
    .done(res => {
      Swal.close();
      toastr.success(res.message);
      $('#addProfileModal').modal('hide');
      location.reload();
    })
    .fail(xhr => {
      Swal.close();
      toastr.error(xhr.responseJSON?.message || 'Failed to link player');
    });
  });



  // ==========================================================
  // 🟦 EDIT USER PROFILE
  // ==========================================================
  $(document).on('submit', '#editUserForm', function (e) {
    e.preventDefault();

    Swal.fire({
      title: 'Saving...',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });

    $.ajax({
      url: APP_URL + '/backend/user/' + userId,
      type: 'PUT',
      data: $(this).serialize(),
      success: res => {
        Swal.close();
        toastr.success(res.message);
        $('#editUser').modal('hide');
        location.reload();
      },
      error: xhr => {
        Swal.close();
        toastr.error(xhr.responseJSON?.message || 'Update failed');
      }
    });
  });
  $(document).on('click', '.unlink-player', function () {

  const btn     = $(this);
  const userId  = btn.data('user');
  const playerId = btn.data('player');
  const row     = btn.closest('.linked-player-row');

  Swal.fire({
    title: 'Unlink player?',
    text: 'This will remove the player from this profile.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, unlink'
  }).then(result => {

    if (!result.isConfirmed) return;

    $.ajax({
      url: APP_URL + `/backend/user/${userId}/players/${playerId}`,
      type: 'DELETE',
      success: res => {
        toastr.success(res.message);
        row.slideUp(200, () => row.remove());
      },
      error: xhr => {
        toastr.error(xhr.responseJSON?.message || 'Failed to unlink');
      }
    });

  });
});

  // ==========================================================
  // 🟦 CREATE EVENT (super-admin)
  // ==========================================================
  if ($('#addEvent').length) {

    // Select2 for admin picker inside modal
    $('#addEvent').on('shown.bs.modal', function () {
      if ($('.select2user').hasClass('select2-hidden-accessible')) return;

      $('.select2user').select2({
        dropdownParent: $('#addEvent'),
        placeholder: 'Search for an admin',
        width: '100%',
        allowClear: true,
        minimumInputLength: 2,
        ajax: {
          url: <?php echo json_encode(route('backend.user.search'), 15, 512) ?>,
          dataType: 'json',
          delay: 250,
          data: params => ({
            q: params.term || '',
            page: params.page || 1
          }),
          processResults: data => data,
          cache: true
        }
      });
    });

    // Quill editor for information field
    if ($('#full-editor').length) {
      var quillEditor = new Quill('#full-editor', {
        bounds: '#full-editor',
        placeholder: 'Type Something...',
        modules: {
          formula: true,
          toolbar: [
            [{ font: [] }, { size: [] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ color: [] }, { background: [] }],
            [{ header: '1' }, { header: '2' }, 'blockquote'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['link', 'image'],
            ['clean']
          ]
        },
        theme: 'snow'
      });

      // AJAX submit
      $('#createEventButton').on('click', function () {
        var information = quillEditor.root.innerHTML;
        var data = $('#addEvent form').serialize() + '&info=' + encodeURIComponent(information);

        Swal.fire({
          title: 'Creating event...',
          allowOutsideClick: false,
          didOpen: () => Swal.showLoading()
        });

        $.ajax({
          url: APP_URL + '/events',
          method: 'POST',
          data: data,
          success: function (res) {
            Swal.close();
            toastr.success('Event created successfully');
            $('#addEvent').modal('hide');
            location.reload();
          },
          error: function (xhr) {
            Swal.close();
            toastr.error(xhr.responseJSON?.message || 'Failed to create event');
          }
        });
      });
    }
  }

});
</script>
<?php $__env->stopSection(); ?>



<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\dashboard.blade.php ENDPATH**/ ?>