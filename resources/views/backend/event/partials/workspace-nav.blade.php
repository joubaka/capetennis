@php
  $eventWorkspaceActive = $eventWorkspaceActive ?? match (true) {
    request()->routeIs('headOffice.*', 'admin.events.draws', 'backend.event-venue-schedule.*') => 'draws',
    request()->routeIs('admin.events.results.*', 'backend.scoreboard.team.show') => 'results',
    request()->routeIs('admin.events.finances*') => 'finances',
    request()->routeIs('admin.events.settings*') => 'settings',
    request()->routeIs('admin.events.entries*', 'admin.events.teams') => 'entries',
    request()->routeIs(
      'convenor.*', 'admin.events.categories', 'admin.events.announcements*',
      'backend.events.disciplinary.*', 'admin.events.transactions', 'transactions.pdf'
    ) => 'more',
    default => 'overview',
  };
@endphp
<x-backend.context-nav label="Event navigation">
  @can('event-draw.view', $event)
    <a href="{{ route('admin.events.overview', $event) }}" @if($eventWorkspaceActive === 'overview') aria-current="page" @endif><i class="ti ti-layout-grid" aria-hidden="true"></i>Event overview</a>
    <a href="{{ route($event->isTeam() ? 'admin.events.teams' : 'admin.events.entries.new', $event) }}" @if($eventWorkspaceActive === 'entries') aria-current="page" @endif><i class="ti ti-users" aria-hidden="true"></i>{{ $event->isTeam() ? 'Teams' : 'Entries' }}</a>
    <a href="{{ route('headOffice.show', $event->id) }}" @if($eventWorkspaceActive === 'draws') aria-current="page" @endif><i class="ti ti-tournament" aria-hidden="true"></i>Draws</a>
    <a href="{{ route($event->isTeam() ? 'backend.scoreboard.team.show' : 'admin.events.results.individual', $event) }}" @if($eventWorkspaceActive === 'results') aria-current="page" @endif><i class="ti ti-trophy" aria-hidden="true"></i>Results</a>
  @endcan
  @can('event-finance.view', $event)
    <a href="{{ route('admin.events.finances', $event) }}" @if($eventWorkspaceActive === 'finances') aria-current="page" @endif><i class="ti ti-report-money" aria-hidden="true"></i>Finances</a>
  @endcan
  @can('event.manage', $event)
    <a href="{{ route('admin.events.settings', $event) }}" @if($eventWorkspaceActive === 'settings') aria-current="page" @endif><i class="ti ti-settings" aria-hidden="true"></i>Settings</a>
  @endcan
  @php
    $canSeeEventTools = auth()->user()?->can('event-draw.view', $event)
      || auth()->user()?->can('event-category.manage', $event)
      || auth()->user()?->can('event.manage', $event);
  @endphp
  @if($canSeeEventTools)
    <div class="event-workspace-more dropdown">
      <button class="event-workspace-more__toggle" type="button" data-bs-toggle="dropdown"
              aria-expanded="false" @if($eventWorkspaceActive === 'more') aria-current="page" @endif>
        <i class="ti ti-dots" aria-hidden="true"></i>More
      </button>
      <div class="dropdown-menu dropdown-menu-end">
        @can('event-draw.view', $event)
          <a class="dropdown-item" href="{{ route('admin.events.transactions', $event) }}"><i class="ti ti-credit-card" aria-hidden="true"></i>Transactions</a>
        @endcan
        @can('event-category.manage', $event)
          <a class="dropdown-item" href="{{ route('admin.events.categories', $event) }}"><i class="ti ti-list-details" aria-hidden="true"></i>Categories</a>
        @endcan
        @can('event.manage', $event)
          <a class="dropdown-item" href="{{ route('convenor.show', $event->id) }}"><i class="ti ti-users" aria-hidden="true"></i>Event directors</a>
          <a class="dropdown-item" href="{{ route('admin.events.announcements', $event) }}"><i class="ti ti-megaphone" aria-hidden="true"></i>Announcements</a>
          @if(\App\Models\SiteSetting::disciplinarySystemEnabled())
            <a class="dropdown-item" href="{{ route('backend.events.disciplinary.index', $event) }}"><i class="ti ti-scale" aria-hidden="true"></i>Discipline &amp; incidents</a>
          @endif
        @endcan
        @if($event->series)
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="{{ route('series.show', $event->series) }}"><i class="ti ti-layers" aria-hidden="true"></i>{{ $event->series->name }}</a>
          @if(!$event->isTeam())
            <a class="dropdown-item" href="{{ route('ranking.series.list', $event->series) }}" target="_blank" rel="noopener"><i class="ti ti-printer" aria-hidden="true"></i>Print series rankings</a>
          @endif
        @endif
      </div>
    </div>
  @endif
</x-backend.context-nav>
@if(request('schedule') === 'applied')
  <div class="alert alert-success" role="status">
    <strong>Schedule applied.</strong> Review each draw's order of play, then publish it when you are ready.
  </div>
@endif
