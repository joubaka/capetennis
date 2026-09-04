@props(['title', 'eyebrow' => 'Cape Tennis', 'subtitle' => null, 'icon' => 'ti-layout-dashboard'])
<header {{ $attributes->class(['ct-page-header']) }}>
  <div class="ct-page-identity">
    <span class="ct-page-mark" aria-hidden="true"><i class="ti {{ $icon }}"></i></span>
    <div class="ct-page-heading">
      <p class="ct-eyebrow">{{ $eyebrow }}</p>
      <h1>{{ $title }}</h1>
      @if($subtitle)<p class="ct-page-subtitle">{{ $subtitle }}</p>@endif
      @isset($meta)<div class="ct-page-meta">{{ $meta }}</div>@endisset
    </div>
  </div>
  @isset($actions)<div class="ct-page-actions">{{ $actions }}</div>@endisset
</header>
