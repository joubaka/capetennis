@php
  $occupied = $region->teams->sum(fn ($team) => $team->workspaceSlots->filter(fn ($slot) => (int) $slot->player_id > 0 || $slot->noProfile)->count());
  $unpaid = $region->teams->sum(fn ($team) => $team->workspaceSlots->filter(fn ($slot) => ((int) $slot->player_id > 0 || $slot->noProfile) && (int) $slot->pay_status !== 1)->count());
  $reserves = ($teamSelectionInvitations ?? collect())->only($region->teams->modelKeys())->flatten(1)->where('status', \App\Models\TeamSelectionInvitation::RESERVE)->count();
@endphp
<div class="roster-region-header">
  <div><h2 class="h5 mb-1">{{ $region->region_name }}</h2><p class="text-muted mb-0">{{ $region->teams->count() }} teams · {{ $occupied }} occupied places · {{ $unpaid }} unpaid · {{ $reserves }} reserves</p></div>
  <div class="d-flex flex-wrap gap-2"><div class="dropdown"><button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">Send Emails</button><div class="dropdown-menu dropdown-menu-end">
    <button type="button" class="dropdown-item emailRegionBtn" data-regionid="{{ $region->id }}" data-regionname="{{ $region->region_name }}">Players in this region</button>
    <button type="button" class="dropdown-item emailUnpaidRegionBtn" data-regionid="{{ $region->id }}" data-regionname="{{ $region->region_name }}">Unpaid players in this region</button>
  </div></div>
    <div class="dropdown"><button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-label="Clothing for {{ $region->region_name }}">Clothing</button><div class="dropdown-menu dropdown-menu-end"><a class="dropdown-item" href="{{ route('backend.region.clothing.edit', ['region' => $region->id, 'event_id' => $event->id]) }}">Clothing setup</a><a class="dropdown-item" href="{{ route('backend.region.clothing.orders', ['region' => $region->id, 'event_id' => $event->id]) }}">Clothing orders</a></div></div>
  </div>
