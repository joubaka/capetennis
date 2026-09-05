@php
  $eventWorkspaceIcon = $eventWorkspaceIcon ?? 'ti-trophy';
  $eventWorkspaceSubtitle = $eventWorkspaceSubtitle ?? null;
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
      <a class="event-workspace-action" href="{{ route('events.show', $event) }}" target="_blank" rel="noopener">
        <i class="ti ti-world" aria-hidden="true"></i>
        <span>Public page</span>
        <i class="ti ti-external-link event-workspace-action__external" aria-hidden="true"></i>
      </a>
      <a class="event-workspace-action" href="{{ route('admin.events.overview', $event) }}">
        <i class="ti ti-home" aria-hidden="true"></i>
        <span>Event home</span>
      </a>
    </x-slot:actions>
  </x-backend.page-header>
  @include('backend.event.partials.workspace-nav')
</div>
