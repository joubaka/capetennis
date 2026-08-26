
@php($capezLogo = \App\Models\SiteSetting::get('brand_logo_url', asset('assets/img/logos/cape-tennis-logo-transparent.png')))
<img src="{{ filter_var($capezLogo, FILTER_VALIDATE_URL) ? $capezLogo : asset(ltrim($capezLogo, '/')) }}" height="40" width="40" alt="Capez logo">
