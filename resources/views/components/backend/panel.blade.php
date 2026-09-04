@props(['title' => null, 'description' => null])
<section {{ $attributes->class(['card', 'ct-panel']) }}>
  @if($title || isset($actions))
    <div class="card-header ct-panel-header">
      <div>@if($title)<h2 class="h5 mb-0">{{ $title }}</h2>@endif
      @if($description)<p class="text-muted mt-1 mb-0">{{ $description }}</p>@endif</div>
      @isset($actions)<div class="ct-page-actions">{{ $actions }}</div>@endisset
    </div>
  @endif
  <div class="card-body">{{ $slot }}</div>
</section>
