@php
  $isFlexible = (bool) $draw->is_flexible;
  $drawUrl = route('backend.draw.roundrobin.show', $draw->id);
  $format = \App\Http\Controllers\Backend\DrawSetupController::OPTIONS[$draw->settings?->workflow][0]
    ?? ($isFlexible ? 'Custom Monrad' : null);
  $publishUrl = $isFlexible
    ? route('flexible-monrad.publish', $draw->id)
    : route('draw.toggle.publish', $draw->id);
  $publishRevision = $draw->relationLoaded('flexibleMonrad') ? ($draw->flexibleMonrad?->revision ?? 0) : 0;
@endphp
<article class="draw-overview-row" data-draw-id="{{ $draw->id }}"
         data-name="{{ $draw->drawName }}" data-format="{{ $format ?? '' }}" data-published="{{ $draw->published ? '1' : '0' }}"
         data-schedule="{{ $draw->oop_published ? '1' : '0' }}">
  <div class="draw-overview-info">
    <span class="draw-division-mark">@include('backend.headOffice.partials.draw-icon', ['icon' => 'bracket'])</span>
    <div class="draw-division-text">
      <h3 class="h6 mb-0"><a href="{{ $drawUrl }}" class="draw-overview-name">{{ $draw->drawName }}</a></h3>
      <div class="draw-division-state"><span class="draw-publication {{ $draw->published ? 'is-published' : 'is-draft' }}"><span class="draws-status-dot" aria-hidden="true"></span>{{ $draw->published ? 'Published' : 'Draft' }}</span>
      <span class="draw-publication {{ $draw->oop_published ? 'is-published' : 'is-draft' }}">
        <i class="ti ti-calendar-event" aria-hidden="true"></i>
        {{ $draw->oop_published ? 'Schedule published' : ($draw->order_of_play_count > 0 ? 'Schedule ready' : 'Schedule not ready') }}
      </span>
      @if($draw->locked)<span class="draw-lock">@include('backend.headOffice.partials.draw-icon', ['icon' => 'lock']) Locked</span>@endif</div>
    </div>
  </div>
  <div class="draw-format-cell"><span class="draw-column-label">Format</span><span class="{{ $format ? 'draw-format-name' : 'draw-format-unset' }}">{{ $format ?? 'Not specified' }}</span></div>
  <div class="draw-match-cell"><span class="draw-column-label">Matches</span><span class="draw-match-count" aria-label="{{ $draw->draw_fixtures_count }} {{ Str::plural('match', $draw->draw_fixtures_count) }}">{{ $draw->draw_fixtures_count }}</span></div>
  <div class="draw-venue-cell">
      <span class="draw-column-label">Venue</span>
      <span class="draw-venues" data-draw-id="{{ $draw->id }}">
        @forelse($draw->venues as $venue)
          <span>{{ $venue->name }} ({{ $venue->pivot->num_courts }} {{ Str::plural('court', $venue->pivot->num_courts) }}){{ !$loop->last ? ', ' : '' }}</span>
        @empty
          Venues not set
        @endforelse
      </span>
  </div>
  <div class="draw-overview-actions">
    <a class="btn draws-button draw-open-button" href="{{ $drawUrl }}">Open draw @include('backend.headOffice.partials.draw-icon', ['icon' => 'arrow'])</a>
    <a class="btn draws-button draw-schedule-button" href="{{ $drawUrl }}#schedule">@include('backend.headOffice.partials.draw-icon', ['icon' => 'calendar']) <span>Schedule</span></a>
    @can('publish', $draw)
      <button type="button" class="btn draws-button draw-publish-button {{ $draw->published ? 'draws-button-secondary' : 'draws-button-primary' }} toggle-publish"
              data-url="{{ $publishUrl }}" data-draw-name="{{ $draw->drawName }}"
              data-published="{{ $draw->published ? '1' : '0' }}"
              @if($isFlexible) data-revision="{{ $publishRevision }}" @endif
              @if($draw->published && $draw->locked) disabled title="Unlock this draw before unpublishing it" @endif
              @if(!$draw->published && !$format) disabled title="Select a draw format before publishing" @endif
              aria-label="{{ $draw->published ? 'Unpublish' : 'Publish' }} {{ $draw->drawName }}">
        <i class="ti ti-{{ $draw->published ? 'eye-off' : 'eye' }}" aria-hidden="true"></i>
        <span>{{ $draw->published ? 'Unpublish' : 'Publish' }}</span>
      </button>
    @endcan
    @can('publish', $draw)
      @if($draw->order_of_play_count > 0)
        <button type="button"
                class="btn draws-button {{ $draw->oop_published ? 'draws-button-secondary' : 'draw-schedule-button' }} toggle-schedule-publication"
                data-url="{{ route('draw.toggle.publish.schedule', $draw) }}"
                data-draw-name="{{ $draw->drawName }}"
                data-published="{{ $draw->oop_published ? '1' : '0' }}"
                @if(!$draw->oop_published && !$draw->published) disabled title="Publish the draw first" @endif>
          <i class="ti ti-clock" aria-hidden="true"></i>
          <span>{{ $draw->oop_published ? 'Unpublish times' : 'Publish times' }}</span>
        </button>
      @endif
    @endcan
    <div class="dropdown">
      <button type="button" class="btn draws-button draw-more-button" data-bs-toggle="dropdown"
              aria-expanded="false" aria-label="More actions for {{ $draw->drawName }}">@include('backend.headOffice.partials.draw-icon', ['icon' => 'dots'])</button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="{{ $drawUrl }}#settings"><i class="ti ti-settings me-2" aria-hidden="true"></i>Draw settings</a></li>
        <li><a class="dropdown-item" href="{{ $drawUrl }}#groups">Players</a></li>
        <li><a class="dropdown-item" href="{{ $drawUrl }}#print">Print this draw</a></li>
        @if($draw->published)<li><a class="dropdown-item" href="{{ route('public.roundrobin.show', $draw) }}" target="_blank" rel="noopener">Open public draw</a></li>@endif
        @can('update', $draw)
          <li><button type="button" class="dropdown-item btn-add-venues" data-draw-id="{{ $draw->id }}" data-draw-name="{{ $draw->drawName }}"><i class="ti ti-map-pin me-2" aria-hidden="true"></i>Assign venues</button></li>
        @endcan
        @can('super-user')
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="{{ route('engine.draw.show', $draw->id) }}"><i class="ti ti-tool me-2" aria-hidden="true"></i>Engine diagnostics</a></li>
        @endcan
        @if(!$draw->locked && !$draw->published)
          @can('delete', $draw)
            <li><hr class="dropdown-divider"></li>
            <li><button type="button" class="dropdown-item text-danger btn-delete-draw" data-url="{{ route('draws.destroy', $draw->id) }}" data-draw-name="{{ $draw->drawName }}"><i class="ti ti-trash me-2" aria-hidden="true"></i>Delete draw</button></li>
          @endcan
        @endif
      </ul>
    </div>
  </div>
</article>
