<?php $__env->startSection('title', 'Cape Tennis Assistant'); ?>

<?php $__env->startSection('content'); ?>
  <?php echo $__env->make('backend.event.partials.header', ['eventWorkspaceSubtitle' => 'Super-user assistant pilot'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <div class="card">
    <div class="card-body">
      <h2 class="h4">Test your tournament plan</h2>
      <p>Open <strong>Ask assistant</strong> to review scheduling requests for this event. A preview does not change fixtures, saved bookings or public schedules.</p>
      <p class="mb-0">Saving, draw generation and publication each require a reviewed confirmation. Access is restricted to the super-user during testing.</p>
    </div>
  </div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\event\assistant-workspace.blade.php ENDPATH**/ ?>