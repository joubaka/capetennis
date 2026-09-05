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
        content="width=device-width, initial-scale=1.0" />

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
  @php($brandLogo = \App\Models\SiteSetting::get('brand_logo_url', asset('assets/img/logos/cape-tennis-logo-transparent.png')))
  @php($brandLogoUrl = filter_var($brandLogo, FILTER_VALIDATE_URL) ? $brandLogo : asset(ltrim($brandLogo, '/')))
  <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('assets/img/pwa/cape-tennis-app-192.png') }}" />
  <link rel="manifest" href="{{ asset('manifest.webmanifest') }}" />
  <link rel="apple-touch-icon" sizes="192x192" href="{{ asset('assets/img/pwa/cape-tennis-app-192.png') }}" />
  <meta name="theme-color" content="#12358f" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="default" />
  <meta name="apple-mobile-web-app-title" content="Cape Tennis" />

  <!-- Styles -->
  @include('layouts/sections/styles')
  @include('draw.partials.bracket-assets')
  @if($backendWorkspace ?? false)
    <link rel="stylesheet" href="{{ asset('css/backend-workspace.css') }}?v={{ filemtime(public_path('css/backend-workspace.css')) }}">
  @else
    <link rel="stylesheet" href="{{ asset('css/frontend-workspace.css') }}?v={{ filemtime(public_path('css/frontend-workspace.css')) }}">
  @endif

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
  @include('layouts/sections/scriptsIncludes')
</head>

<body @class([
  'ct-backend' => $backendWorkspace ?? false,
  'ct-frontend' => !($backendWorkspace ?? false),
])>

  <!-- Layout Content -->
  @yield('layoutContent')
  <!-- / Layout Content -->

  <!-- Vuexy scripts -->
  @include('layouts/sections/scripts')

  @include('layouts.sections.pwa-install')
  @include('layouts.sections.audit-interactions')


</body>
</html>
