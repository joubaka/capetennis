<?php
  // Explicit presentation opt-in: public pages can continue sharing the Vuexy shell.
  $backendWorkspace = true;
  $pageConfigs = array_merge($pageConfigs ?? [], [
    'myLayout' => 'horizontal',
    'myStyle' => 'light',
    'hasCustomizer' => false,
  ]);
  $container = 'container-xxl ct-backend-container';
  $containerNav = 'container-xxl ct-backend-nav-container';
?>


<?php echo $__env->make('layouts.horizontalLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\layouts\backend.blade.php ENDPATH**/ ?>