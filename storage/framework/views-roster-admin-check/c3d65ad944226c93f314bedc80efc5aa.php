<div id="team-workspace-content" data-result-url="<?php echo e(route('get.event.category.data')); ?>">
  <?php echo $__env->make('backend.adminPage.admin_show.team_show', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('_partials._modals.modal-edit-team-category', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\adminPage\admin_show\team-workspace.blade.php ENDPATH**/ ?>