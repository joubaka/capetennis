<style>
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

@php
$containerNav = $containerNav ?? 'container-fluid';
$navbarDetached = ($navbarDetached ?? '');
@endphp

<!-- Navbar -->
@if(isset($navbarDetached) && $navbarDetached == 'navbar-detached')
<nav class="layout-navbar {{$containerNav}} navbar navbar-expand-xl {{$navbarDetached}} align-items-center bg-navbar-theme" id="layout-navbar">
@endif
@if(isset($navbarDetached) && $navbarDetached == '')
<nav class="layout-navbar navbar navbar-expand-xl align-items-center bg-navbar-theme" id="layout-navbar">
  <div class="{{$containerNav}}">

    <button type="button" class="mobile-horizontal-menu-toggle d-xl-none" aria-controls="layout-menu" aria-expanded="false" aria-label="Open navigation menu">
      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
        <path d="M4 6h16M4 12h16M4 18h16"></path>
      </svg>
      @if(auth()->check()
          && auth()->user()->hasAnyRole(['super-user','admin'])
          && ($pendingBankRefundCount ?? 0) > 0)
        <span class="mobile-menu-refund-badge" aria-label="{{ $pendingBankRefundCount }} pending bank refunds">
          {{ $pendingBankRefundCount }}
        </span>
      @endif
    </button>
@endif

  <!-- Brand -->
  @if(isset($navbarFull))
  <div class="navbar-brand app-brand demo d-none d-xl-flex py-0 me-4">
    <a href="{{ url('/') }}" class="app-brand-link gap-2">
      <span class="app-brand-logo demo">
        @include('_partials.macros',["height"=>20])
      </span>
      <span class="app-brand-text demo menu-text fw-bold">
        {{ config('variables.templateName') }}
      </span>
    </a>
  </div>
  @endif

  <!-- Menu toggle -->
  @if(!isset($navbarHideToggle))
  <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0
      {{ isset($menuHorizontal) ? ' d-xl-none ' : '' }}
      {{ isset($contentNavbar) ? ' d-xl-none ' : '' }}"
      style="z-index:1051;">
    <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)">
      <i class="ti ti-menu-2 ti-sm"></i>
    </a>
  </div>
  @endif

  <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">

    <!-- Style Switcher -->
    <div class="navbar-nav align-items-center">
      <a class="nav-link style-switcher-toggle hide-arrow" href="javascript:void(0);">
        <i class="ti ti-sm"></i>
      </a>
    </div>

    <ul class="navbar-nav flex-row align-items-center ms-auto">

      {{-- 🔔 ADMIN: Pending bank refunds --}}
      @if(auth()->check()
          && auth()->user()->hasAnyRole(['super-user','admin'])
          && ($pendingBankRefundCount ?? 0) > 0)
        <li class="nav-item me-2 mobile-navbar-action">
          <a href="{{ route('admin.refunds.bank.index') }}" class="btn btn-outline-primary btn-sm position-relative">
            <i class="ti ti-clock"></i>
            Bank refunds
            <span class="ml-2 badge rounded-pill bg-success text-dark">
              {{ $pendingBankRefundCount }}
            </span>
          </a>
        </li>
      @endif

      {{-- Super-user: Add New Event --}}
      @if(auth()->check() && auth()->user()->hasRole('super-user'))
        <li class="nav-item me-2 mobile-navbar-action">
          <a href="{{ route('backend.events.create') }}" class="btn btn-success btn-sm">
            <i class="ti ti-plus me-1"></i> New Event
          </a>
        </li>
      @endif

      {{-- Profile shortcut --}}
      @if (Auth::check())
        <li class="nav-item me-2 mobile-navbar-action">
          <a href="{{ route('my.tennis') }}" class="btn btn-outline-success btn-sm">
            <i class="ti ti-ball-tennis me-1"></i> My Tennis
          </a>
        </li>
        <li class="nav-item me-2 mobile-navbar-action">
          <a href="{{ route('backend.dashboard') }}" class="btn btn-warning btn-sm">
            My Profile
          </a>
        </li>
      @else
        <li class="nav-item">
          <a class="dropdown-item" href="{{ route('login') }}">
            <i class="ti ti-login me-2"></i>
            Login
          </a>
        </li>
      @endif

      <!-- User dropdown -->
      <li class="nav-item navbar-dropdown dropdown-user dropdown">
        <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
          <div class="avatar avatar-online">
            @auth
              <img src="{{ Auth::user()->profile_photo_url }}"
                   class="w-px-40 h-auto rounded-circle">
            @else
              <span class="badge bg-label-primary">Guest</span>
            @endauth
          </div>
        </a>

        <ul class="dropdown-menu dropdown-menu-end">

          <li>
            <a class="dropdown-item" href="{{ route('backend.dashboard') }}">
              <div class="d-flex">
                <div class="flex-shrink-0 me-3">
                  <div class="avatar avatar-online">
                    <img src="{{ Auth::user() ? Auth::user()->profile_photo_url : asset('assets/img/avatars/default.svg') }}"
                         class="w-px-40 h-auto rounded-circle">
                  </div>
                </div>
                <div class="flex-grow-1">
                  <span class="fw-semibold d-block">
                    {{ Auth::user()->name ?? 'Guest' }}
                  </span>
                </div>
              </div>
            </a>
          </li>

          <li><div class="dropdown-divider"></div></li>

          @auth
          <li>
            <a class="dropdown-item" href="{{ route('my.tennis') }}">
              <i class="ti ti-ball-tennis me-2"></i>
              My Tennis
            </a>
          </li>
          @endauth

          <li>
            <a class="dropdown-item" href="{{ route('backend.dashboard') }}">
              <i class="ti ti-user-check me-2"></i>
              My Profile
            </a>
          </li>

          @auth
          <li>
            <a class="dropdown-item" href="{{ route('player.profile.create') }}">
              <i class="ti ti-user-plus me-2"></i>
              Add Player Profile
            </a>
          </li>
          @endauth

          {{-- Wallet --}}
          @auth
          <li>
            <a class="dropdown-item" href="{{ route('wallet.show', Auth::id()) }}">
              <i class="fa-solid fa-wallet me-2"></i>
              My Wallet
            </a>
          </li>
          @endauth

          @if (Auth::check() && Laravel\Jetstream\Jetstream::hasApiFeatures())
          <li>
            <a class="dropdown-item" href="{{ route('api-tokens.index') }}">
              <i class="ti ti-key me-2"></i>
              API Tokens
            </a>
          </li>
          @endif

          <li><div class="dropdown-divider"></div></li>

          @if (Auth::check())
          <li>
            <a class="dropdown-item"
               href="{{ route('logout') }}"
               onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
              <i class="ti ti-logout me-2"></i>
              Logout
            </a>
          </li>

          <form method="POST" id="logout-form" action="{{ route('logout') }}">
            @csrf
          </form>
          @else
          <li>
            <a class="dropdown-item" href="{{ route('login') }}">
              <i class="ti ti-login me-2"></i>
              Login
            </a>
          </li>
          @endif

        </ul>
      </li>
      <!--/ User -->
    </ul>
  </div>

@if(!isset($navbarDetached))
  </div>
@endif

</nav>
<!-- / Navbar -->
