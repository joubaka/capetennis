@php
  $isTeamDraw = $draw->isTeamDraw();
  $isLocked = (bool) $draw->locked;
  $isPublished = (bool) $draw->published;
  $isSchedulePublished = (bool) $draw->oop_published;
  $individualWorkspaceUrl = $draw->needsWorkflowChoice() ? route('draw.setup.show', $draw) : route('backend.draw.roundrobin.show', $draw);
  $individualSettingsUrl = $draw->needsWorkflowChoice() ? $individualWorkspaceUrl : $individualWorkspaceUrl.'#settings';
@endphp
<div class="list-group-item event-draw-publication-card">
  <div class="user-info">
    <h6 class="mb-2">{{ $draw->drawName }} <span class="text-muted">— {{ optional($draw->draw_types)->drawTypeName ?? 'Type' }}</span></h6>
    <div class="event-draw-publication d-flex flex-wrap gap-2 mb-3" aria-live="polite">
      <span class="event-draw-status badge bg-label-{{ $isPublished ? 'success' : 'warning' }}">{{ $isPublished ? 'Draw published' : 'Draw hidden' }}</span>
      <span class="event-schedule-status badge bg-label-{{ $isSchedulePublished ? 'success' : 'secondary' }}">{{ $isSchedulePublished ? ($isPublished ? 'Schedule published' : 'Schedule preview only') : 'Schedule hidden' }}</span>
      @if($isLocked)<span class="badge bg-label-secondary">Locked</span>@endif
    </div>
    <p class="event-publication-note small text-muted mb-3">{{ $isSchedulePublished && ! $isPublished ? 'Schedule preview only: publish the draw to make these times public.' : 'Draws and match times are published separately.' }}</p>
    <div class="draw-venues mb-3" data-draw-id="{{ $draw->id }}">
      <small class="text-muted">Venues:</small>
      @forelse($draw->venues as $venue)
        <span class="badge bg-label-primary me-1">{{ $venue->name }} <span class="text-muted">({{ $venue->pivot->num_courts }})</span></span>
      @empty
        <small class="text-muted">None assigned</small>
      @endforelse
    </div>
    <div class="draw-card-actions draw-card-primary d-flex flex-wrap gap-2 mt-3" role="group" aria-label="Draw workspace">
      <a class="btn btn-sm btn-primary" href="{{ $isTeamDraw ? route('backend.team-fixtures.index', ['draw_id' => $draw->id]) : $individualWorkspaceUrl }}">{{ $isTeamDraw ? 'Open team fixtures' : 'Open singles draw' }}</a>
      <a class="btn btn-sm btn-outline-primary" href="{{ route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]]) }}"><i class="ti ti-calendar me-1"></i>Schedule matches</a>
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('backend.event-venue-schedule.calendar', ['event' => $draw->event_id, 'draw_id' => $draw->id, 'date' => 'all']) }}">Review / publish times</a>
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('backend.event-venue-schedule.calendar.preview', ['event' => $draw->event_id, 'draw_id' => $draw->id, 'date' => 'all']) }}"><i class="ti ti-eye me-1"></i>Public schedule preview</a>
    </div>
    @can('publish', $draw)
    <div class="draw-card-actions draw-card-publication d-flex flex-wrap gap-2 mt-3" role="group" aria-label="Publication controls">
      <button type="button" class="btn btn-sm btn-outline-secondary toggle-publish" data-url="{{ route('draw.toggle.publish', $draw->id) }}" data-status="{{ $isPublished ? 1 : 0 }}"><i class="ti ti-{{ $isPublished ? 'eye-off' : 'eye' }} me-1"></i>{{ $isPublished ? 'Hide draw' : 'Publish draw' }}</button>
      <button type="button" class="btn btn-sm btn-outline-secondary toggle-publish-schedule" data-url="{{ route('draw.toggle.publish.schedule', $draw->id) }}" data-status="{{ $isSchedulePublished ? 1 : 0 }}"><i class="ti ti-{{ $isSchedulePublished ? 'eye-off' : 'eye' }} me-1"></i>{{ $isSchedulePublished ? 'Hide schedule' : 'Publish schedule' }}</button>
    </div>
    @endcan
    <div class="draw-card-actions d-flex flex-wrap gap-2 mt-3" role="group" aria-label="Draw configuration">
      @can('update', $draw)
      <a class="btn btn-sm btn-outline-primary" href="{{ $isTeamDraw ? route('draws.manage', $draw->id) : $individualSettingsUrl }}"><i class="ti ti-edit me-1"></i>Edit draw</a>
      <button type="button" class="btn btn-sm btn-outline-info btn-add-venues" data-draw-id="{{ $draw->id }}" data-draw-name="{{ $draw->drawName }}" data-url="{{ route('backend.draw.venues.store', $draw->id) }}"><i class="ti ti-map-pin me-1"></i>Assign venues</button>
      @endcan
      @if(auth()->user()?->can('generateFixtures', $draw) || auth()->user()?->can('delete', $draw))
      <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">More actions</button>
        <ul class="dropdown-menu dropdown-menu-end">
          @if($isTeamDraw)
          @can('generateFixtures', $draw)
          <li><button type="button" class="dropdown-item btn-recreate-fixtures" @disabled($isLocked || $isPublished) data-url="{{ route('headoffice.recreateFixturesForDraw', $draw->id) }}" data-draw-id="{{ $draw->id }}" data-draw-name="{{ $draw->drawName }}"><i class="ti ti-refresh me-1"></i>Recreate fixtures</button></li>
          @endcan
          @endif
          @can('delete', $draw)
          <li><button type="button" class="dropdown-item text-danger btn-delete-draw" @disabled($isLocked || $isPublished) data-url="{{ route('draws.destroy', $draw->id) }}" data-draw-id="{{ $draw->id }}" data-draw-name="{{ $draw->drawName }}"><i class="ti ti-trash me-1"></i>Delete draw</button></li>
          @endcan
        </ul>
      </div>
      @endif
    </div>
  </div>
</div>
