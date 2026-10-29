@php
  $modalSlotType = $team->noProfile && $rosterSlot->noProfile ? 'imported' : 'profile';
  $modalId = 'roster-player-actions-'.$modalSlotType.'-'.$team->id.'-'.$identity->id;
  $modalRowKey = (string) ($rosterSlot->id ?? 'imported-'.$rosterSlot->noProfile?->id);
@endphp
<button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}" data-roster-actions-target="#{{ $modalId }}" aria-label="Player actions for {{ $name }}">Player actions <i class="ti ti-dots" aria-hidden="true"></i></button>
<div class="modal fade roster-player-actions-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}-title" aria-hidden="true"
     data-roster-actions-modal data-team-id="{{ $team->id }}" data-slot-id="{{ $identity->id }}" data-slot-type="{{ $modalSlotType }}"
     data-region-id="{{ $team->region_id }}" data-event-id="{{ $event->id }}" data-roster-row-key="{{ $modalRowKey }}">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="{{ $modalId }}-title">Player actions · {{ $name }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body roster-player-actions-body">{{ $slot }}</div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
    </div>
  </div>
</div>
