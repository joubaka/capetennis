{{-- resources/views/frontend/event/partials/profile-team.blade.php --}}

<div class="col-12 col-md-6 team-roster-column">
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
          @forelse($team->teamPlayers->filter(fn($member) => !$member->selected_team_reserve) as $slot)
            @php
              $dummyId    = 0;
              $isDummy    = (int) $slot->player_id === $dummyId;
              $player     = $isDummy ? null : $slot->player;

              $paid       = (int) ($slot->pay_status ?? 0) === 1;
              $canOrder   = $region->usesOnlineClothingOrders() && (int) ($region->clothing_order ?? 0) === 1;
              $signupOpen = $event->published && $event->registrationIsOpen();

              $playerName = $player
                ? trim(($player->name ?? '').' '.($player->surname ?? ''))
                : '— Empty slot —';
              $isInvitationTarget = $player
                && request()->integer('team') === (int) $team->id
                && request()->integer('player') === (int) $player->id;
              $playerClothingOrders = $player
                ? ($myPaidClothingOrdersByPlayer ?? collect())->get($team->id.'-'.$player->id, collect())
                : collect();
              $clothingQuantity = $playerClothingOrders->sum(
                fn ($order) => $order->items->sum(fn ($item) => max(1, (int) ($item->qty ?? 1)))
              );
              $clothingDetailsId = 'clothing-orders-'.$event->id.'-'.$team->id.'-'.($player?->id ?? 0);
              $myPaidTeamOrder = $player
                ? ($myPaidTeamOrdersByPlayer ?? collect())->get($team->id.'-'.$player->id)
                : null;
            @endphp

            <li id="team-registration-{{ $team->id }}-{{ $player?->id ?? 0 }}"
                class="list-group-item {{ $isDummy ? 'bg-light' : '' }} {{ $isInvitationTarget ? 'border border-success rounded bg-success-subtle' : '' }}"
                @if($isInvitationTarget) data-team-registration-target="true" @endif>
              <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 team-player-row">

                {{-- LEFT: RANK + NAME --}}
                <div class="d-flex align-items-center team-player-identity">
                  <span class="badge bg-light text-muted border rounded-circle me-2" style="width: 24px; height: 24px; line-height: 16px; font-size: 0.75rem;">
                    {{ $slot->rank }}
                  </span>
                  <span class="{{ $isDummy ? 'text-muted fst-italic' : 'fw-medium' }}">
                    {{ $playerName }}@if($player)<x-player-rating :player-id="$player->id" :context="$team->category" />@endif
                  </span>
                </div>

                {{-- RIGHT: STATUS / ACTIONS --}}
                <div class="d-flex align-items-center justify-content-sm-end flex-wrap gap-1 team-player-actions">

                  @if($player)

                    {{-- PAID --}}
                    @if($paid)
                      <span class="badge bg-success-subtle text-success px-2 py-1">
                        <i class="ti ti-circle-check me-1"></i>Registered
                      </span>

                      {{-- WITHDRAW BUTTON --}}
                      @if(($canWithdraw ?? false) && $myPaidTeamOrder)
                        <button type="button"
                                class="btn btn-sm btn-outline-danger withDrawPlayer"
                                title="Cancel registration and withdraw from event"
                                aria-label="Withdraw {{ $playerName }} from this team"
                                data-id="{{ $slot->id }}"
                                data-team="{{ $team->id }}"
                                data-player="{{ $player->id }}"
                                data-event="{{ $event->id }}"
                                data-url="{{ route('team.player.withdraw', [$team->id, $player->id, $event->id]) }}">
                          <i class="ti ti-x me-1" aria-hidden="true"></i>Withdraw
                        </button>
                      @endif

                    {{-- UNPAID + SIGNUPS OPEN --}}
                    @elseif($signupOpen)
                      <a href="{{ route('team.payment.payfast', [$team->id, $player->id, $event->id]) }}"
                         class="btn btn-sm btn-warning team-registration-button">
                        <i class="ti ti-credit-card me-1"></i>Register
                      </a>

                    {{-- UNPAID + SIGNUPS CLOSED --}}
                    @else
                      <span class="badge bg-secondary-subtle text-secondary">
                        <i class="ti ti-lock me-1"></i>Closed
                      </span>
                    @endif

                  @else
                    {{-- EMPTY SLOT --}}
                    <span class="badge bg-light text-muted border">
                      <i class="ti ti-user-plus me-1"></i>Available
                    </span>
                  @endif

                </div>
              </div>

              @include('frontend.event.partials.player-clothing-controls')
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

@include('frontend.event.partials.team-roster-styles')
