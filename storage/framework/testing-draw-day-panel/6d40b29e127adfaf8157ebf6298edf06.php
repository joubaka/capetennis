<style>
.desktop-announcement-badge {
  position: absolute;
  top: 0;
  right: 0;
}

@media (max-width: 575.98px) {
  .desktop-announcement-badge { display: none !important; }

  .mobile-announcement-trigger {
    position: relative;
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    padding: 0;
  }

  .mobile-announcement-trigger .ti { font-size: 1.2rem; }

  .mobile-announcement-trigger [data-announcement-menu-badge] {
    position: absolute;
    top: 0;
    right: 0;
    font-size: .65rem;
    padding: .2em .4em;
  }
}

@media (max-width: 1199.98px) {
  .mobile-horizontal-menu-toggle {
    align-items: center;
    background: #fff;
    border: 1px solid rgba(75, 70, 92, .18);
    border-radius: .5rem;
    color: #5d596c;
    display: inline-flex !important;
    flex: 0 0 2.5rem;
    font-size: 1.35rem;
    height: 2.5rem;
    justify-content: center;
    margin-right: .75rem;
    order: -1;
    padding: .35rem;
    position: relative;
    width: 2.5rem;
    z-index: 1101;
  }

  .mobile-horizontal-menu-toggle svg {
    display: block;
    height: 1.35rem;
    stroke: currentColor;
    stroke-linecap: round;
    stroke-width: 2;
    width: 1.35rem;
  }

  .mobile-menu-refund-badge {
    align-items: center;
    background: #28c76f;
    border: 2px solid #fff;
    border-radius: 999px;
    color: #fff;
    display: flex;
    font-size: .65rem;
    font-weight: 700;
    height: 1.25rem;
    justify-content: center;
    min-width: 1.25rem;
    padding: 0 .2rem;
    position: absolute;
    right: -.45rem;
    top: -.45rem;
  }

  .mobile-navbar-action {
    display: none !important;
  }

  .layout-horizontal .layout-menu-toggle {
    display: none !important;
  }

  .layout-horizontal #layout-menu {
    background: var(--bs-body-bg, #fff);
    box-shadow: 0 .5rem 1rem rgba(75, 70, 92, .14);
    display: none;
    left: 0;
    position: fixed;
    bottom: 0;
    max-width: calc(100vw - 3rem);
    right: auto;
    top: 0;
    transform: translate3d(-100%, 0, 0) !important;
    transition: transform .25s ease;
    visibility: hidden;
    width: 17rem;
    z-index: 1095;
  }

  .layout-horizontal.mobile-menu-open #layout-menu {
    display: block;
    transform: translate3d(0, 0, 0) !important;
    visibility: visible;
  }

  .layout-horizontal.mobile-menu-open::before {
    background: rgba(47, 43, 61, .38);
    content: '';
    inset: 0;
    position: fixed;
    z-index: 1090;
  }

  .layout-horizontal #layout-menu .menu-inner {
    display: flex;
    flex-direction: column;
    max-height: 100vh;
    overflow-y: auto;
    padding: .5rem 0;
  }

  .layout-horizontal #layout-menu .menu-item,
  .layout-horizontal #layout-menu .menu-link {
    width: 100%;
  }

  .layout-horizontal #layout-menu .menu-link {
    align-items: center;
    display: flex;
    min-height: 2.75rem;
    padding: .65rem 1rem;
  }
}

@media (max-width: 1199px) {

  /* 🔴 REAL CLICK BLOCKER FIX */
  .navbar-nav-right {
    position: static !important;
  }

  .navbar-nav-right > ul.navbar-nav {
    pointer-events: none;
  }

  .navbar-nav-right > ul.navbar-nav > li,
  .navbar-nav-right > ul.navbar-nav > li * {
    pointer-events: auto;
  }

  /* Ensure menu toggle always wins */
  .layout-menu-toggle {
    position: relative;
    z-index: 1100;
  }
}
</style>

<?php
$containerNav = $containerNav ?? 'container-fluid';
$navbarDetached = ($navbarDetached ?? '');
?>

