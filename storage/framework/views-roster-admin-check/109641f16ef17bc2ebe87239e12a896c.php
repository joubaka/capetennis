<?php
    $suspended = $suspended ?? false;
    $activePoints = $activePoints ?? 0;
    $threshold = $threshold ?? 12;
?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($suspended): ?>
    <span class="badge bg-danger"><i class="ti ti-ban me-1"></i>Suspended</span>
<?php elseif($activePoints >= $threshold): ?>
    <span class="badge bg-warning text-dark"><i class="ti ti-alert-triangle me-1"></i>Threshold Reached</span>
<?php elseif($activePoints > 0): ?>
    <span class="badge bg-label-warning"><?php echo e($activePoints); ?> pts</span>
<?php else: ?>
    <span class="badge bg-success"><i class="ti ti-check me-1"></i>Clear</span>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\disciplinary\_status_badge.blade.php ENDPATH**/ ?>