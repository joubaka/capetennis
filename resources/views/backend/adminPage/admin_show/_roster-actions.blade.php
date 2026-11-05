@php($compactRoster = $compactRoster ?? false)
@php($identity = $team->noProfile ? $slot->noProfile : $slot)
@php($replacementPaid = (int) $slot->pay_status === 1 || (int) ($identity?->pay_status ?? 0) === 1)
@if($identity && ($team->noProfile || (int) $identity->player_id > 0))
<div class="team-roster-actions">
  @if($compactRoster)
    @include('backend.adminPage.admin_show._roster-order-buttons')
    @component('backend.adminPage.admin_show._roster-player-modal', ['team' => $team, 'rosterSlot' => $slot, 'identity' => $identity, 'name' => $name, 'event' => $event])
  @endif
  @if(!$replacementPaid || auth()->user()->hasRole('super-user'))
    <details class="roster-replacement">
      @if($compactRoster)
        <summary class="roster-action-choice">
          <span>
            <strong class="d-block">Replace this player</strong>
            <span class="small text-body">
              @if($replacementPaid)
                Pick a player for the same spot and choose what happens to the original payment before saving.
              @else
                Pick a different player to take the same spot in the team.
              @endif
            </span>
          </span>
          <i class="ti ti-chevron-down" aria-hidden="true"></i>
        </summary>
      @else
        <summary class="btn btn-sm btn-outline-primary">Change player</summary>
      @endif
    <form method="POST" action="{{ route('backend.team.changePlayer') }}" class="mt-2 roster-replacement-form" data-team-roster-change>
        @csrf
        <input type="hidden" name="team_id" value="{{ $team->id }}"><input type="hidden" name="event_id" value="{{ $event->id }}">
        <input type="hidden" name="slot_id" value="{{ $identity->id }}"><input type="hidden" name="expected_rank" value="{{ $identity->rank }}">
        <input type="hidden" name="expected_player_id" value="{{ $team->noProfile ? (int) $identity->player_profile : $identity->player_id }}">
        @if($team->noProfile && !$replacementPaid)
          <fieldset class="roster-replacement-choice mb-3">
            <legend class="form-label">Replacement type</legend>
            <label><input type="radio" name="replacement_type" value="profile" checked data-replacement-type> Existing player profile</label>
            <label><input type="radio" name="replacement_type" value="name" data-replacement-type> Player without a profile</label>
          </fieldset>
        @endif
        <fieldset data-replacement-fields="profile">
          <legend class="visually-hidden">Existing player profile</legend>
          <label class="form-label w-100">Replacement player<select class="form-select" name="player_id" required data-roster-replacement-select="{{ route('backend.team.availablePlayers', ['team_id' => $team->id, 'event_id' => $event->id]) }}"><option value=""></option></select></label>
        </fieldset>
        @if($team->noProfile && !$replacementPaid)
          <fieldset data-replacement-fields="name" hidden disabled>
            <legend class="visually-hidden">Player without a profile</legend>
            <p class="small text-muted">Enter the replacement player's name.</p>
            <label class="form-label w-100">First name<input class="form-control" name="name" required maxlength="100"></label>
            <label class="form-label w-100">Surname<input class="form-control" name="surname" required maxlength="100"></label>
          </fieldset>
        @endif
        @if($replacementPaid)
          <label class="form-label w-100">Original player's payment<select class="form-select" name="refund_action" required>
            <option value="">Choose payment option</option>
            <option value="refund">Refund original payer's wallet</option>
            @if(!\App\Models\TeamSelectionInvitation::where('team_id', $team->id)->whereHas('selectionImport', fn ($query) => $query->where('event_id', $event->id)->whereIn('status', ['draft', 'sent']))->exists())
              <option value="transfer">Move full payment to replacement</option>
              <option value="replacement">Use refund toward replacement player</option>
            @endif
            <option value="waive">Keep the original payment (no refund)</option>
          </select></label>
          @if(\App\Models\SiteSetting::get('withdrawal_allowed', '1') !== '1')
            <label class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="withdrawal_override" value="1">
              <span class="form-check-label">Override withdrawal closure for this replacement</span>
            </label>
            <p class="small text-muted">Withdrawals are currently disabled. Confirm this exception and explain why in Reason. It applies only to this replacement and does not reopen withdrawals. Any refund deadline override must be confirmed separately.</p>
          @endif
          @if(now()->gt($event->withdrawalCloseAt()))
            <label class="form-check mb-2">
              <input class="form-check-input" type="checkbox" name="deadline_override" value="1">
              <span class="form-check-label">Override refund deadline for this replacement</span>
            </label>
            <p class="small text-muted">To return or move payment after the deadline, explicitly confirm this override and explain why in Reason. Leave it unchecked when waiving the refund. The normal refund fee still applies to wallet refunds and refund-funded replacements; moving the full payment waives that fee.</p>
          @endif
          <label class="form-label w-100">Reason<textarea class="form-control" name="reason" required maxlength="1000"></textarea></label>
          <details class="small mb-3">
            <summary>How payment options work</summary>
            <ul class="mb-0 mt-2">
              <li>Moving the full payment to the replacement waives the withdrawal fee.</li>
              <li>Wallet refunds and refunds used toward a replacement deduct the withdrawal fee.</li>
              <li>The replacement stays unpaid until their entry and regional fees are covered. The original payer has five minutes to pay any shortfall before they need to restart checkout.</li>
              <li>Unused or released funds stay in the original payer’s wallet. The original payment record stays with the payer and the original player.</li>
            </ul>
          </details>
        @endif
        <button class="btn btn-primary">Save replacement</button>
        <p class="small text-muted mt-1">The current player stays in place until you save.</p>
      </form>
    </details>
  @endif

  @unless($compactRoster)
    @include('backend.adminPage.admin_show._roster-order-buttons')
  @endunless
  @if(!$compactRoster || !$replacementPaid)
    <form method="POST" action="{{ route('backend.team.removePlayer') }}" data-team-roster-change>
      @csrf
      <input type="hidden" name="team_id" value="{{ $team->id }}"><input type="hidden" name="event_id" value="{{ $event->id }}">
      <input type="hidden" name="remove" value="1">
      <input type="hidden" name="slot_id" value="{{ $identity->id }}"><input type="hidden" name="expected_rank" value="{{ $identity->rank }}">
      <input type="hidden" name="expected_player_id" value="{{ $team->noProfile ? (int) $identity->player_profile : $identity->player_id }}">
        @if($compactRoster)
          <button type="button" class="roster-action-choice" data-team-roster-remove-toggle aria-expanded="false">
            <span>
              <strong class="d-block">Remove this unpaid player</strong>
              <span class="small text-body">Leave the spot empty. You’ll be asked to confirm.</span>
            </span>
            <i class="ti ti-chevron-down" aria-hidden="true"></i>
          </button>
        @else
          <button type="button" class="btn btn-sm btn-outline-danger" data-team-roster-remove-toggle aria-expanded="false">Remove from roster</button>
        @endif
        <span data-team-roster-remove-confirm hidden role="group" aria-label="Confirm player removal">
          @if($compactRoster)
            <span class="small">Remove this player and leave the spot empty?</span>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-team-roster-remove-cancel>Cancel</button>
            <button class="btn btn-sm btn-danger">Remove player</button>
          @else
            <span class="small">Remove this player?</span>
            <button class="btn btn-sm btn-danger">Confirm remove</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-team-roster-remove-cancel>Cancel</button>
          @endif
        </span>
    </form>
  @endif
  @if($compactRoster && !($rosterOnly ?? false) && $slot->exists && (int) $slot->player_id > 0 && (int) $slot->pay_status === 1)
    @role('super-user')
      @include('backend.adminPage.admin_show._roster-withdrawal-action', ['slot' => $slot])
    @endrole
  @endif
  @if($compactRoster && $replacementPaid && !auth()->user()->hasRole('super-user'))
    <p class="alert alert-secondary">This player has paid. Contact a Super Admin to replace or withdraw them.</p>
  @endif
  @if(!$compactRoster || !$replacementPaid)
    @include('backend.adminPage.admin_show._roster-name-form')
  @endif
  @if($compactRoster)
    @endcomponent
  @endif
</div>
@endif
