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
@if(session('success'))
  toastr.success(@json(session('success')));
@endif
@if(session('error'))
  toastr.error(@json(session('error')));
@endif
@if(session('info'))
  toastr.info(@json(session('info')));
@endif
@if(session('warning'))
  toastr.warning(@json(session('warning')));
@endif
@if($errors->any())
  toastr.error(@json($errors->first()));
@endif
</script>

{{-- 7️⃣ Page scripts MUST be last --}}
@yield('page-script')

@stack('modals')
