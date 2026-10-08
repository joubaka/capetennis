<!-- BEGIN: Vendor JS -->
<script>console.log('🎾 Cape Tennis v<?php echo e(config("app.asset_version")); ?>');</script>


<script src="<?php echo e(asset('assets/vendor/libs/jquery/jquery.js')); ?>"></script>


<script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>


<script src="<?php echo e(asset('assets/vendor/libs/popper/popper.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/js/bootstrap.js')); ?>"></script>


<script src="<?php echo e(asset('assets/vendor/js/helpers.js')); ?>"></script>


<script src="<?php echo e(asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/node-waves/node-waves.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/hammer/hammer.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/i18n/i18n.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/libs/typeahead-js/typeahead.js')); ?>"></script>


<script src="<?php echo e(asset('assets/vendor/js/menu.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/main.js')); ?>"></script>


<script src="<?php echo e(asset(mix('js/app.js'))); ?>"></script>


<?php echo $__env->yieldContent('vendor-script'); ?>

<!-- END: Vendor JS -->

<?php echo $__env->yieldPushContent('pricing-script'); ?>


<script src="<?php echo e(asset('assets/vendor/libs/toastr/toastr.js')); ?>"></script>
<script>
toastr.options = { positionClass: 'toast-top-right', timeOut: 5000, closeButton: true, progressBar: true };

(function (root) {
  const storageKey = 'ct.feedback.afterReload';

  function liveRegion() {
    let region = document.getElementById('app-feedback-live-region');
    if (region) return region;

    region = document.createElement('div');
    region.id = 'app-feedback-live-region';
    region.className = 'visually-hidden';
    region.setAttribute('role', 'status');
    region.setAttribute('aria-live', 'polite');
    region.setAttribute('aria-atomic', 'true');
    document.body.appendChild(region);
    return region;
  }

  function show(message, type = 'success', title = null) {
    if (!message) return;

    const normalizedType = type === 'danger' ? 'error' : type;
    const region = liveRegion();
    region.setAttribute('role', normalizedType === 'error' ? 'alert' : 'status');
    region.textContent = '';
    window.setTimeout(() => { region.textContent = String(message); }, 10);

    if (root.toastr && typeof root.toastr[normalizedType] === 'function') {
      root.toastr[normalizedType](String(message), title || undefined);
      return;
    }

    console[normalizedType === 'error' ? 'error' : 'log'](`[${normalizedType}] ${message}`);
  }

  function messagesFrom(data, fallback) {
    if (data?.errors) {
      const messages = Object.values(data.errors).flat().filter(Boolean);
      if (messages.length) return messages;
    }

    return [data?.message || fallback || 'Something went wrong.'];
  }

  async function responseError(response, fallback) {
    const data = await response.json().catch(() => ({}));
    const error = new Error(messagesFrom(data, fallback)[0]);
    error.messages = messagesFrom(data, fallback);
    error.status = response.status;
    return error;
  }

  function fromError(error, fallback) {
    const messages = error?.messages?.length ? error.messages : [error?.message || fallback || 'Something went wrong.'];
    messages.forEach(message => show(message, 'error'));
  }

  function afterReload(message, type = 'success') {
    try {
      sessionStorage.setItem(storageKey, JSON.stringify({ message, type }));
    } catch (error) {
      show(message, type);
    }
  }

  root.AppFeedback = {
    show,
    success: message => show(message, 'success'),
    error: message => show(message, 'error'),
    warning: message => show(message, 'warning'),
    info: message => show(message, 'info'),
    fromError,
    responseError,
    afterReload,
  };

  // Compatibility for older pages that already call a global helper.
  root.showToast = function (message, type = 'success') { show(message, type); };

  try {
    const pending = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
    sessionStorage.removeItem(storageKey);
    if (pending?.message) show(pending.message, pending.type || 'success');
  } catch (error) {
    sessionStorage.removeItem(storageKey);
  }
}(window));
<?php if(session('success')): ?>
  AppFeedback.success(<?php echo json_encode(session('success'), 15, 512) ?>);
<?php endif; ?>
<?php if(session('error')): ?>
  AppFeedback.error(<?php echo json_encode(session('error'), 15, 512) ?>);
<?php endif; ?>
<?php if(session('info')): ?>
  AppFeedback.info(<?php echo json_encode(session('info'), 15, 512) ?>);
<?php endif; ?>
<?php if(session('warning')): ?>
  AppFeedback.warning(<?php echo json_encode(session('warning'), 15, 512) ?>);
<?php endif; ?>
<?php if(isset($errors) && $errors->any()): ?>
  <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    AppFeedback.error(<?php echo json_encode($message, 15, 512) ?>);
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
<?php endif; ?>
</script>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
<script src="<?php echo e(asset('js/manual-email.js')); ?>?v=<?php echo e(filemtime(public_path('js/manual-email.js'))); ?>"></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php echo $__env->make('draw.partials.player-rating-assets', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php echo $__env->yieldContent('page-script'); ?>

<?php echo $__env->yieldPushContent('modals'); ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\layouts\sections\scripts.blade.php ENDPATH**/ ?>