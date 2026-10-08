<?php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
?>



<?php $__env->startSection('title', 'Verify Email'); ?>

<?php $__env->startSection('page-style'); ?>

<link rel="stylesheet" href="<?php echo e(asset(mix('assets/vendor/css/pages/page-auth.css'))); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="authentication-wrapper authentication-basic px-4">
  <div class="authentication-inner py-4">

    <!-- Logo -->
    <div class="app-brand justify-content-center mb-5">
      <a href="<?php echo e(url('/')); ?>" class="app-brand-link">
        <span class="app-brand-logo demo"><?php echo $__env->make('_partials.macros',['height'=>20,'withbg' => "fill: #fff;"], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
        <span class="app-brand-text demo text-body fw-bold"><?php echo e(config('variables.templateName')); ?></span>
      </a>
    </div>
    <!-- /Logo -->

    <!-- Verify Email -->
    <div class="card">
      <div class="card-body">
        <h4 class="mb-3">Verify your email ✉️</h4>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status') == 'verification-link-sent'): ?>
        <div class="alert alert-success" role="alert">
          <div class="alert-body">
            A new verification link has been sent to the email address you provided during registration.
          </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <p class="text-start">
          Account activation link sent to your email address: <strong><?php echo e(Auth::user()->email); ?></strong> Please follow the link inside to continue.
        </p>
        <div class="mt-4 d-flex flex-column justify-content-between gap-2">
          <form method="POST" action="<?php echo e(route('verification.send')); ?>">
            <?php echo csrf_field(); ?>
            <button type="submit" class="w-100 btn btn-label-secondary">
              click here to request another
            </button>
          </form>

          <form method="POST" action="<?php echo e(route('logout')); ?>">
            <?php echo csrf_field(); ?>

            <button type="submit" class="w-100 btn btn-danger">
              Log Out
            </button>
          </form>
        </div>
      </div>
    </div>
    <!-- /Verify Email -->
  </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts/blankLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\auth\verify-email.blade.php ENDPATH**/ ?>