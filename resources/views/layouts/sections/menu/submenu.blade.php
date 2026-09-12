@php
  $menuItemIsActive ??= static function ($item): bool {
    $currentRouteName = Route::currentRouteName() ?? '';
    $slugs = is_array($item->slug) ? $item->slug : [$item->slug];

    foreach ($slugs as $slug) {
      if ($slug === $currentRouteName || (isset($item->submenu) && $slug !== '' && str_starts_with($currentRouteName, $slug))) {
        return true;
      }
    }

    return false;
  };
@endphp
<ul class="menu-sub">
  @if (isset($menu))
    @foreach ($menu as $submenu)

    {{-- active menu method --}}
    @php
      $active = $configData["layout"] === 'vertical' ? 'active open' : 'active';
      $activeClass = $menuItemIsActive($submenu) ? $active : null;
    @endphp

      <li class="menu-item {{$activeClass}}">
        <a href="{{ isset($submenu->url) ? url($submenu->url) : 'javascript:void(0)' }}" class="{{ isset($submenu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}" @if($activeClass && !isset($submenu->submenu)) aria-current="page" @endif @if (isset($submenu->target) and !empty($submenu->target)) target="_blank" @endif>
          @if (isset($submenu->icon))
          <i class="{{ $submenu->icon }}"></i>
          @endif
          <div>{{ isset($submenu->name) ? __($submenu->name) : '' }}</div>
        </a>

        {{-- submenu --}}
        @if (isset($submenu->submenu))
          @include('layouts.sections.menu.submenu',['menu' => $submenu->submenu])
        @endif
      </li>
    @endforeach
  @endif
</ul>
