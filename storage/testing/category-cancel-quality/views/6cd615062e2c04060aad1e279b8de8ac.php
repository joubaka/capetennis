<?php $__env->startSection('title', 'Player Plus purchase details'); ?>
<?php $__env->startSection('content'); ?>
<div class="container-xxl container-p-y pp-journey ct-player-workspace" style="max-width:900px">
  <?php echo $__env->make('frontend.player-plus.styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php if (isset($component)) { $__componentOriginal3480b572702ecfa053ab08ce698dcaca = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal3480b572702ecfa053ab08ce698dcaca = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-workspace-header','data' => ['workspace' => $workspace ?? null,'title' => 'Player Plus purchase details','eyebrow' => 'Player Plus · Before you buy','subtitle' => 'Purchase details version '.$version]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-workspace-header'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['workspace' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($workspace ?? null),'title' => 'Player Plus purchase details','eyebrow' => 'Player Plus · Before you buy','subtitle' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Purchase details version '.$version)]); ?>
     <?php $__env->slot('navigation', null, []); ?> <nav class="d-flex flex-wrap gap-2" aria-label="Player Plus navigation"><a class="btn btn-outline-primary" href="<?php echo e(route('player-plus.index', ['player' => $workspace['player']->id ?? null])); ?>">Back to Player Plus</a><a class="btn btn-outline-primary" href="<?php echo e(route('player-plus.orders.index', ['player' => $workspace['player']->id ?? null])); ?>">My Plus orders</a></nav> <?php $__env->endSlot(); ?>
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
  <?php if (isset($component)) { $__componentOriginal30d1e6548142cbb27dce511f3ad9ccfd = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal30d1e6548142cbb27dce511f3ad9ccfd = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.player-plus-benefits','data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('player-plus-benefits'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
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
  <div class="pp-terms-body mt-4">
  <h2 class="h4 mt-0"><i class="ti ti-calendar" aria-hidden="true"></i>Annual automatic renewal</h2>
  <p>Annual PayFast subscriptions require separate recurring-payment consent: the annual price is charged now and the same agreed price is charged each year through PayFast until cancelled. Each verified annual payment buys a year for the named player. Check the player name, profile number and birth year before paying. Stop future renewals from My Plus orders; paid access remains until its paid expiry.</p>
  <h2 class="h4">Once-off wallet payment</h2>
  <p>You can pay the full annual price from your own available wallet funds for 12 months. This is a once-off purchase with no automatic renewal, PayFast subscription or future wallet charge. Funds reserved for other checkouts cannot be spent. The full debit and membership activation must both succeed; insufficient funds do not create a paid membership. Sponsoring another player does not grant private access.</p>
  <p>Existing once-off annual checkouts and paid terms keep their original conditions and do not become subscriptions. Their order pages continue to identify them as once-off purchases with no automatic renewal.</p>
  <h2 class="h4"><i class="ti ti-refresh" aria-hidden="true"></i>Monthly automatic renewal</h2>
  <p>If you choose the monthly plan, your separate recurring-payment consent authorises the first monthly price now and the same agreed price each month through PayFast until cancelled. Later price changes apply only to new subscriptions. The next scheduled billing date appears in your order after verified payment. Month-end dates use the last day of shorter months. Only verified payment extends access; failed or missing payments do not grant a free period.</p>
  <p>The original payer can stop either plan's automatic renewal from My Plus orders, including while new purchases are paused. Cancellation must be confirmed by PayFast; a pending or failed request does not mean charges have stopped. Verified access remains until the paid period ends. A charge arriving after a cancellation request, an unexpected extra charge or a payment outside the expected billing period is held for reconciliation and does not extend access. Contact support promptly if cancellation needs confirmation.</p>
  <p>From My Profile or your player dashboard, open <a href="<?php echo e(route('player-plus.orders.index', ['player' => $workspace['player']->id ?? null])); ?>">My Plus orders</a>, choose the subscription order, then select <strong>Stop automatic renewal</strong>. Only the original payer can do this. Once-off wallet purchases have no renewal to cancel.</p>
  <h2 class="h4"><i class="ti ti-chart-line" aria-hidden="true"></i>Ratings and pathway guidance depend on evidence</h2>
  <p>Payment does not create results, guarantee a rating, improve a rating or guarantee selection. History may be provisional, incomplete, withheld after publication changes, or unavailable until suitable published singles results and saved calculations exist. Pathway guidance needs suitable ball-level evidence and uses the configured development benchmarks. Discuss development decisions with a coach; guidance does not automatically promote a player.</p>
  <h2 class="h4"><i class="ti ti-shield-lock" aria-hidden="true"></i>Sponsorship and privacy</h2>
  <p>You may pay for another player. Only that player and their currently linked accounts can use private benefits. Purchase does not create an account link. Payer receipts and order access remain with the payer.</p>
  <h2 class="h4"><i class="ti ti-help" aria-hidden="true"></i>Payment issues and requests for help</h2>
  <p>Returning from PayFast does not prove payment. Initial payments received after checkout expiry or cancellation, unexpected additional payments, and recurring payments outside the expected billing period need Cape Tennis review and do not automatically activate or extend membership. Normal verified monthly renewals follow the subscription schedule, not the initial checkout expiry. Do not pay again while a payment is being checked.</p>
  <p>For an incorrect-player purchase, missing data, duplicate or delayed payment, or a refund enquiry, contact <a href="mailto:support@capetennis.co.za">support@capetennis.co.za</a> with your P order number. Support must verify the payer and payment evidence before deciding or processing a remedy. Do not email bank credentials, passwords or full card details. This page does not authorise an automatic refund or transfer, and does not limit rights that otherwise apply.</p>
  <p>Essential tournament information remains free. Morning emails and entered-event updates, including draw alerts after ten unchanged minutes, are planned and are not part of this purchase until activated and offered.</p>
  <p><a href="<?php echo e(route('player-plus.orders.index', ['player' => $workspace['player']->id ?? null])); ?>">Find my Plus orders</a></p>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/contentNavbarLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\player-plus\terms.blade.php ENDPATH**/ ?>