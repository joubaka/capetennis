@if(auth()->user()?->hasRole('super-user'))
  <link rel="stylesheet" href="{{ asset('css/super-admin-controls.css') }}?v={{ filemtime(public_path('css/super-admin-controls.css')) }}">
  <script type="application/json" id="ct-super-admin-routes" data-base-path="{{ parse_url(url('/'), PHP_URL_PATH) ?? '' }}">@json(app(\App\Support\SuperAdminControls::class)->manifest())</script>
  <script src="{{ asset('js/super-admin-controls.js') }}?v={{ filemtime(public_path('js/super-admin-controls.js')) }}" defer></script>
@endif
