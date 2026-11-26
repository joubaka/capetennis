<?php
    $annualPrice = \App\Services\Performance\PlayerPlusService::amount(\App\Services\Performance\PlayerPlusService::priceCents());
    $monthlyPrice = \App\Services\Performance\PlayerPlusService::amount(\App\Services\Performance\PlayerPlusService::monthlyPriceCents());
?>
<div <?php echo e($attributes->class(['d-flex flex-column gap-1'])); ?> data-player-plus-pricing>
  <?php if (isset($component)) { $__componentOriginal42d2545d0ae398c2199755893eaa354f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal42d2545d0ae398c2199755893eaa354f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-plus-coming-soon','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-plus-coming-soon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal42d2545d0ae398c2199755893eaa354f)): ?>
<?php $attributes = $__attributesOriginal42d2545d0ae398c2199755893eaa354f; ?>
<?php unset($__attributesOriginal42d2545d0ae398c2199755893eaa354f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal42d2545d0ae398c2199755893eaa354f)): ?>
<?php $component = $__componentOriginal42d2545d0ae398c2199755893eaa354f; ?>
<?php unset($__componentOriginal42d2545d0ae398c2199755893eaa354f); ?>
<?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Services\Performance\PlayerPlusService::salesEnabled()): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(\App\Services\Performance\PlayerPlusService::monthlyEnabled()): ?>
      <div><strong>R<?php echo e($monthlyPrice); ?></strong> <span class="small">per player / month</span></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div><strong>R<?php echo e($annualPrice); ?></strong> <span class="small">per player / year</span></div>
    <p class="small mb-0">PayFast plans renew automatically until cancelled. Annual wallet payments are once off. Manage renewal in My Plus orders.</p>
  <?php else: ?>
    <p class="small mb-0">Pricing will be published when Player Plus launches.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! \App\Services\Performance\PlayerPlusService::salesEnabled()): ?>
    <p class="small mb-0" role="status"><?php echo e(app(\App\Services\Performance\PlayerRatingAccess::class)->comingSoon() ? 'Player Plus is coming soon. Purchases are not open yet.' : 'New purchases are currently paused.'); ?></p>
  <?php elseif(! \App\Services\Performance\PlayerPlusService::monthlyEnabled()): ?>
    <p class="small mb-0" role="status">Monthly purchases are currently unavailable.</p>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php /**PATH D:\wamp64\www\ct\resources\views\components\player-plus-pricing.blade.php ENDPATH**/ ?>