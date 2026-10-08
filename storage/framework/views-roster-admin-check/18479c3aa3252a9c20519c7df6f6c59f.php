<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($pageConfigs)): ?>
<?php echo Helper::updatePageConfig($pageConfigs); ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php
$configData = Helper::appClasses();
?>


<?php

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

?>

<?php $__env->startSection('layoutContent'); ?>
<div class="layout-wrapper layout-navbar-full layout-horizontal layout-without-menu">
  <div class="layout-container">

    <!-- BEGIN: Navbar-->
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isNavbar): ?>
    <?php echo $__env->make('layouts/sections/navbar/navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <!-- END: Navbar-->


    <!-- Layout page -->
    <div class="layout-page">

      <!-- Content wrapper -->
      <div class="content-wrapper">

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isMenu): ?>
        <?php echo $__env->make('layouts/sections/menu/horizontalMenu', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <!-- Content -->
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isFlex): ?>
        <div class="<?php echo e($container); ?> d-flex align-items-stretch flex-grow-1 p-0">

        <?php else: ?>

          <div class="<?php echo e($container); ?> flex-grow-1 container-p-y">
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php echo $__env->yieldContent('content'); ?>

            <!-- pricingModal -->
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pricingModal): ?>
            <?php echo $__env->make('_partials/_modals/modal-pricing', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <!--/ pricingModal -->

          </div>
          <!-- / Content -->

          
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isMenu): ?>
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
              if (open) {
                window.requestAnimationFrame(() => menu.querySelector('a, button')?.focus());
              }
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
              if (event.key === 'Tab' && layout.classList.contains('mobile-menu-open')) {
                const focusable = [...menu.querySelectorAll('a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])')]
                  .filter(element => element.offsetParent !== null);
                if (!focusable.length) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                  event.preventDefault();
                  last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                  event.preventDefault();
                  first.focus();
                }
              }
            });

            window.addEventListener('resize', function () {
              if (window.innerWidth >= 1200) closeMenu();
            });
          })();
        </script>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          <!-- Footer -->
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isFooter): ?>
          <?php echo $__env->make('layouts/sections/footer/footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <!-- / Footer -->
          <div class="content-backdrop fade"></div>
        </div>
        <!--/ Content wrapper -->
      </div>
      <!-- / Layout page -->
    </div>
    <!-- / Layout Container -->

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isMenu): ?>
    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <!-- Drag Target Area To SlideIn Menu On Small Screens -->
    <div class="drag-target"></div>
  </div>
  <!-- / Layout wrapper -->
  <?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/commonMaster' , array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\layouts\horizontalLayout.blade.php ENDPATH**/ ?>