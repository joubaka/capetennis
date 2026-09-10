@php
  $teamWorkspaceMode = $teamWorkspaceMode ?? 'admin';
  $teamWorkspaceActive = $teamWorkspaceActive ?? 'players';
  $teamWorkspaceShowAdminTabs = $teamWorkspaceShowAdminTabs ?? (Auth::id() === 584);
@endphp

<div class="tabs-wrap">
  <ul class="nav nav-tabs nav-fill px-2" role="tablist">
    @if($teamWorkspaceShowAdminTabs)
      <li class="nav-item" role="presentation">
        <button type="button" class="nav-link" role="tab"
          data-bs-toggle="tab" data-bs-target="#tab-regions"
          aria-controls="tab-regions" aria-selected="false">
          <i class="ti ti-home ti-xs me-1"></i>
          Regions
          <span class="badge rounded-pill bg-label-primary ms-1">{{ $regionCount }}</span>
          <span class="badge rounded-pill bg-label-info ms-1">{{ $teamCount }}</span>
        </button>
      </li>

      <li class="nav-item" role="presentation">
        <button type="button" class="nav-link" role="tab"
          data-bs-toggle="tab" data-bs-target="#tab-categories"
          aria-controls="tab-categories" aria-selected="false" tabindex="-1">
          <i class="ti ti-category ti-xs me-1"></i>
          Categories
          <span class="badge rounded-pill bg-label-warning ms-1">{{ $categoryCount }}</span>
        </button>
      </li>
    @endif

    <li class="nav-item" role="presentation">
      @if($teamWorkspaceMode === 'regional')
        <a class="nav-link {{ $teamWorkspaceActive === 'players' ? 'active' : '' }}"
           href="{{ route('backend.team-selection.index', ['event' => $event, 'view' => 'players']) }}"
           @if($teamWorkspaceActive === 'players') aria-current="page" @endif>
          <i class="ti ti-users-group ti-xs me-1"></i>
          Players
          <span class="badge rounded-pill bg-label-success ms-1">{{ $playerCount }}</span>
          @if($reserveCount > 0)
            <span class="badge rounded-pill bg-label-warning ms-1">{{ $reserveCount }} reserves</span>
          @endif
        </a>
      @else
        <button type="button" class="nav-link active" role="tab"
          data-bs-toggle="tab" data-bs-target="#tab-players"
          aria-controls="tab-players" aria-selected="true">
          <i class="ti ti-users-group ti-xs me-1"></i>
          Players
          <span class="badge rounded-pill bg-label-success ms-1">{{ $playerCount }}</span>
          @if($reserveCount > 0)
            <span class="badge rounded-pill bg-label-warning ms-1">{{ $reserveCount }} reserves</span>
          @endif
        </button>
      @endif
    </li>

    <li class="nav-item" role="presentation">
      @if($teamWorkspaceMode === 'regional')
        <a class="nav-link {{ $teamWorkspaceActive === 'order' ? 'active' : '' }}"
           href="{{ route('backend.team-selection.index', ['event' => $event, 'view' => 'order']) }}"
           @if($teamWorkspaceActive === 'order') aria-current="page" @endif>
          <i class="ti ti-list-ordered ti-xs me-1"></i>
          Player order
        </a>
      @else
        <button type="button" class="nav-link" role="tab"
          data-bs-toggle="tab" data-bs-target="#tab-order"
          aria-controls="tab-order" aria-selected="false" tabindex="-1">
          <i class="ti ti-list-ordered ti-xs me-1"></i>
          Player order
        </button>
      @endif
    </li>

    @if($teamWorkspaceShowAdminTabs)
      <li class="nav-item" role="presentation">
        <button id="result-rank-button" type="button" class="nav-link" role="tab"
          data-bs-toggle="tab" data-bs-target="#tab-result-rank"
          aria-controls="tab-result-rank" aria-selected="false">
          <i class="ti ti-award ti-xs me-1"></i>
          Result Ranks
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <a href="{{ route('headOffice.show', $event->id) }}" class="nav-link">
          <i class="ti ti-gauge ti-xs me-1"></i> Dashboard
        </a>
      </li>
    @endif
  </ul>
</div>
