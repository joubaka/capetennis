@php
  $rosterOnly = $rosterOnly ?? false;
  $occupied = $region->teams->sum(fn ($team) => $team->workspaceSlots->filter(fn ($slot) => (int) $slot->player_id > 0 || $slot->noProfile)->count());
  $unpaid = $region->teams->sum(fn ($team) => $team->workspaceSlots->filter(fn ($slot) => ((int) $slot->player_id > 0 || $slot->noProfile) && (int) $slot->pay_status !== 1)->count());
  $reserves = $region->teams->sum(fn ($team) => $team->workspaceSlots->where('selected_team_reserve', true)->count() + ($teamSelectionInvitations ?? collect())->get($team->id, collect())->where('status', \App\Models\TeamSelectionInvitation::RESERVE)->whereNotIn('player_id', $team->workspaceSlots->where('selected_team_reserve', true)->pluck('player_id'))->count());
@endphp
<div class="roster-region-header">
  <div><h2 class="h5 mb-1">{{ $region->region_name }}</h2><p class="text-muted mb-0">{{ $region->teams->count() }} teams · {{ $occupied }} occupied places @unless($rosterOnly)· {{ $unpaid }} unpaid · {{ $reserves }} reserves
@endunless
</p></div>
  @unless($rosterOnly)
  <div class="d-flex flex-wrap gap-2">
    @if(app(\App\Services\TeamSelection\RegionManagerAccessService::class)->isEventManager(auth()->user(), $event))
      <a class="btn btn-outline-primary" href="{{ route('backend.team-selection.region-profile-matches.index', [$event, $region]) }}">Check profiles for whole region</a>
    @endif
    <div class="dropdown"><button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Send Emails</button><div class="dropdown-menu dropdown-menu-end">
    <button type="button" class="dropdown-item emailRegionBtn" data-regionid="{{ $region->id }}" data-regionname="{{ $region->region_name }}">Players in this region</button>
    <button type="button" class="dropdown-item emailUnpaidRegionBtn" data-regionid="{{ $region->id }}" data-regionname="{{ $region->region_name }}">Unpaid players in this region</button>
  </div></div>
    <div class="dropdown"><button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-label="Clothing for {{ $region->region_name }}">Clothing</button><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="{{ route('backend.region.clothing.edit', ['region' => $region->id, 'event_id' => $event->id]) }}">Clothing setup</a><a class="dropdown-item" href="{{ route('backend.region.clothing.orders', ['region' => $region->id, 'event_id' => $event->id]) }}">Clothing orders</a></div></div>
  </div>

@endunless

</div>
<p class="small text-muted" data-roster-match-count role="status" aria-live="polite"></p>
@forelse($region->teams as $team)
  @php
    $invitations = ($teamSelectionInvitations ?? collect())->get($team->id, collect());
    $rankingManaged = $invitations->isNotEmpty();
    $teamReserves = $invitations->where('status', \App\Models\TeamSelectionInvitation::RESERVE)->sortBy('queue_position');
    $reserveCount = $team->workspaceSlots->where('selected_team_reserve', true)->count() + $teamReserves->whereNotIn('player_id', $team->workspaceSlots->where('selected_team_reserve', true)->pluck('player_id'))->count();
    $slots = $team->workspaceSlots;
    $occupiedSlots = $slots->filter(fn ($slot) => (int) $slot->player_id > 0 || $slot->noProfile);
    $rosterLocked = app(\App\Services\TeamRosterService::class)->isLocked($event, $team);
    $rosterCanEdit = $rosterOnly || auth()->user()->can('team.players.manage', $team);
  @endphp
  <details class="roster-team" data-roster-team="{{ $team->id }}" data-category="{{ $team->category_event_id }}" data-publication="{{ $team->published ? 'published' : 'draft' }}" data-team-name="{{ $team->name }}" @if($loop->first) open
@endif
>
    <summary class="roster-team-summary">
      <span class="roster-team-title"><strong>{{ $team->name }}</strong><span class="small text-muted">{{ $team->category?->category?->name ?? 'Category not set' }} · {{ $occupiedSlots->count() }}/{{ $team->num_team_members }} places @unless($rosterOnly)· {{ $occupiedSlots->where('pay_status', '!=', 1)->count() }} unpaid · {{ $reserveCount }} reserves
@endunless
</span></span>
      @unless($rosterOnly)
      <span class="d-flex flex-wrap gap-2 align-items-center">@if($rankingManaged)<span class="badge bg-label-info">Ranking-managed</span>
