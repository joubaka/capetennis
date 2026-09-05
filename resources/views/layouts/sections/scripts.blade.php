<!-- BEGIN: Vendor JS -->
<script>console.log('🎾 Cape Tennis v{{ config("app.asset_version") }}');</script>

{{-- 1️⃣ jQuery FIRST (ABSOLUTE RULE) --}}
<script src="{{ asset('assets/vendor/libs/jquery/jquery.js') }}"></script>

{{-- jQuery UI (optional, but AFTER jQuery) --}}
<script src="https://code.jquery.com/ui/1.14.1/jquery-ui.js"></script>

{{-- 2️⃣ Bootstrap & core deps --}}
<script src="{{ asset('assets/vendor/libs/popper/popper.js') }}"></script>
<script src="{{ asset('assets/vendor/js/bootstrap.js') }}"></script>

{{-- 3️⃣ Vuexy helpers (REQUIRES jQuery) --}}
<script src="{{ asset('assets/vendor/js/helpers.js') }}"></script>

{{-- 4️⃣ Vuexy plugins --}}
<script src="{{ asset('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/node-waves/node-waves.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/hammer/hammer.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/i18n/i18n.js') }}"></script>
<script src="{{ asset('assets/vendor/libs/typeahead-js/typeahead.js') }}"></script>

{{-- 5️⃣ Vuexy core --}}
<script src="{{ asset('assets/vendor/js/menu.js') }}"></script>
<script src="{{ asset('assets/js/main.js') }}"></script>

{{-- 6️⃣ Laravel Mix app JS (SUBFOLDER SAFE) --}}
<script src="{{ asset(mix('js/app.js')) }}"></script>

{{-- Page-level vendor scripts --}}
@yield('vendor-script')

<!-- END: Vendor JS -->

@stack('pricing-script')

{{-- Global toastr flash-to-toast handler --}}
<script src="{{ asset('assets/vendor/libs/toastr/toastr.js') }}"></script>
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
@if(session('success'))
  AppFeedback.success(@json(session('success')));
@endif
@if(session('error'))
  AppFeedback.error(@json(session('error')));
@endif
@if(session('info'))
  AppFeedback.info(@json(session('info')));
@endif
@if(session('warning'))
  AppFeedback.warning(@json(session('warning')));
@endif
@if(isset($errors) && $errors->any())
  @foreach($errors->all() as $message)
    AppFeedback.error(@json($message));
  @endforeach
@endif
</script>

{{-- 7️⃣ Page scripts MUST be last --}}
@yield('page-script')

@stack('modals')
