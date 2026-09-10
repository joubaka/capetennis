@extends('layouts.backend')

@section('title', 'Team Selection & Invitations')

@section('vendor-style')
<link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection

@section('page-style')
<style>
  .region-workspace-card { border: 0; box-shadow: 0 .35rem 1.25rem rgba(31, 57, 104, .09); overflow: hidden; }
  .region-workspace-card > .card-header { background: linear-gradient(115deg, #173f78, #2563a9); color: #fff; }
  .region-workspace-card > .card-header .text-muted { color: rgba(255,255,255,.76) !important; }
  .regional-metric { height: 100%; border: 1px solid #dbe6f4; border-radius: .65rem; padding: .85rem 1rem; background: linear-gradient(145deg, #fff, #f5f9ff); }
  .regional-metric small { display: block; color: #68778c; }
  .regional-metric strong { display: block; margin-top: .15rem; color: #173f78; font-size: 1.25rem; }
  .regional-team-card { border: 1px solid #dbe6f4; border-top: 4px solid #2374bb; box-shadow: 0 .2rem .7rem rgba(31, 57, 104, .07); }
  .regional-team-card .card-header { background: linear-gradient(90deg, #f3f8ff, #fff8ef); }
  .regional-team-card .card-header[data-team-workspace-header] { cursor: pointer; }
  .regional-team-card .table > :not(caption) > * > * { padding: .7rem .65rem; }
  .regional-team-card .reserve-row { background: #fffaf0; }
  .regional-readonly { border-left: 4px solid #f59e0b; background: #fff9ed; }
  @media (max-width: 767.98px) {
    .regional-team-card .table { min-width: 760px; }
  }
</style>
@endsection

@section('vendor-script')
<script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
@include('backend.event.partials.header', [
  'event' => $event,
  'eventWorkspaceActive' => 'entries',
  'eventWorkspaceRegionalOnly' => ! $isEventManager,
  'eventWorkspaceShowHome' => $isEventManager,
])
<div class="container-xxl flex-grow-1 container-p-y">
  @if($isEventManager)
    <div class="d-flex justify-content-end mb-3">
      <a href="{{ route('backend.event.clothing.index', $event) }}" class="btn btn-outline-primary"><i class="ti ti-shirt me-1"></i>Clothing setup</a>
    </div>
  @endif

  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><strong>Action blocked.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

  @if($isEventManager)
    <div class="alert alert-info">Link each ranking-fed region to its own published series. Imported outside-region rosters can remain unlinked and will not be changed.</div>
  @else
    <div class="alert alert-info">You are viewing team selection, invitations and announcements for your assigned region.</div>
  @endif

  @if($eventRegions->count() > 1)
    <div class="nav nav-tabs flex-nowrap overflow-auto mb-3" role="tablist" aria-label="Event regions" data-region-tabs>
      @foreach($eventRegions as $eventRegion)
        <button
          type="button"
          class="nav-link text-nowrap {{ $loop->first ? 'active' : '' }}"
          id="region-tab-{{ $eventRegion->id }}"
          data-bs-toggle="tab"
          data-bs-target="#region-panel-{{ $eventRegion->id }}"
          role="tab"
          aria-controls="region-panel-{{ $eventRegion->id }}"
          aria-selected="{{ $loop->first ? 'true' : 'false' }}"
        >{{ $eventRegion->region?->region_name }}</button>
      @endforeach
    </div>
  @endif

  <div class="{{ $eventRegions->count() > 1 ? 'tab-content' : 'row g-3' }}">
    @foreach($eventRegions as $eventRegion)
      @php($source = $eventRegion->rankingSource)
      @php($sourceReady = $source && $readySeriesIds->contains($source->series_id))
      @php($regionTeams = $teams->get($eventRegion->region_id, collect()))
      @php($categorySetup = $source ? $categorySetups->get($source->id) : null)
      @php($activeImport = $source?->imports?->whereIn('status', ['draft','sent'])->sortByDesc('id')->first())
      @php($recipientEmailFor = fn($invitation) => collect([$invitation->player?->user?->email])->merge($invitation->player?->users?->pluck('email') ?? collect())->first(fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL)))
      @php($clothingAvailable = $eventRegion->region?->usesOnlineClothingOrders() && (bool)$eventRegion->region?->clothing_order && $eventRegion->region?->clothingItems?->contains(fn($item) => (float)$item->price > 0 && $item->sizes->isNotEmpty()))
      @php($regionManager = $regionManagers->get($eventRegion->id))
      @php($defaultCandidates = $defaultRegionManagerCandidates->get($eventRegion->id, collect()))
      @php($regionAnnouncementRecipients = $announcementRecipients->get($eventRegion->id, collect()))
      <div
        id="region-panel-{{ $eventRegion->id }}"
        class="{{ $eventRegions->count() > 1 ? 'tab-pane fade'.($loop->first ? ' show active' : '') : 'col-12' }}"
        role="tabpanel"
        aria-labelledby="region-tab-{{ $eventRegion->id }}"
        tabindex="0"
      >
        <div class="card region-workspace-card">
          <div class="card-header d-flex flex-wrap justify-content-between gap-2">
            <div><h5 class="mb-1">{{ $eventRegion->region?->region_name }}</h5><span class="text-muted small">{{ $regionTeams->count() }} teams · {{ $regionTeams->sum('num_team_members') }} configured places</span></div>
            @if($activeImport)
              <span class="badge bg-label-{{ $activeImport->status === 'sent' ? 'success' : 'warning' }}">{{ ucfirst($activeImport->status) }}</span>
            @elseif($source)
              <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="badge bg-label-primary">Series linked</span>
                <form method="POST" action="{{ route('backend.team-selection.unlink', [$event, $source]) }}" onsubmit="return confirm('Unlink this ranking series? Existing event categories and teams will be kept.');">
                  @csrf @method('DELETE')
                  <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ti ti-unlink me-1"></i>Unlink series</button>
                </form>
              </div>
            @else
              <span class="badge bg-label-secondary">Manual/imported or not linked</span>
            @endif
          </div>
          <div class="card-body">
            <div class="border rounded p-3 mb-3">
              <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start">
                <div><small class="text-muted d-block">Regional organizer</small><strong>{{ $regionManager?->name ?: trim(($regionManager?->userName ?? '').' '.($regionManager?->userSurname ?? '')) ?: $regionManager?->email ?: 'Not assigned' }}</strong>@if($regionManager?->email)<div class="small text-muted">{{ $regionManager->email }} · player profile not required</div>@endif</div>
                @if($isEventManager && $eventRegion->managerAssignment)<span class="badge bg-label-primary">Custom assignment</span>@elseif($regionManager)<span class="badge bg-label-secondary">Series organizer default</span>@endif
              </div>
              @if($isEventManager)
                <form method="POST" action="{{ route('backend.team-selection.manager.assign', [$event, $eventRegion]) }}" class="row g-2 align-items-end mt-1">@csrf @method('PUT')
                  <div class="col-md-7"><label class="form-label" for="region-manager-{{ $eventRegion->id }}">Select a system user</label><select id="region-manager-{{ $eventRegion->id }}" name="manager_user_id" class="form-select region-manager-select" data-placeholder="Search by name or email…"><option value=""></option>@if($eventRegion->managerAssignment && $regionManager)<option value="{{ $regionManager->id }}" selected>{{ trim($regionManager->name ?: (($regionManager->userName ?? '').' '.($regionManager->userSurname ?? ''))) }} · {{ $regionManager->email }}</option>@endif</select></div>
                  <div class="col-md-3 d-grid"><button class="btn btn-outline-primary">Assign to this region</button></div>
                  <div class="col-md-2 d-grid"><button class="btn btn-outline-secondary" name="use_default" value="1" @disabled(!$defaultRegionManagers->get($eventRegion->id))>Use default</button></div>
                </form>
                <div class="form-text">This grants access to this region; it does not remove existing event-wide or other-region access. A player profile is not required.</div>
                @if(!$eventRegion->managerAssignment && $defaultCandidates->count() > 1)<div class="alert alert-warning mt-2 mb-0">This series has multiple common event organizers. No default was selected automatically; assign the intended account explicitly.</div>@endif
              @endif
            </div>
            @if($activeImport)
              @php($selectedInvitations = $activeImport->invitations->whereIn('status', [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, \App\Models\TeamSelectionInvitation::PAID_CONFIRMED]))
              <div class="row g-2 mb-3" aria-label="Regional roster summary">
                <div class="col-6 col-lg-3"><div class="regional-metric"><small>Active selection</small><strong>{{ $selectedInvitations->count() }}</strong></div></div>
                <div class="col-6 col-lg-3"><div class="regional-metric"><small>Reserve queue</small><strong>{{ $activeImport->invitations->where('status', \App\Models\TeamSelectionInvitation::RESERVE)->count() }}</strong></div></div>
                <div class="col-6 col-lg-3"><div class="regional-metric"><small>Registration paid</small><strong>{{ $activeImport->invitations->where('status', \App\Models\TeamSelectionInvitation::PAID_CONFIRMED)->count() }}</strong></div></div>
                <div class="col-6 col-lg-3"><div class="regional-metric"><small>Contact needed</small><strong>{{ $activeImport->invitations->filter(fn($i) => !$recipientEmailFor($i))->count() }}</strong></div></div>
              </div>
            @endif

            <div class="regional-readonly rounded p-3 mb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
              <div><strong>Regional teams &amp; players</strong><div class="small text-muted">This mirrors the host roster view. Ranking positions, selection history and payment state are shown as read-only records.</div></div>
              <span class="badge bg-label-warning">Region-scoped workspace</span>
            </div>

            @if($regionTeams->isNotEmpty())
              <div class="row g-3 mb-3">
                @foreach($regionTeams as $regionTeam)
                  @php($teamInvitations = $activeImport?->invitations?->where('team_id', $regionTeam->id)->sortBy('queue_position') ?? collect())
                  @php($teamSelected = $teamInvitations->whereIn('status', [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, \App\Models\TeamSelectionInvitation::PAID_CONFIRMED]))
                  @php($teamReserves = $teamInvitations->where('status', \App\Models\TeamSelectionInvitation::RESERVE))
                  <div class="col-12">
                    <div class="card regional-team-card">
                      <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2" data-team-workspace-header data-team-workspace-target="#team-workspace-{{ $regionTeam->id }}">
                        <div>
                          <h6 class="mb-1">{{ $regionTeam->name }}</h6>
                          <span class="text-muted small">{{ $teamSelected->count() }} selected · {{ $teamReserves->count() }} reserves · {{ $regionTeam->num_team_members }} configured places</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                          <span class="badge {{ $regionTeam->published ? 'bg-label-success' : 'bg-label-secondary' }}">{{ $regionTeam->published ? 'Published' : 'Not published' }}</span>
                          @if($teamSelected->isNotEmpty())<button class="btn btn-sm btn-outline-success roster-email-button" type="button" data-bs-toggle="modal" data-bs-target="#roster-email-{{ $eventRegion->id }}" data-target-type="team" data-team-id="{{ $regionTeam->id }}" data-recipient="{{ $teamSelected->count() }} active player(s) in {{ $regionTeam->name }}"><i class="ti ti-mail me-1"></i>Email team</button>@endif
                          <button class="btn btn-sm btn-primary team-workspace-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#team-workspace-{{ $regionTeam->id }}" aria-controls="team-workspace-{{ $regionTeam->id }}" aria-expanded="false"><i class="ti ti-eye me-1"></i><span>Show team</span></button>
                          <button class="btn btn-sm btn-outline-primary team-settings-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#team-settings-{{ $regionTeam->id }}" aria-controls="team-settings-{{ $regionTeam->id }}" aria-expanded="false"><i class="ti ti-settings me-1"></i><span>Team settings</span></button>
                        </div>
                      </div>
                      <div class="collapse" id="team-settings-{{ $regionTeam->id }}">
                        <div class="card-body border-bottom">
                          <form method="POST" action="{{ route('backend.team-selection.teams.update', [$event, $eventRegion, $regionTeam]) }}" class="row g-2 align-items-end">@csrf @method('PATCH')
                            <input type="hidden" name="settings_team_id" value="{{ $regionTeam->id }}">
                            <div class="col-md-7"><label class="form-label">Team name</label><input name="name" value="{{ $regionTeam->name }}" class="form-control" maxlength="255" required></div>
                            <div class="col-md-3"><input type="hidden" name="published" value="0"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="published" value="1" id="published-team-{{ $regionTeam->id }}" @checked($regionTeam->published)><label class="form-check-label" for="published-team-{{ $regionTeam->id }}">Published for registration</label></div></div>
                            <div class="col-md-2 d-grid"><button class="btn btn-primary">Save team</button></div>
                          </form>
                        </div>
                      </div>
                      <div class="collapse" id="team-workspace-{{ $regionTeam->id }}">
                      @if($activeImport)
                        <ul class="nav nav-tabs px-3 pt-3" role="tablist">
                          <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#team-players-{{ $regionTeam->id }}" type="button"><i class="ti ti-users me-1"></i>Players</button></li>
                          <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#team-order-{{ $regionTeam->id }}" type="button"><i class="ti ti-list-numbers me-1"></i>Player order</button></li>
                        </ul>
                        <div class="tab-content p-0">
                        <div class="tab-pane fade show active" id="team-players-{{ $regionTeam->id }}">
                        <form method="POST" action="{{ route('backend.team-selection.players.add', [$event, $activeImport, $regionTeam]) }}" class="row g-2 align-items-end p-3 border-bottom">
                          @csrf
                          <input type="hidden" name="add_team_id" value="{{ $regionTeam->id }}">
                          <div class="col-lg-5">
                            <label class="form-label" for="add-player-{{ $regionTeam->id }}">Add an existing system player profile</label>
                            <select id="add-player-{{ $regionTeam->id }}" name="player_id" class="form-select team-player-select" data-placeholder="Search player name, email or cell…" data-search-url="{{ route('backend.team-selection.players.search', [$event, $activeImport, $regionTeam]) }}" required><option value=""></option></select>
                          </div>
                          <div class="col-lg-5">
                            <label class="form-label" for="add-player-reason-{{ $regionTeam->id }}">Reason</label>
                            <input id="add-player-reason-{{ $regionTeam->id }}" type="text" name="reason" class="form-control" maxlength="1000" placeholder="Why this player is being added" required>
                          </div>
                          <div class="col-lg-2 d-grid"><button class="btn btn-outline-primary"><i class="ti ti-user-plus me-1"></i>Add as reserve</button></div>
                          <div class="col-12 form-text">Only linked player profiles are shown. The player is appended to the reserve queue; the active roster and published ranking snapshot stay unchanged.</div>
                        </form>
                        <div class="table-responsive">
                          <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Rank</th><th>Player</th><th>Contact</th><th>Ranking</th><th>Selection / payment</th><th>Email</th><th>Regional action</th></tr></thead>
                            <tbody>
                              @forelse($teamInvitations as $invitation)
                                @php($recipientEmail = $recipientEmailFor($invitation))
                                @php($delivery = $invitation->emailLogs->sortByDesc('id')->first())
                                @php($isReserve = $invitation->status === \App\Models\TeamSelectionInvitation::RESERVE)
                                <tr class="{{ $isReserve ? 'reserve-row' : '' }}">
                                  <td><span class="badge {{ $isReserve ? 'bg-label-warning' : 'bg-label-primary' }}">{{ $isReserve ? 'Reserve '.$invitation->queue_position : 'Rank '.$invitation->roster_rank }}</span></td>
                                  <td><strong>{{ $invitation->player?->full_name ?: 'Missing player' }}</strong>@if(!$invitation->player?->profile_complete)<div class="small text-warning">Profile incomplete</div>@endif</td>
                                  <td><div>{{ $recipientEmail ?: 'Account link required' }}</div><div class="small text-muted">{{ $invitation->player?->cellNr ?: 'No cell number' }}</div></td>
                                  <td>@if(data_get($invitation->snapshot_json, 'selection_source') === 'manual_system_profile')<strong>Manual addition</strong><div class="small text-muted">Not in ranking snapshot</div>@else<strong>#{{ $invitation->ranking_position }}</strong><div class="small text-muted">{{ number_format((float)$invitation->total_points, 2) }} pts</div>@endif</td>
                                  <td><span class="badge bg-label-{{ $invitation->status === \App\Models\TeamSelectionInvitation::PAID_CONFIRMED ? 'success' : ($isReserve ? 'warning' : 'info') }}">{{ str($invitation->status)->replace('_',' ')->title() }}</span><div class="small text-muted mt-1">Read only</div></td>
                                  <td>
                                    <div>{{ $delivery ? ucfirst($delivery->status) : 'Not sent' }}</div>
                                    @if($activeImport->status === 'sent' && !$isReserve)
                                      <div class="d-flex flex-wrap gap-1 mt-1">
                                        <a class="btn btn-xs btn-outline-secondary" target="_blank" href="{{ route('backend.team-selection.invitations.email.view', [$event, $activeImport, $invitation]) }}">View email</a>
                                        @if(in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true))
                                          <form method="POST" action="{{ route('backend.team-selection.invitations.email.resend', [$event, $activeImport, $invitation]) }}" onsubmit="return confirm('Resend the saved invitation email to this player?');">@csrf<button class="btn btn-xs btn-outline-primary">Resend</button></form>
                                        @endif
                                      </div>
                                    @endif
                                  </td>
                                  <td>
                                    <div class="d-flex flex-wrap gap-1">
                                      @if($recipientEmail && !$isReserve)<button class="btn btn-sm btn-outline-success roster-email-button" type="button" data-bs-toggle="modal" data-bs-target="#roster-email-{{ $eventRegion->id }}" data-target-type="player" data-team-id="{{ $regionTeam->id }}" data-invitation-id="{{ $invitation->id }}" data-recipient="{{ $invitation->player?->full_name }} · {{ $recipientEmail }}"><i class="ti ti-mail"></i></button>@endif
                                    @if(!$isReserve && in_array($invitation->status, [\App\Models\TeamSelectionInvitation::INVITED, \App\Models\TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT], true) && $teamReserves->isNotEmpty())
                                      <details><summary class="btn btn-sm btn-outline-warning">Change player</summary><form method="POST" action="{{ route('backend.team-selection.invitations.replace', [$event, $activeImport, $invitation]) }}" class="mt-2" onsubmit="return confirm('Replace this unpaid player with the next eligible reserve?');">@csrf<div class="small text-muted mb-1">The next eligible reserve will take this exact roster rank.</div><input type="text" name="reason" class="form-control form-control-sm mb-1" maxlength="1000" value="Player not available." placeholder="Required reason" required><button class="btn btn-sm btn-warning w-100">Confirm replacement</button></form></details>
                                    @elseif(!$recipientEmail)
                                      <span class="text-muted small">No action available</span>
                                    @endif
                                    </div>
                                  </td>
                                </tr>
                              @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No ranked players have been imported for this team yet.</td></tr>
                              @endforelse
                            </tbody>
                          </table>
                        </div>
                        </div>
                        <div class="tab-pane fade" id="team-order-{{ $regionTeam->id }}">
                          <div class="p-3 border-bottom small text-muted">Change the playing order without changing the selected players, their payment state, or the original ranking snapshot. Every move is audited.</div>
                          <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                              <thead><tr><th>Roster rank</th><th>Player</th><th>Published ranking</th><th>Selection status</th><th>Move</th></tr></thead>
                              <tbody>
                                @foreach($teamSelected->sortBy('roster_rank') as $orderedInvitation)
                                  <tr>
                                    <td><span class="badge bg-label-primary">Rank {{ $orderedInvitation->roster_rank }}</span></td>
                                    <td><strong>{{ $orderedInvitation->player?->full_name }}</strong></td>
                                    <td>@if(data_get($orderedInvitation->snapshot_json, 'selection_source') === 'manual_system_profile')Manual addition <span class="text-muted">· not in ranking snapshot</span>@else#{{ $orderedInvitation->ranking_position }} <span class="text-muted">· {{ number_format((float)$orderedInvitation->total_points, 2) }} pts</span>@endif</td>
                                    <td>{{ str($orderedInvitation->status)->replace('_',' ')->title() }}</td>
                                    <td><div class="d-flex gap-1">
                                      <form method="POST" action="{{ route('backend.team-selection.invitations.move', [$event, $activeImport, $orderedInvitation]) }}">@csrf<input type="hidden" name="direction" value="up"><button class="btn btn-sm btn-outline-primary" title="Move up" @disabled($loop->first)><i class="ti ti-arrow-up"></i></button></form>
                                      <form method="POST" action="{{ route('backend.team-selection.invitations.move', [$event, $activeImport, $orderedInvitation]) }}">@csrf<input type="hidden" name="direction" value="down"><button class="btn btn-sm btn-outline-primary" title="Move down" @disabled($loop->last)><i class="ti ti-arrow-down"></i></button></form>
                                    </div></td>
                                  </tr>
                                @endforeach
                              </tbody>
                            </table>
                          </div>
                        </div>
                        </div>
                      @else
                        <div class="card-body text-muted">Player places will appear here after the region reviews and imports its published ranking.</div>
                      @endif
                      </div>
                    </div>
                  </div>
                @endforeach
              </div>
            @else
              <div class="alert alert-warning">No regional teams exist yet. Link a ranking series and set up the region’s categories and teams below.</div>
            @endif
            <form method="POST" action="{{ route('backend.team-selection.link', [$event, $eventRegion]) }}" class="row g-2 align-items-end">@csrf
              <div class="col-lg-7"><label class="form-label">Ranking series</label><select name="series_id" class="form-select" {{ $activeImport ? 'disabled' : '' }} required><option value="">Choose {{ $event->start_date?->format('Y') }} series…</option>@foreach($series as $item)<option value="{{ $item->id }}" @selected($source?->series_id === $item->id)>{{ $item->name }}{{ $readySeriesIds->contains($item->id) ? ' · latest ranking published' : ' · ranking not ready' }}</option>@endforeach</select></div>
              <div class="col-sm-5 col-lg-2"><label class="form-label">Reserves per team</label><input type="number" name="reserve_count" min="0" max="20" value="{{ $source?->reserve_count ?? 2 }}" class="form-control" {{ $activeImport ? 'disabled' : '' }} required></div>
              <div class="col-sm-7 col-lg-3 d-grid"><button class="btn btn-outline-primary" {{ $activeImport ? 'disabled' : '' }}>Link series</button></div>
            </form>

            @if($source && !$activeImport)
              <div class="d-flex flex-wrap gap-2 mt-3">
                <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#ranking-category-setup-{{ $source->id }}">
                  <i class="ti ti-category-plus me-1"></i>Set up categories &amp; teams
                </button>
                @if($sourceReady && $regionTeams->isNotEmpty())
                  <a class="btn btn-primary" href="{{ route('backend.team-selection.preview', [$event, $source]) }}"><i class="ti ti-download me-1"></i>Import ranked players</a>
                @endif
                <a class="btn btn-outline-secondary" href="{{ route('backend.region.clothing.edit', ['region' => $eventRegion->region_id, 'event_id' => $event->id]) }}"><i class="ti ti-shirt me-1"></i>Clothing setup</a>
              </div>
              <div class="form-text">Create the event teams from the ranking categories, then review the ranked-player import.</div>
              @if(!$sourceReady)
                <div class="alert alert-warning mt-3 mb-0"><strong>Player import unavailable:</strong> review and publish a canonical ranking for {{ $source->series?->name }} first. You can still create its categories and teams now.</div>
              @endif
            @elseif($activeImport)
              <div class="regional-readonly rounded p-2 mt-3 small"><strong>Ranking snapshot:</strong> <code>{{ $activeImport->ranking_run_id }}</code> · locked to preserve the imported selection record.</div>
              @php($emailLogs = $activeImport->invitations->flatMap->emailLogs)
              @if($activeImport->status === 'sent')
                <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                  <span class="badge bg-label-secondary">Email queued: {{ $emailLogs->where('status','queued')->count() }}</span>
                  <span class="badge bg-label-success">Sent: {{ $emailLogs->where('status','sent')->count() }}</span>
                  <span class="badge bg-label-danger">Failed: {{ $emailLogs->where('status','failed')->count() }}</span>
                  <span class="badge bg-label-warning">Skipped: {{ $emailLogs->where('status','skipped')->count() }}</span>
                  @if($emailLogs->where('status','failed')->isNotEmpty())
                    <form method="POST" action="{{ route('backend.team-selection.emails.retry', [$event, $activeImport]) }}">@csrf<button class="btn btn-sm btn-outline-danger">Retry failed emails</button></form>
                  @endif
                </div>
                <form method="POST" action="{{ route('backend.team-selection.deadlines.extend', [$event, $activeImport]) }}" class="row g-2 align-items-end mt-2">@csrf @method('PATCH')
                  <div class="col-md-3"><label class="form-label">Response deadline</label><input type="datetime-local" name="response_deadline" value="{{ $activeImport->response_deadline?->format('Y-m-d\\TH:i') }}" class="form-control" required></div>
                  <div class="col-md-3"><label class="form-label">Payment deadline</label><input type="datetime-local" name="payment_deadline" value="{{ $activeImport->payment_deadline?->format('Y-m-d\\TH:i') }}" class="form-control" required></div>
                  <div class="col-md-3"><label class="form-label">Replacement payment deadline</label><input type="datetime-local" name="replacement_payment_deadline" value="{{ ($activeImport->replacement_payment_deadline ?: $activeImport->payment_deadline)?->format('Y-m-d\\TH:i') }}" class="form-control" required></div>
                  <div class="col-md-3 d-grid"><button class="btn btn-outline-primary">Extend deadlines</button></div>
                </form>
              @endif
              @if($activeImport->status === 'draft')
                <div class="d-flex flex-wrap align-items-center gap-2 mt-3"><button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#prepare-invitations-{{ $activeImport->id }}"><i class="ti ti-mail-cog me-1"></i>Prepare invitations</button><span class="text-muted small">Review the message, deadlines and exact recipients before sending.</span></div>
                <form method="POST" action="{{ route('backend.team-selection.restart', [$event, $activeImport]) }}" class="mt-3" onsubmit="return confirm('Remove this unsent import and clear its generated roster places?');">@csrf<button class="btn btn-sm btn-outline-danger">Restart draft import</button></form>
              @endif
            @endif

            <hr class="my-4">
            <h6>Regional announcements</h6>
            <p class="text-muted small">Visible only to this region’s invited players. Email, when selected, is sent only to active selected players in this region.</p>
            @foreach($eventRegion->announcements as $announcement)
              @php($announcementLogs = $announcement->emailLogs)
              <div class="border rounded p-2 mb-2"><div class="d-flex justify-content-between gap-2"><strong>{{ $announcement->title }}</strong><form method="POST" action="{{ route('backend.team-selection.announcements.destroy', [$event, $eventRegion, $announcement]) }}" onsubmit="return confirm('Hide this announcement from the regional portal? Previously sent email cannot be recalled.');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Hide</button></form></div><div class="small mt-1">{!! $announcement->message !!}</div><div class="d-flex flex-wrap gap-2 align-items-center text-muted small mt-1"><span>{{ $announcement->created_at->format('d M Y H:i') }}{{ $announcement->emailed_at ? ' · email queued' : ' · portal only' }}</span>@if($announcementLogs->isNotEmpty())<span class="badge bg-label-secondary">Queued {{ $announcementLogs->where('status','queued')->count() }}</span><span class="badge bg-label-success">Sent {{ $announcementLogs->where('status','sent')->count() }}</span><span class="badge bg-label-danger">Failed {{ $announcementLogs->where('status','failed')->count() }}</span>@if($announcementLogs->where('status','failed')->isNotEmpty())<form method="POST" action="{{ route('backend.team-selection.announcements.retry', [$event, $eventRegion, $announcement]) }}">@csrf<button class="btn btn-sm btn-outline-danger">Retry current recipients</button></form>@endif @endif</div></div>
            @endforeach
            <form method="POST" action="{{ route('backend.team-selection.announcements.store', [$event, $eventRegion]) }}" class="row g-2">@csrf
              <div class="col-md-4"><label class="form-label">Title</label><input name="title" class="form-control" maxlength="255" required></div>
              <div class="col-md-8"><label class="form-label">Message</label><textarea name="message" class="form-control" rows="2" maxlength="20000" required></textarea></div>
              <input type="hidden" name="recipient_hash" value="{{ hash('sha256', $regionAnnouncementRecipients->toJson()) }}">
              <div class="col-12"><details><summary>Review {{ $regionAnnouncementRecipients->count() }} exact email recipient(s)</summary><div class="small text-muted mt-1">@forelse($regionAnnouncementRecipients as $email)<div>{{ $email }}</div>@empty No active selected players currently have a valid email address. @endforelse</div></details></div>
              <div class="col-12 d-flex flex-wrap gap-3 align-items-center"><div><div class="form-check"><input type="hidden" name="send_email" value="0"><input class="form-check-input" type="checkbox" name="send_email" value="1" id="send-region-announcement-{{ $eventRegion->id }}" @disabled($regionAnnouncementRecipients->isEmpty())><label class="form-check-label" for="send-region-announcement-{{ $eventRegion->id }}">Also email these {{ $regionAnnouncementRecipients->count() }} recipient(s)</label></div><div class="form-check"><input class="form-check-input" type="checkbox" name="confirm_recipients" value="1" id="confirm-region-announcement-{{ $eventRegion->id }}"><label class="form-check-label" for="confirm-region-announcement-{{ $eventRegion->id }}">I reviewed and confirm this exact recipient list</label></div></div><button class="btn btn-outline-primary ms-auto">Publish regional announcement</button></div>
            </form>
          </div>
        </div>
      </div>

      @if($activeImport?->status === 'draft')
        <div class="modal fade" id="prepare-invitations-{{ $activeImport->id }}" tabindex="-1" aria-labelledby="prepare-invitations-title-{{ $activeImport->id }}" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable"><form method="POST" action="{{ route('backend.team-selection.send', [$event, $activeImport]) }}" class="modal-content">@csrf
            <input type="hidden" name="selection_import_id" value="{{ $activeImport->id }}">
            <div class="modal-header"><div><h5 class="modal-title" id="prepare-invitations-title-{{ $activeImport->id }}">Prepare regional invitations</h5><div class="text-muted small">{{ $eventRegion->region?->region_name }} · {{ $activeImport->invitations->where('status','invited')->count() }} selected recipients</div></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
              <div class="alert alert-info"><strong>Preview required.</strong> Open the actual sample email before sending. The exact message, event details, deadlines and clothing prices are snapshotted for audit and failed-email retries. Any change requires another preview.</div>
              <div class="row g-3">
                <div class="col-12"><label class="form-label">Email subject</label><input type="text" name="email_subject" maxlength="180" class="form-control" value="{{ old('email_subject', 'Platteland team invitation: '.$event->name) }}" required></div>
                <div class="col-12"><label class="form-label">Invitation message</label><textarea name="email_message" rows="4" maxlength="10000" class="form-control" required>{{ old('email_message', 'You have been selected to represent your region. Please review the event information and respond before the deadline.') }}</textarea><div class="form-text">This message appears near the top of every invitation.</div></div>
                <div class="col-12"><label class="form-label">Event information</label><textarea name="event_information" rows="5" maxlength="20000" class="form-control">{{ old('event_information', trim(strip_tags((string)$event->information))) }}</textarea><div class="form-text">Dates and entry fee are inserted automatically. Add venues, arrival times, accommodation or team instructions here.</div></div>
                <div class="col-md-4"><label class="form-label">Response deadline</label><input type="datetime-local" name="response_deadline" value="{{ old('response_deadline') }}" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Payment deadline</label><input type="datetime-local" name="payment_deadline" value="{{ old('payment_deadline') }}" class="form-control" required></div>
                <div class="col-md-4"><label class="form-label">Replacement payment deadline</label><input type="datetime-local" name="replacement_payment_deadline" value="{{ old('replacement_payment_deadline') }}" class="form-control" required><div class="form-text">Final payment cutoff for a promoted reserve.</div></div>
                <div class="col-md-4"><label class="form-label">Reply-to email</label><input type="email" name="reply_to" value="{{ old('reply_to', $event->email) }}" class="form-control" maxlength="255"><div class="form-text">Optional contact for player replies.</div></div>
                <div class="col-12"><input type="hidden" name="include_clothing" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="include_clothing" value="1" id="include-clothing-{{ $activeImport->id }}" @checked(old('include_clothing', $clothingAvailable)) @disabled(!$clothingAvailable)><label class="form-check-label" for="include-clothing-{{ $activeImport->id }}">Include optional regional clothing items, sizes, prices and ordering steps</label></div>@if(!$clothingAvailable)<div class="form-text text-warning">Complete this region's clothing items, sizes and approved prices, then open clothing ordering to enable this option.</div>@endif</div>
              </div>
              <hr><div class="row g-2"><div class="col-sm-4"><div class="border rounded p-3"><small class="text-muted d-block">Invitations</small><strong>{{ $activeImport->invitations->where('status','invited')->count() }}</strong></div></div><div class="col-sm-4"><div class="border rounded p-3"><small class="text-muted d-block">Reserves held back</small><strong>{{ $activeImport->invitations->where('status','reserve')->count() }}</strong></div></div><div class="col-sm-4"><div class="border rounded p-3"><small class="text-muted d-block">Missing account/email</small><strong>{{ $activeImport->invitations->filter(fn($i) => !$recipientEmailFor($i))->count() }}</strong></div></div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-outline-primary" formaction="{{ route('backend.team-selection.email.preview', [$event, $activeImport]) }}" formtarget="_blank">Preview actual email</button><button type="submit" class="btn btn-success" onclick="return confirm('Queue these invitations for the selected players in this region?');">Confirm and send {{ $activeImport->invitations->where('status','invited')->count() }} invitations</button></div>
          </form></div>
        </div>
      @endif

      <div class="modal fade" id="roster-email-{{ $eventRegion->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered"><form method="POST" action="{{ route('backend.team-selection.roster-email.send', [$event, $eventRegion]) }}" class="modal-content">@csrf
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
        </form></div>
      </div>

      @if($source && !$activeImport && $categorySetup)
        @php($setupRows = $categorySetup['rows'])
        @php($missingSetupRows = $setupRows->reject(fn($row) => $row['ready']))
        <div class="modal fade" id="ranking-category-setup-{{ $source->id }}" tabindex="-1" aria-labelledby="ranking-category-setup-title-{{ $source->id }}" aria-hidden="true">
          <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
              <div class="modal-header">
                <div>
                  <h5 class="modal-title" id="ranking-category-setup-title-{{ $source->id }}">Create teams from ranking categories</h5>
                  <div class="text-muted small">{{ $eventRegion->region?->region_name }} · {{ $source->series?->name }}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="alert alert-info">
                  Select the categories this region will enter. Existing event teams are preserved; only missing categories, team links and empty roster places are created.
                </div>
                @if($setupRows->isEmpty())
                  <div class="alert alert-warning mb-0">This series has no ranking categories yet. Add its ranking lists first.</div>
                @else
                  <form id="ranking-category-form-{{ $source->id }}" method="POST" action="{{ route('backend.team-selection.teams.create', [$event, $source]) }}">
                    @csrf
                    <input type="hidden" name="setup_source" value="{{ $source->id }}">
                    <div class="row g-2 align-items-end mb-3">
                      <div class="col-sm-6 col-md-4">
                        <label class="form-label" for="default-team-size-{{ $source->id }}">Default team size</label>
                        <input type="number" class="form-control default-team-size" id="default-team-size-{{ $source->id }}" value="8" min="1" max="50" inputmode="numeric">
                        <div class="form-text">Selected players per team, excluding reserves.</div>
                      </div>
                      <div class="col-sm-6 col-md-4 d-grid">
                        <button type="button" class="btn btn-outline-primary apply-default-team-size">Apply to checked teams</button>
                      </div>
                    </div>
                    <div class="table-responsive">
                      <table class="table align-middle mb-0">
                        <thead><tr><th style="width:48px">Use</th><th>Ranking category</th><th>Ranked</th><th>Event status</th><th>Team name</th><th style="width:140px">Team size</th></tr></thead>
                        <tbody>
                          @foreach($setupRows as $rowIndex => $row)
                            @php($ready = $row['ready'])
                            <tr>
                              <td>
                                @if($ready)
                                  <i class="ti ti-circle-check text-success" aria-label="Ready"></i>
                                @else
                                  <input type="hidden" name="categories[{{ $rowIndex }}][selected]" value="0">
                                  <input class="form-check-input" type="checkbox" name="categories[{{ $rowIndex }}][selected]" value="1" checked aria-label="Create {{ $row['category_name'] }} team">
                                @endif
                              </td>
                              <td><strong>{{ $row['category_name'] }}</strong></td>
                              <td>{{ $categorySetup['published_ready'] ? $row['ranked_count'] : 'Pending publication' }}</td>
                              <td>
                                @if($ready)
                                  <span class="badge bg-label-success">Team ready</span>
                                @elseif($row['team'])
                                  <span class="badge bg-label-info">Existing team will be linked</span>
                                @elseif($row['event_category'])
                                  <span class="badge bg-label-warning">Category exists · team missing</span>
                                @else
                                  <span class="badge bg-label-secondary">New category &amp; team</span>
                                @endif
                              </td>
                              <td>
                                @if($ready)
                                  {{ $row['team']->name }}
                                @else
                                  <input type="hidden" name="categories[{{ $rowIndex }}][ranking_list_id]" value="{{ $row['ranking_list_id'] }}">
                                  <input type="text" class="form-control" name="categories[{{ $rowIndex }}][team_name]" value="{{ old("categories.$rowIndex.team_name", $row['team']?->name ?: $row['suggested_team_name']) }}" required maxlength="255">
                                @endif
                              </td>
                              <td>
                                @if($ready)
                                  {{ $row['team']->num_team_members }}
                                @else
                                  <input type="number" class="form-control team-size-input" name="categories[{{ $rowIndex }}][num_players]" value="{{ old("categories.$rowIndex.num_players", $row['team']?->num_team_members ?: 8) }}" min="1" max="50" inputmode="numeric" aria-label="Team size for {{ $row['category_name'] }}" required>
                                @endif
                              </td>
                            </tr>
                          @endforeach
                        </tbody>
                      </table>
                    </div>
                  </form>
                @endif
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                @if($missingSetupRows->isNotEmpty())
                  <button type="submit" form="ranking-category-form-{{ $source->id }}" class="btn btn-primary">Create selected teams</button>
                @elseif($categorySetup['published_ready'])
                  <a class="btn btn-primary" href="{{ route('backend.team-selection.preview', [$event, $source]) }}">Continue to ranked-player preview</a>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endif
    @endforeach
  </div>
</div>
@endsection

@section('page-script')
<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[id^="team-workspace-"]').forEach(function (workspace) {
    const toggle = document.querySelector(`[data-bs-target="#${workspace.id}"]`);
    const header = document.querySelector(`[data-team-workspace-target="#${workspace.id}"]`);
    const label = toggle?.querySelector('span');
    const icon = toggle?.querySelector('i');
    header?.addEventListener('click', function (event) {
      if (event.target.closest('a, button, input, select, textarea, summary, details, form, label')) return;
      toggle?.click();
    });
    workspace.addEventListener('shown.bs.collapse', function () {
      if (label) label.textContent = 'Hide team';
      icon?.classList.replace('ti-eye', 'ti-eye-off');
    });
    workspace.addEventListener('hidden.bs.collapse', function () {
      if (label) label.textContent = 'Show team';
      icon?.classList.replace('ti-eye-off', 'ti-eye');
    });
  });

  document.querySelectorAll('[id^="team-settings-"]').forEach(function (settings) {
    const toggle = document.querySelector(`[data-bs-target="#${settings.id}"]`);
    const label = toggle?.querySelector('span');
    settings.addEventListener('shown.bs.collapse', function () {
      if (label) label.textContent = 'Close settings';
    });
    settings.addEventListener('hidden.bs.collapse', function () {
      if (label) label.textContent = 'Team settings';
    });
  });

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

  if (window.jQuery?.fn?.select2) {
    window.jQuery('.region-manager-select').select2({
      width: '100%',
      placeholder: function () { return window.jQuery(this).data('placeholder'); },
      allowClear: true,
      minimumInputLength: 2,
      ajax: {
        url: @json(route('backend.team-selection.users.search', $event)),
        dataType: 'json',
        delay: 250,
        data: function (params) { return { q: params.term }; },
        processResults: function (data) { return data; },
        cache: true
      }
    });
    window.jQuery('.team-player-select').each(function () {
      const select = window.jQuery(this);
      select.select2({
        width: '100%',
        placeholder: select.data('placeholder'),
        minimumInputLength: 2,
        ajax: {
          url: select.data('search-url'),
          dataType: 'json',
          delay: 250,
          data: function (params) { return { q: params.term }; },
          processResults: function (data) { return data; },
          cache: true
        }
      });
    });
  }

  const addTeamId = @json(old('add_team_id'));
  if (addTeamId && typeof bootstrap !== 'undefined') {
    const workspace = document.getElementById(`team-workspace-${addTeamId}`);
    if (workspace) bootstrap.Collapse.getOrCreateInstance(workspace, { toggle: false }).show();
  }

  const settingsTeamId = @json(old('settings_team_id'));
  if (settingsTeamId && typeof bootstrap !== 'undefined') {
    const settings = document.getElementById(`team-settings-${settingsTeamId}`);
    if (settings) bootstrap.Collapse.getOrCreateInstance(settings, { toggle: false }).show();
  }

  const sourceId = @json(session('open_team_setup_source') ?: old('setup_source'));
  if (sourceId && typeof bootstrap !== 'undefined') {
    const modal = document.getElementById(`ranking-category-setup-${sourceId}`);
    if (modal) bootstrap.Modal.getOrCreateInstance(modal).show();
  }

  const invitationImportId = @json(old('selection_import_id'));
  if (invitationImportId && typeof bootstrap !== 'undefined') {
    const invitationModal = document.getElementById(`prepare-invitations-${invitationImportId}`);
    if (invitationModal) bootstrap.Modal.getOrCreateInstance(invitationModal).show();
  }

  document.querySelectorAll('[id^="ranking-category-form-"]').forEach(function (form) {
    const defaultSize = form.querySelector('.default-team-size');
    const applyDefault = form.querySelector('.apply-default-team-size');
    if (defaultSize && applyDefault) {
      applyDefault.addEventListener('click', function () {
        const value = defaultSize.value;
        if (!value) return;
        form.querySelectorAll('input[type="checkbox"][name$="[selected]"]:checked').forEach(function (checkbox) {
          const input = checkbox.closest('tr').querySelector('.team-size-input');
          if (input) input.value = value;
        });
      });
    }

    form.querySelectorAll('input[type="checkbox"][name$="[selected]"]').forEach(function (checkbox) {
      const row = checkbox.closest('tr');
      const toggleInputs = function () {
        row.querySelectorAll('input[name$="[team_name]"], input[name$="[num_players]"]').forEach(function (input) {
          input.disabled = !checkbox.checked;
        });
      };
      checkbox.addEventListener('change', toggleInputs);
      toggleInputs();
    });
  });
});
</script>
@endsection
