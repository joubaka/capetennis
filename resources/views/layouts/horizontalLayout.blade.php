@isset($pageConfigs)
{!! Helper::updatePageConfig($pageConfigs) !!}
@endisset
@php
$configData = Helper::appClasses();
@endphp

@extends('layouts/commonMaster' )
@php

$menuHorizontal = true;
$navbarFull = true;

/* Display elements */
$isNavbar = ($isNavbar ?? true);
$isMenu = ($isMenu ?? true);
$isFlex = ($isFlex ?? false);
$isFooter = ($isFooter ?? true);
$customizerHidden = ($customizerHidden ?? '');
$pricingModal = ($pricingModal ?? false);

/* HTML Classes */
$menuFixed = (isset($configData['menuFixed']) ? $configData['menuFixed'] : '');
$navbarFixed = (isset($configData['navbarFixed']) ? $configData['navbarFixed'] : '');
$footerFixed = (isset($configData['footerFixed']) ? $configData['footerFixed'] : '');
$menuCollapsed = (isset($configData['menuCollapsed']) ? $configData['menuCollapsed'] : '');

/* Content classes */
$container = ($container ?? 'container-xxl');
$containerNav = ($containerNav ?? 'container-xxl');

@endphp

@section('layoutContent')
<div class="layout-wrapper layout-navbar-full layout-horizontal layout-without-menu">
  <div class="layout-container">

    <!-- BEGIN: Navbar-->
    @if ($isNavbar)
    @include('layouts/sections/navbar/navbar')
    @endif
    <!-- END: Navbar-->


    <!-- Layout page -->
    <div class="layout-page">

      <!-- Content wrapper -->
      <div class="content-wrapper">

        @if ($isMenu)
        @include('layouts/sections/menu/horizontalMenu')

        @endif

        <!-- Content -->
        @if ($isFlex)
        <div class="{{$container}} d-flex align-items-stretch flex-grow-1 p-0">

        @else

          <div class="{{$container}} flex-grow-1 container-p-y">
            @endif

            @yield('content')

            <!-- pricingModal -->
            @if ($pricingModal)
            @include('_partials/_modals/modal-pricing')
            @endif
            <!--/ pricingModal -->

          </div>
          <!-- / Content -->

          {{-- Keep the menu adjacent to content: the theme uses an adjacent-sibling spacing rule. --}}
          @if ($isMenu)
        <script>
          (function () {
            const toggle = document.querySelector('.mobile-horizontal-menu-toggle');
            const layout = document.querySelector('.layout-horizontal');
            const menu = document.getElementById('layout-menu');

            if (!toggle || !layout || !menu || toggle.dataset.menuReady === 'true') return;
            toggle.dataset.menuReady = 'true';

            const closeMenu = function () {
              layout.classList.remove('mobile-menu-open');
              document.documentElement.classList.remove('layout-menu-expanded');
              toggle.setAttribute('aria-expanded', 'false');
              toggle.setAttribute('aria-label', 'Open navigation menu');
            };

            toggle.addEventListener('click', function (event) {
              event.preventDefault();
              event.stopPropagation();
              const open = layout.classList.toggle('mobile-menu-open');
              document.documentElement.classList.toggle('layout-menu-expanded', open);
              toggle.setAttribute('aria-expanded', String(open));
              toggle.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
            });

            document.addEventListener('click', function (event) {
              if (layout.classList.contains('mobile-menu-open') && !menu.contains(event.target) && !toggle.contains(event.target)) {
                closeMenu();
              }
            });

            menu.addEventListener('click', function (event) {
              if (event.target.closest('a:not(.menu-toggle)')) closeMenu();
            });

            document.addEventListener('keydown', function (event) {
              if (event.key === 'Escape' && layout.classList.contains('mobile-menu-open')) {
                closeMenu();
                toggle.focus();
              }
            });

            window.addEventListener('resize', function () {
              if (window.innerWidth >= 1200) closeMenu();
            });
          })();
        </script>
          @endif

          <!-- Footer -->
          @if ($isFooter)
          @include('layouts/sections/footer/footer')
          @endif
          <!-- / Footer -->
          <div class="content-backdrop fade"></div>
        </div>
        <!--/ Content wrapper -->
      </div>
      <!-- / Layout page -->
    </div>
    <!-- / Layout Container -->

    @if ($isMenu)
    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>
    @endif
    <!-- Drag Target Area To SlideIn Menu On Small Screens -->
    <div class="drag-target"></div>
  </div>
  <!-- / Layout wrapper -->
  @endsection
