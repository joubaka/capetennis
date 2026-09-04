@props(['title', 'description' => null, 'icon' => 'ti-inbox'])
<div {{ $attributes->class(['ct-empty-state']) }}>
  <i class="ti {{ $icon }}" aria-hidden="true"></i>
  <h3 class="h5">{{ $title }}</h3>
  @if($description)<p class="text-muted">{{ $description }}</p>@endif
  {{ $slot }}
</div>
