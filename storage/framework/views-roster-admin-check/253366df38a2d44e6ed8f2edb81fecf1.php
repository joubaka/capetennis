<?php if (isset($component)) { $__componentOriginalaa758e6a82983efcbf593f765e026bd9 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalaa758e6a82983efcbf593f765e026bd9 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => $__env->getContainer()->make(Illuminate\View\Factory::class)->make('mail::message'),'data' => []] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('mail::message'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
<?php echo $__env->make('emails._capez-header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
# Wallet Refund Confirmed

<?php
    $player       = $registration->players->first();
    $playerName   = $emailSnapshot['player_name'] ?? ($player ? trim($player->name . ' ' . $player->surname) : 'Player');
    $event        = $registration->categoryEvent?->event;
    $eventName    = $emailSnapshot['event_name'] ?? $event?->name ?? 'the event';
    $categoryName = $emailSnapshot['category_name'] ?? $registration->categoryEvent?->category?->name ?? '';
    $gross = $emailSnapshot['gross'] ?? $registration->refund_gross;
    $fee = $emailSnapshot['fee'] ?? $registration->refund_fee;
    $net = $emailSnapshot['net'] ?? $registration->refund_net;
?>

Hi <?php echo e($payerName ?? $playerName); ?>,

Your refund for **<?php echo e($eventName); ?>** has been credited to your Cape Tennis wallet.

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refundReason): ?>
The entry for **<?php echo e($playerName); ?>** has been cancelled.

**Reason:** <?php echo e($refundReason); ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

---

**Event:** <?php echo e($eventName); ?>

**Category:** <?php echo e($categoryName); ?>

**Refund method:** Wallet (instant)
**Amount paid:** R<?php echo e(number_format($gross, 2)); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($fee > 0): ?>
**Deduction:** R<?php echo e(number_format($fee, 2)); ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
**Amount refunded:** R<?php echo e(number_format($net, 2)); ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($emailSnapshot['preview'] ?? false): ?>
**Refunded on:** Recorded after successful confirmation.
<?php else: ?>
**Refunded on:** <?php echo e($registration->refunded_at?->format('d M Y H:i') ?? now()->format('d M Y H:i')); ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

---

Your wallet balance has been updated and the funds are available immediately for future registrations.

Sign in with the same account used to pay for this registration. At registration checkout, choose **Apply Wallet Balance** and follow the displayed payment options. If further payment is required, checkout will explain the available PayFast option.

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(count($adminReplyTo)): ?>
If you have any questions, reply to this email to contact the event admin.
<?php else: ?>
If you have any questions, please contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za).
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

Thanks,
Cape Tennis
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalaa758e6a82983efcbf593f765e026bd9)): ?>
<?php $attributes = $__attributesOriginalaa758e6a82983efcbf593f765e026bd9; ?>
<?php unset($__attributesOriginalaa758e6a82983efcbf593f765e026bd9); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalaa758e6a82983efcbf593f765e026bd9)): ?>
<?php $component = $__componentOriginalaa758e6a82983efcbf593f765e026bd9; ?>
<?php unset($__componentOriginalaa758e6a82983efcbf593f765e026bd9); ?>
<?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\emails\refund\wallet-confirmation.blade.php ENDPATH**/ ?>