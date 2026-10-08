<?php $__env->startComponent('mail::message'); ?>
<?php echo $__env->make('emails._capez-header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
# <?php echo e(match($action) {
    'registration' => 'Team registration confirmed',
    'withdrawal' => 'Team player withdrawn',
    'refund_requested' => 'Team refund requested',
    'refund_completed' => 'Team refund completed',
    default => 'Team registration update',
}); ?>


**Event:** <?php echo e($order->event?->name ?? 'Team event'); ?>

**Player:** <?php echo e($order->effectivePlayer?->full_name ?? trim(($order->effectivePlayer?->name ?? '').' '.($order->effectivePlayer?->surname ?? ''))); ?>

**Team:** <?php echo e($order->team?->name ?? ('Team #'.$order->team_id)); ?>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($details['gross'])): ?>
**Original payment:** R<?php echo e(number_format($details['gross'], 2)); ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($details['fee'])): ?>
**Withdrawal fee:** R<?php echo e(number_format($details['fee'], 2)); ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($details['net'])): ?>
**Refund amount:** R<?php echo e(number_format($details['net'], 2)); ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($details['payfast_net'])): ?>
**Returned through PayFast:** R<?php echo e(number_format($details['payfast_net'], 2)); ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($details['wallet_net'])): ?>
**Returned to wallet:** R<?php echo e(number_format($details['wallet_net'], 2)); ?>

<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($action === 'refund_requested'): ?>
Your refund request has been recorded and is awaiting processing.
<?php elseif($action === 'refund_completed'): ?>
Your refund has been processed. PayFast refunds may take several business days to reflect.
<?php elseif($action === 'withdrawal'): ?>
The player has been removed from the team registration. Any eligible refund is handled separately.
<?php else: ?>
Payment was confirmed and the player is registered for the team event.
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

Thanks,
Cape Tennis
<?php echo $__env->renderComponent(); ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\emails\team\action.blade.php ENDPATH**/ ?>