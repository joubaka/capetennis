@can('team-draw.createFormat', $draw->event)
@php
  $decisionService = app(\App\Services\TeamCompetitionDecisionService::class);
  $phase = $draw->team_draw_selection['competition']['phase'] ?? null;
  $decisionToken = $decisionService->token($draw);
@endphp
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
@if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
<style>.organiser-decision input,.organiser-decision select,.organiser-decision button,.organiser-decision textarea{min-height:44px}.organiser-decision{overflow-wrap:anywhere}.organiser-decision .decision-row{display:grid;grid-template-columns:minmax(0,1fr) 5rem;gap:.75rem;align-items:center;padding:.75rem 0;border-bottom:1px solid #ddd}.organiser-decision .form-control,.organiser-decision .form-select{max-width:100%}</style>
@if($phase === 'round_robin')
@php($savedDecision = $decisionService->standingsDecision($draw))
<details class="card card-body my-3 organiser-decision" @if($errors->any()) open @endif>
  <summary style="min-height:44px">Organiser: decide final standings</summary>
  <p class="mt-3">Set the final order for tied standings, a scoring mistake or a disqualification. Recorded scores and points remain unchanged. Reasons and decision history are private to organisers.</p>
  @if($savedDecision)
    <div class="alert {{ $savedDecision['valid'] ? 'alert-info' : 'alert-warning' }}">{{ $savedDecision['valid'] ? 'An organiser final order is active.' : 'Results or teams changed. Review and save a new decision before progressing.' }}</div>
    <p class="small">Last decision: {{ $savedDecision['decided_at'] }} · {{ $savedDecision['reason'] }}</p>
  @endif
  @if(!empty($draw->team_draw_selection['competition']['playoff_draw_id']))
    <p>Playoffs already exist. These qualified positions are frozen to protect their pairings and results.</p>
  @else
  @php($decisionRows = app(\App\Services\TeamStandingsService::class)->forDraw($draw))
  <form method="POST" action="{{ route('team-draw.decision.standings', $draw) }}">@csrf
    <input type="hidden" name="token" value="{{ $decisionToken }}"><input type="hidden" name="request_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
    <p>Choose each side once, in final position order. Excluded sides cannot qualify.</p>
    @foreach($decisionRows as $index => $row)
      <div class="mb-3"><label class="form-label" for="decision-rank-{{ $draw->id }}-{{ $index }}">Position {{ $index + 1 }}</label>
        <select class="form-select" id="decision-rank-{{ $draw->id }}-{{ $index }}" name="order[]" required>
          @foreach($decisionRows as $option)<option value="{{ $option['team_id'] }}" @selected((int) old('order.'.$index, $row['team_id']) === (int) $option['team_id'])>{{ $option['name'] }}</option>@endforeach
        </select>
      </div>
    @endforeach
    <fieldset class="mb-3"><legend class="h6">Exclude from playoff qualification</legend>
      @foreach($decisionRows as $row)<label class="form-check d-flex gap-2 align-items-center mb-2"><input class="form-check-input m-0" type="checkbox" name="excluded[]" value="{{ $row['team_id'] }}" @checked(in_array($row['team_id'], old('excluded', $savedDecision['excluded'] ?? [])))><span>{{ $row['name'] }}</span></label>@endforeach
    </fieldset>
    <label class="form-label" for="standings-reason-{{ $draw->id }}">Private reason / evidence</label><textarea class="form-control mb-3" id="standings-reason-{{ $draw->id }}" name="reason" minlength="5" maxlength="2000" required>{{ old('reason') }}</textarea>
    <label class="form-check d-flex gap-2 align-items-center mb-3"><input class="form-check-input m-0" type="checkbox" name="accept_incomplete" value="1"><span>I authorise progression even if round-robin results are incomplete.</span></label>
    <button class="btn btn-primary" @disabled($draw->locked)>Save final standings decision</button>
  </form>
  @endif
</details>
@elseif($phase === 'playoff')
@foreach($draw->teamTies()->with('rubbers.teamResults', 'rubbers.draw')->orderBy('tie_nr')->get() as $decisionTie)
@php($restoreDecision = (int) old('decision_tie_id') === (int) $decisionTie->id)
@php($rawOutcome = app(\App\Services\TeamStandingsService::class)->tieOutcome($decisionTie, false))
@if($rawOutcome['complete'] && $rawOutcome['home'] === $rawOutcome['away'])
<details class="card card-body my-3 organiser-decision" @if($errors->any()) open @endif>
  <summary style="min-height:44px">Resolve {{ $decisionTie->tie_nr === 1 ? '1st / 2nd' : '3rd / 4th' }} playoff: {{ $rawOutcome['home'] }}–{{ $rawOutcome['away'] }}</summary>
  <p class="mt-3">Record the agreed deciding procedure. The original match results and round-robin standings remain unchanged.</p>
  @php($existingPlayoffDecision = $draw->team_draw_selection['decisions']['playoff_'.$decisionTie->id] ?? null)
  @if($existingPlayoffDecision)<p class="small">Previous organiser reason: {{ $existingPlayoffDecision['reason'] }}</p>@endif
  <form method="POST" action="{{ route('team-draw.decision.playoff', [$draw, $decisionTie]) }}">@csrf
    <input type="hidden" name="token" value="{{ $decisionToken }}"><input type="hidden" name="request_id" value="{{ (string) \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="decision_tie_id" value="{{ $decisionTie->id }}">
    <label class="form-label" for="method-{{ $decisionTie->id }}">Agreed deciding method</label><select class="form-select mb-3" name="method" id="method-{{ $decisionTie->id }}"><option value="sets_games" @selected($restoreDecision && old('method') === 'sets_games')>Sets won, then games won</option><option value="mixed_tiebreak" @selected($restoreDecision && old('method') === 'mixed_tiebreak')>Deciding mixed-doubles tie-break to 10 (win by two)</option><option value="ruling" @selected($restoreDecision && old('method') === 'ruling')>Organiser ruling / disqualification</option></select>
    <label class="form-label" for="winner-{{ $decisionTie->id }}">Winning side</label><select class="form-select mb-3" name="winner_team_id" id="winner-{{ $decisionTie->id }}"><option value="{{ $decisionTie->home_team_id }}" @selected($restoreDecision && (int) old('winner_team_id') === (int) $decisionTie->home_team_id)>{{ $decisionTie->home_side_name }}</option><option value="{{ $decisionTie->away_team_id }}" @selected($restoreDecision && (int) old('winner_team_id') === (int) $decisionTie->away_team_id)>{{ $decisionTie->away_side_name }}</option></select>
    <details class="mb-3"><summary style="min-height:44px">Deciding mixed-doubles score and players</summary><p>Complete these only when using a deciding mixed-doubles tie-break.</p><div class="row g-3"><div class="col-6"><label class="form-label" for="home-{{ $decisionTie->id }}">Home score</label><input class="form-control" type="number" min="0" max="999" name="home_score" value="{{ $restoreDecision ? old('home_score') : '' }}" id="home-{{ $decisionTie->id }}"></div><div class="col-6"><label class="form-label" for="away-{{ $decisionTie->id }}">Away score</label><input class="form-control" type="number" min="0" max="999" name="away_score" value="{{ $restoreDecision ? old('away_score') : '' }}" id="away-{{ $decisionTie->id }}"></div></div><label class="form-label mt-3" for="players-{{ $decisionTie->id }}">Players on each side</label><textarea class="form-control" name="participants" maxlength="500" id="players-{{ $decisionTie->id }}">{{ $restoreDecision ? old('participants') : '' }}</textarea></details>
    <label class="form-label" for="reason-{{ $decisionTie->id }}">Private reason / evidence</label><textarea class="form-control mb-3" name="reason" id="reason-{{ $decisionTie->id }}" minlength="5" maxlength="2000" required>{{ $restoreDecision ? old('reason') : '' }}</textarea>
    <button class="btn btn-primary" @disabled($draw->locked)>Confirm deciding result</button>
  </form>
</details>
@endif
@endforeach
@endif
@endcan
