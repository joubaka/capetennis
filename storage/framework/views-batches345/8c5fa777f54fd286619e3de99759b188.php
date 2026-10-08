<?php
$configData = Helper::appClasses();
?>
<style>
  #layout-menu > .horizontal-menu-shell {
    max-width: 1180px;
    margin-inline: auto;
    width: 100%;
  }

  @media (max-width: 1199.98px) {
    #layout-menu > .horizontal-menu-shell {
      max-width: none;
    }
  }
</style>
<!-- Horizontal Menu -->
<aside id="layout-menu" class="layout-menu-horizontal menu-horizontal menu bg-menu-theme flex-grow-0" aria-label="Primary navigation">
  <div class="horizontal-menu-shell <?php echo e($containerNav); ?> d-flex h-100">
    <ul class="menu-inner">
      <?php
        $currentRouteName = Route::currentRouteName() ?? '';
        $menuItemIsActive = static function ($item) use ($currentRouteName): bool {
          $slugs = is_array($item->slug) ? $item->slug : [$item->slug];

          foreach ($slugs as $slug) {
            if ($slug === $currentRouteName || (isset($item->submenu) && $slug !== '' && str_starts_with($currentRouteName, $slug))) {
              return true;
            }
          }

          return false;
        };
      ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showAdminHome ?? false): ?>
      <li class="menu-item <?php echo e($currentRouteName === 'backend.dashboard' ? 'active' : ''); ?>">
        <a href="<?php echo e(route('backend.dashboard')); ?>" class="menu-link" <?php if($currentRouteName === 'backend.dashboard'): ?> aria-current="page" <?php endif; ?>>
          <i class="menu-icon ti ti-layout-dashboard"></i>
          <div>Admin home</div>
        </a>
      </li>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $menuData[1]->menu; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $menu): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

      
      <?php ($activeClass = $menuItemIsActive($menu) ? 'active' : null); ?>

      

      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('super-user')): ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($menu->menuLevel == 'super' || $menu->menuLevel == 'all'): ?>
      <li class="menu-item <?php echo e($activeClass); ?>">
        <a href="<?php echo e(isset($menu->url) ? url($menu->url) : 'javascript:void(0);'); ?>" class="<?php echo e(isset($menu->submenu) ? 'menu-link menu-toggle' : 'menu-link'); ?>" <?php if($activeClass && !isset($menu->submenu)): ?> aria-current="page" <?php endif; ?> <?php if(isset($menu->target) and !empty($menu->target)): ?> target="_blank" <?php endif; ?>>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($menu->icon)): ?>
          <i class="<?php echo e($menu->icon); ?>"></i>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <div><?php echo e(isset($menu->name) ? __($menu->name) : ''); ?></div>
        </a>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($menu->submenu)): ?>
        <?php echo $__env->make('layouts.sections.menu.submenu',['menu' => $menu->submenu], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </li>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php else: ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($menu->menuLevel == 'all'): ?>
      <li class="menu-item <?php echo e($activeClass); ?>">
        <a href="<?php echo e(isset($menu->url) ? url($menu->url) : 'javascript:void(0);'); ?>" class="<?php echo e(isset($menu->submenu) ? 'menu-link menu-toggle' : 'menu-link'); ?>" <?php if($activeClass && !isset($menu->submenu)): ?> aria-current="page" <?php endif; ?> <?php if(isset($menu->target) and !empty($menu->target)): ?> target="_blank" <?php endif; ?>>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($menu->icon)): ?>
          <i class="<?php echo e($menu->icon); ?>"></i>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <div><?php echo e(isset($menu->name) ? __($menu->name) : ''); ?></div>
        </a>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($menu->submenu)): ?>
        <?php echo $__env->make('layouts.sections.menu.submenu',['menu' => $menu->submenu], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </li>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endif; ?>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


    </ul>
  </div>
</aside>
<!--/ Horizontal Menu -->
<?php /**PATH C:\wamp64\www\ct\resources\views\layouts\sections\menu\horizontalMenu.blade.php ENDPATH**/ ?>