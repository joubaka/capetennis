@php
  $isTeamSchedule = (int) optional(optional($draw->event)->eventTypeModel)->type === \App\Models\EventType::TEAM;
  $scheduleRoute = $isTeamSchedule
    ? route('backend.team-schedule.page', $draw)
    : route('backend.individual-schedule.page', $draw);
@endphp
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="scheduleManagerLabel" aria-hidden="true">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="scheduleManagerLabel">Schedule manager</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><p class="mb-0">Scheduling is managed in the event-scoped schedule workspace, with venue, court and lifecycle validation.</p></div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button><a class="btn btn-primary" href="{{ $scheduleRoute }}">Open schedule manager</a></div>
  </div></div>
</div>
