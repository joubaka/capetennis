<?php $__env->startSection('title', 'Login'); ?>

<?php $__env->startSection('page-style'); ?>
  
  <link rel="stylesheet" href="<?php echo e(asset(mix('assets/vendor/css/pages/page-auth.css'))); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="authentication-wrapper authentication-basic px-4">
  <div class="authentication-inner py-4">
    <!-- Logo -->
    <div class="app-brand justify-content-center mb-5">
      <a href="<?php echo e(url('/')); ?>" class="app-brand-link gap-2">
        <span class="app-brand-logo demo bg-primary"><?php echo $__env->make('_partials.macros',["height"=>20,"withbg"=>'fill: #fff;'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
        <span class="app-brand-text demo text-body fw-bold"><?php echo e(config('variables.templateName')); ?></span>
      </a>
    </div>
    <!-- /Logo -->
    <div class="card">
      <div class="card-body">
       <?php echo $terms; ?>

      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/blankLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\terms.blade.php ENDPATH**/ ?>