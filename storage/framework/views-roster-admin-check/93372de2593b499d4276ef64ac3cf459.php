<?php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
?>



<?php $__env->startSection('title', 'Forgot Password'); ?>

<?php $__env->startSection('page-style'); ?>

<link rel="stylesheet" href="<?php echo e(asset(mix('assets/vendor/css/pages/page-auth.css'))); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row">
    <!-- /Left Text -->
    <div class="d-none d-lg-flex col-lg-7 p-0">
      <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center">
        <img src="<?php echo e(asset('assets/img/illustrations/auth-forgot-password-illustration-'.$configData['style'].'.png')); ?>" alt="auth-forgot-password-cover" class="img-fluid my-5 auth-illustration" data-app-light-img="illustrations/auth-forgot-password-illustration-light.png" data-app-dark-img="illustrations/auth-forgot-password-illustration-dark.png">

        <img src="<?php echo e(asset('assets/img/illustrations/bg-shape-image-'.$configData['style'].'.png')); ?>" alt="auth-forgot-password-cover" class="platform-bg" data-app-light-img="illustrations/bg-shape-image-light.png" data-app-dark-img="illustrations/bg-shape-image-dark.png">
      </div>
    </div>
    <!-- /Left Text -->
    <!-- Forgot Password -->
    <div class="d-flex col-12 col-lg-5 col-xl-4 align-items-center authentication-bg p-sm-5 p-4 mx-auto">
      <div class="w-px-400 mx-auto">
        <!-- Logo -->
        <div class="app-brand justify-content-center mb-5">
          <a href="<?php echo e(url('/')); ?>" class="app-brand-link">
            <span class="app-brand-logo demo"><?php echo $__env->make('_partials.macros',["height"=>20,"withbg"=>'fill: #fff;'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
            <span class="app-brand-text demo text-body fw-bold"><?php echo e(config('variables.templateName')); ?></span>
          </a>
        </div>
        <!-- /Logo -->
        <h4 class="mb-2">Forgot Password? 🔒</h4>
        <p class="mb-4">Enter your email and we'll send you instructions to reset your password</p>

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?>
        <div class="mb-1 text-success">
          <?php echo e(session('status')); ?>

        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <form id="formAuthentication" class="mb-3" action="<?php echo e(route('password.email')); ?>" method="POST">
          <?php echo csrf_field(); ?>
          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="text" class="form-control <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="email" name="email" placeholder="john@example.com" autofocus>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
            <span class="invalid-feedback" role="alert">
              <strong><?php echo e($message); ?></strong>
            </span>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
          <button type="submit" class="btn btn-primary d-grid w-100">Send Reset Link</button>
        </form>
        <div class="text-center">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Route::has('login')): ?>
          <a href="<?php echo e(route('login')); ?>" class="d-flex align-items-center justify-content-center">
            <i class="ti ti-chevron-left scaleX-n1-rtl"></i>
            Back to login
          </a>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>
    <!-- /Forgot Password -->
  </div>
</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts/blankLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\auth\forgot-password.blade.php ENDPATH**/ ?>