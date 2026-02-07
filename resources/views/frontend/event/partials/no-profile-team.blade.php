<div class="col-12 col-md-6">
  <div class="card h-100">
    <div class="card-header mb-0">
      <h5 class="m-0 me-2 m-4">{{ $team->name }}</h5>
    </div>

    <div class="card-body">
      <ul class="list-unstyled m-0 p-0">
        @foreach($team->team_players_no_profile as $key => $play)
          @php
            $teamModel  = $play->team;
            $paystatus  = $teamModel->team_players[$key]->pay_status ?? null;
          @endphp

          <li class="d-flex align-items-start mb-3">
            <div class="d-flex w-100 flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">

              {{-- Left side: number + name --}}
              <div class="d-flex align-items-center">
                <span class="badge bg-label-primary me-2">{{ $key+1 }}</span>

                @if($play->player_profile && $play->profile)
                  {{-- ✅ Linked profile --}}
                  <span class="fs-6 mb-0">
                    {{ ucfirst(strtolower($play->profile->name)) }}
                    {{ ucfirst(strtolower($play->profile->surname)) }}
                  </span>
                @else
                  {{-- 🔹 No profile: clickable link --}}
                  <a href="{{ route('player.create', [
                      'type' => 'noProfile',
                      'noProfile' => $play->id,
                      'team' => $team->id,
                      'event' => $event->id
                  ]) }}" class="fs-6 mb-0">
                    {{ ucfirst(strtolower($play->name)) }}
                    {{ ucfirst(strtolower($play->surname)) }}
                  </a>
                @endif
              </div>

              {{-- Right side: status / actions --}}
              <div class="d-flex align-items-center mt-2 mt-sm-0">
                @if($paystatus)
                  <span class="btn btn-sm btn-success disabled">Registered</span>
                @elseif($play->player_profile && $play->pay_status == 0)
                  <a href="{{ route('team.payment.payfast', [$teamModel->id, $play->player_profile, $event->id]) }}"
                     class="btn btn-sm btn-warning">
                    Register
                  </a>
                @else
                  <span class="btn btn-sm btn-secondary disabled">Click on name</span>
                @endif
              </div>

            </div>
          </li>
        @endforeach
      </ul>
    </div>
  </div>
</div>
