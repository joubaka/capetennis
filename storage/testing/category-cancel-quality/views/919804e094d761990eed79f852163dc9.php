<?php $__env->startSection('title', 'Player Plus'); ?>
<?php $__env->startSection('content'); ?>
<div id="player-plus-page" class="container-xxl container-p-y pp-journey ct-player-workspace">
  <style>
    #player-plus-page { max-width: 1120px; }
    #player-plus-page .plus-page-heading { display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:1rem;margin-bottom:1.25rem; }
    #player-plus-page .plus-page-heading h1 { font-size:1rem;margin:0;color:#004177; }
    #player-plus-page .plus-page-intro { padding:clamp(1.25rem,4vw,2.5rem);background:linear-gradient(120deg,#f5f8fc,#eaf1f8);border-radius:18px;margin-bottom:1.25rem; }
    #player-plus-page .plus-page-intro h2 { font-size:clamp(1.8rem,4vw,2.8rem);letter-spacing:-.035em;color:#172e45;max-width:24ch; }
    #player-plus-page .plus-page-intro p { color:#536873;max-width:60ch;line-height:1.6; }
    #player-plus-page .plus-player-choice { background:#fff;border:1px solid #dce5ef;border-radius:16px;padding:clamp(1rem,3vw,1.5rem);margin-top:1.5rem; }
    #player-plus-page .plus-player-choice .list-group-item { border-color:#dce5ef;overflow-wrap:anywhere; }
    #player-plus-page .plus-player-choice .btn-primary, #player-plus-page .plus-page-intro .btn { background:#004177;border-color:#004177;color:#fff;min-height:44px; }
    #player-plus-page .plus-page-intro .btn:hover, #player-plus-page .plus-player-choice .btn-primary:hover { background:#00345f;border-color:#00345f; }
    @media(max-width:575.98px) { #player-plus-page .plus-player-choice form .form-control { max-width:none !important; } }
  </style>
  <?php echo $__env->make('frontend.player-plus.styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php if (isset($component)) { $__componentOriginal3480b572702ecfa053ab08ce698dcaca = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3480b572702ecfa053ab08ce698dcaca = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-workspace-header','data' => ['workspace' => $workspace ?? null,'title' => 'Choose your player','eyebrow' => 'Player Plus · Step 2 of 4','subtitle' => 'Select one player to check membership and available plans.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-workspace-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['workspace' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($workspace ?? null),'title' => 'Choose your player','eyebrow' => 'Player Plus · Step 2 of 4','subtitle' => 'Select one player to check membership and available plans.']); ?>
     <?php $__env->slot('navigation', null, []); ?> <nav class="d-flex flex-wrap gap-2" aria-label="Player Plus navigation"><a class="btn btn-outline-primary" href="<?php echo e(route('my.tennis', ['player' => $workspace['player']->id ?? null])); ?>">My Tennis</a><a class="btn btn-outline-primary" href="<?php echo e(route('player-plus.orders.index', ['player' => $workspace['player']->id ?? null])); ?>">My Plus orders</a></nav> <?php $__env->endSlot(); ?>
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
  <?php if (isset($component)) { $__componentOriginald6fe41f5dbf7c3b0211c2c5447c14d79 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald6fe41f5dbf7c3b0211c2c5447c14d79 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-plus-stepper','data' => ['current' => 2]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-plus-stepper'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['current' => 2]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald6fe41f5dbf7c3b0211c2c5447c14d79)): ?>
<?php $attributes = $__attributesOriginald6fe41f5dbf7c3b0211c2c5447c14d79; ?>
<?php unset($__attributesOriginald6fe41f5dbf7c3b0211c2c5447c14d79); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald6fe41f5dbf7c3b0211c2c5447c14d79)): ?>
<?php $component = $__componentOriginald6fe41f5dbf7c3b0211c2c5447c14d79; ?>
<?php unset($__componentOriginald6fe41f5dbf7c3b0211c2c5447c14d79); ?>
<?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($enabled)): ?><p class="alert alert-info">New purchases are currently paused. Existing verified memberships keep their benefits until expiry.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <section class="plus-player-choice" aria-labelledby="choose-plus-player">
  <h2 id="choose-plus-player" class="h4" style="scroll-margin-top:6rem">Choose a player</h2>
  <p>Your linked players appear below. You can also sponsor another player without gaining access to their private information.</p>
  <form method="get" class="d-flex flex-wrap gap-2 mb-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($workspace['player'] ?? null): ?><input type="hidden" name="player" value="<?php echo e($workspace['player']->id); ?>"><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <label class="visually-hidden" for="plus-search">Search by player name</label>
    <input id="plus-search" class="form-control" style="max-width:24rem;min-height:44px" name="search" value="<?php echo e($search); ?>" placeholder="Search by name (at least 3 characters)" minlength="3" maxlength="100">
    <button class="btn btn-primary" style="min-height:44px">Search players</button>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($search): ?><a class="btn btn-outline-secondary" href="<?php echo e(route('player-plus.index', ['player' => $workspace['player']->id ?? null])); ?>">My linked players</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </form>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p class="alert alert-danger"><?php echo e($error); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <div class="list-group mb-3">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $players; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $player): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
      <a class="list-group-item list-group-item-action py-3" href="<?php echo e(route('player-plus.player', $player)); ?>"><?php echo e($player->full_name); ?> <small class="text-muted">· Profile #<?php echo e($player->id); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player->dateOfBirth): ?> · Born <?php echo e(substr($player->dateOfBirth, 0, 4)); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></small></a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><p class="alert alert-info">No players found. Search for the player you would like to cover.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
  <?php echo e($players->links()); ?>

  </section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\player-plus\index.blade.php ENDPATH**/ ?>