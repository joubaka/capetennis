@php
  $eventWorkspaceIcon = $eventWorkspaceIcon ?? 'ti-trophy';
  $eventWorkspaceSubtitle = $eventWorkspaceSubtitle ?? null;
  $eventWorkspaceHomeUrl = $eventWorkspaceHomeUrl ?? route('admin.events.overview', $event);
  $eventWorkspaceShowHome = $eventWorkspaceShowHome ?? true;
@endphp
<div class="event-workspace-chrome no-print">
  <x-backend.page-header
    :title="$event->name"
    eyebrow="Tournament workspace"
    :subtitle="$eventWorkspaceSubtitle"
    :icon="$eventWorkspaceIcon">
    <x-slot:meta>
      @if($event->start_date)
        <span><i class="ti ti-calendar-event me-1" aria-hidden="true"></i>{{ $event->start_date->format('d M Y') }}</span>
      @endif
      @if($event->venue_name)<span><i class="ti ti-map-pin me-1" aria-hidden="true"></i>{{ $event->venue_name }}</span>@endif
      @if($event->status_label)<span class="badge bg-label-primary">{{ $event->status_label }}</span>@endif
    </x-slot:meta>
    <x-slot:actions>
      <a class="event-workspace-action" href="{{ route('events.show', $event) }}" target="_self">
        <i class="ti ti-world" aria-hidden="true"></i>
        <span>Public page</span>
      </a>
      @if($eventWorkspaceShowHome)
        <a class="event-workspace-action" href="{{ $eventWorkspaceHomeUrl }}">
          <i class="ti ti-home" aria-hidden="true"></i>
          <span>Event home</span>
        </a>
      @endif
    </x-slot:actions>
  </x-backend.page-header>
  @include('backend.event.partials.workspace-nav')
</div>
@if(session('schedule_adaptation_warning'))
  <div class="alert alert-warning mb-3" role="status">
    {{ session('schedule_adaptation_warning') }}
    @can('event.manage', $event)
      <a class="alert-link ms-1" href="{{ route('backend.event-venue-schedule.index', $event) }}">Review updated schedule</a>
    @endcan
  </div>
@endif