</div>
<p class="small text-muted" data-roster-match-count role="status" aria-live="polite"></p>
@forelse($region->teams as $team)
  @php
    $invitations = ($teamSelectionInvitations ?? collect())->get($team->id, collect());
    $rankingManaged = $invitations->isNotEmpty();
    $teamReserves = $invitations->where('status', \App\Models\TeamSelectionInvitation::RESERVE)->sortBy('queue_position');
    $slots = $team->workspaceSlots;
    $occupiedSlots = $slots->filter(fn ($slot) => (int) $slot->player_id > 0 || $slot->noProfile);
  @endphp
  <details class="roster-team" data-roster-team="{{ $team->id }}" data-category="{{ $team->category_event_id }}" data-publication="{{ $team->published ? 'published' : 'draft' }}" data-team-name="{{ $team->name }}" @if($loop->first) open @endif>
    <summary class="roster-team-summary">
      <span class="roster-team-title"><strong>{{ $team->name }}</strong><span class="small text-muted">{{ $team->category?->category?->name ?? 'Category not set' }} · {{ $occupiedSlots->count() }}/{{ $team->num_team_members }} places · {{ $occupiedSlots->where('pay_status', '!=', 1)->count() }} unpaid · {{ $teamReserves->count() }} reserves</span></span>
      <span class="d-flex flex-wrap gap-2 align-items-center">@if($rankingManaged)<span class="badge bg-label-info">Ranking-managed</span>@endif<span class="badge {{ $team->published ? 'bg-label-success' : 'bg-label-secondary' }}" data-team-publication="{{ $team->id }}">{{ $team->published ? 'Published' : 'Unpublished' }}</span><span class="roster-disclosure" aria-hidden="true">⌄</span></span>
    </summary>
    <div class="roster-team-body">
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <span class="small text-muted">Team #{{ $team->id }} · Player order is shown by rank.</span>
        <div class="d-flex flex-wrap gap-2">
          @can('team.players.manage', $team)
            <button type="button" class="btn btn-outline-secondary emailTeamBtn" data-teamid="{{ $team->id }}" data-teamname="{{ $team->name }}">Send team email</button>
          @endcan
        </div>
      </div>
      <div class="table-responsive"><table class="table align-middle team-player-table mb-0">
        <thead><tr><th scope="col">Rank</th><th scope="col">Player</th><th scope="col">Contact</th><th scope="col">Payment</th><th scope="col">Actions</th></tr></thead>
        <tbody>
          @forelse($slots as $slot)
            @php
              $player = (int) $slot->player_id > 0 ? $slot->player : null;
              $np = $player ? null : $slot->noProfile;
              $name = $player ? trim($player->name.' '.$player->surname) : ($np ? trim($np->name.' '.$np->surname) : 'Empty place');
              $email = $player?->email ?: $np?->email;
              $cell = $player?->cellNr ?: $np?->cell_nr;
              $whatsAppUrl = \App\Support\PhoneContact::whatsAppUrl($cell);
              $paid = (int) $slot->pay_status === 1;
            @endphp
            <tr data-roster-row="{{ $slot->id ?? 'imported-'.$np?->id }}" data-occupied="{{ ($player || $np) ? 'true' : 'false' }}" data-search="{{ $name.' '.$email.' '.$cell }}" data-payment="{{ ($player || $np) ? ($paid ? 'paid' : 'unpaid') : 'vacant' }}" data-profile="{{ $player ? 'linked' : ($np ? 'imported' : 'vacant') }}">
              <td data-label="Rank"><span class="badge bg-label-primary">{{ $slot->rank }}</span></td>
              <td data-label="Player"><strong>{{ $name }}</strong>@if($player)<x-player-rating :player-id="$player->id" :context="$team->category" />@else<span class="badge bg-label-warning ms-1">{{ $np ? 'Unlinked profile' : 'Vacant' }}</span>@endif</td>
              <td data-label="Contact"><div class="roster-contact">
                @if($email)<div class="d-flex align-items-center gap-2"><a href="mailto:{{ $email }}">{{ $email }}</a></div>@endif
                @if($cell)<div class="d-flex flex-wrap align-items-center gap-2"><a href="tel:{{ preg_replace('/[^0-9+]/', '', $cell) }}">{{ $cell }}</a>@if($whatsAppUrl)<a href="{{ $whatsAppUrl }}" class="btn btn-outline-success" target="_blank" rel="noopener noreferrer" aria-label="Open WhatsApp for {{ $name }} (opens in a new tab)"><i class="ti ti-brand-whatsapp me-1" aria-hidden="true"></i>WhatsApp</a>@endif</div>@endif
                @if(!$email && !$cell)<span class="text-muted">No contact details captured.</span>@endif
              </div></td>
              <td data-label="Payment"><span class="badge {{ $paid ? 'bg-label-success' : 'bg-label-warning' }}">{{ $paid ? 'Paid' : (($player || $np) ? 'Unpaid' : '—') }}</span></td>
              <td data-label="Actions">@can('team.players.manage', $team)
                @if($player)<button type="button" class="btn btn-outline-secondary emailPlayer" data-playerid="{{ $player->id }}" data-name="{{ $name }}">Send email</button>@else<span class="small text-muted">{{ $np ? 'Manage imported player in selection' : 'No player assigned' }}</span>@endif
              @endcan</td>
            </tr>
          @empty <tr><td colspan="5" class="text-muted">No roster places have been created.</td></tr> @endforelse
        </tbody>
      </table></div>
      @if($rankingManaged)
        <details class="roster-reserves mt-3"><summary>Reserve queue ({{ $teamReserves->count() }})</summary><p class="small text-muted mt-2">Reserves enter the active roster, draws, exports and emails to players in the team or region only after promotion.</p>
          <ol class="mb-0">@forelse($teamReserves as $reserve)<li>{{ $reserve->player?->full_name ?? 'Missing player' }} <span class="text-muted">· Ranking #{{ $reserve->ranking_position ?? '—' }}</span></li>@empty<li class="list-unstyled">No reserves remain.</li>@endforelse</ol>
        </details>
      @endif
    </div>
  </details>
@empty <div class="alert alert-light border">No teams in this region.</div> @endforelse
@if($region->teams->isNotEmpty())<div class="alert alert-light border" data-roster-empty hidden>No players or teams match these filters. Clear filters to see the region roster.</div>@endif
