<?php $__env->startSection('title', 'Player Plus checkout'); ?>
<?php $__env->startSection('content'); ?>
<?php
  $subscription = $subscription ?? null;
  $checkoutOpen = $order->status === 'pending' && $order->expires_at->isFuture();
  $salesPaused = $checkoutOpen && !$order->payfast_handed_off_at && !($retrySalesOpen ?? \App\Services\Performance\PlayerPlusService::salesEnabled());
  $stateTone = $order->reconciliation_reason || $order->status === 'reconciliation_required' ? 'review' : ($order->activated_at ? 'verified' : ($checkoutOpen ? 'pending' : 'closed'));
  $stateLabel = $stateTone === 'review' ? 'Payment needs review' : ($stateTone === 'verified' ? 'Payment verified' : ($checkoutOpen ? ($order->payfast_handed_off_at && !$form ? 'Awaiting confirmation' : ($salesPaused ? 'Purchases paused' : 'Payment pending')) : 'Checkout closed'));
?>
<div class="container-xxl container-p-y pp-checkout pp-journey ct-player-workspace" data-plus-checkout data-order-state="<?php echo e($stateTone); ?>">
  <style>
    .pp-checkout{max-width:1180px;color:#243e50}.pp-checkout *{box-sizing:border-box}.pp-checkout .pp-checkout-nav{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem}.pp-checkout .pp-checkout-nav a{display:inline-flex;align-items:center;min-height:44px;font-size:.875rem;color:#004177}.pp-checkout .pp-checkout-nav span{font-size:.8rem;color:#607681}
    .pp-checkout .pp-checkout-hero{position:relative;overflow:hidden;border-radius:22px;background:linear-gradient(120deg,#172e45,#004177);padding:2.25rem;margin-bottom:1.5rem;color:#fff}.pp-checkout .pp-checkout-hero:after{content:'';position:absolute;right:-70px;top:-150px;width:340px;height:340px;border:1px solid #ffffff17;border-radius:50%;pointer-events:none}.pp-checkout .pp-checkout-eyebrow{font-size:.7rem;letter-spacing:.15em;text-transform:uppercase;color:#ffd3aa;font-weight:700;margin:0 0 .8rem}.pp-checkout .pp-checkout-hero h1{color:#fff;font-size:clamp(1.7rem,3.7vw,2.4rem);letter-spacing:-.035em;line-height:1.2;margin:0 0 .9rem;max-width:850px;overflow-wrap:anywhere}.pp-checkout .pp-checkout-hero>p:last-of-type{color:#dce8f5;line-height:1.65;max-width:65ch;margin-bottom:1.25rem}.pp-checkout .pp-checkout-meta{position:relative;z-index:1;display:flex;align-items:center;flex-wrap:wrap;gap:.65rem 1rem;font-size:.8rem;color:#d1dfef}.pp-checkout .pp-order-chip{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .75rem;border:1px solid #ffffff38;border-radius:7px;background:#ffffff0b;color:#fff}.pp-checkout .pp-order-chip strong{font-weight:600}
    .pp-checkout .pp-checkout-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.45fr);gap:1.5rem;align-items:start}.pp-checkout .pp-checkout-benefit-column{grid-column:2;grid-row:1;min-width:0}.pp-checkout .pp-checkout-summary{grid-column:1;grid-row:1;min-width:0}.pp-checkout .pp-payment-card{border:1px solid #dce5ef;border-top:4px solid #004177;border-radius:16px;background:#fff;box-shadow:0 8px 28px #172e4509;padding:1.5rem}.pp-checkout[data-order-state=review] .pp-payment-card{border-top-color:#b5832e}.pp-checkout[data-order-state=closed] .pp-payment-card{border-top-color:#87979e}.pp-checkout .pp-payment-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem;margin-bottom:1.1rem}.pp-checkout .pp-payment-heading h2{font-size:1.05rem;margin:0;color:#172e45}.pp-checkout .pp-payment-heading>span{font-size:.72rem;line-height:1.4;padding:.35rem .55rem;border-radius:6px;background:#eef4fa;color:#004177;max-width:9rem;text-align:center}.pp-checkout .pp-checkout-price{display:flex;align-items:baseline;flex-wrap:wrap;gap:.2rem .55rem;margin-bottom:.4rem}.pp-checkout .pp-checkout-price strong{font-size:2.4rem;letter-spacing:-.045em;line-height:1.2;color:#172e45}.pp-checkout .pp-checkout-price>span{font-size:.875rem;color:#607681}.pp-checkout .pp-plan-disclosure{font-size:.8rem;line-height:1.65;color:#526c78;margin-bottom:1.25rem;padding-bottom:1.25rem;border-bottom:1px solid #dce5ef}.pp-checkout .pp-payment-card p{font-size:.875rem;line-height:1.7;overflow-wrap:anywhere}.pp-checkout .pp-payment-card .alert{padding:1rem;border-radius:10px;border:0;line-height:1.65}.pp-checkout .pp-payment-card .btn{min-height:48px;display:flex;align-items:center;justify-content:center;width:100%;white-space:normal;line-height:1.5;gap:.5rem}.pp-checkout .pp-payment-card .btn-primary{background:#004177;border-color:#004177;box-shadow:0 4px 10px #00417714}.pp-checkout .pp-payment-card .btn-primary:hover{background:#00345f;border-color:#00345f}.pp-checkout .pp-payment-card .btn-outline-primary{color:#004177;border-color:#9ab4cf}.pp-checkout .pp-payment-card .btn-outline-secondary{border-color:#d7e1ed;color:#607681}.pp-checkout .pp-payment-trust{display:flex;align-items:flex-start;gap:.45rem;font-size:.75rem;line-height:1.6;color:#607681;margin-top:1rem}.pp-checkout .pp-payment-trust .ti{font-size:1rem;margin-top:.1rem;flex-shrink:0}
    .pp-checkout .pp-checkout-benefits{border-radius:16px!important;padding:1.5rem!important;box-shadow:none}.pp-checkout .pp-checkout-benefits>p:first-of-type{font-size:.875rem;color:#526c78;line-height:1.7;margin-top:0!important}.pp-checkout .pp-checkout-benefits>p.fw-semibold{font-size:1.2rem;line-height:1.45;color:#172e45;margin-top:1.25rem}.pp-checkout .pp-checkout-benefits>ul{list-style:none;padding:0!important;margin:1.2rem 0!important;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem}.pp-checkout .pp-checkout-benefits>ul>li{position:relative;padding:3.7rem 1rem 1rem;background:#f2f6fb;border:1px solid #dce5ef;border-radius:11px;line-height:1.7;color:#38516e;min-width:0}.pp-checkout .pp-checkout-benefits>ul>li:before{content:'↗';position:absolute;top:1rem;left:1rem;display:grid;place-items:center;width:1.9rem;height:1.9rem;border-radius:8px;background:#e3edf7;color:#004177;font-size:1.15rem;font-weight:700}.pp-checkout .pp-checkout-benefits>ul>li:nth-child(2):before{content:'≡'}.pp-checkout .pp-checkout-benefits>ul>li:nth-child(3):before{content:'★'}.pp-checkout .pp-checkout-benefits>ul>li:nth-child(4):before{content:'→'}.pp-checkout .pp-checkout-benefits details{border-top:1px solid #dce5ef;padding-top:.5rem;margin-top:1.2rem}.pp-checkout .pp-checkout-benefits summary{font-weight:600;color:#264d73}.pp-checkout .pp-checkout-benefits details p{line-height:1.7}.pp-checkout a:not(.btn){color:#004177}.pp-checkout .pp-renewal-panel{padding-top:1.25rem;border-top:1px solid #dce5ef;margin-top:1.25rem}.pp-checkout .pp-renewal-panel h2{font-size:1rem;color:#172e45;margin-bottom:.75rem}.pp-checkout .pp-renewal-panel .btn-outline-danger{font-size:.825rem;min-height:44px}.pp-checkout .pp-checkout-footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem 1.5rem;border-top:1px solid #dce5ef;padding-top:1rem;margin-top:1.75rem;font-size:.85rem}.pp-checkout .pp-checkout-footer a{display:inline-flex;align-items:center;min-height:44px}.pp-checkout :is(a,button,summary):focus-visible{outline:3px solid #ff9f43;outline-offset:3px}
    .pp-checkout .pp-payment-card .btn{min-height:48px!important}
    .pp-checkout .pp-current-state{margin-top:.35rem;color:#004177;font-size:clamp(1.35rem,3vw,1.85rem)!important;font-weight:700;line-height:1.25!important}
    .pp-checkout[data-order-state=review] .pp-current-state{color:#805618}
    .pp-checkout .pp-next-action{max-width:42rem}
    @media(min-width:576px){.pp-checkout .pp-next-action .btn{width:fit-content}}
    @media(max-width:991.98px){.pp-checkout .pp-checkout-grid{grid-template-columns:minmax(0,1fr)}.pp-checkout .pp-checkout-summary{grid-column:1;grid-row:1;position:static}.pp-checkout .pp-checkout-benefit-column{grid-column:1;grid-row:2}.pp-checkout .pp-checkout-hero{padding:1.75rem}.pp-checkout .pp-checkout-price strong{font-size:2.1rem}}
    @media(max-width:575.98px){.pp-checkout .pp-checkout-hero{border-radius:16px;padding:1.4rem}.pp-checkout .pp-payment-card,.pp-checkout .pp-checkout-benefits{padding:1.2rem!important}.pp-checkout .pp-checkout-grid{gap:1rem}.pp-checkout .pp-checkout-benefits>ul{grid-template-columns:1fr}.pp-checkout .pp-checkout-benefits>ul>li{padding:1rem 1rem 1rem 3.7rem}.pp-checkout .pp-payment-heading{flex-wrap:wrap}.pp-checkout .pp-checkout-footer{align-items:flex-start;flex-direction:column;gap:.2rem}}
  </style>
  <?php echo $__env->make('frontend.player-plus.styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php if (isset($component)) { $__componentOriginal3480b572702ecfa053ab08ce698dcaca = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3480b572702ecfa053ab08ce698dcaca = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-workspace-header','data' => ['workspace' => $workspace ?? null,'title' => 'Player Plus for '.($order->player?->full_name ?? 'Player profile unavailable'),'eyebrow' => 'Cape Tennis · Optional Player Plus','subtitle' => 'Track your payment and manage this order.']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-workspace-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['workspace' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($workspace ?? null),'title' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Player Plus for '.($order->player?->full_name ?? 'Player profile unavailable')),'eyebrow' => 'Cape Tennis · Optional Player Plus','subtitle' => 'Track your payment and manage this order.']); ?>
     <?php $__env->slot('summary', null, []); ?> 
    <div class="pp-checkout-meta">
      <span class="pp-order-chip"><i class="ti ti-receipt" aria-hidden="true"></i> Order <strong>P<?php echo e($order->id); ?></strong></span>
      <span>Profile #<?php echo e($order->player_id); ?> <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->player?->dateOfBirth): ?> · Born <?php echo e(substr($order->player->dateOfBirth, 0, 4)); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></span>
    </div>
     <?php $__env->endSlot(); ?>
     <?php $__env->slot('navigation', null, []); ?> <nav class="d-flex flex-wrap gap-2" aria-label="Checkout navigation"><a class="btn btn-outline-primary" href="<?php echo e(route('player-plus.orders.index', ['player' => $workspace['player']->id ?? null])); ?>">← Your orders</a><a class="btn btn-outline-primary" href="<?php echo e(route('player-plus.index', ['player' => $workspace['player']->id ?? null])); ?>">Player Plus checkout</a></nav> <?php $__env->endSlot(); ?>
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
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?><p class="alert alert-success"><?php echo e(session('success')); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><p class="alert alert-danger"><?php echo e($error); ?></p><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('plus_checkout_review')): ?><p class="alert alert-info" role="status"><?php echo e(session('plus_checkout_review')); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <section class="pp-payment-card mb-3" aria-labelledby="pp-payment-heading">
    <div class="pp-payment-heading">
      <div>
        <h2 id="pp-payment-heading">Payment status</h2>
        <p class="pp-current-state mb-0"><?php echo e($stateLabel); ?></p>
      </div>
      <span>Order P<?php echo e($order->id); ?></span>
    </div>
    <div class="pp-next-action" role="group" aria-label="Next action">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($returnFlow ?? false): ?>
        <section class="alert alert-info" role="status" data-plus-payment-return data-status-url="<?php echo e(route('player-plus.orders.payment-status', $order)); ?>">
          <span data-plus-return-message>Checking for verified PayFast confirmation. We’ll take you to the next step when the payment status is available.</span>
          <a class="d-block mt-2" href="<?php echo e(route('player-plus.orders.return', $order)); ?>">Check payment status</a>
        </section>
        <script src="<?php echo e(asset('assets/js/player-plus-checkout.js')); ?>" defer></script>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->activated_at): ?>
        <p class="alert alert-<?php echo e($order->term_ends_at->isFuture() ? 'success' : 'secondary'); ?>">Payment verified. Purchased membership term: <?php echo e($order->term_starts_at->timezone('Africa/Johannesburg')->format('j M Y H:i')); ?> to <?php echo e($order->term_ends_at->timezone('Africa/Johannesburg')->format('j M Y H:i')); ?> SAST. <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->isRecurring()): ?>Subscription payments and purchased terms are recorded separately in My Plus orders.<?php else: ?> <?php echo e($order->term_ends_at->isFuture() ? 'No automatic renewal.' : 'This purchased term has expired. No automatic renewal payment was taken.'); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></p>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->reconciliation_reason): ?><p class="alert alert-warning">An additional payment needs Cape Tennis reconciliation. The original membership term is unchanged. Keep order P<?php echo e($order->id); ?> as your reference.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php elseif($order->status === 'reconciliation_required' || $order->reconciliation_reason): ?>
        <p class="alert alert-warning">Your payment was received and needs Cape Tennis reconciliation. Membership was not activated. Keep order P<?php echo e($order->id); ?> as your reference.</p>
      <?php elseif($order->status === 'pending' && $order->expires_at->isFuture()): ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($form): ?>
          <p>Continue to PayFast to <?php echo e($order->isRecurring() ? 'authorise a subscription at' : 'make a once-off'); ?> R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($order->price_cents)); ?><?php echo e($order->isRecurring() ? ($order->isMonthly() ? ' per month until cancelled' : ' per year until cancelled') : ''); ?>. Membership starts only after verified payment confirmation.</p>
          <?php echo $form; ?>

          <button type="submit" form="payfastForm" class="btn btn-primary" style="min-height:44px">Continue to PayFast · R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($order->price_cents)); ?> <i class="ti ti-arrow-right" aria-hidden="true"></i></button>
        <?php elseif(!$order->payfast_handed_off_at): ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($salesPaused)): ?>
            <form method="post" action="<?php echo e(route('player-plus.orders.pay', $order)); ?>"><?php echo csrf_field(); ?><button class="btn btn-primary" style="min-height:44px">Pay R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($order->price_cents)); ?> with PayFast <i class="ti ti-arrow-right" aria-hidden="true"></i></button></form>
          <?php else: ?><p class="alert alert-info">Purchases are paused. This order cannot begin payment.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php else: ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($returnFlow ?? false)): ?>
            <p class="alert alert-info">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($returnedFromCancel ?? false): ?> <strong>Cancelled or couldn’t finish at PayFast?</strong> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              Awaiting verified PayFast confirmation.
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($retrySalesOpen ?? false): ?>
                If you cancelled or could not finish, check back after <time datetime="<?php echo e($order->expires_at->toAtomString()); ?>"><?php echo e($order->expires_at->timezone('Africa/Johannesburg')->format('H:i:s')); ?> SAST</time>
                to start a fresh checkout. This gives PayFast time to confirm any payment already made.
              <?php else: ?>
                New purchases for this plan are currently paused.
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </p>
            <a href="<?php echo e($statusUrl ?? route('player-plus.orders.show', $order)); ?>" class="btn btn-outline-primary"
               data-plus-retry-refresh-ms="<?php echo e(max(1, $order->expires_at->getTimestamp() - now()->getTimestamp() + 1) * 1000); ?>">Check payment status</a>
            <script src="<?php echo e(asset('assets/js/player-plus-checkout.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/player-plus-checkout.js'))); ?>" defer></script>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php else: ?><p>This checkout is closed or expired. A delayed payment will be held for reconciliation with the original payer.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canRestart ?? false): ?>
        <a href="<?php echo e(route('player-plus.player', $order->player)); ?>" class="btn btn-primary">Start a new checkout</a>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canClose ?? false): ?>
        <form class="mt-3" method="post" action="<?php echo e(route('player-plus.orders.cancel', $order)); ?>"><?php echo csrf_field(); ?><button class="btn btn-outline-secondary">Close checkout</button></form>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($stateTone === 'review'): ?>
        <a class="btn btn-outline-primary mt-2" href="mailto:support@capetennis.co.za?subject=Player%20Plus%20order%20P<?php echo e($order->id); ?>">Contact payment support</a>
      <?php elseif($stateTone === 'verified' || ($stateTone === 'closed' && !($canRestart ?? false)) || ($salesPaused && !($canClose ?? false))): ?>
        <a class="btn btn-outline-primary mt-2" href="<?php echo e(route('player-plus.orders.index', ['player' => $workspace['player']->id ?? null])); ?>">View My Plus orders</a>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($checkoutOpen): ?><div class="pp-payment-trust"><i class="ti ti-lock" aria-hidden="true"></i><span>Payment is handled by PayFast. Cape Tennis activates access only after verified payment.</span></div><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($subscription): ?>
        <section class="pp-renewal-panel" aria-label="Automatic renewal" data-plus-subscription>
          <h2 class="h5"><?php echo e($order->isMonthly() ? 'Monthly' : 'Annual'); ?> automatic renewal</h2>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($subscription->cancelled_at): ?>
            <p>Automatic renewal stopped. PayFast confirmed cancellation on <?php echo e($subscription->cancelled_at->timezone('Africa/Johannesburg')->format('j M Y H:i')); ?> SAST. Verified paid access remains until its paid expiry.</p>
          <?php else: ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($subscription->cancellation_requested_at): ?><p class="alert alert-warning">Cancellation needs PayFast confirmation. Automatic charges may continue until it is confirmed. Retry below or contact support.</p>
            <?php else: ?><p>R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($order->price_cents)); ?> per <?php echo e($order->isMonthly() ? 'month' : 'year'); ?>. Next scheduled renewal: <?php echo e($subscription->next_billing_at->timezone('Africa/Johannesburg')->format('j M Y')); ?>. Access extends only after verified payment.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <form method="post" action="<?php echo e(route('player-plus.orders.stop-renewal', $order)); ?>" onsubmit="return confirm('Stop future automatic renewals? Your verified paid access remains until its paid expiry.');">
              <?php echo csrf_field(); ?><button class="btn btn-outline-danger" style="min-height:44px"><?php echo e($subscription->cancellation_requested_at ? 'Retry stopping automatic renewal' : 'Stop automatic renewal'); ?></button>
            </form>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </section>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </section>
  <div class="pp-checkout-grid">
    <section class="pp-checkout-summary pp-payment-card" aria-labelledby="pp-order-details">
      <div class="pp-payment-heading"><h2 id="pp-order-details">Order details</h2></div>
      <div class="pp-checkout-price"><strong>R<?php echo e(\App\Services\Performance\PlayerPlusService::amount($order->price_cents)); ?></strong><span><?php echo e($order->isRecurring() ? ($order->isMonthly() ? '/ month' : '/ year') : 'for 12 months'); ?></span></div>
      <p class="pp-plan-disclosure"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->isRecurring()): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($subscription?->cancelled_at): ?>Automatic renewal stopped. Saved <?php echo e($order->isMonthly() ? 'monthly' : 'annual'); ?> subscription price.<?php else: ?> <?php echo e($order->isMonthly() ? 'Monthly' : 'Annual'); ?> automatic renewal at your agreed price until cancelled.<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> <?php else: ?> Once-off annual purchase. No automatic renewal. <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?> For one named player.</p>
      <p class="small text-muted">Eligible results are needed for a rating; purchasing Plus does not guarantee one.</p>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->isWalletPurchase()): ?><p class="alert alert-info" data-plus-wallet-paid>Paid from your wallet. This once-off purchase has no automatic renewal.</p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </section>
    <div class="pp-checkout-benefit-column">
      <?php if (isset($component)) { $__componentOriginal30d1e6548142cbb27dce511f3ad9ccfd = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal30d1e6548142cbb27dce511f3ad9ccfd = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-plus-benefits','data' => ['showPricing' => false,'class' => 'pp-checkout-benefits']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-plus-benefits'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['show-pricing' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(false),'class' => 'pp-checkout-benefits']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal30d1e6548142cbb27dce511f3ad9ccfd)): ?>
<?php $attributes = $__attributesOriginal30d1e6548142cbb27dce511f3ad9ccfd; ?>
<?php unset($__attributesOriginal30d1e6548142cbb27dce511f3ad9ccfd); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal30d1e6548142cbb27dce511f3ad9ccfd)): ?>
<?php $component = $__componentOriginal30d1e6548142cbb27dce511f3ad9ccfd; ?>
<?php unset($__componentOriginal30d1e6548142cbb27dce511f3ad9ccfd); ?>
<?php endif; ?>
    </div>
  </div>
  <footer class="pp-checkout-footer">
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->player): ?><a href="<?php echo e(route('player-plus.player', $order->player)); ?>">Player membership and benefits</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <a href="<?php echo e(route('player-plus.orders.index', ['player' => $workspace['player']->id ?? null])); ?>">My Plus orders</a>
    <a href="mailto:support@capetennis.co.za?subject=Player%20Plus%20order%20P<?php echo e($order->id); ?>">Contact payment support · P<?php echo e($order->id); ?></a>
  </footer>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\player-plus\order.blade.php ENDPATH**/ ?>