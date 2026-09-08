{{-- resources/views/frontend/event/partials/no-profile-team.blade.php --}}

  <div class="col-12 col-md-6">
  <div class="card h-100 shadow-sm">

    {{-- HEADER --}}
    <div class="card-header border">
      <div class="d-flex align-items-center">
        <i class="ti ti-users fs-4 text-primary me-2"></i>
        <h5 class="m-0 fw-semibold">{{ $team->name ?? 'Team' }}</h5>
      </div>
    </div>

    {{-- BODY --}}
    @if((int) ($team->published ?? 0) !== 1)
      <div class="card-body text-center py-4">
        <i class="ti ti-eye-off fs-1 text-warning d-block mb-2"></i>
        <span class="text-warning fw-medium">Team not yet published</span>
      </div>
    @else
    <div class="card-body p-0">
      @php($registrationOpen = app(\App\Domain\Teams\Services\ExternalTeamRosterService::class)->registrationIsOpen($event))
      <ul class="list-group list-group-flush m-0">

        @forelse($team->team_players_no_profile as $play)
          @php
            $teamModel  = $play->team;
            $teamSlot = $teamModel->team_players->firstWhere('rank', $play->rank);
            $paystatus  = $teamSlot?->pay_status;
            $hasLinkedProfile = $play->player_profile && $play->profile;
            $linkProfileUrl = route('player.create', [
                'type' => 'noProfile',
                'noProfile' => $play->id,
                'team' => $team->id,
                'event' => $event->id
            ]);

            $playerName = $hasLinkedProfile
              ? ucfirst(strtolower($play->profile->name)) . ' ' . ucfirst(strtolower($play->profile->surname))
              : ucfirst(strtolower($play->name)) . ' ' . ucfirst(strtolower($play->surname));
          @endphp

          <li class="list-group-item">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">

              {{-- LEFT: RANK + NAME --}}
              <div class="d-flex align-items-center">
                <span class="badge bg-light text-muted border rounded-circle me-2" style="width: 24px; height: 24px; line-height: 16px; font-size: 0.75rem;">
                  {{ $play->rank }}
                </span>

                @if($hasLinkedProfile)
                  <span class="fw-medium">{{ $playerName }}</span>
                @elseif($registrationOpen)
                  <a href="{{ $linkProfileUrl }}" class="text-primary fw-medium" title="Click to link a player profile">
                    <i class="ti ti-link me-1"></i>{{ $playerName }}
                  </a>
                @else
                  <span class="fw-medium">{{ $playerName }}</span>
                @endif
              </div>

              {{-- RIGHT: STATUS / ACTIONS --}}
              <div class="d-flex align-items-center gap-1">

                @if($paystatus)
                  <span class="badge bg-success-subtle text-success px-2 py-1">
                    <i class="ti ti-circle-check me-1"></i>Registered
                  </span>

                @elseif($hasLinkedProfile && $registrationOpen)
                  <a href="{{ route('team.payment.payfast', [$teamModel->id, $play->player_profile, $event->id]) }}"
                     class="btn btn-sm btn-warning">
                    <i class="ti ti-credit-card me-1"></i>Register
                  </a>

                @elseif(!$hasLinkedProfile && $registrationOpen)
                  <a href="{{ $linkProfileUrl }}" class="btn btn-xs btn-outline-primary" title="Link a profile first">
                    <i class="ti ti-user-plus me-1"></i>Link Profile
                  </a>
                @else
                  <span class="badge bg-secondary-subtle text-secondary"><i class="ti ti-lock me-1"></i>Closed</span>
                @endif

              </div>
            </div>
          </li>

        @empty
          <li class="list-group-item text-center py-4">
            <i class="ti ti-users-minus fs-1 text-muted d-block mb-2"></i>
            <span class="text-muted">No team slots defined</span>
          </li>
        @endforelse

      </ul>
    </div>
    @endif

  </div>
</div>
