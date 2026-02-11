<style>
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
        <li class="nav-item me-2">
          <span class="btn btn-outline-primary btn-sm position-relative disabled">
            <i class="ti ti-clock"></i>
            Bank refunds
            <span class="ml-2 badge rounded-pill bg-success text-dark">
              {{ $pendingBankRefundCount }}
            </span>
          </span>
        </li>
      @endif

      {{-- Profile shortcut --}}
      @if (Auth::check())
        <li class="nav-item me-2">
          <a href="{{ route('dashboard') }}" class="btn btn-warning btn-sm">
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
            <a class="dropdown-item" href="{{ route('dashboard') }}">
              <div class="d-flex">
                <div class="flex-shrink-0 me-3">
                  <div class="avatar avatar-online">
                    <img src="{{ Auth::user() ? Auth::user()->profile_photo_url : asset('assets/img/avatars/1.png') }}"
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

          <li>
            <a class="dropdown-item" href="{{ route('dashboard') }}">
              <i class="ti ti-user-check me-2"></i>
              My Profile
            </a>
          </li>

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
