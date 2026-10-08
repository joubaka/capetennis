<div class="table-responsive mb-5">
  <a href="<?php echo e(route('transactions.pdf', $event->id)); ?>" target="_blank" class="btn btn-sm btn-outline-danger mb-3">
    Download PDF
  </a>
  <?php echo $__env->make('backend.adminPage._includes.transactions_table', ['transactions' => $transactions], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\tabs\transactions.blade.php ENDPATH**/ ?>