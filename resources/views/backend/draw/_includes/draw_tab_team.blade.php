@php
  $isTeamDraw = $draw->isTeamDraw();
  $isLocked = (bool) $draw->locked;
  $isPublished = (bool) $draw->published;
  $isSchedulePublished = (bool) $draw->oop_published;
  $canConfirmScoring = $isTeamDraw && ($draw->team_scoring_rules['undecided'] ?? false)
    && isset($draw->team_draw_selection['competition']) && !$isPublished && !$isLocked
    && auth()->user()?->can('team-draw.updateTeamDraw', $draw)
    && !$draw->teamTies()->whereNotNull('published_at')->exists();
  $individualWorkspaceUrl = $draw->needsWorkflowChoice() ? route('draw.setup.show', $draw) : route('backend.draw.roundrobin.show', $draw);
  $individualSettingsUrl = $draw->needsWorkflowChoice() ? $individualWorkspaceUrl : $individualWorkspaceUrl.'#settings';
@endphp
<div class="list-group-item {{ ($hideDrawHeading ?? false) ? '' : 'event-draw-publication-card' }}">
  <div class="user-info">
    @unless($hideDrawHeading ?? false)
    <h6 class="mb-2">{{ $draw->drawName }} <span class="text-muted">— {{ optional($draw->draw_types)->drawTypeName ?? 'Type' }}</span></h6>
    @endunless
    <div class="event-draw-publication d-flex flex-wrap gap-2 {{ ($compactOverview ?? false) ? 'd-none' : 'mb-3' }}" aria-live="polite">
      <span class="event-draw-status badge bg-label-{{ $isPublished ? 'success' : 'warning' }} {{ ($compactOverview ?? false) ? 'd-none' : '' }}">{{ $isPublished ? 'Draw published' : 'Draw hidden' }}</span>
      <span class="event-schedule-status badge bg-label-{{ $isSchedulePublished ? 'success' : 'secondary' }}">{{ $isSchedulePublished ? ($isPublished ? 'Schedule published' : 'Schedule preview only') : 'Schedule hidden' }}</span>
      @unless($compactOverview ?? false)
        @include('backend.draw.partials.scoring-readiness')
      @endunless
      @if($isLocked && !($compactOverview ?? false))<span class="badge bg-label-secondary">Locked</span>@endif
    </div>
    <p class="event-publication-note small text-muted mb-3 {{ ($compactOverview ?? false) ? 'd-none' : '' }}">{{ $isSchedulePublished && ! $isPublished ? 'Schedule preview only: publish the draw to make these times public.' : ($isTeamDraw ? 'Publishing a team draw enables scoring after validation. Match times are published separately. Mark matches on court when play starts.' : 'Draws and match times are published separately.') }}</p>
    <div class="draw-venues mb-3 {{ ($compactOverview ?? false) ? 'd-none' : '' }}" data-draw-id="{{ $draw->id }}">
      <small class="text-muted">Venues:</small>
      @forelse($draw->venues as $venue)
        <span class="badge bg-label-primary me-1">{{ $venue->name }} <span class="text-muted">({{ $venue->pivot->num_courts }})</span></span>
      @empty
        <small class="text-muted">None assigned</small>
      @endforelse
    </div>
    <div class="draw-card-actions draw-card-primary d-flex flex-wrap gap-2 mt-3" role="group" aria-label="Draw workspace">
      @if(($compactOverview ?? false) && $canConfirmScoring)
        <a class="btn btn-sm btn-primary" href="{{ route('team-draw.competition.scoring', $draw) }}">Confirm scoring</a>
        @can('event.manage', $draw->event)<a data-draw-guide-open class="btn btn-sm btn-outline-secondary" href="{{ route('draw.guide.show', [$draw->event_id, $draw]) }}">Continue setup</a>@endcan
      @else
        @can('event.manage', $draw->event)<a data-draw-guide-open class="btn btn-sm btn-primary" href="{{ route('draw.guide.show', [$draw->event_id, $draw]) }}">Continue setup</a>@endcan
      @endif
      <a class="btn btn-sm {{ ($compactOverview ?? false) ? 'btn-outline-primary' : 'btn-primary' }}" href="{{ $isTeamDraw ? route('backend.team-fixtures.index', ['draw_id' => $draw->id]) : $individualWorkspaceUrl }}">{{ $isTeamDraw ? 'Open team fixtures' : 'Open singles draw' }}</a>
      @if(($compactOverview ?? false) && $isTeamDraw)
        @can('team-fixture.view', $draw)
          @if($draw->team_format_snapshot !== null)
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('backend.team-draw.operations', $draw) }}">Team ties</a>
          @endif
          <a class="btn btn-sm btn-outline-secondary" href="{{ route('backend.team-draw.standings', $draw) }}">Draw standings</a>
        @endcan
      @endif
    </div>
    @if($compactOverview ?? false)
      <div class="event-draw-scoring-actions">@include('backend.draw.partials.scoring-readiness')</div>
      <details class="event-draw-schedule" aria-label="Match schedule">
        <summary><span class="fw-semibold">Match schedule</span><span class="small text-muted">Plan, preview and publish times</span></summary>
    @endif
    <div class="draw-card-actions d-flex flex-wrap gap-2 mt-3" role="group" aria-label="Schedule tools">
      <a class="btn btn-sm btn-outline-primary" href="{{ route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]]) }}"><i class="ti ti-calendar me-1"></i>Schedule matches</a>
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('backend.event-venue-schedule.calendar', ['event' => $draw->event_id, 'draw_id' => $draw->id, 'date' => 'all']) }}">Edit schedule / publish times</a>
      <a class="btn btn-sm btn-outline-secondary" href="{{ route('backend.event-venue-schedule.calendar.preview', ['event' => $draw->event_id, 'draw_id' => $draw->id, 'date' => 'all']) }}"><i class="ti ti-eye me-1"></i>Public schedule preview</a>
    </div>
    @can('publish', $draw)
    <div class="draw-card-actions draw-card-publication d-flex flex-wrap gap-2 mt-3" role="group" aria-label="Publication controls">
      <button type="button" class="btn btn-sm btn-outline-secondary toggle-publish {{ ($compactOverview ?? false) ? 'd-none' : '' }}" data-url="{{ route('draw.toggle.publish', $draw->id) }}" data-status="{{ $isPublished ? 1 : 0 }}"><i class="ti ti-{{ $isPublished ? 'eye-off' : 'eye' }} me-1"></i>{{ $isPublished ? 'Hide draw' : 'Publish draw' }}</button>
      <button type="button" class="btn btn-sm btn-outline-secondary toggle-publish-schedule" data-url="{{ route('draw.toggle.publish.schedule', $draw->id) }}" data-status="{{ $isSchedulePublished ? 1 : 0 }}"><i class="ti ti-{{ $isSchedulePublished ? 'eye-off' : 'eye' }} me-1"></i>{{ $isSchedulePublished ? 'Hide schedule' : 'Publish schedule' }}</button>
    </div>
    @endcan
    @if($compactOverview ?? false)
      </details>
      <details class="event-draw-tools" aria-label="Draw settings"><summary><span class="fw-semibold">Draw settings</span><span class="small text-muted">Venues and advanced actions</span></summary>
    @endif
    <div class="draw-card-actions d-flex flex-wrap gap-2 mt-3" role="group" aria-label="Draw configuration">
      @can('update', $draw)
      <a class="btn btn-sm btn-outline-primary" href="{{ $isTeamDraw ? route('draws.manage', $draw->id) : $individualSettingsUrl }}"><i class="ti ti-edit me-1"></i>Edit draw</a>
      <button type="button" class="btn btn-sm btn-outline-info btn-add-venues" data-draw-id="{{ $draw->id }}" data-draw-name="{{ $draw->drawName }}" data-url="{{ route('backend.draw.venues.store', $draw->id) }}"><i class="ti ti-map-pin me-1"></i>Assign venues</button>
      @endcan
      @if(auth()->user()?->can('generateFixtures', $draw) || auth()->user()?->can('delete', $draw))
      @if($compactOverview ?? false)
      <details class="event-draw-advanced"><summary class="text-muted small">Advanced actions</summary>
      <div class="d-flex flex-wrap gap-2 mt-2">
        @if($isTeamDraw)
          @can('generateFixtures', $draw)
          <button type="button" class="btn btn-sm btn-outline-secondary btn-recreate-fixtures" @disabled($isLocked || $isPublished) data-url="{{ route('headoffice.recreateFixturesForDraw', $draw->id) }}" data-draw-id="{{ $draw->id }}" data-draw-name="{{ $draw->drawName }}">Recreate fixtures</button>
          @endcan
        @endif
        @can('delete', $draw)
          <button type="button" class="btn btn-sm btn-outline-danger btn-delete-draw" @disabled($isLocked || $isPublished) data-url="{{ route('draws.destroy', $draw->id) }}" data-draw-id="{{ $draw->id }}" data-draw-name="{{ $draw->drawName }}">Delete draw</button>
        @endcan
      </div>
      </details>
      @else
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
      @endif
    </div>
    @if($compactOverview ?? false)
      <p class="small text-muted mt-3 mb-0">Created {{ $draw->created_at->format('d M, Y') }}</p>
    @endif
    @if($compactOverview ?? false)</details>@endif
  </div>
</div>

@include('backend.draw.partials.guide-modal')
