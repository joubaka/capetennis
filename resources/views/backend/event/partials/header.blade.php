<div class="card mb-3 event-hero-card">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">

      <div>
        <h3 class="mb-1">{{ $event->name }}</h3>

        <div class="text-muted event-meta">
          <i class="ti ti-calendar-event me-1"></i>
          {{ optional($event->start_date)->format('d M Y') }}
          <span class="mx-2">·</span>
          <i class="ti ti-map-pin me-1"></i>
          {{ $event->venue_name ?? 'Venue TBC' }}
        </div>

        <div class="mt-2">
          <span class="badge bg-label-primary">
            {{ $event->status_label }}
          </span>
        </div>
      </div>

      {{-- Quick admin actions --}}
      @if(auth()->check() && (
          auth()->user()->hasRole('super-user') ||
          $event->admins->contains(auth()->id())
      ))
        <div class="d-flex gap-2 flex-wrap">
          <a href="{{ route('admin.events.overview', $event) }}" class="btn btn-warning btn-sm">
            <i class="ti ti-shield"></i> Admin
          </a>
        </div>
      @endif

    </div>
  </div>
</div>
