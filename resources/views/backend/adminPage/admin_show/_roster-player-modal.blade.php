@php
  $modalSlotType = $team->noProfile && $rosterSlot->noProfile ? 'imported' : 'profile';
  $modalId = 'roster-player-actions-'.$modalSlotType.'-'.$team->id.'-'.$identity->id;
  $modalRowKey = (string) ($rosterSlot->id ?? 'imported-'.$rosterSlot->noProfile?->id);
@endphp
<button type="button" class="btn btn-sm btn-outline-secondary roster-icon-control" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}" data-roster-actions-target="#{{ $modalId }}" title="Player actions" aria-label="Player actions for {{ $name }}"><i class="ti ti-dots" aria-hidden="true"></i></button>
<div class="modal fade roster-player-actions-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-title" aria-describedby="{{ $modalId }}-description" aria-hidden="true"
     data-roster-actions-modal data-team-id="{{ $team->id }}" data-slot-id="{{ $identity->id }}" data-slot-type="{{ $modalSlotType }}"
     data-region-id="{{ $team->region_id }}" data-event-id="{{ $event->id }}" data-roster-row-key="{{ $modalRowKey }}">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <div class="small text-muted">Player actions</div>
          <h5 class="modal-title" id="{{ $modalId }}-title">{{ $name }}</h5>
          <p class="small text-body mb-0 mt-1" id="{{ $modalId }}-description">Choose what you want to do with this roster spot.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body roster-player-actions-body d-grid gap-2">{{ $slot }}</div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Back to roster</button></div>
    </div>
  </div>
</div>
