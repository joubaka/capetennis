<table class="table table-sm align-middle table-bordered text-nowrap" data-team-id="{{ $team->id }}">
  <thead class="table-light">
    <tr>
      <th>#</th>
      <th>Profile Player</th>
      @if($team->noProfile)
        <th>No-Profile Player</th>
      @endif
      <th>Email</th>
      <th>Cell</th>
      <th>Pay Status</th>
      <th>Actions</th>
    </tr>
  </thead>

  @php
    $profiles = $team->players()->withPivot('rank','pay_status')->orderBy('team_players.rank')->get();
    $noProfiles = $team->noProfile ? $team->team_players_no_profile()->orderBy('rank')->get() : collect();
    $max = max($profiles->count(), $noProfiles->count());
  @endphp

  <tbody>
    @for($i = 0; $i < $max; $i++)
      @php
        $profile = $profiles[$i] ?? null;
        $noProfile = $team->noProfile ? ($noProfiles[$i] ?? null) : null;
        $pivotId = $profile?->pivot?->id ?? $noProfile?->id;
        $payStatus = $profile?->pivot?->pay_status ?? 0;
      @endphp
      <tr data-playerteamid="{{ $pivotId }}" data-team-id="{{ $team->id }}">
        <td><span class="badge bg-label-primary">{{ $i + 1 }}</span></td>
        <td class="name {{ $profile ? 'table-success' : 'table-light' }}">{{ $profile?->name }} {{ $profile?->surname }}</td>
        @if($team->noProfile)
          <td class="noprofile-name {{ $noProfile ? 'table-warning' : 'table-light' }}">
            {{ $noProfile?->name }} {{ $noProfile?->surname }}
          </td>
        @endif
        <td class="email">{{ $profile?->email ?? $noProfile?->email ?? '—' }}</td>
        <td class="cellNr">{{ $profile?->cellNr ?? $noProfile?->cellNr ?? '—' }}</td>
        <td class="payStatus">
          <span class="badge {{ $payStatus ? 'bg-label-success' : 'bg-label-danger' }}">
            {{ $payStatus ? 'Paid' : 'Not Paid' }}
          </span>
        </td>
        <td>
          <div class="dropdown">
            <button class="btn p-0 dropdown-toggle" data-bs-toggle="dropdown">
              <i class="ti ti-dots-vertical"></i>
            </button>
            <div class="dropdown-menu">
              <a class="dropdown-item insertPlayer" href="javascript:void(0);" data-pivot="{{ $pivotId }}" data-position="{{ $i + 1 }}" data-teamid="{{ $team->id }}">
                <i class="ti ti-insert me-1"></i> Replace Player
              </a>
              <a class="dropdown-item changePayStatus" href="javascript:void(0);" data-pivot="{{ $pivotId }}">
                <i class="ti ti-credit-card me-1"></i> Change Pay Status
              </a>
              <a class="dropdown-item refundToWallet" href="javascript:void(0);" data-pivot="{{ $pivotId }}">
                <i class="ti ti-cash me-1"></i> Refund to Wallet
              </a>
            </div>
          </div>
        </td>
      </tr>
    @endfor
  </tbody>
</table>
