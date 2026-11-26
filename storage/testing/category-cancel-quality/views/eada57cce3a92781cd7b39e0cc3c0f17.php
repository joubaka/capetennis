<?php $__env->startSection('title', 'My Player Plus orders'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl container-p-y pp-journey ct-player-workspace" style="max-width:900px">
  <?php echo $__env->make('frontend.player-plus.styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php if (isset($component)) { $__componentOriginal3480b572702ecfa053ab08ce698dcaca = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3480b572702ecfa053ab08ce698dcaca = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-workspace-header','data' => ['workspace' => $workspace ?? null,'title' => 'My Player Plus orders','eyebrow' => 'Player Plus · Your memberships','subtitle' => 'Your purchases and payment attempts appear here. Sponsoring a player does not grant access to their private ratings.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-workspace-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['workspace' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($workspace ?? null),'title' => 'My Player Plus orders','eyebrow' => 'Player Plus · Your memberships','subtitle' => 'Your purchases and payment attempts appear here. Sponsoring a player does not grant access to their private ratings.']); ?>
     <?php $__env->slot('navigation', null, []); ?> <nav class="d-flex flex-wrap gap-2" aria-label="Player Plus navigation"><a class="btn btn-outline-primary" href="<?php echo e(route('my.tennis', ['player' => $workspace['player']->id ?? null])); ?>">My Tennis</a><a class="btn btn-outline-primary" href="<?php echo e(route('player-plus.index', ['player' => $workspace['player']->id ?? null])); ?>">Player memberships</a></nav> <?php $__env->endSlot(); ?>
   <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal3480b572702ecfa053ab08ce698dcaca)): ?>
<?php $attributes = $__attributesOriginal3480b572702ecfa053ab08ce698dcaca; ?>
<?php unset($__attributesOriginal3480b572702ecfa053ab08ce698dcaca); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal3480b572702ecfa053ab08ce698dcaca)): ?>
<?php $component = $__componentOriginal3480b572702ecfa053ab08ce698dcaca; ?>
<?php unset($__componentOriginal3480b572702ecfa053ab08ce698dcaca); ?>
<?php endif; ?>
  <div class="list-group pp-order-list">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $orders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <a class="list-group-item list-group-item-action pp-order-row" href="<?php echo e(route('player-plus.orders.show', $order)); ?>">
        <span class="pp-order-icon"><i class="ti ti-file-invoice" aria-hidden="true"></i></span>
        <div class="pp-order-copy">
        <strong>P<?php echo e($order->id); ?> · <?php echo e($order->player?->full_name ?? 'Player profile unavailable'); ?></strong>
        <span class="d-block">R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($order->price_cents)); ?> · <?php echo e($order->isMonthly() ? 'Monthly' : 'Annual'); ?> · <?php echo e(str_replace('_', ' ', ucfirst($order->status))); ?></span>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->term_ends_at): ?><span class="d-block">Purchased term <?php echo e($order->term_ends_at->isFuture() ? 'ends' : 'ended'); ?> <?php echo e($order->term_ends_at->timezone('Africa/Johannesburg')->format('j M Y')); ?></span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->reconciliation_reason): ?><span class="d-block">Payment review recorded. Open this order for support details.</span><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <i class="ti ti-chevron-right" aria-hidden="true"></i>
      </a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="alert alert-info">You have no Player Plus orders yet.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <div class="mt-3"><?php echo e($orders->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\player-plus\orders.blade.php ENDPATH**/ ?>