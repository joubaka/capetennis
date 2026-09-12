@php
$configData = Helper::appClasses();
@endphp
<style>
  #layout-menu > .horizontal-menu-shell {
    max-width: 1180px;
    margin-inline: auto;
    width: 100%;
  }

  @media (max-width: 1199.98px) {
    #layout-menu > .horizontal-menu-shell {
      max-width: none;
    }
  }
</style>
<!-- Horizontal Menu -->
<aside id="layout-menu" class="layout-menu-horizontal menu-horizontal menu bg-menu-theme flex-grow-0" aria-label="Primary navigation">
  <div class="horizontal-menu-shell {{$containerNav}} d-flex h-100">
    <ul class="menu-inner">
      @php
        $currentRouteName = Route::currentRouteName() ?? '';
        $menuItemIsActive = static function ($item) use ($currentRouteName): bool {
          $slugs = is_array($item->slug) ? $item->slug : [$item->slug];

          foreach ($slugs as $slug) {
            if ($slug === $currentRouteName || (isset($item->submenu) && $slug !== '' && str_starts_with($currentRouteName, $slug))) {
              return true;
            }
          }

          return false;
        };
      @endphp
      @foreach ($menuData[1]->menu as $menu)

      {{-- active menu method --}}
      @php($activeClass = $menuItemIsActive($menu) ? 'active' : null)

      {{-- main menu --}}

      @can('super-user')
      @if($menu->menuLevel == 'super' || $menu->menuLevel == 'all')
      <li class="menu-item {{$activeClass}}">
        <a href="{{ isset($menu->url) ? url($menu->url) : 'javascript:void(0);' }}" class="{{ isset($menu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}" @if($activeClass && !isset($menu->submenu)) aria-current="page" @endif @if (isset($menu->target) and !empty($menu->target)) target="_blank" @endif>
          @isset($menu->icon)
          <i class="{{ $menu->icon }}"></i>
          @endisset
          <div>{{ isset($menu->name) ? __($menu->name) : '' }}</div>
        </a>

        {{-- submenu --}}
        @isset($menu->submenu)
        @include('layouts.sections.menu.submenu',['menu' => $menu->submenu])
        @endisset
      </li>
      @endif
      @else
      @if($menu->menuLevel == 'all')
      <li class="menu-item {{$activeClass}}">
        <a href="{{ isset($menu->url) ? url($menu->url) : 'javascript:void(0);' }}" class="{{ isset($menu->submenu) ? 'menu-link menu-toggle' : 'menu-link' }}" @if($activeClass && !isset($menu->submenu)) aria-current="page" @endif @if (isset($menu->target) and !empty($menu->target)) target="_blank" @endif>
          @isset($menu->icon)
          <i class="{{ $menu->icon }}"></i>
          @endisset
          <div>{{ isset($menu->name) ? __($menu->name) : '' }}</div>
        </a>

        {{-- submenu --}}
        @isset($menu->submenu)
        @include('layouts.sections.menu.submenu',['menu' => $menu->submenu])
        @endisset
      </li>
      @endif
      @endcan
      @endforeach


    </ul>
  </div>
</aside>
<!--/ Horizontal Menu -->
