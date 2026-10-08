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
# Player Withdrawal Notification

<?php
    $registration = $registration;
    $player       = $registration->players->first();
    $playerName   = $player ? trim($player->name . ' ' . $player->surname) : 'Unknown Player';
    $event        = $registration->categoryEvent?->event;
    $eventName    = $event?->name ?? '–';
    $categoryName = $registration->categoryEvent?->category?->name ?? '–';
    $withdrawnAt  = $registration->withdrawn_at?->format('d M Y H:i') ?? now()->format('d M Y H:i');

    // Player contact
    $playerEmail  = $player?->email ?? $registration->user?->email ?? '–';
    $playerCell   = $player?->cell ?? '–';

    // Who performed the withdrawal
    $actor = $initiatedBy === 'admin' ? 'Event administrator' : ($playerName . ' (player)');
?>

A player has **withdrawn** from your event.

---

**Player:** <?php echo e($playerName); ?>  
**Email:** <?php echo e($playerEmail); ?>  
**Cell:** <?php echo e($playerCell); ?>  
**Event:** <?php echo e($eventName); ?>  
**Category:** <?php echo e($categoryName); ?>  
**Withdrawn on:** <?php echo e($withdrawnAt); ?>  
**Initiated by:** <?php echo e($actor); ?>


---

**Refund Summary**

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($registration->refund_status === 'completed'): ?>
Refund has been **completed**.

- **Method:** <?php echo e(ucfirst($registration->refund_method)); ?>  
- **Gross:** R<?php echo e(number_format($registration->refund_gross, 2)); ?>  
- **Fee:** R<?php echo e(number_format($registration->refund_fee, 2)); ?>  
- **Net refunded:** R<?php echo e(number_format($registration->refund_net, 2)); ?>  
- **Refunded on:** <?php echo e($registration->refunded_at?->format('d M Y H:i') ?? '–'); ?>


<?php elseif($registration->refund_status === 'pending'): ?>
Refund is **pending**.

- **Method:** <?php echo e(ucfirst($registration->refund_method)); ?>  
- **Gross:** R<?php echo e(number_format($registration->refund_gross, 2)); ?>  
- **Fee:** R<?php echo e(number_format($registration->refund_fee, 2)); ?>  
- **Net to be refunded:** R<?php echo e(number_format($registration->refund_net, 2)); ?>


<?php else: ?>
**No refund** has been issued.

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(! $registration->is_paid): ?>
*(Registration was not paid.)*
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

---

You can view this registration in the [admin panel](<?php echo e(url('/backend/event/' . ($event?->id ?? '') . '/entries')); ?>).

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
<?php /**PATH C:\wamp64\www\ct\resources\views\emails\withdrawal\admin.blade.php ENDPATH**/ ?>