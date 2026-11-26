<?php $__env->startSection('title', 'Pay category difference'); ?>
<?php $__env->startSection('content'); ?>
<div class="container py-4" style="max-width:720px">
  <h1 class="h4">Pay category difference</h1>
  <p>Continue to PayFast to pay R<?php echo e(number_format((float) $adjustment->difference, 2)); ?>.</p>
  <?php echo $form; ?>

  <button class="btn btn-primary" type="submit" form="payfastForm">Continue to PayFast</button>
  <a class="btn btn-link" href="<?php echo e(route('registrations.categoryAdjustment.show', $adjustment)); ?>">Back</a>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\frontend\registrations\category-adjustment-handoff.blade.php ENDPATH**/ ?>