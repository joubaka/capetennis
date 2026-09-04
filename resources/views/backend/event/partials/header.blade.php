<x-backend.page-header :title="$event->name" eyebrow="Tournament workspace" icon="ti-trophy">
  <x-slot:meta>
    @if($event->start_date)
      <span><i class="ti ti-calendar-event me-1" aria-hidden="true"></i>{{ $event->start_date->format('d M Y') }}</span>
    @endif
    @if($event->venue_name)<span><i class="ti ti-map-pin me-1" aria-hidden="true"></i>{{ $event->venue_name }}</span>@endif
    @if($event->status_label)<span class="badge bg-label-primary">{{ $event->status_label }}</span>@endif
  </x-slot:meta>
</x-backend.page-header>
@include('backend.event.partials.workspace-nav')
