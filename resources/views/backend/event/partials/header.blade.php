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
      @can('event.manage', $event)
        @if(auth()->user()?->hasRole('super-user') && config('event-assistant.enabled', true))
          <button type="button" class="event-workspace-action" data-event-assistant-open aria-controls="event-assistant-panel" aria-expanded="false">
            <i class="ti ti-message-circle" aria-hidden="true"></i><span>Ask assistant</span>
          </button>
        @endif
      @endcan
      @unless($rosterOnly ?? false)
      @if($event->isTeam())
      @can('event.manage', $event)
      <a class="event-workspace-action" href="{{ route('backend.selection-import.index', $event) }}"><i class="ti ti-download" aria-hidden="true"></i><span>Import team + reserves</span></a>
      @endcan
      @endif
      @if(auth()->check() && app(\App\Services\EventCommunicationService::class)->managesWholeEvent($event, auth()->user()))
      <a class="event-workspace-action" href="{{ route('backend.event-communications.index', ['event' => $event, 'wrap_up' => 1]) }}"><i class="ti ti-mail" aria-hidden="true"></i><span>Wrap-up email</span></a>
      @endif
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
      @endunless
    </x-slot:actions>
  </x-backend.page-header>
  @include('backend.event.partials.workspace-nav')
</div>
@can('event.manage', $event)
  @if(auth()->user()?->hasRole('super-user') && config('event-assistant.enabled', true))
    @include('backend.event.partials.assistant')
  @endif
@endcan
@if(session('schedule_adaptation_warning'))
  <div class="alert alert-warning mb-3" role="status">
    {{ session('schedule_adaptation_warning') }}
    @can('event.manage', $event)
      <a class="alert-link ms-1" href="{{ route('backend.event-venue-schedule.index', $event) }}">Review updated schedule</a>
    @endcan
  </div>
@endif
