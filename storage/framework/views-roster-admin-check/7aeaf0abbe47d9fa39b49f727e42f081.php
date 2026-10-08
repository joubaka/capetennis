<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($pageConfigs)): ?>
<?php echo Helper::updatePageConfig($pageConfigs); ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php
$configData = Helper::appClasses();
?>


<?php
/* Display elements */
$contentNavbar = ($contentNavbar ?? true);
$containerNav = ($containerNav ?? 'container-xxl');
$isNavbar = ($isNavbar ?? true);
$isMenu = ($isMenu ?? true);
$isFlex = ($isFlex ?? false);
$isFooter = ($isFooter ?? true);
$customizerHidden = ($customizerHidden ?? '');
$pricingModal = ($pricingModal ?? false);

/* HTML Classes */
$navbarDetached = 'navbar-detached';
$menuFixed = (isset($configData['menuFixed']) ? $configData['menuFixed'] : '');
$navbarFixed = (isset($configData['navbarFixed']) ? $configData['navbarFixed'] : '');
$footerFixed = (isset($configData['footerFixed']) ? $configData['footerFixed'] : '');
$menuCollapsed = (isset($configData['menuCollapsed']) ? $configData['menuCollapsed'] : '');

/* Content classes */
$container = ($container ?? 'container-xxl');

?>

<?php $__env->startSection('layoutContent'); ?>
<div class="layout-wrapper layout-content-navbar <?php echo e($isMenu ? '' : 'layout-without-menu'); ?>">
  <div class="layout-container">

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isMenu): ?>
    <?php echo $__env->make('layouts/sections/menu/verticalMenu', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


    <!-- Layout page -->
    <div class="layout-page">
      <!-- BEGIN: Navbar-->
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isNavbar): ?>
      <?php echo $__env->make('layouts/sections/navbar/navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <!-- END: Navbar-->


      <!-- Content wrapper -->
      <div class="content-wrapper">

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

    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isMenu): ?>
    <!-- Overlay -->
    <div class="layout-overlay layout-menu-toggle"></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <!-- Drag Target Area To SlideIn Menu On Small Screens -->
    <div class="drag-target"></div>
  </div>
  <!-- / Layout wrapper -->
  <?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/commonMaster' , array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\layouts\contentNavbarLayout.blade.php ENDPATH**/ ?>