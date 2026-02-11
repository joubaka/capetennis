{{-- resources/views/frontend/event/partials/profile-team.blade.php --}}

<div class="col-12 col-md-6">
  <div class="card h-100">

    {{-- HEADER --}}
    <div class="card-header">
      <h5 class="m-0 m-4">{{ $team->name ?? 'Team' }}</h5>
    </div>

    {{-- BODY --}}
    @if((int)($team->published ?? 0) === 1)

      <div class="card-body">
        <ul class="list-unstyled m-0 p-0">

          {{-- LOOP THROUGH SLOTS --}}
          @forelse($team->teamPlayers as $slot)
            @php
              $dummyId    = 0;
              $isDummy    = (int) $slot->player_id === $dummyId;
              $player     = $isDummy ? null : $slot->player;

              $paid       = (int) ($slot->pay_status ?? 0) === 1;
              $canOrder   = (int) ($region->clothing_order ?? 0) === 1;
              $signupOpen = (int) ($event->signUp ?? 0) === 1;

              $playerName = $player
                ? trim(($player->name ?? '').' '.($player->surname ?? ''))
                : '— Empty slot —';
            @endphp

            <li class="d-flex align-items-start mb-3">
              <div class="d-flex w-100 flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">

                {{-- LEFT: RANK + NAME --}}
                <div class="d-flex align-items-center">
                  <span class="badge bg-label-primary me-2">{{ $slot->rank }}</span>
                  <span class="fs-6 {{ $isDummy ? 'text-muted fst-italic' : '' }}">
                    {{ $playerName }}
                  </span>
                </div>

                {{-- RIGHT: STATUS / ACTIONS --}}
                <div class="d-flex align-items-center mt-2 mt-sm-0">

                  @if($player)

                    {{-- PAID --}}
                    @if($paid)
                      <span class="btn btn-sm btn-success disabled">Registered</span>

                    {{-- UNPAID + SIGNUPS OPEN --}}
                    @elseif($signupOpen)
                      <a href="{{ route('team.payment.payfast', [$team->id, $player->id, $event->id]) }}"
                         class="btn btn-sm btn-warning">
                        Register
                      </a>

                    {{-- UNPAID + SIGNUPS CLOSED --}}
                    @else
                      <span class="text-muted fst-italic">Registration closed</span>
                    @endif

                    {{-- CLOTHING --}}
               @if($canOrder)
  <a href="javascript:void(0)"
     class="btn btn-sm btn-outline-secondary ms-1 clothing-order"
     data-playerid="{{ $player->id }}"
     data-name="{{ $playerName }}"
     data-team="{{ $team->id }}"
     data-region="{{ $region->id }}"
     data-eventid="{{ $event->id }}"
     data-bs-toggle="modal"
     data-bs-target="#clothing-order-modal">
    Clothing order
  </a>
@endif


                  @else
                    {{-- EMPTY SLOT --}}
                    <span class="text-muted fst-italic">Available</span>
                  @endif

                </div>
              </div>
            </li>

          @empty
            <li class="text-muted">No team slots defined.</li>
          @endforelse

        </ul>
      </div>

    @else
      <div class="card-body">
        <div class="text-danger fw-bold">Team not yet published</div>
      </div>
    @endif

  </div>
</div>

