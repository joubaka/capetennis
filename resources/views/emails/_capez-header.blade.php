@php
    $capezLogo = \App\Models\SiteSetting::get('brand_logo_url', asset('assets/img/logos/cape-tennis-logo-transparent.png'));
    $capezLogo = filter_var($capezLogo, FILTER_VALIDATE_URL) ? $capezLogo : asset(ltrim($capezLogo, '/'));
@endphp
<div style="text-align:center; padding:0 0 22px;">
    <img src="{{ $capezLogo }}" alt="Capez — Cape Tennis" width="150" style="display:inline-block; width:150px; max-width:100%; height:auto; border:0;">
</div>
