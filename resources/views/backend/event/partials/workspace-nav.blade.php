@php($eventWorkspaceActive = $eventWorkspaceActive ?? match (true) {
  request()->routeIs('headOffice.*', 'admin.events.draws') => 'draws',
  request()->routeIs('convenor.*') => 'directors',
  request()->routeIs('admin.events.finances*') => 'finances',
  request()->routeIs('admin.events.settings*') => 'settings',
  request()->routeIs('admin.events.entries*', 'admin.events.teams') => 'entries',
  default => 'overview',
})
<x-backend.context-nav label="Event navigation">
  @can('event-draw.view', $event)
    <a href="{{ route('admin.events.overview', $event) }}" @if($eventWorkspaceActive === 'overview') aria-current="page" @endif><i class="ti ti-layout-grid" aria-hidden="true"></i>Event overview</a>
    <a href="{{ route($event->isTeam() ? 'admin.events.teams' : 'admin.events.entries.new', $event) }}" @if($eventWorkspaceActive === 'entries') aria-current="page" @endif><i class="ti ti-users" aria-hidden="true"></i>{{ $event->isTeam() ? 'Teams' : 'Entries' }}</a>
    <a href="{{ route('headOffice.show', $event->id) }}" @if($eventWorkspaceActive === 'draws') aria-current="page" @endif><i class="ti ti-tournament" aria-hidden="true"></i>Draws</a>
  @endcan
  @can('event.manage', $event)
    <a href="{{ route('convenor.show', $event->id) }}" @if($eventWorkspaceActive === 'directors') aria-current="page" @endif><i class="ti ti-users" aria-hidden="true"></i>Event directors</a>
  @endcan
  @can('event-finance.view', $event)
    <a href="{{ route('admin.events.finances', $event) }}" @if($eventWorkspaceActive === 'finances') aria-current="page" @endif><i class="ti ti-report-money" aria-hidden="true"></i>Finances</a>
  @endcan
  @can('event.manage', $event)
    <a href="{{ route('admin.events.settings', $event) }}" @if($eventWorkspaceActive === 'settings') aria-current="page" @endif><i class="ti ti-settings" aria-hidden="true"></i>Settings</a>
  @endcan
</x-backend.context-nav>
@if(request('schedule') === 'applied')
  <div class="alert alert-success" role="status">
    <strong>Schedule applied.</strong> Review each draw's order of play, then publish it when you are ready.
  </div>
@endif
