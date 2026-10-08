<!-- laravel style -->
<script src="<?php echo e(asset('assets/vendor/js/helpers.js')); ?>"></script>
<!-- beautify ignore:start -->
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($configData['hasCustomizer']): ?>
  <!--! Template customizer & Theme config files MUST be included after core stylesheets and helpers.js in the <head> section -->
  <!--? Template customizer: To hide customizer set displayCustomizer value false in config.js.  -->
  <script src="<?php echo e(asset('assets/vendor/js/template-customizer.js')); ?>"></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <!--? Config:  Mandatory theme config file contain global vars & default theme options, Set your preferred theme option in this file.  -->
  <script src="<?php echo e(asset('assets/js/config.js')); ?>"></script>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($configData['hasCustomizer']): ?>
  <script>
    window.templateCustomizer = new TemplateCustomizer({
      cssPath: '',
      themesPath: '',
      defaultShowDropdownOnHover: <?php echo e($configData['showDropdownOnHover']); ?>, // true/false (for horizontal layout only)
      displayCustomizer: <?php echo e($configData['displayCustomizer']); ?>,
      lang: '<?php echo e(app()->getLocale()); ?>',
      pathResolver: function(path) {
        var resolvedPaths = {
          // Core stylesheets
          <?php $__currentLoopData = ['core']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            '<?php echo e($name); ?>.css': '<?php echo e(asset(mix("assets/vendor/css{$configData['rtlSupport']}/{$name}.css"))); ?>',
            '<?php echo e($name); ?>-dark.css': '<?php echo e(asset(mix("assets/vendor/css{$configData['rtlSupport']}/{$name}-dark.css"))); ?>',
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

          // Themes
          <?php $__currentLoopData = ['default', 'bordered', 'semi-dark']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            'theme-<?php echo e($name); ?>.css': '<?php echo e(asset(mix("assets/vendor/css{$configData['rtlSupport']}/theme-{$name}.css"))); ?>',
            'theme-<?php echo e($name); ?>-dark.css':
            '<?php echo e(asset(mix("assets/vendor/css{$configData['rtlSupport']}/theme-{$name}-dark.css"))); ?>',
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        }
        return resolvedPaths[path] || path;
      },
      'controls': <?php echo json_encode($configData['customizerControls']); ?>,
    });
  </script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\layouts\sections\scriptsIncludes.blade.php ENDPATH**/ ?>