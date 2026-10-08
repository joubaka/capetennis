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
# Withdrawal Confirmation

<?php
    $registration = $registration;
    $player       = $registration->players->first();
    $playerName   = $player ? trim($player->name . ' ' . $player->surname) : 'Player';
    $event        = $registration->categoryEvent?->event;
    $eventName    = $event?->name ?? 'the event';
    $categoryName = $registration->categoryEvent?->category?->name ?? '';
    $withdrawnAt  = $registration->withdrawn_at?->format('d M Y H:i') ?? now()->format('d M Y H:i');
?>

Hi <?php echo e($playerName); ?>,

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($initiatedBy === 'admin'): ?>
Your registration has been **withdrawn by an event administrator**.
<?php else: ?>
Your withdrawal from **<?php echo e($eventName); ?>** has been confirmed.
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

---

**Event:** <?php echo e($eventName); ?>  
**Category:** <?php echo e($categoryName); ?>  
**Withdrawn on:** <?php echo e($withdrawnAt); ?>  
**Initiated by:** <?php echo e($initiatedBy === 'admin' ? 'Event administrator' : 'You'); ?>


---

**Refund Summary**

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registration->refund_status === 'completed'): ?>
Your refund has been processed.

- **Refund method:** <?php echo e(ucfirst($registration->refund_method)); ?>  
- **Amount paid:** R<?php echo e(number_format($registration->refund_gross, 2)); ?>  
- **Withdrawal fee:** R<?php echo e(number_format($registration->refund_fee, 2)); ?>

- **Amount refunded:** R<?php echo e(number_format($registration->refund_net, 2)); ?>  
- **Refunded on:** <?php echo e($registration->refunded_at?->format('d M Y H:i') ?? '–'); ?>


<?php elseif($registration->refund_status === 'pending'): ?>
Your refund is **pending** and will be processed shortly.

- **Refund method:** <?php echo e(ucfirst($registration->refund_method)); ?>  
- **Amount paid:** R<?php echo e(number_format($registration->refund_gross, 2)); ?>  
- **Withdrawal fee:** R<?php echo e(number_format($registration->refund_fee, 2)); ?>

- **Amount to be refunded:** R<?php echo e(number_format($registration->refund_net, 2)); ?>


<?php else: ?>
No refund has been processed yet. If this paid withdrawal is eligible, follow the refund instructions shown after withdrawal or contact support.

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $registration->is_paid): ?>
*(Registration was not paid.)*
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

---

If you have any questions, please contact us at [support@capetennis.co.za](mailto:support@capetennis.co.za).

Thanks,<br>
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
<?php /**PATH C:\wamp64\www\ct\resources\views\emails\withdrawal\player.blade.php ENDPATH**/ ?>