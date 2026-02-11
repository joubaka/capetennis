<!DOCTYPE html>

<html lang="{{ session()->get('locale') ?? app()->getLocale() }}"
      class="{{ $configData['style'] }}-style {{ $navbarFixed ?? '' }} {{ $menuFixed ?? '' }} {{ $menuCollapsed ?? '' }} {{ $footerFixed ?? '' }} {{ $customizerHidden ?? '' }}"
      dir="{{ $configData['textDirection'] }}"
      data-theme="{{ $configData['theme'] }}"
      data-assets-path="{{ asset('/assets') . '/' }}"
      data-base-url="{{ url('/') }}"
      data-framework="laravel"
      data-template="{{ $configData['layout'] . '-menu-' . $configData['theme'] . '-' . $configData['style'] }}">

<head>

  <meta charset="utf-8" />
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

  <title>
    @yield('title') |
    {{ config('variables.templateName') ?? 'TemplateName' }} -
    {{ config('variables.templateSuffix') ?? 'TemplateSuffix' }}
  </title>

  <meta name="description" content="{{ config('variables.templateDescription') ?? '' }}" />
  <meta name="keywords" content="{{ config('variables.templateKeyword') ?? '' }}">

  <!-- CSRF -->
  <meta name="csrf-token" content="{{ csrf_token() }}">

  <!-- Canonical SEO -->
  <link rel="canonical" href="{{ config('variables.productPage') ?? '' }}">

  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="{{ asset('assets/img/favicon/favicon.ico') }}" />

  <!-- Styles -->
  @include('layouts/sections/styles')

  <!-- Vuexy core helpers / config -->
  @include('layouts/sections/scriptsIncludes')
</head>

<body>

  <!-- Layout Content -->
  @yield('layoutContent')
  <!-- / Layout Content -->

  <!-- Vuexy scripts -->
  @include('layouts/sections/scripts')


</body>
</html>
