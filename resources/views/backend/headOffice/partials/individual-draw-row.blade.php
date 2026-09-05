@php
  $isFlexible = (bool) $draw->is_flexible;
  $drawUrl = route('backend.draw.roundrobin.show', $draw->id);
  $format = \App\Http\Controllers\Backend\DrawSetupController::OPTIONS[$draw->settings?->workflow][0]
    ?? ($isFlexible ? 'Custom Monrad' : null);
  $publishUrl = $isFlexible
    ? route('flexible-monrad.publish', $draw->id)
    : route('draw.toggle.publish', $draw->id);
  $publishRevision = $draw->relationLoaded('flexibleMonrad') ? ($draw->flexibleMonrad?->revision ?? 0) : 0;
  $flexibleGraph = $draw->relationLoaded('flexibleMonrad') ? ($draw->flexibleMonrad?->graph ?? []) : [];
  $pendingLateWithdrawalCount = $draw->getAttribute('pending_late_withdrawal_count');
  $pendingLateWithdrawalCount ??= count(array_diff(
      array_map('intval', array_keys($flexibleGraph['late_withdrawals'] ?? [])),
      array_map('intval', $flexibleGraph['withdrawn'] ?? [])
    ));
@endphp
<article class="draw-overview-row" data-draw-id="{{ $draw->id }}"
         data-name="{{ $draw->drawName }}" data-format="{{ $format ?? '' }}" data-published="{{ $draw->published ? '1' : '0' }}"
         data-schedule="{{ $draw->oop_published ? '1' : '0' }}" data-has-schedule="{{ $draw->order_of_play_count > 0 ? '1' : '0' }}"
         data-schedulable="{{ !$draw->locked && !$draw->published ? '1' : '0' }}">
  <div class="draw-overview-info">
    <label class="draw-row-selector" for="draw-select-{{ $draw->id }}">
      <input class="form-check-input draw-select" id="draw-select-{{ $draw->id }}" type="checkbox" value="{{ $draw->id }}">
      <span class="visually-hidden">Select {{ $draw->drawName }}</span>
    </label>
    <span class="draw-division-mark">@include('backend.headOffice.partials.draw-icon', ['icon' => 'bracket'])</span>
    <div class="draw-division-text">
      <h3 class="h6 mb-0 draw-overview-title"><a href="{{ $drawUrl }}" class="draw-overview-name">{{ $draw->drawName }}</a>
        @if($pendingLateWithdrawalCount > 0)
          <a href="{{ $drawUrl }}" class="draw-attention-badge" aria-label="{{ $pendingLateWithdrawalCount }} late {{ Str::plural('withdrawal', $pendingLateWithdrawalCount) }} {{ $pendingLateWithdrawalCount === 1 ? 'requires' : 'require' }} attention in {{ $draw->drawName }}" title="Open draw to review the late withdrawal">
            <i class="ti ti-alert-triangle-filled" aria-hidden="true"></i>
            {{ $pendingLateWithdrawalCount }} {{ Str::plural('late withdrawal', $pendingLateWithdrawalCount) }}
          </a>
        @endif
      </h3>
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
                data-draw-published="{{ $draw->published ? '1' : '0' }}"
                data-published="{{ $draw->oop_published ? '1' : '0' }}">
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
        @if($draw->published || $draw->oop_published)<li><a class="dropdown-item" href="{{ $draw->usesFlexibleMonrad() ? route('public.flexible-monrad.show', $draw) : route('public.roundrobin.show', $draw) }}" target="_blank" rel="noopener">{{ $draw->published ? 'Open public draw' : 'Preview times on front page' }}</a></li>@endif
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
