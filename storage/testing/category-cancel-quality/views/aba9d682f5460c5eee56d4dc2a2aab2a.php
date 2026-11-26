
<?php
  $lockedPreview = ($locked ?? false) === true;
  $compactNavigation = $compactNavigation ?? false;
  $activeView = match (true) {
    request()->routeIs('my.tennis.feature-preview') => request()->route('feature'),
    request()->routeIs('my.tennis.record') => request()->route('section') ?? 'record',
    default => $recordSection ?? null,
  };
  $previewUrl = fn ($feature) => route('my.tennis.feature-preview', ['player' => $navigationPlayer->id, 'feature' => $feature]);
?>
<style>.ct-player-navigation .btn{min-height:44px;display:inline-flex;align-items:center;color:#004177;border-color:#9ab4cf}.ct-player-navigation .btn[aria-current=page]{background:#004177;color:#fff}</style>
<nav class="<?php echo \Illuminate\Support\Arr::toCssClasses(['ct-player-navigation record-nav d-flex flex-wrap gap-2 my-3', 'ct-player-navigation-compact' => $compactNavigation, 'ct-player-navigation-embedded' => $embeddedDashboard ?? false]); ?>" aria-label="Player Plus views" <?php if($lockedPreview): ?> data-plus-locked-navigation <?php endif; ?>>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($embeddedDashboard ?? false)): ?>
  <a class="btn btn-outline-primary" href="<?php echo e(route('my.tennis', ['player' => $navigationPlayer->id])); ?>">Overview</a>
  <a class="btn btn-outline-primary" href="<?php echo e(route('my.tennis', ['player' => $navigationPlayer->id])); ?>#rating"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($compactNavigation)): ?><?php if (isset($component)) { $__componentOriginalb7701658b742c1480bd9cae408300c9d = $component; } ?>
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
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php echo e($compactNavigation ? 'Rating' : 'Player rating'); ?></a>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <a class="btn btn-outline-primary" href="<?php echo e($lockedPreview ? $previewUrl('record') : route('my.tennis.record', $navigationPlayer)); ?>" <?php if($lockedPreview): ?> data-plus-feature="record" <?php endif; ?> <?php if($activeView === 'record'): ?> aria-current="page" <?php endif; ?>><?php echo e($compactNavigation ? 'Results' : 'Playing Record'); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($lockedPreview): ?><small>Plus preview</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></a>
  <a class="btn btn-outline-primary" href="<?php echo e($lockedPreview ? $previewUrl('opponents') : route('my.tennis.record', ['player' => $navigationPlayer, 'section' => 'opponents'])); ?>" <?php if($lockedPreview): ?> data-plus-feature="opponents" <?php endif; ?> <?php if($activeView === 'opponents'): ?> aria-current="page" <?php endif; ?>><?php echo e($compactNavigation ? 'Opponents' : 'Opponent Insights'); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($lockedPreview): ?><small>Plus preview</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></a>
  <a class="btn btn-outline-primary" <?php if($lockedPreview): ?> data-plus-feature="pathway" <?php else: ?> data-pathway-navigation <?php endif; ?> href="<?php echo e($lockedPreview ? $previewUrl('pathway') : route('my.tennis.pathway', $navigationPlayer)); ?>" <?php if($activeView === 'pathway' || request()->routeIs('my.tennis.pathway')): ?> aria-current="page" <?php endif; ?>><?php echo e($compactNavigation ? 'Next steps' : 'Tennis pathway'); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($lockedPreview): ?><small>Plus preview</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></a>
  <a class="btn btn-outline-primary" href="<?php echo e($lockedPreview ? $previewUrl('events') : route('my.tennis.event-history', $navigationPlayer)); ?>" <?php if($lockedPreview): ?> data-plus-feature="events" <?php endif; ?> <?php if($activeView === 'events' || request()->routeIs('my.tennis.event-history')): ?> aria-current="page" <?php endif; ?>><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($compactNavigation)): ?><?php if (isset($component)) { $__componentOriginalb7701658b742c1480bd9cae408300c9d = $component; } ?>
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
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php echo e($compactNavigation ? 'Event ratings' : 'Past-event ratings'); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($lockedPreview): ?><small>Plus preview</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></a>
</nav>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\player-navigation.blade.php ENDPATH**/ ?>