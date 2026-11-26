<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['title', 'eyebrow' => 'Cape Tennis · Player Plus', 'subtitle' => null, 'heading' => 'h1', 'headingId' => null, 'workspace' => null, 'compact' => false]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['title', 'eyebrow' => 'Cape Tennis · Player Plus', 'subtitle' => null, 'heading' => 'h1', 'headingId' => null, 'workspace' => null, 'compact' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php if (! $__env->hasRenderedOnce('player-workspace')): $__env->markAsRenderedOnce('player-workspace');
$__env->startPush('page-styles'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/css/player-workspace.css')); ?>?v=<?php echo e(filemtime(public_path('assets/css/player-workspace.css'))); ?>">
<?php $__env->stopPush(); endif; ?>
<?php ($headingTag = in_array($heading, ['h1', 'h2'], true) ? $heading : 'h1'); ?>
<header <?php echo e($attributes->class(['ct-workspace-header', 'ct-workspace-header-compact' => $compact])); ?>>
  <div class="ct-workspace-header-main">
    <div class="ct-workspace-header-copy">
      <span class="ct-workspace-eyebrow"><?php echo e($eyebrow); ?></span>
      <<?php echo e($headingTag); ?> class="ct-workspace-title" <?php if($headingId): ?> id="<?php echo e($headingId); ?>" <?php endif; ?>><?php echo e($title); ?></<?php echo e($headingTag); ?>>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($subtitle): ?><p class="ct-workspace-subtitle"><?php echo e($subtitle); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php echo e($slot); ?>

    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$compact && (isset($summary) || ($workspace['player'] ?? null))): ?><div class="ct-workspace-header-summary">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($workspace['canViewRating'] ?? false): ?><?php echo $__env->make('frontend.my-tennis.rating-snapshot', ['ratingSnapshot' => $workspace['snapshot']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php elseif(($workspace['related'] ?? false)): ?><?php echo $__env->make('frontend.my-tennis.locked-rating-snapshot', ['ratingPlayer' => $workspace['player']], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($summary)): ?><?php echo e($summary); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($actions) || $workspace): ?><div class="ct-workspace-header-actions">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($workspace): ?><?php echo $__env->make('frontend.my-tennis.workspace-selector', ['compactSelector' => $compact], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($actions)): ?><?php echo e($actions); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($workspace['player'] ?? null) && (($workspace['related'] ?? false) || ($workspace['canViewRating'] ?? false))): ?>
    <div class="ct-workspace-header-navigation">
      <?php echo $__env->make('frontend.my-tennis.player-navigation', ['navigationPlayer' => $workspace['player'], 'locked' => !$workspace['canViewRating'], 'compactNavigation' => $compact], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($navigation)): ?><div class="ct-workspace-header-navigation"><?php echo e($navigation); ?></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($compact && isset($summary)): ?>
    <div class="ct-workspace-header-summary"><?php echo e($summary); ?></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</header>
<?php /**PATH D:\wamp64\www\ct\resources\views\components\player-workspace-header.blade.php ENDPATH**/ ?>