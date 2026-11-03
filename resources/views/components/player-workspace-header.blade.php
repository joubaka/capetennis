@props(['title', 'eyebrow' => 'Cape Tennis · Player Plus', 'subtitle' => null, 'heading' => 'h1', 'headingId' => null])
@once
  <link rel="stylesheet" href="{{ asset('assets/css/player-workspace.css') }}?v={{ filemtime(public_path('assets/css/player-workspace.css')) }}">
@endonce
@php($headingTag = in_array($heading, ['h1', 'h2'], true) ? $heading : 'h1')
<header {{ $attributes->class(['ct-workspace-header']) }}>
  <div class="ct-workspace-header-main">
    <div class="ct-workspace-header-copy">
      <span class="ct-workspace-eyebrow">{{ $eyebrow }}</span>
      <{{ $headingTag }} class="ct-workspace-title" @if($headingId) id="{{ $headingId }}" @endif>{{ $title }}</{{ $headingTag }}>
      @if($subtitle)<p class="ct-workspace-subtitle">{{ $subtitle }}</p>@endif
      {{ $slot }}
    </div>
    @isset($summary)<div class="ct-workspace-header-summary">{{ $summary }}</div>@endisset
    @isset($actions)<div class="ct-workspace-header-actions">{{ $actions }}</div>@endisset
  </div>
  @isset($navigation)<div class="ct-workspace-header-navigation">{{ $navigation }}</div>@endisset
</header>
