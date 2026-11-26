<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refund->relationLoaded('historicalImport') && $refund->historicalImport): ?>
  <?php ($refundImport = $refund->historicalImport); ?>
  <p class="small text-muted mb-1 mt-2">
    Historical refund #<?php echo e($refundImport->source_refund_id); ?> copied from
    <a href="<?php echo e($refundImport->source_snapshot['source_url']); ?>" target="_blank" rel="noopener noreferrer">verified live history</a>.
    Recorded locally by <?php echo e($refundImport->imported_by_name); ?> on <?php echo e($refundImport->imported_at->format('d M Y H:i')); ?>.
    The original refund actors and dates above belong to the source record. Importing this evidence sent no payment.
  </p>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\clothing\partials\historical-refund-source.blade.php ENDPATH**/ ?>