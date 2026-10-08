<?php
$configData = Helper::appClasses();
?>
<!-- Horizontal Menu -->

<div class="menu menu-vertical bg-menu-theme py-3" id="menu-1" >
    <ul class="menu-inner">
      
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $menuData[2]->menu; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $menu): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

      
      <?php
      $activeClass = null;
      $currentRouteName = Route::currentRouteName();

      if ($currentRouteName === $menu->slug) {
      $activeClass = 'active';
      }
      elseif (isset($menu->submenu)) {
      if (gettype($menu->slug) === 'array') {
      foreach($menu->slug as $slug){
      if (str_contains($currentRouteName,$slug) and strpos($currentRouteName,$slug) === 0) {
      $activeClass = 'active';
      }
      }
      }
      else{
      if (str_contains($currentRouteName,$menu->slug) and strpos($currentRouteName,$menu->slug) === 0) {
      $activeClass = 'active';
      }
      }

      }
     
      ?>

      
    
     
      <li class="menu-item <?php echo e($activeClass); ?>">
        <a href="<?php echo e(isset($menu->url) ? url($menu->url).'/'.$event->id : 'javascript:void(0);'); ?>" class="<?php echo e(isset($menu->submenu) ? 'menu-link menu-toggle' : 'menu-link'); ?>" <?php if(isset($menu->target) and !empty($menu->target)): ?> target="_blank" <?php endif; ?>>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($menu->icon)): ?>
          <i class="<?php echo e($menu->icon); ?>"></i>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <div><?php echo e(isset($menu->name) ? __($menu->name) : ''); ?></div>
        </a>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($menu->submenu)): ?>
        <?php echo $__env->make('layouts.sections.menu.submenu',['menu' => $menu->submenu], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </li>
     
   
     
     
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


    </ul>
</div>

<!--/ Horizontal Menu --><?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\navbar\navbar.blade.php ENDPATH**/ ?>