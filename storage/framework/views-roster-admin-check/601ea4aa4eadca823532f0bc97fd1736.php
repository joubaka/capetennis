<?php
$customizerHidden = 'customizer-hide';
$configData = Helper::appClasses();
?>



<?php $__env->startSection('title', 'Login'); ?>

<?php $__env->startSection('page-style'); ?>

<link rel="stylesheet" href="<?php echo e(asset(mix('assets/vendor/css/pages/page-auth.css'))); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="authentication-wrapper authentication-cover authentication-bg">
  <div class="authentication-inner row">
    <!-- /Left Text -->
    <div class="d-none d-lg-flex col-lg-7 p-0">
      <div class="auth-cover-bg auth-cover-bg-color d-flex justify-content-center align-items-center">
        <?php ($capezLogo = \App\Models\SiteSetting::get('brand_logo_url', asset('assets/img/logos/cape-tennis-logo-transparent.png'))); ?>
        <img src="<?php echo e(filter_var($capezLogo, FILTER_VALIDATE_URL) ? $capezLogo : asset(ltrim($capezLogo, '/'))); ?>" alt="Capez logo" class="rounded img-fluid my-5 auth-illustration">

     </div>
    </div>
    <!-- /Left Text -->

    <!-- Login -->
    <div class="d-flex col-12 col-lg-5 align-items-center authentication-bg p-sm-5 p-4">
      <div class="w-px-400 mx-auto">
        <!-- Logo -->
        <div class="app-brand justify-content-center mb-5">
          <a href="<?php echo e(url('/')); ?>" class="app-brand-link">
            <span class="app-brand-logo demo"><?php echo $__env->make('_partials.macros',["height"=>20,"withbg"=>'fill: #fff;'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span>
            <span class="app-brand-text demo text-body fw-bold"><?php echo e(config('variables.templateName')); ?></span>
          </a>
        </div>
        <!-- /Logo -->
        <h4 class="mb-2">Welcome to <?php echo e(config('variables.templateName')); ?>! 👋</h4>
        <p class="mb-4">

  <span class="text-muted">New users — create your profile </span>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Route::has('register')): ?>
    <a href="<?php echo e(route('register')); ?>" class="fw-semibold text-primary">here</a>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <span class="text-muted"> if you don’t have an existing account. Use parent details.</span>
</p>
  <p>Please sign-in to your account below if you have an existing account.</p>
        

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('status')): ?>
        <div class="alert alert-success mb-1 rounded-0" role="alert">
          <div class="alert-body">
            <?php echo e(session('status')); ?>

          </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    <form id="formAuthentication" class="mb-3" action="<?php echo e(route('login')); ?>" method="POST">
        <?php echo csrf_field(); ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(app(\App\Support\Auth\PostLoginDestination::class)->isSafeLocalPath(request('redirect'))): ?>
            <input type="hidden" name="redirect" value="<?php echo e(request('redirect')); ?>">
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <div class="mb-3">
            <label for="login-email" class="form-label">Email</label>
            <input type="text" class="form-control <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" id="login-email" name="email" placeholder="john@example.com" autofocus value="<?php echo e(old('email')); ?>">
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
          <div class="mb-3 form-password-toggle">
            <div class="d-flex justify-content-between">
              <label class="form-label" for="login-password">Password</label>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Route::has('password.request')): ?>
              <a href="<?php echo e(route('password.request')); ?>">
                <small>Forgot Password?</small>
              </a>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="input-group input-group-merge">
              <input type="password" id="login-password" class="form-control <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" name="password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" aria-describedby="password" />
              <span class="input-group-text cursor-pointer"><i class="ti ti-eye-off"></i></span>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['password'];
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
          </div>
          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="remember-me" name="remember" <?php echo e(old('remember') ? 'checked' : ''); ?>>
              <label class="form-check-label" for="remember-me">
                Remember Me
              </label>
            </div>
          </div>
          <button class="btn btn-primary d-grid w-100" type="submit">Sign in</button>
        </form>

        <p class="text-center">
          <span>New on our platform?</span>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(Route::has('register')): ?>
          <a href="<?php echo e(route('register')); ?>">
            <span>Create an account</span>
          </a>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </p>

        
        </div>
      </div>
    </div>
    <!-- /Login -->
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/blankLayout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\auth\login.blade.php ENDPATH**/ ?>