<!-- Navbar -->
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($navbarDetached) && $navbarDetached == 'navbar-detached'): ?>
<nav class="layout-navbar <?php echo e($containerNav); ?> navbar navbar-expand-xl <?php echo e($navbarDetached); ?> align-items-center bg-navbar-theme" id="layout-navbar">
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($navbarDetached) && $navbarDetached == ''): ?>
<nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
  <div class="<?php echo e($containerNav); ?>">

    <button type="button" class="mobile-horizontal-menu-toggle d-xl-none" aria-controls="layout-menu" aria-expanded="false" aria-label="Open navigation menu">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
        <path d="M4 6h16M4 12h16M4 18h16"></path>
      </svg>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->check()
          && auth()->user()->hasAnyRole(['super-user','admin'])
          && ($pendingBankRefundCount ?? 0) > 0): ?>
        <span class="mobile-menu-refund-badge" aria-label="<?php echo e($pendingBankRefundCount); ?> pending bank refunds">
          <?php echo e($pendingBankRefundCount); ?>

        </span>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </button>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <!-- Brand -->
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($navbarFull)): ?>
  <div class="navbar-brand app-brand demo d-none d-xl-flex py-0 me-4">
    <a href="<?php echo e(url('/')); ?>" class="app-brand-link gap-2">
      <span class="app-brand-logo demo">
        <?php echo $__env->make('_partials.macros',["height"=>20], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      </span>
      <span class="app-brand-text demo menu-text fw-bold">
        <?php echo e(config('variables.templateName')); ?>

      </span>
    </a>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <!-- Menu toggle -->
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!isset($navbarHideToggle)): ?>
  <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0
      <?php echo e(isset($menuHorizontal) ? ' d-xl-none ' : ''); ?>

      <?php echo e(isset($contentNavbar) ? ' d-xl-none ' : ''); ?>"
      style="z-index:1051;">
    <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
      <i class="ti ti-menu-2 ti-sm"></i>
    </a>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
    <style>
      .ct-app-navigation { display: none; flex-shrink: 0; gap: .375rem; margin-right: .5rem; }
      .ct-app-navigation .btn { min-width: 44px; min-height: 44px; padding: .5rem; }
      .ct-app-standalone .ct-app-navigation { display: flex; }
      @media (display-mode: standalone), (display-mode: minimal-ui), (display-mode: window-controls-overlay) {
        .ct-app-navigation { display: flex; }
      }
      @media (max-width: 575.98px) {
        .ct-app-navigation-label { display: none; }
      }
    </style>
    <div class="ct-app-navigation" role="group" aria-label="App navigation">
      <button type="button" class="btn btn-outline-primary" id="ct-app-back" aria-label="Go back" title="Go back">
        <i class="ti ti-arrow-left" aria-hidden="true"></i>
        <span class="ct-app-navigation-label">Back</span>
      </button>
      <button type="button" class="btn btn-outline-primary" id="ct-app-refresh" aria-label="Refresh page" title="Refresh page">
        <i class="ti ti-refresh" aria-hidden="true"></i>
        <span class="ct-app-navigation-label">Refresh</span>
      </button>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('event_mail_issues')): ?>
      <?php ($emailIssueCount=\App\Models\EventMailIssue::where('user_id',Auth::id())->whereNull('read_at')->count()); ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($emailIssueCount): ?><a class="btn btn-sm btn-outline-danger me-2 d-none d-sm-inline-flex align-items-center gap-1" href="<?php echo e(route('backend.email-issues')); ?>" aria-label="<?php echo e($emailIssueCount); ?> email issues need attention">Email issues <span class="badge bg-danger"><?php echo e($emailIssueCount); ?></span></a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <!-- Style Switcher -->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($backendWorkspace ?? false)): ?>
    <div class="navbar-nav align-items-center">
      <a class="nav-link style-switcher-toggle hide-arrow" href="javascript:void(0);">
        <i class="ti ti-sm"></i>
      </a>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <ul class="navbar-nav flex-row align-items-center ms-auto">

      
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->check()
          && auth()->user()->hasAnyRole(['super-user','admin'])
          && ($pendingBankRefundCount ?? 0) > 0): ?>
        <li class="nav-item me-2 mobile-navbar-action">
          <a href="<?php echo e(route('admin.refunds.bank.index')); ?>" class="btn btn-outline-primary btn-sm position-relative">
            <i class="ti ti-clock"></i>
            Bank refunds
            <span class="ml-2 badge rounded-pill bg-success text-dark">
              <?php echo e($pendingBankRefundCount); ?>

            </span>
          </a>
        </li>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->check() && auth()->user()->hasRole('super-user')): ?>
        <li class="nav-item me-2 mobile-navbar-action">
          <a href="<?php echo e(route('backend.events.create')); ?>" class="btn btn-success btn-sm">
            <i class="ti ti-plus me-1"></i> New Event
          </a>
        </li>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::check()): ?>
        <li class="nav-item me-2 mobile-navbar-action">
          <a href="<?php echo e(route('my.tennis')); ?>" class="btn btn-outline-success btn-sm">
            <i class="ti ti-ball-tennis me-1"></i> My Tennis
          </a>
        </li>
        <li class="nav-item me-2 mobile-navbar-action">
          <a href="<?php echo e(route('backend.dashboard')); ?>" class="btn btn-warning btn-sm">
            My Profile
          </a>
        </li>
      <?php else: ?>
        <li class="nav-item">
          <a class="dropdown-item" href="<?php echo e(route('login')); ?>">
            <i class="ti ti-login me-2"></i>
            Login
          </a>
        </li>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
        <li class="nav-item d-sm-none me-1">
          <button type="button" class="btn btn-outline-primary mobile-announcement-trigger"
                  data-bs-toggle="modal" data-bs-target="#announcement-inbox" aria-haspopup="dialog">
            <i class="ti ti-speakerphone" aria-hidden="true"></i>
            <span class="visually-hidden">Event announcements</span>
            <span class="badge rounded-pill d-none" role="img" data-announcement-menu-badge></span>
          </button>
        </li>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

      <!-- User dropdown -->
      <li class="nav-item navbar-dropdown dropdown-user dropdown">
        <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
          <div class="avatar avatar-online">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
              <img src="<?php echo e(Auth::user()->profile_photo_url); ?>"
                   class="w-px-40 h-auto rounded-circle" alt="Profile menu">
            <?php else: ?>
              <span class="badge bg-label-primary">Guest</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
        </a>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
          <button type="button" class="badge rounded-pill border-0 d-none desktop-announcement-badge"
                  data-announcement-menu-badge data-bs-toggle="modal" data-bs-target="#announcement-inbox"></button>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <ul class="dropdown-menu dropdown-menu-end">

          <li>
            <a class="dropdown-item" href="<?php echo e(route('backend.dashboard')); ?>">
              <div class="d-flex">
                <div class="flex-shrink-0 me-3">
                  <div class="avatar avatar-online">
                    <img src="<?php echo e(Auth::user() ? Auth::user()->profile_photo_url : asset('assets/img/avatars/default.svg')); ?>"
                         class="w-px-40 h-auto rounded-circle">
                  </div>
                </div>
                <div class="flex-grow-1">
                  <span class="fw-semibold d-block">
                    <?php echo e(Auth::user()->name ?? 'Guest'); ?>

                  </span>
                </div>
              </div>
            </a>
          </li>

          <li><div class="dropdown-divider"></div></li>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($scoringGuideAssignments->isNotEmpty()): ?>
          <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#scoring-guide">How to score</button></li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
          <li>
            <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#announcement-inbox">
              <i class="ti ti-speakerphone me-2" aria-hidden="true"></i>
              Announcements
              <span class="badge rounded-pill ms-1 d-none" role="img" data-announcement-menu-badge></span>
            </button>
          </li>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\Illuminate\Support\Facades\Schema::hasTable('event_mail_issues')): ?>
          <li><a class="dropdown-item" href="<?php echo e(route('backend.email-issues')); ?>">Email issues <span class="badge bg-danger"><?php echo e(\App\Models\EventMailIssue::where('user_id',Auth::id())->whereNull('read_at')->count()); ?></span></a></li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <li>
            <a class="dropdown-item" href="<?php echo e(route('my.tennis')); ?>">
              <i class="ti ti-ball-tennis me-2"></i>
              My Tennis
            </a>
          </li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          <li>
            <a class="dropdown-item" href="<?php echo e(route('backend.dashboard')); ?>">
              <i class="ti ti-user-check me-2"></i>
              My Profile
            </a>
          </li>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
          <li>
            <a class="dropdown-item" href="<?php echo e(route('player.profile.create')); ?>">
              <i class="ti ti-user-plus me-2"></i>
              Add Player Profile
            </a>
          </li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
          <li>
            <a class="dropdown-item" href="<?php echo e(route('wallet.show', Auth::id())); ?>">
              <i class="fa-solid fa-wallet me-2"></i>
              My Wallet
            </a>
          </li>
          <li>
            <a class="dropdown-item <?php echo e(request()->routeIs('account.statement') ? 'active' : ''); ?>" href="<?php echo e(route('account.statement')); ?>" <?php if(request()->routeIs('account.statement')): ?> aria-current="page" <?php endif; ?>>
              <i class="ti ti-file-invoice me-2"></i>
              Account statement
            </a>
          </li>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (\Illuminate\Support\Facades\Blade::check('role', 'super-user')): ?>
          <li><a class="dropdown-item <?php echo e(request()->routeIs('superadmin.account-statements.*') ? 'active' : ''); ?>" href="<?php echo e(route('superadmin.account-statements.index')); ?>" <?php if(request()->routeIs('superadmin.account-statements.*')): ?> aria-current="page" <?php endif; ?>><i class="ti ti-users me-2"></i>User account statements</a></li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::check() && Laravel\Jetstream\Jetstream::hasApiFeatures()): ?>
          <li>
            <a class="dropdown-item" href="<?php echo e(route('api-tokens.index')); ?>">
              <i class="ti ti-key me-2"></i>
              API Tokens
            </a>
          </li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          <li><div class="dropdown-divider"></div></li>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Auth::check()): ?>
          <li>
            <a class="dropdown-item"
               href="<?php echo e(route('logout')); ?>"
               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
              <i class="ti ti-logout me-2"></i>
              Logout
            </a>
          </li>

          <form method="POST" id="logout-form" action="<?php echo e(route('logout')); ?>">
            <?php echo csrf_field(); ?>
          </form>
          <?php else: ?>
          <li>
            <a class="dropdown-item" href="<?php echo e(route('login')); ?>">
              <i class="ti ti-login me-2"></i>
              Login
            </a>
          </li>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        </ul>
      </li>
      <!--/ User -->
    </ul>
  </div>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!isset($navbarDetached)): ?>
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</nav>
<!-- / Navbar -->
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
  <?php echo $__env->make('layouts.sections.announcement-inbox', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($scoringGuideAssignments->isNotEmpty()): ?>
  <?php echo $__env->make('frontend.scoring.guide', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views/layouts/sections/navbar/navbar.blade.php ENDPATH**/ ?>