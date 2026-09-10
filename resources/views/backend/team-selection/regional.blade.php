@extends('layouts.backend')

@section('title', 'Regional Team Administration')

@section('page-style')
<link rel="stylesheet" href="{{ asset('css/team-admin-workspace.css') }}?v={{ filemtime(public_path('css/team-admin-workspace.css')) }}">
@endsection

@section('content')
@include('backend.event.partials.header', [
  'event' => $event,
  'eventWorkspaceActive' => 'entries',
  'eventWorkspaceRegionalOnly' => true,
  'eventWorkspaceShowHome' => false,
])

@php
  $teamWorkspaceActive = request('view') === 'order' ? 'order' : 'players';
  $regionCount = $workspaceRegions->count();
  $teamCount = $workspaceRegions->sum(fn ($region) => $region->teams->count());
  $categoryCount = 0;
  $playerCount = $workspaceRegions->sum(
    fn ($region) => $region->teams->sum(fn ($team) => $team->teamPlayers->filter(fn ($slot) => (int) $slot->player_id > 0 || $slot->noProfile)->count())
  );
  $reserveCount = $teamSelectionInvitations->flatten(1)
    ->where('status', \App\Models\TeamSelectionInvitation::RESERVE)->count();
  $teamWorkspaceRegional = true;
@endphp

<div class="container-xxl flex-grow-1 container-p-y">
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><strong>Action blocked.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  <div class="team-admin-workspace" data-backend-wide data-regional-team-workspace>
    <div class="nav-tabs-shadow mb-4">
      @include('backend.event.partials.team-workspace-nav', [
        'teamWorkspaceMode' => 'regional',
        'teamWorkspaceShowAdminTabs' => false,
      ])

      <div class="tab-content p-3">
        @if($teamWorkspaceActive === 'players')
          @include('backend.adminPage.admin_show.tabs.players', ['regionsInEvent' => $workspaceRegions])
        @else
          @include('backend.adminPage.admin_show.tabs.player-order', ['regionsInEvent' => $workspaceRegions])
        @endif
      </div>
    </div>
  </div>

  @foreach($eventRegions as $eventRegion)
    <div class="modal fade" id="roster-email-{{ $eventRegion->id }}" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-lg modal-dialog-centered">
        <form method="POST" action="{{ route('backend.team-selection.roster-email.send', [$event, $eventRegion]) }}" class="modal-content">
          @csrf
          <input type="hidden" name="target_type" value="team" data-roster-email-target>
          <input type="hidden" name="team_id" data-roster-email-team>
          <input type="hidden" name="invitation_id" data-roster-email-invitation>
          <div class="modal-header"><div><h5 class="modal-title">Email selected roster</h5><div class="small text-muted" data-roster-email-recipient></div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
          <div class="modal-body">
            <div class="mb-3"><label class="form-label">Subject</label><input class="form-control" name="subject" maxlength="180" required></div>
            <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="7" maxlength="20000" required></textarea></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_recipients" value="1" id="confirm-roster-email-{{ $eventRegion->id }}" required><label class="form-check-label" for="confirm-roster-email-{{ $eventRegion->id }}">I confirm the recipient details above are correct</label></div>
          </div>
          <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="ti ti-send me-1"></i>Queue email</button></div>
        </form>
      </div>
    </div>
  @endforeach
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[id^="roster-email-"]').forEach(function (modal) {
    modal.addEventListener('show.bs.modal', function (event) {
      const button = event.relatedTarget;
      if (!button) return;
      modal.querySelector('[data-roster-email-target]').value = button.dataset.targetType || 'team';
      modal.querySelector('[data-roster-email-team]').value = button.dataset.teamId || '';
      modal.querySelector('[data-roster-email-invitation]').value = button.dataset.invitationId || '';
      modal.querySelector('[data-roster-email-recipient]').textContent = button.dataset.recipient || '';
    });
  });
});
</script>
@endsection
