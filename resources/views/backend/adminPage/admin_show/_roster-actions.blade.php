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
      <summary class="btn btn-sm btn-outline-primary">Change player</summary>
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
            <option value="">Choose refund or waiver</option>
            <option value="refund">Refund original payer's wallet</option>
            <option value="waive">Waive refund — no money returned</option>
          </select></label>
          <label class="form-label w-100">Reason<textarea class="form-control" name="reason" required maxlength="1000"></textarea></label>
          <p class="small text-muted">Refunds follow the current fee and withdrawal deadline policy. The replacement starts unpaid. Original payment history stays with the original payer and player.</p>
        @endif
        <button class="btn btn-primary">Save player</button>
        <p class="small text-muted mt-1">The current player stays in place until you save.</p>
      </form>
    </details>
  @endif

  @unless($compactRoster)
    @include('backend.adminPage.admin_show._roster-order-buttons')
  @endunless
    <form method="POST" action="{{ route('backend.team.removePlayer') }}" data-team-roster-change>
      @csrf
      <input type="hidden" name="team_id" value="{{ $team->id }}"><input type="hidden" name="event_id" value="{{ $event->id }}">
      <input type="hidden" name="remove" value="1">
      <input type="hidden" name="slot_id" value="{{ $identity->id }}"><input type="hidden" name="expected_rank" value="{{ $identity->rank }}">
      <input type="hidden" name="expected_player_id" value="{{ $team->noProfile ? (int) $identity->player_profile : $identity->player_id }}">
        <button type="button" class="btn btn-sm btn-outline-danger" data-team-roster-remove-toggle aria-expanded="false">Remove</button>
        <span data-team-roster-remove-confirm hidden role="group" aria-label="Confirm player removal">
          <span class="small">Remove this player?</span>
          <button class="btn btn-sm btn-danger">Confirm remove</button>
          <button type="button" class="btn btn-sm btn-outline-secondary" data-team-roster-remove-cancel>Cancel</button>
        </span>
    </form>
  @if($team->noProfile && (int) $identity->player_profile === 0)
    <details>
      <summary class="btn btn-sm btn-outline-secondary">Change name</summary>
    <form method="POST" action="{{ route('backend.team.editPlayerName') }}" class="mt-2" data-team-roster-change>
        @csrf
        <input type="hidden" name="team_id" value="{{ $team->id }}"><input type="hidden" name="event_id" value="{{ $event->id }}">
        <input type="hidden" name="slot_id" value="{{ $identity->id }}"><input type="hidden" name="expected_rank" value="{{ $identity->rank }}">
        <input type="hidden" name="expected_player_id" value="0">
        <input type="hidden" name="expected_name" value="{{ $identity->name }}"><input type="hidden" name="expected_surname" value="{{ $identity->surname }}">
        <label class="form-label w-100">First name<input class="form-control" name="name" value="{{ $identity->name }}" required maxlength="100"></label>
        <label class="form-label w-100">Surname<input class="form-control" name="surname" value="{{ $identity->surname }}" required maxlength="100"></label>
        <button class="btn btn-primary">Save name</button>
      </form>
    </details>
  @endif
  @if($compactRoster)
    @if(!($rosterOnly ?? false) && $slot->exists && (int) $slot->player_id > 0 && (int) $slot->pay_status === 1)
      @role('super-user')
        <a class="btn btn-sm btn-outline-warning mt-2" href="{{ route('backend.team.withdrawal.show', [$event, $team, $slot]) }}">Review withdrawal / refund</a>
      @endrole
    @endif
    @endcomponent
  @endif
</div>
@endif
