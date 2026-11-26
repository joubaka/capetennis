<?php
  $headerPlayer = $selectedPlayer ?? ($ratingPlayer ?? ($workspace['player'] ?? null));
  $headerCanViewRating = $canViewRating ?? ($workspace['canViewRating'] ?? false);
  $headerSnapshot = $ratingSnapshot ?? ($workspace['snapshot'] ?? null);
  $headerRelated = $workspace['related'] ?? isset($selectedPlayer);
  $headerSubtitle = !$headerRelated && $headerPlayer && ($headerCanViewRating || isset($ratingPreviewPlayer))
      ? 'Admin preview'
      : null;
  $headerQuery = ['player' => $headerPlayer?->id, 'preview_non_plus' => ($previewNonPlus ?? false) ? 1 : null];
  $headerRatingUrl = route('my.tennis', $headerQuery + ['tab' => 'rating']);
?>

<?php if (isset($component)) { $__componentOriginal3480b572702ecfa053ab08ce698dcaca = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3480b572702ecfa053ab08ce698dcaca = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-workspace-header','data' => ['compact' => true,'title' => $headerPlayer?->full_name ?? 'My Tennis','eyebrow' => 'My Tennis','headingId' => 'my-tennis-player-heading','subtitle' => $headerSubtitle,'dataPlayerProfileHeader' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-workspace-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['compact' => true,'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($headerPlayer?->full_name ?? 'My Tennis'),'eyebrow' => 'My Tennis','heading-id' => 'my-tennis-player-heading','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($headerSubtitle),'data-player-profile-header' => true]); ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($headerPlayer): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($headerCanViewRating): ?>
      <?php echo $__env->make('frontend.my-tennis.profile-rating-badge', ['badgeSnapshot' => $headerSnapshot, 'badgeUrl' => $headerRatingUrl], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php else: ?>
      <div class="ct-my-tennis-rating-badge" data-profile-plus-preview>
        <?php if (isset($component)) { $__componentOriginalb7701658b742c1480bd9cae408300c9d = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalb7701658b742c1480bd9cae408300c9d = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.ctr-mark','data' => ['size' => 'heading']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('ctr-mark'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['size' => 'heading']); ?>
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
        <a class="btn btn-sm btn-outline-primary" href="<?php echo e(route('my.tennis.feature-preview', ['player' => $headerPlayer, 'feature' => 'rating'])); ?>">Preview Plus</a>
      </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
   <?php $__env->slot('actions', null, []); ?> 
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($workspace['players']) || isset($players)): ?>
      <?php echo $__env->make('frontend.my-tennis.player-selector', [
        'players' => $workspace['players'] ?? $players,
        'selectedPlayer' => $headerRelated ? $headerPlayer : null,
        'ratingPreviewPlayer' => !$headerRelated && $headerCanViewRating ? $headerPlayer : ($ratingPreviewPlayer ?? null),
      ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <div class="ct-my-tennis-utilities">
      <a data-my-tennis-link href="<?php echo e(route('my.tennis', $headerQuery + ['tab' => 'manage'])); ?>" class="ct-my-tennis-secondary-link" <?php if(($workspaceTab ?? null) === 'manage'): ?> aria-current="page" <?php endif; ?>>Manage players</a>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($headerPlayer && $headerCanViewRating): ?>
        <a href="<?php echo e(route('player-plus.player', $headerPlayer)); ?>" class="ct-my-tennis-secondary-link">Plus membership</a>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
   <?php $__env->endSlot(); ?>
   <?php $__env->slot('navigation', null, []); ?> 
    <?php echo $__env->make('frontend.my-tennis.dashboard-tabs', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
   <?php $__env->endSlot(); ?>
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
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\my-tennis\profile-header.blade.php ENDPATH**/ ?>