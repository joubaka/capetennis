<!-- BEGIN: Theme CSS-->
<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap" rel="stylesheet">

{{-- Keep font stylesheets same-origin so font files are not blocked when the
     application is reached through either the www or non-www hostname. --}}
<link rel="stylesheet" href="{{ mix('assets/vendor/fonts/fontawesome.css') }}" />
<link rel="stylesheet" href="{{ mix('assets/vendor/fonts/tabler-icons.css') }}" />
<link rel="stylesheet" href="{{ mix('assets/vendor/fonts/flag-icons.css') }}" />

{{-- The bundled Vuexy Tabler font predates a number of icon names used by
     newer screens. Keep those names working through stable local aliases. --}}
<style>
  .ti-users-group::before { content: "\ebf2"; }
  .ti-user-edit::before, .ti-user-cog::before { content: "\ea98"; }
  .ti-check-circle::before { content: "\ea67"; }
  .ti-credit-card-refund::before, .ti-pay::before { content: "\ea84"; }
  .ti-calendar-meet::before, .ti-calendar-event::before { content: "\ea53"; }
  .ti-megaphone::before { content: "\ed61"; }
  .ti-sliders::before { content: "\ea03"; }
  .ti-list-ordered::before, .ti-insert::before { content: "\eb6b"; }
  .ti-currency-rand::before { content: "\eb84"; }
  .ti-file-type-pdf::before { content: "\eb67"; }
  .ti-reload::before { content: "\eb56"; }
  .ti-shield-search::before { content: "\eb22"; }
  .ti-user-heart::before { content: "\eb4d"; }
  .ti-target-arrow::before { content: "\eb35"; }
  .ti-device-mobile-down::before { content: "\ea8a"; }
  .ti-users-minus::before { content: "\ebf2"; }
  .ti-plus-circle::before { content: "\eb4b"; }
  .ti-trending-::before { content: "\eb43"; }

  /* DataTables must not inherit an icon font for its sort indicators. */
  table.dataTable thead th.sorting::after,
  table.dataTable thead th.sorting_asc::after,
  table.dataTable thead th.sorting_desc::after { font-family: Arial, sans-serif; }
</style>

<!-- Core CSS -->
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/css' .$configData['rtlSupport'] .'/core' .($configData['style'] !== 'light' ? '-' . $configData['style'] : '') .'.css')) }}" class="{{ $configData['hasCustomizer'] ? 'template-customizer-core-css' : '' }}" />
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/css' .$configData['rtlSupport'] .'/' .$configData['theme'] .($configData['style'] !== 'light' ? '-' . $configData['style'] : '') .'.css')) }}" class="{{ $configData['hasCustomizer'] ? 'template-customizer-theme-css' : '' }}" />
{{-- demo.css is copied as a static asset, so it is not present in mix-manifest.json. --}}
<link rel="stylesheet" href="{{ asset('assets/css/demo.css') }}" />


<link rel="stylesheet" href="{{ asset(mix('assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css')) }}" />
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/libs/node-waves/node-waves.css')) }}" />
<link rel="stylesheet" href="{{ asset(mix('assets/vendor/libs/typeahead-js/typeahead.css')) }}" />
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/toastr/toastr.css') }}" />

<!-- Vendor Styles -->
@yield('vendor-style')


<!-- Page Styles -->
@yield('page-style')




