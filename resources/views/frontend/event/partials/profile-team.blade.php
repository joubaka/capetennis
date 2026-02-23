{{-- resources/views/frontend/event/partials/profile-team.blade.php --}}

<div class="col-12 col-md-6">
  <div class="card h-100 shadow-sm">

    {{-- HEADER --}}
    <div class="card-header border rounded-top">
      <div class="d-flex align-items-center">
        <i class="ti ti-users fs-4 text-primary me-2"></i>
        <h5 class="m-0 fw-semibold">{{ $team->name ?? 'Team' }}</h5>
      </div>
    </div>

    {{-- BODY --}}
    @if((int)($team->published ?? 0) === 1)

      <div class="card-body p-0">
        <ul class="list-group list-group-flush m-0">

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

            <li class="list-group-item {{ $isDummy ? 'bg-light' : '' }}">
              <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">

                {{-- LEFT: RANK + NAME --}}
                <div class="d-flex align-items-center">
                  <span class="badge bg-light text-muted border rounded-circle me-2" style="width: 24px; height: 24px; line-height: 16px; font-size: 0.75rem;">
                    {{ $slot->rank }}
                  </span>
                  <span class="{{ $isDummy ? 'text-muted fst-italic' : 'fw-medium' }}">
                    {{ $playerName }}
                  </span>
                </div>

                {{-- RIGHT: STATUS / ACTIONS --}}
                <div class="d-flex align-items-center gap-1">

                  @if($player)

                    {{-- PAID --}}
                    @if($paid)
                      <span class="badge bg-success-subtle text-success px-2 py-1">
                        <i class="ti ti-circle-check me-1"></i>Registered
                      </span>

                      {{-- WITHDRAW BUTTON --}}
                      @php
                        $canWithdraw = $event->withdrawal_deadline && now()->lt($event->withdrawal_deadline);
                      @endphp
                      @if($canWithdraw && auth()->check() && ($player->users->contains('id', auth()->id()) || (int)auth()->id() === 584))
                        <button type="button"
                                class="btn btn-xs btn-outline-danger withDrawPlayer"
                                title="Cancel registration and withdraw from event"
                                data-id="{{ $slot->id }}"
                                data-team="{{ $team->id }}"
                                data-player="{{ $player->id }}"
                                data-event="{{ $event->id }}"
                                data-url="{{ route('team.player.withdraw', [$team->id, $player->id, $event->id]) }}">
                          <i class="ti ti-x"></i>
                        </button>
                      @endif

                    {{-- UNPAID + SIGNUPS OPEN --}}
                    @elseif($signupOpen)
                      <a href="{{ route('team.payment.payfast', [$team->id, $player->id, $event->id]) }}"
                         class="btn btn-sm btn-warning">
                        <i class="ti ti-credit-card me-1"></i>Register
                      </a>

                    {{-- UNPAID + SIGNUPS CLOSED --}}
                    @else
                      <span class="badge bg-secondary-subtle text-secondary">
                        <i class="ti ti-lock me-1"></i>Closed
                      </span>
                    @endif

                    {{-- CLOTHING --}}
                    @if($canOrder)
                      <a href="javascript:void(0)"
                         class="btn btn-xs btn-outline-secondary clothing-order"
                         title="Order clothing"
                         data-playerid="{{ $player->id }}"
                         data-name="{{ $playerName }}"
                         data-team="{{ $team->id }}"
                         data-region="{{ $region->id }}"
                         data-eventid="{{ $event->id }}"
                         data-bs-toggle="modal"
                         data-bs-target="#clothing-order-modal">
                        <i class="ti ti-shirt"></i>
                      </a>
                    @endif

                  @else
                    {{-- EMPTY SLOT --}}
                    <span class="badge bg-light text-muted border">
                      <i class="ti ti-user-plus me-1"></i>Available
                    </span>
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

    @else
      <div class="card-body text-center py-4">
        <i class="ti ti-eye-off fs-1 text-warning d-block mb-2"></i>
        <span class="text-warning fw-medium">Team not yet published</span>
      </div>
    @endif

  </div>
</div>

