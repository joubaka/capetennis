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
      <?php if(($menu->playerPlusMarketing ?? false) && !app(\App\Services\Performance\PlayerRatingAccess::class)->allowsMarketing(auth()->user())) continue; ?>

      
      <?php ($activeClass = $menuItemIsActive($menu) ? 'active' : null); ?>

      

      <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('super-user')): ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($menu->menuLevel == 'super' || $menu->menuLevel == 'all'): ?>
      <li class="menu-item <?php echo e($activeClass); ?>">
        <a href="<?php echo e(isset($menu->url) ? url($menu->url) : 'javascript:void(0);'); ?>" class="<?php echo e(isset($menu->submenu) ? 'menu-link menu-toggle' : 'menu-link'); ?>" <?php if($activeClass && !isset($menu->submenu)): ?> aria-current="page" <?php endif; ?> <?php if(isset($menu->target) and !empty($menu->target)): ?> target="_blank" <?php endif; ?>>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($menu->slug ?? null) === 'ctr.index'): ?>
          <?php if (isset($component)) { $__componentOriginalb7701658b742c1480bd9cae408300c9d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7701658b742c1480bd9cae408300c9d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-mark','data' => ['class' => 'me-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'me-2']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $attributes = $__attributesOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__attributesOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $component = $__componentOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__componentOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?>
          <?php elseif(isset($menu->icon)): ?>
          <i class="<?php echo e($menu->icon); ?>"></i>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <div><?php echo e(isset($menu->name) ? __($menu->name) : ''); ?></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($menu->playerPlusMarketing ?? false): ?><?php if (isset($component)) { $__componentOriginal42d2545d0ae398c2199755893eaa354f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal42d2545d0ae398c2199755893eaa354f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-plus-coming-soon','data' => ['class' => 'ms-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-plus-coming-soon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'ms-2']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal42d2545d0ae398c2199755893eaa354f)): ?>
<?php $attributes = $__attributesOriginal42d2545d0ae398c2199755893eaa354f; ?>
<?php unset($__attributesOriginal42d2545d0ae398c2199755893eaa354f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal42d2545d0ae398c2199755893eaa354f)): ?>
<?php $component = $__componentOriginal42d2545d0ae398c2199755893eaa354f; ?>
<?php unset($__componentOriginal42d2545d0ae398c2199755893eaa354f); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($menu->slug ?? null) === 'ctr.index'): ?>
          <?php if (isset($component)) { $__componentOriginalb7701658b742c1480bd9cae408300c9d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7701658b742c1480bd9cae408300c9d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-mark','data' => ['class' => 'me-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'me-2']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $attributes = $__attributesOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__attributesOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalb7701658b742c1480bd9cae408300c9d)): ?>
<?php $component = $__componentOriginalb7701658b742c1480bd9cae408300c9d; ?>
<?php unset($__componentOriginalb7701658b742c1480bd9cae408300c9d); ?>
<?php endif; ?>
          <?php elseif(isset($menu->icon)): ?>
          <i class="<?php echo e($menu->icon); ?>"></i>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <div><?php echo e(isset($menu->name) ? __($menu->name) : ''); ?></div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($menu->playerPlusMarketing ?? false): ?><?php if (isset($component)) { $__componentOriginal42d2545d0ae398c2199755893eaa354f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal42d2545d0ae398c2199755893eaa354f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-plus-coming-soon','data' => ['class' => 'ms-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-plus-coming-soon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'ms-2']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal42d2545d0ae398c2199755893eaa354f)): ?>
<?php $attributes = $__attributesOriginal42d2545d0ae398c2199755893eaa354f; ?>
<?php unset($__attributesOriginal42d2545d0ae398c2199755893eaa354f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal42d2545d0ae398c2199755893eaa354f)): ?>
<?php $component = $__componentOriginal42d2545d0ae398c2199755893eaa354f; ?>
<?php unset($__componentOriginal42d2545d0ae398c2199755893eaa354f); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php /**PATH C:\wamp64\www\ct\resources\views/layouts/sections/menu/horizontalMenu.blade.php ENDPATH**/ ?>