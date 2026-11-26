<?php $__env->startSection('title', 'Player Plus registration'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl container-p-y pp-player pp-journey ct-player-workspace" data-plus-player-page data-plus-review-plan="<?php echo e($errors->any() ? old('plan', 'annual_recurring') : ''); ?>">
  <?php echo $__env->make('frontend.player-plus.styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <style>
    .pp-player .pp-plan-choice .pp-choice-price{font-size:1.35rem;font-weight:700;color:#004177;overflow-wrap:anywhere}
    .pp-player .pp-plan-choices .pp-plan-choice:last-of-type{margin-bottom:1.25rem}
    .pp-player .pp-plan-choice .pp-choice-terms{font-size:.9rem}
    .pp-player .pp-purchase .pp-plan-note{padding:.75rem 1rem;background:#f1f5fa;border-left:3px solid #004177;border-radius:6px}
    .pp-player .pp-plan-choices{padding:1.5rem;border:1px solid #dce5ef;border-radius:16px;background:#fff}.pp-player .pp-plan-choices h2{color:#172e45;font-size:1.5rem}.pp-player .pp-plan-choice{display:flex;flex-direction:column;gap:.5rem;text-align:left;width:100%;padding:1.25rem;margin:1rem 0;background:#eef4fa;border:1px solid #9ab4cf;border-radius:12px;color:#172e45;min-height:44px}.pp-player .pp-plan-choice:hover{border-color:#004177;background:#e5eff8}.pp-player .pp-plan-choice>span:last-child{align-self:flex-start;background:#ff9f43;border-radius:7px;padding:.5rem 1rem;color:#172e45;font-weight:700}.pp-player [hidden]{display:none!important}.pp-player .pp-purchase [data-plus-change-plan]{width:auto;margin:0 0 1rem}
  </style>
  <style>
    .pp-player { max-width: 880px; color: #243e50; font-size: 1rem; line-height: 1.65; }
    .pp-player .small, .pp-player small { font-size: .875rem; }
    .pp-player .pp-membership-header {
      background: #172e45;
      border-top: 3px solid #ff9f43;
      padding: clamp(1rem, 3vw, 1.5rem);
      margin-bottom: 1rem;
    }
    .pp-player .pp-membership-header .ct-workspace-title { font-size: clamp(1.4rem, 4vw, 2rem); }
    .pp-player .pp-membership-header .ct-workspace-header-navigation { margin-top: .75rem; padding-top: .65rem; }
    .pp-player .pp-membership-header .pp-rating-link {
      color: #172e45;
      background: #ff9f43;
      border-color: #ff9f43;
      font-weight: 600;
    }
    .pp-player .pp-membership-header .pp-rating-link:hover { color: #172e45; background: #ffbd7a; border-color: #ffbd7a; }
    .pp-player .pp-status {
      border: 1px solid #dce5ef;
      border-left: 4px solid #004177;
      background: #eef4fa;
      border-radius: 12px;
      padding: 1rem 1.25rem;
      margin-bottom: 1rem;
    }
    .pp-player .pp-status p { margin: .35rem 0 0; color: #38516e; }
    .pp-player .pp-status h2 { font-size: 1rem; color: #172e45; margin: 0; }
    .pp-player .pp-status.pp-paused { border-left-color: #b56514; background: #fff6eb; }
    .pp-player .pp-status[data-plus-readiness="available"] { border-left-color: #258164; background: #eef7f2; }
    .pp-player .pp-purchase{border:1px solid #c8d8e4;border-radius:16px;padding:1.5rem;background:#fff;margin-bottom:1rem}.pp-player .pp-purchase h2{font-size:1.25rem;color:#172e45;margin:0}.pp-player .pp-plan-price{font-size:2rem;font-weight:700;color:#172e45;margin:.5rem 0 0;line-height:1.3}.pp-player .pp-plan-price span{font-size:1rem;font-weight:400}.pp-player .pp-plan-note{color:#526c78;margin:.65rem 0 1.25rem}.pp-player .pp-consents{border:0;padding:0;margin:1.25rem 0 0;min-width:0}.pp-player .pp-consents legend{font-size:1rem;font-weight:600;color:#172e45;margin-bottom:.75rem}.pp-player .pp-consent{display:flex;gap:.75rem;align-items:flex-start;padding:.65rem 0;margin:0;cursor:pointer;font-size:.9375rem;line-height:1.6}.pp-player .pp-consent input{flex:0 0 20px;width:20px;height:20px;margin-top:.25rem;accent-color:#004177}.pp-player .pp-purchase .btn{width:100%;margin-top:1.25rem;min-height:48px}.pp-player .pp-sponsor-note{background:#fff6eb;border:1px solid #f2d6b8;border-radius:10px;padding:1rem;margin-bottom:1rem;color:#62401d}.pp-player .pp-plan-availability{font-size:.9375rem;color:#526c78;margin:0 0 1rem}.pp-player .pp-error-summary{margin-bottom:1.5rem}.pp-player .pp-field-error{color:#a12222;font-size:.875rem;margin:.25rem 0}
    @media (max-width: 575.98px) { .pp-player .pp-purchase { padding: 1.2rem; } }
  </style>
  <?php
    $membershipStatusOnly = ($purchaseBlocked ?? false) || ($adminAccess ?? null)?->mode === 'suspended'
      || (($adminAccess ?? null)?->mode === 'grant' && $adminAccess->starts_at->lte(now()) && $adminAccess->ends_at->isFuture())
      || ($membership && $membership->starts_at->lte(now()) && $membership->ends_at->isFuture());
    $showCheckout = !$membershipStatusOnly && $enabled && \App\Services\Performance\PlayerPlusService::monthlyReady();
    $ordersUrl = route('player-plus.orders.index', ['player' => $workspace['player']->id ?? null]);
    $myTennisUrl = route('my.tennis', [
      'player' => ($linked || $canViewRating) ? $player->id : null,
      'tab' => $canViewRating ? 'pathway' : null,
    ]);
  ?>
  <?php if (isset($component)) { $__componentOriginal3480b572702ecfa053ab08ce698dcaca = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3480b572702ecfa053ab08ce698dcaca = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-workspace-header','data' => ['class' => 'pp-membership-header','title' => 'Player Plus for '.$player->full_name,'eyebrow' => 'Cape Tennis · Player Plus']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-workspace-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'pp-membership-header','title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Player Plus for '.$player->full_name),'eyebrow' => 'Cape Tennis · Player Plus']); ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCheckout): ?><p>Choose a plan, then review the price and renewal terms before paying.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <p class="small">Profile #<?php echo e($player->id); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player->dateOfBirth): ?> · Born <?php echo e(substr($player->dateOfBirth, 0, 4)); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
     <?php $__env->slot('navigation', null, []); ?> 
      <nav class="d-flex flex-wrap gap-2" aria-label="Player Plus navigation">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canViewRating): ?>
          <a class="btn pp-rating-link" href="<?php echo e($myTennisUrl); ?>">Back to My Tennis · Rating</a>
        <?php else: ?>
          <a class="btn" href="<?php echo e($myTennisUrl); ?>">Back to My Tennis</a>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <a class="btn" href="<?php echo e($ordersUrl); ?>">My Plus orders</a>
        <a class="btn" href="<?php echo e(route('player-plus.index', ['player' => $workspace['player']->id ?? null])); ?>">Choose another player</a>
      </nav>
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
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCheckout): ?><?php if (isset($component)) { $__componentOriginald6fe41f5dbf7c3b0211c2c5447c14d79 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginald6fe41f5dbf7c3b0211c2c5447c14d79 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-plus-stepper','data' => ['current' => 3]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-plus-stepper'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['current' => 3]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginald6fe41f5dbf7c3b0211c2c5447c14d79)): ?>
<?php $attributes = $__attributesOriginald6fe41f5dbf7c3b0211c2c5447c14d79; ?>
<?php unset($__attributesOriginald6fe41f5dbf7c3b0211c2c5447c14d79); ?>
<?php endif; ?>
<?php if (isset($__componentOriginald6fe41f5dbf7c3b0211c2c5447c14d79)): ?>
<?php $component = $__componentOriginald6fe41f5dbf7c3b0211c2c5447c14d79; ?>
<?php unset($__componentOriginald6fe41f5dbf7c3b0211c2c5447c14d79); ?>
<?php endif; ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <section class="alert alert-danger pp-error-summary" role="alert" aria-labelledby="pp-error-heading"><h2 class="h6" id="pp-error-heading">Please review your checkout details</h2><ul class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></section>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <section class="pp-checkout" aria-label="Player Plus membership and checkout">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($linked)): ?><p class="pp-sponsor-note"><strong>You are sponsoring <?php echo e($player->name); ?>.</strong> Only the player and accounts already linked to them can use their private Player Plus benefits. Payment does not link the player to your account.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($purchaseBlocked ?? false): ?>
        <p class="alert alert-info">A new purchase for this player is not currently available. The linked account holder or existing payer can review their own access and orders, or contact Cape Tennis support.</p>
      <?php elseif(($adminAccess ?? null)?->mode === 'suspended'): ?>
        <p class="alert alert-warning">Player Plus access is deactivated. Contact Cape Tennis support. Deactivation does not stop automatic renewal; the payer can manage billing in My Plus orders.</p>
      <?php elseif(($adminAccess ?? null)?->mode === 'grant' && $adminAccess->starts_at->lte(now()) && $adminAccess->ends_at->isFuture()): ?>
        <p class="alert alert-success">This player has admin-granted access until <?php echo e($adminAccess->ends_at->format('j M Y H:i')); ?>. This grant does not record a payment or change existing subscription billing.</p>
      <?php elseif($membership && $membership->starts_at->lte(now()) && $membership->ends_at->isFuture()): ?>
        <p class="alert alert-success">This player is covered until <?php echo e($membership->ends_at->timezone('Africa/Johannesburg')->format('j M Y H:i')); ?>. <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($membership->order?->isRecurring()): ?>The payer can manage automatic renewal in My Plus orders.<?php else: ?> This existing once-off term has no automatic renewal or charge.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
      <?php else: ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($membership && $linked): ?><p class="alert alert-secondary">Membership expired on <?php echo e($membership->ends_at->timezone('Africa/Johannesburg')->format('j M Y H:i')); ?>. <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($membership->order?->isRecurring()): ?>The payer should check payment and renewal status in My Plus orders.<?php else: ?> No renewal payment has been taken automatically.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($showCheckout)): ?>
          <section class="pp-status pp-paused" aria-label="Purchases paused"><h2>Purchases are paused</h2><p>New purchases are currently paused. Existing verified memberships keep their benefits until expiry.</p></section>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($readiness): ?>
        <section class="pp-status" aria-label="Rating availability" data-plus-readiness="<?php echo e($readiness['state']); ?>"><h2><?php echo e($readiness['label']); ?></h2><p><?php echo e($readiness['description']); ?></p></section>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCheckout): ?>
          <section class="pp-plan-choices" data-plus-plan-choices hidden aria-labelledby="pp-plan-heading">
            <h2 id="pp-plan-heading" tabindex="-1">Choose a plan</h2>
            <p>For <?php echo e($player->full_name); ?> · One named player</p>
            <button type="button" class="pp-plan-choice" data-plus-choose="annual_recurring"><strong>Annual · PayFast</strong><span class="pp-choice-price">R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($priceCents)); ?> due now</span><span class="pp-choice-terms">Then R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($priceCents)); ?> every year until cancelled.</span><span>Continue <span aria-hidden="true">→</span></span></button>
            <button type="button" class="pp-plan-choice" data-plus-choose="annual"><strong>12 months · Wallet</strong><span class="pp-choice-price">R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($priceCents)); ?> due now</span><span class="pp-choice-terms">No automatic renewal. Available wallet funds: R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($walletAvailableCents ?? 0)); ?>.</span><span>Review wallet payment <span aria-hidden="true">→</span></span></button>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($monthlyEnabled): ?><button type="button" class="pp-plan-choice" data-plus-choose="monthly"><strong>Monthly · PayFast</strong><span class="pp-choice-price">R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents)); ?> due now</span><span class="pp-choice-terms">Then R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents)); ?> every month until cancelled.</span><span>Continue <span aria-hidden="true">→</span></span></button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <p class="small">Eligible results are needed; buying Plus does not guarantee a rating. <a href="<?php echo e(route('player-plus.terms', ['player' => $workspace['player']->id ?? null])); ?>">Purchase details &amp; payment support</a></p>
          </section>
          <form method="post" action="<?php echo e(route('player-plus.store', $player)); ?>" class="pp-purchase" data-plus-plan="annual_recurring" aria-labelledby="pp-annual-heading">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="request_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="terms_version" value="<?php echo e($termsVersion); ?>">
            <input type="hidden" name="quoted_price_cents" value="<?php echo e($priceCents); ?>">
            <input type="hidden" name="plan" value="annual_recurring">
            <button type="button" class="btn btn-outline-primary" data-plus-change-plan hidden>← Change plan</button>
            <h2 id="pp-annual-heading" tabindex="-1">Review &amp; pay · Annual Player Plus</h2>
            <p>For <?php echo e($player->full_name); ?></p>
            <p class="pp-plan-price">R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($priceCents)); ?> <span>due now for one player</span></p>
            <p class="pp-plan-note"><strong>Renews at R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($priceCents)); ?> every year</strong> through PayFast until you cancel. Stop future renewals from My Plus orders. Paid access continues until the paid period ends.</p>
            <button type="button" class="btn btn-outline-primary" data-plus-choose="annual" hidden data-plus-wallet-alternative>Pay once from wallet instead · No automatic renewal</button>
            <fieldset class="pp-consents"><legend>Confirm your purchase</legend>
              <label class="pp-consent" for="pp-annual-confirm"><input id="pp-annual-confirm" type="checkbox" name="confirm" value="1" required <?php $__errorArgs = ['confirm'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="pp-annual-confirm-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>><span>I have read the <a href="<?php echo e(route('player-plus.terms', ['player' => $workspace['player']->id ?? null])); ?>">purchase details</a> and am buying Player Plus for <?php echo e($player->full_name); ?>. Ratings or pathway assessments may be unavailable. Paying does not link this player to my account or give me private access.</span></label>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['confirm'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="pp-field-error" id="pp-annual-confirm-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <label class="pp-consent" for="pp-annual-recurring"><input id="pp-annual-recurring" type="checkbox" name="recurring_consent" value="1" required <?php $__errorArgs = ['recurring_consent'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="pp-annual-recurring-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>><span>I authorise R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($priceCents)); ?> now and automatic annual payments of R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($priceCents)); ?> through PayFast until I cancel.</span></label>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['recurring_consent'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="pp-field-error" id="pp-annual-recurring-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </fieldset>
            <button class="btn btn-primary">Continue to PayFast · R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($priceCents)); ?></button>
          </form>
          <form method="post" action="<?php echo e(route('player-plus.store', $player)); ?>" class="pp-purchase" data-plus-plan="annual" aria-labelledby="pp-wallet-heading">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="request_token" value="<?php echo e($token); ?>">
            <input type="hidden" name="terms_version" value="<?php echo e($termsVersion); ?>">
            <input type="hidden" name="quoted_price_cents" value="<?php echo e($priceCents); ?>">
            <input type="hidden" name="plan" value="annual">
            <input type="hidden" name="payment_method" value="wallet">
            <button type="button" class="btn btn-outline-primary" data-plus-change-plan hidden>← Change plan</button>
            <h2 id="pp-wallet-heading" tabindex="-1">Review &amp; pay · Wallet</h2>
            <p>For <?php echo e($player->full_name); ?></p>
            <p class="pp-plan-price">R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($priceCents)); ?> <span>due now for 12 months</span></p>
            <p class="pp-plan-note"><strong>One wallet payment for 12 months.</strong> No automatic renewal or future wallet debit. Available wallet funds: <strong>R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($walletAvailableCents ?? 0)); ?></strong>. Funds reserved for other checkouts are excluded.</p>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($walletAvailableCents ?? 0) < $priceCents): ?><p class="alert alert-info">Your available wallet funds do not cover this purchase. Choose the PayFast plan or add wallet funds before paying.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <fieldset class="pp-consents"><legend>Confirm your wallet purchase</legend>
              <label class="pp-consent" for="pp-wallet-confirm"><input id="pp-wallet-confirm" type="checkbox" name="confirm" value="1" required <?php $__errorArgs = ['confirm'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="pp-wallet-confirm-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>><span>I have read the <a href="<?php echo e(route('player-plus.terms', ['player' => $workspace['player']->id ?? null])); ?>">purchase details</a> and am buying Player Plus for <?php echo e($player->full_name); ?>. Ratings may be unavailable. Paying does not link this player to my account or give me private access.</span></label>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['confirm'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="pp-field-error" id="pp-wallet-confirm-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <label class="pp-consent" for="pp-wallet-consent"><input id="pp-wallet-consent" type="checkbox" name="wallet_consent" value="1" required <?php $__errorArgs = ['wallet_consent'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="pp-wallet-consent-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>><span>I authorise a once-off R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($priceCents)); ?> debit from my wallet for 12 months. There is no automatic renewal.</span></label>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['wallet_consent'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="pp-field-error" id="pp-wallet-consent-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </fieldset>
            <button class="btn btn-primary" <?php if(($walletAvailableCents ?? 0) < $priceCents): echo 'disabled'; endif; ?>>Pay from wallet · R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($priceCents)); ?></button>
          </form>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($monthlyEnabled): ?>
            <form method="post" action="<?php echo e(route('player-plus.store', $player)); ?>" class="pp-purchase" data-plus-monthly-purchase data-plus-plan="monthly" aria-labelledby="pp-monthly-heading">
              <?php echo csrf_field(); ?>
              <button type="button" class="btn btn-outline-primary" data-plus-change-plan hidden>← Change plan</button>
              <h2 id="pp-monthly-heading" tabindex="-1">Review &amp; pay · Monthly Player Plus</h2>
              <p>For <?php echo e($player->full_name); ?></p>
              <p class="pp-plan-price">R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents)); ?> <span>due now for one player</span></p>
              <p class="pp-plan-note"><strong>Renews at R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents)); ?> every month</strong> through PayFast until you cancel. Stop future renewals from My Plus orders. Paid access continues until the paid period ends.</p>
              <input type="hidden" name="request_token" value="<?php echo e($token); ?>">
              <input type="hidden" name="terms_version" value="<?php echo e($termsVersion); ?>">
              <input type="hidden" name="plan" value="monthly">
              <input type="hidden" name="quoted_price_cents" value="<?php echo e($monthlyPriceCents); ?>">
              <fieldset class="pp-consents"><legend>Confirm your purchase</legend>
                <label class="pp-consent" for="pp-monthly-confirm"><input id="pp-monthly-confirm" type="checkbox" name="confirm" value="1" required <?php $__errorArgs = ['confirm'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="pp-monthly-confirm-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>><span>I have read the <a href="<?php echo e(route('player-plus.terms', ['player' => $workspace['player']->id ?? null])); ?>">purchase details</a> and am buying Player Plus for <?php echo e($player->full_name); ?>. Ratings or pathway assessments may be unavailable. Paying does not link this player to my account or give me private access.</span></label>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['confirm'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="pp-field-error" id="pp-monthly-confirm-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <label class="pp-consent" for="pp-monthly-recurring"><input id="pp-monthly-recurring" type="checkbox" name="recurring_consent" value="1" required <?php $__errorArgs = ['recurring_consent'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="pp-monthly-recurring-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>><span>I authorise a payment of R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents)); ?> now and automatic monthly payments of R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents)); ?> through PayFast until I cancel.</span></label>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['recurring_consent'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="pp-field-error" id="pp-monthly-recurring-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </fieldset>
              <button class="btn btn-primary">Continue to PayFast · R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($monthlyPriceCents)); ?></button>
            </form>
          <?php else: ?>
            <p class="pp-plan-availability">Monthly purchases are currently unavailable. Annual PayFast and once-off wallet options are available above.</p>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>
  <?php if (isset($component)) { $__componentOriginal484b0fc2fa74ab089d36a731612a2cf0 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal484b0fc2fa74ab089d36a731612a2cf0 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-plus-renewal-help','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-plus-renewal-help'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal484b0fc2fa74ab089d36a731612a2cf0)): ?>
<?php $attributes = $__attributesOriginal484b0fc2fa74ab089d36a731612a2cf0; ?>
<?php unset($__attributesOriginal484b0fc2fa74ab089d36a731612a2cf0); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal484b0fc2fa74ab089d36a731612a2cf0)): ?>
<?php $component = $__componentOriginal484b0fc2fa74ab089d36a731612a2cf0; ?>
<?php unset($__componentOriginal484b0fc2fa74ab089d36a731612a2cf0); ?>
<?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($showCheckout): ?>
    <script src="<?php echo e(asset('assets/js/player-plus-stepper.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/player-plus-stepper.js'))); ?>" defer></script>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\player-plus\player.blade.php ENDPATH**/ ?>