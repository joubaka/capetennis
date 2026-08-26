
@php($capezLogo = \App\Models\SiteSetting::get('brand_logo_url', 'assets/img/logos/cape-tennis-logo-transparent.png'))
@php($capezLogoIsRemote = filter_var($capezLogo, FILTER_VALIDATE_URL))
@php($capezLogoPath = ltrim($capezLogo, '/'))
@php($capezLogoFile = $capezLogoIsRemote ? null : public_path($capezLogoPath))
@php($capezLogoVersion = $capezLogoFile && is_file($capezLogoFile) ? filemtime($capezLogoFile) : null)
@php($capezLogoUrl = $capezLogoIsRemote ? $capezLogo : asset($capezLogoPath))
<img src="{{ $capezLogoUrl }}{{ $capezLogoVersion ? '?v='.$capezLogoVersion : '' }}" height="40" width="40" alt="Cape Tennis logo">
