@php($profileFieldName = $profileFieldName ?? 'player_id')
@if($team->noProfile)
  <fieldset class="roster-replacement-choice mb-3">
    <legend class="form-label">Player details</legend>
    <label><input type="radio" name="player_source" value="profile" checked data-replacement-type> Existing player profile</label>
    <label><input type="radio" name="player_source" value="name" data-replacement-type> Player without a profile</label>
  </fieldset>
@endif
<fieldset data-replacement-fields="profile">
  <legend class="visually-hidden">Existing player profile</legend>
  <label class="form-label w-100">Player<select class="form-select" name="{{ $profileFieldName }}" required data-roster-replacement-select="{{ route('backend.team.availablePlayers', ['team_id' => $team->id, 'event_id' => $event->id]) }}"><option value=""></option></select></label>
</fieldset>
@if($team->noProfile)
  <fieldset data-replacement-fields="name" hidden disabled>
    <legend class="visually-hidden">Player without a profile</legend>
    <p class="small text-muted">Enter their name now. You can link an existing profile later.</p>
    <div class="row g-2">
      <div class="col-sm-6"><label class="form-label w-100">First name<input class="form-control" name="name" required maxlength="100"></label></div>
      <div class="col-sm-6"><label class="form-label w-100">Surname<input class="form-control" name="surname" required maxlength="100"></label></div>
    </div>
  </fieldset>
@endif
