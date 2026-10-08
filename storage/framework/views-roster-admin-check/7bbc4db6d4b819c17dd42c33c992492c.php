<?php
  $menuItemIsActive ??= static function ($item): bool {
    $currentRouteName = Route::currentRouteName() ?? '';
    $slugs = is_array($item->slug) ? $item->slug : [$item->slug];

    foreach ($slugs as $slug) {
      if ($slug === $currentRouteName || (isset($item->submenu) && $slug !== '' && str_starts_with($currentRouteName, $slug))) {
        return true;
      }
    }

    return false;
  };
?>
<ul class="menu-sub">
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($menu)): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $menu; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $submenu): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

    
    <?php
      $active = $configData["layout"] === 'vertical' ? 'active open' : 'active';
      $activeClass = $menuItemIsActive($submenu) ? $active : null;
    ?>

      <li class="menu-item <?php echo e($activeClass); ?>">
        <a href="<?php echo e(isset($submenu->url) ? url($submenu->url) : 'javascript:void(0)'); ?>" class="<?php echo e(isset($submenu->submenu) ? 'menu-link menu-toggle' : 'menu-link'); ?>" <?php if($activeClass && !isset($submenu->submenu)): ?> aria-current="page" <?php endif; ?> <?php if(isset($submenu->target) and !empty($submenu->target)): ?> target="_blank" <?php endif; ?>>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($submenu->icon)): ?>
          <i class="<?php echo e($submenu->icon); ?>"></i>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <div><?php echo e(isset($submenu->name) ? __($submenu->name) : ''); ?></div>
        </a>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($submenu->submenu)): ?>
          <?php echo $__env->make('layouts.sections.menu.submenu',['menu' => $submenu->submenu], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </li>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</ul>
<?php /**PATH C:\wamp64\www\ct\resources\views\layouts\sections\menu\submenu.blade.php ENDPATH**/ ?>