@endif
<span class="badge {{ $team->published ? 'bg-label-success' : 'bg-label-secondary' }}" data-team-publication="{{ $team->id }}">{{ $team->published ? 'Published' : 'Unpublished' }}</span><span class="roster-disclosure" aria-hidden="true">⌄</span></span>

@endunless

    </summary>
    <div class="roster-team-body">
      @if($rosterLocked)<p class="text-muted">This competition has fixture history. Ask the event administrator to change this player list.</p>
@endif

      @unless($rosterOnly)
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <span class="small text-muted">Team #{{ $team->id }} · Player order is shown by rank.</span>
        <div class="d-flex flex-wrap gap-2">
          @if($team->noProfile && app(\App\Services\TeamSelection\RegionManagerAccessService::class)->isEventManager(auth()->user(), $event))
            <a class="btn btn-sm btn-outline-primary" href="{{ route('backend.team-selection.profile-matches.index', [$event, $team]) }}">Check existing profiles</a>
          @endif
          @can('team.players.manage', $team)
            <button type="button" class="btn btn-sm btn-outline-secondary emailTeamBtn" data-teamid="{{ $team->id }}" data-teamname="{{ $team->name }}">Send team email</button>

@endcan

        </div>
      </div>

@endunless

      @if(!$rosterOnly && !$rosterLocked && !$rankingManaged && $event->isTeam() && !$event->isInterprovincialTrials() && $team->num_team_members > 0 && $rosterCanEdit)
        <div class="mb-3">@include('backend.adminPage.admin_show._roster-add', ['reserveAdd' => true])</div>
      @endif

      <div class="table-responsive roster-compact-table"><table class="table align-middle team-player-table mb-0">
        <thead><tr><th scope="col">Rank</th><th scope="col">Player</th>@unless($rosterOnly)<th scope="col">Contact</th><th scope="col">Payment</th>
@endunless
<th scope="col">Actions</th></tr></thead>
        <tbody>
          @forelse($slots as $slot)
            @php
              $player = $team->noProfile && (int) $slot->noProfile?->player_profile > 0 ? $slot->noProfile->profile : ((int) $slot->player_id > 0 ? $slot->player : null);
              $np = $player ? null : $slot->noProfile;
              $name = $player ? trim($player->name.' '.$player->surname) : ($np ? trim($np->name.' '.$np->surname) : 'Empty place');
              $email = $player?->email ?: $np?->email;
              $cell = $player?->cellNr ?: $np?->cell_nr;
              $whatsAppUrl = \App\Support\PhoneContact::whatsAppUrl($cell);
              $paid = (int) $slot->pay_status === 1;
            @endphp
            @continue($rosterOnly && !$player && !$np)
            <tr data-roster-row="{{ $slot->id ?? 'imported-'.$np?->id }}" data-roster-player-id="{{ $player?->id }}" data-roster-imported-id="{{ $slot->noProfile?->id }}" data-occupied="{{ ($player || $np) ? 'true' : 'false' }}" data-search="{{ $rosterOnly ? $name : $name.' '.$email.' '.$cell }}" @unless($rosterOnly)data-payment="{{ ($player || $np) ? ($paid ? 'paid' : 'unpaid') : 'vacant' }}" data-profile="{{ $player ? 'linked' : ($np ? 'imported' : 'vacant') }}"
@endunless
>
              <td data-label="Rank"><span class="badge bg-label-primary">{{ $slot->rank }}</span>@if(!$rosterOnly && $slot->selected_team_reserve)<span class="badge bg-label-warning ms-1">Reserve</span>
@endif
</td>
              <td data-label="Player"><strong>{{ $name }}</strong>@unless($rosterOnly)@if($player)<x-player-rating :player-id="$player->id" :context="$team->category" />@if($selectionInvitation = $invitations->firstWhere('player_id', $player->id))<span class="badge bg-label-info ms-1">{{ \Illuminate\Support\Str::headline($selectionInvitation->status) }}</span>@endif @else<span class="badge bg-label-warning ms-1">{{ $np ? 'Unlinked profile' : 'Vacant' }}</span>
@endif

@endunless
</td>
              @unless($rosterOnly)
              <td data-label="Contact"><div class="roster-contact">
                @if($email)<div class="d-flex align-items-center gap-2"><a href="mailto:{{ $email }}">{{ $email }}</a></div>
