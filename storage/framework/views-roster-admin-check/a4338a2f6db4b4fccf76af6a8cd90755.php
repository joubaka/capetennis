<!DOCTYPE html>

<html lang="<?php echo e(session()->get('locale') ?? app()->getLocale()); ?>"
      class="<?php echo e($configData['style']); ?>-style <?php echo e($navbarFixed ?? ''); ?> <?php echo e($menuFixed ?? ''); ?> <?php echo e($menuCollapsed ?? ''); ?> <?php echo e($footerFixed ?? ''); ?> <?php echo e($customizerHidden ?? ''); ?>"
      dir="<?php echo e($configData['textDirection']); ?>"
      data-theme="<?php echo e($configData['theme']); ?>"
      data-assets-path="<?php echo e(asset('/assets') . '/'); ?>"
      data-base-url="<?php echo e(url('/')); ?>"
      data-framework="laravel"
      data-template="<?php echo e($configData['layout'] . '-menu-' . $configData['theme'] . '-' . $configData['style']); ?>">

<head>

  <meta charset="utf-8" />
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0" />

  <title>
    <?php echo $__env->yieldContent('title'); ?> |
    <?php echo e(config('variables.templateName') ?? 'TemplateName'); ?> -
    <?php echo e(config('variables.templateSuffix') ?? 'TemplateSuffix'); ?>

  </title>

  <meta name="description" content="<?php echo e(config('variables.templateDescription') ?? ''); ?>" />
  <meta name="keywords" content="<?php echo e(config('variables.templateKeyword') ?? ''); ?>">

  <!-- CSRF -->
  <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

  <!-- Canonical SEO -->
  <link rel="canonical" href="<?php echo e(config('variables.productPage') ?? ''); ?>">

  <!-- Favicon -->
  <?php ($brandLogo = \App\Models\SiteSetting::get('brand_logo_url', asset('assets/img/logos/cape-tennis-logo-transparent.png'))); ?>
  <?php ($brandLogoUrl = filter_var($brandLogo, FILTER_VALIDATE_URL) ? $brandLogo : asset(ltrim($brandLogo, '/'))); ?>
  <link rel="icon" type="image/png" sizes="192x192" href="<?php echo e(asset('assets/img/pwa/cape-tennis-app-192.png')); ?>" />
  <link rel="manifest" href="<?php echo e(asset('manifest.webmanifest')); ?>" />
  <link rel="apple-touch-icon" sizes="192x192" href="<?php echo e(asset('assets/img/pwa/cape-tennis-app-192.png')); ?>" />
  <meta name="theme-color" content="#12358f" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="default" />
  <meta name="apple-mobile-web-app-title" content="Cape Tennis" />

  <!-- Styles -->
  <?php echo $__env->make('layouts/sections/styles', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('draw.partials.bracket-assets', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($backendWorkspace ?? false): ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/backend-workspace.css')); ?>?v=<?php echo e(filemtime(public_path('css/backend-workspace.css'))); ?>">
  <?php else: ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/frontend-workspace.css')); ?>?v=<?php echo e(filemtime(public_path('css/frontend-workspace.css'))); ?>">
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <style>
    /* Keep the circular brand mark fully visible in navbar, menu and auth headers. */
    .app-brand-logo {
      align-items: center;
      background: transparent !important;
      display: inline-flex;
      flex: 0 0 40px;
      height: 40px;
      justify-content: center;
      overflow: visible;
      width: 40px;
    }
    .app-brand-logo img {
      background: transparent !important;
      display: block;
      height: 40px;
      max-height: 40px;
      max-width: 40px;
      object-fit: contain;
      width: 40px;
    }

    /* Vuexy clips the desktop brand to its line-height by default. */
    @media (min-width: 1200px) {
      #layout-navbar .navbar-brand.app-brand {
        align-self: stretch;
        min-height: 56px;
        overflow: visible;
      }
      #layout-navbar .navbar-brand .app-brand-link {
        min-height: 56px;
        overflow: visible;
      }
      #layout-navbar .navbar-brand .app-brand-logo {
        flex-basis: 44px;
        height: 44px;
        overflow: visible;
        width: 44px;
      }
      #layout-navbar .navbar-brand .app-brand-logo img {
        height: 44px;
        max-height: 44px;
        max-width: 44px;
        object-fit: contain;
        width: 44px;
      }
    }

    @media (max-width: 575.98px) {
      .app-brand-logo {
        flex-basis: 36px;
        height: 36px;
        width: 36px;
      }
      .app-brand-logo img {
        height: 36px;
        max-height: 36px;
        max-width: 36px;
        width: 36px;
      }

      #layout-menu .app-brand-logo {
        flex-basis: 44px;
        height: 44px;
        width: 44px;
      }
      #layout-menu .app-brand-logo img {
        height: 42px;
        max-height: 42px;
        max-width: 42px;
        object-fit: contain;
        width: 42px;
      }
    }
  </style>

  <!-- Vuexy core helpers / config -->
  <?php echo $__env->make('layouts/sections/scriptsIncludes', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</head>

<body class="<?php echo \Illuminate\Support\Arr::toCssClasses([
  'ct-backend' => $backendWorkspace ?? false,
  'ct-frontend' => !($backendWorkspace ?? false),
]); ?>">

  <!-- Layout Content -->
  <?php echo $__env->yieldContent('layoutContent'); ?>
  <!-- / Layout Content -->

  <!-- Vuexy scripts -->
  <?php echo $__env->make('layouts/sections/scripts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  <?php echo $__env->make('layouts.sections.pwa-install', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->make('layouts.sections.audit-interactions', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
    <?php echo $__env->make('layouts.sections.match-reminder', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


</body>
</html>
<?php /**PATH C:\wamp64\www\ct\resources\views\layouts\commonMaster.blade.php ENDPATH**/ ?>