@endif

                @if($cell)<div class="d-flex flex-wrap align-items-center gap-2"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $cell) }}">{{ $cell }}</a>@if($whatsAppUrl)<a href="{{ $whatsAppUrl }}" class="btn btn-sm btn-outline-success roster-whatsapp roster-icon-control" title="WhatsApp" target="_blank" rel="noopener noreferrer" aria-label="Open WhatsApp for {{ $name }} (opens in a new tab)"><i class="ti ti-brand-whatsapp" aria-hidden="true"></i></a>
@endif
</div>
@endif

                @if(!$email && !$cell)<span class="text-muted">No contact details captured.</span>
@endif

              </div></td>
              <td data-label="Payment"><span class="badge {{ $paid ? 'bg-label-success' : 'bg-label-warning' }}">{{ $paid ? 'Paid' : (($player || $np) ? 'Unpaid' : '—') }}</span></td>

@endunless

              <td data-label="Actions"><div class="roster-row-actions">
              @if($rosterCanEdit && !$rosterLocked)@include('backend.adminPage.admin_show._roster-actions', ['compactRoster' => true])
@endif
              @if(!$rosterLocked && !$rankingManaged && $slot->selected_team_reserve && $slot->rank > 0 && $slot->rank <= $team->num_team_members && auth()->user()->can('team.players.manage', $team))
                <form method="POST" action="{{ route('backend.team.addPlayers') }}" data-team-roster-change>
                  @csrf<input type="hidden" name="team_id" value="{{ $team->id }}"><input type="hidden" name="event_id" value="{{ $event->id }}"><input type="hidden" name="confirm_main_slot_id" value="{{ $team->noProfile ? $np?->id ?? $slot->noProfile?->id : $slot->id }}"><input type="hidden" name="expected_rank" value="{{ $slot->rank }}">
                  <button class="btn btn-sm btn-outline-primary">Confirm main place</button>
                </form>
              @endif
              @if(!$player && !$np && !$rosterLocked && !$rankingManaged && $event->isTeam() && !$event->isInterprovincialTrials() && $slot->rank <= $team->num_team_members && auth()->user()->can('team.players.manage', $team))
                @include('backend.adminPage.admin_show._roster-fill')
              @endif
              @if($rosterLocked && !$rosterOnly && $slot->exists && $player && $paid)
                @role('super-user')
                  <details class="roster-more-actions"><summary class="btn btn-sm btn-outline-secondary roster-icon-control" aria-label="More actions for {{ $name }}" title="More actions"><i class="ti ti-dots" aria-hidden="true"></i></summary>
                    <div class="roster-more-panel"><a class="btn btn-sm btn-outline-warning" href="{{ route('backend.team.withdrawal.show', [$event, $team, $slot]) }}">Review withdrawal / refund</a></div>
                  </details>
                @endrole
              @endif

              @unless($rosterOnly)
              @can('team.players.manage', $team)
                @if($player)<button type="button" class="btn btn-sm btn-outline-secondary emailPlayer" data-playerid="{{ $player->id }}" data-name="{{ $name }}">Send email</button>@else<span class="small text-muted">{{ $np ? 'Manage imported player in selection' : 'No player assigned' }}</span>
@endif


@endcan


@endunless

              </div></td>
            </tr>
          @empty <tr><td colspan="5" class="text-muted">No roster places have been created.</td></tr> @endforelse
        </tbody>
      </table></div>
      @if($rosterCanEdit && !$rosterLocked)@include('backend.adminPage.admin_show._roster-add')
@endif

      @if($rankingManaged && !$rosterOnly)
        <details class="roster-reserves mt-3"><summary>Reserve queue ({{ $teamReserves->count() }})</summary><p class="small text-muted mt-2">Reserves enter the active roster, draws, exports and emails to players in the team or region only after promotion.</p>
          <ol class="mb-0">@forelse($teamReserves as $reserve)<li>{{ $reserve->player?->full_name ?? 'Missing player' }} <span class="text-muted">· Ranking #{{ $reserve->ranking_position ?? '—' }}</span></li>@empty<li class="list-unstyled">No reserves remain.</li>@endforelse</ol>
        </details>

@endif

    </div>
  </details>
@empty <div class="alert alert-light border">No teams in this region.</div> @endforelse
@if($region->teams->isNotEmpty())<div class="alert alert-light border" data-roster-empty hidden>No players or teams match these filters. Clear filters to see the region roster.</div>
@endif
