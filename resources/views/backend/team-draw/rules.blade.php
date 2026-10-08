@extends('layouts.backend')
@section('title', 'Team scoring rules – '.$event->name)
@section('content')
<div class="container-xxl team-rules-workspace">
  <style>.team-rules-workspace .btn,.team-rules-workspace summary{min-height:44px;}</style>
  <h1 class="h4">{{ $event->name }}: team scoring rules</h1>
  <p>These defaults apply to new draws. Existing draws retain the rules and pairings used when they were generated.</p>
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <form id="team-event-rules-form" method="POST" action="{{ route('backend.team-rules.update', $event) }}">
    @csrf @method('PUT')
    <div class="card mb-3"><div class="card-body">
      <h2 class="h5">Points per rubber</h2>
      <p class="text-muted">Set wins required determines when a match is complete. Straight wins award the straight-win points; a deciding-set win and loss use their own points. Close losses use the greater of normal loss points and close-loss points.</p>
      <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Rubber</th><th>Sets to win</th><th>Straight win</th><th>Deciding win</th><th>Loss</th></tr></thead>
        <tbody>@foreach($rules['rubbers'] as $type => $values)<tr>
          <th>{{ ucwords(str_replace('_', ' ', $type)) }}</th>
          @foreach(['sets_to_win','straight_win','deciding_win','loss'] as $field)@php($value = $values[$field])<td>
            <input class="form-control" style="min-width:80px" type="number" min="{{ $field === 'sets_to_win' ? 1 : 0 }}" max="{{ $field === 'sets_to_win' ? 2 : 100 }}" step="{{ in_array($field, ['sets_to_win','close_game_margin']) ? 1 : '0.5' }}" required
              aria-label="{{ ucwords(str_replace('_',' ',$type.' '.$field)) }}" name="rules[rubbers][{{ $type }}][{{ $field }}]" value="{{ old('rules.rubbers.'.$type.'.'.$field, $value) }}">
          </td>@endforeach
        </tr>@endforeach</tbody>
      </table></div>
    </div></div>
    <details class="card mb-3" @if($errors->any()) open @endif><summary class="card-header">Advanced rubber points</summary><div class="card-body">
      <p class="text-muted">Deciding-set losses and close-loss bonuses use the saved values below. Close losses use the greater of normal loss points and close-loss points.</p>
      <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Rubber</th><th>Deciding loss</th><th>Close loss</th><th>Close game margin</th></tr></thead><tbody>
        @foreach($rules['rubbers'] as $type => $values)<tr><th>{{ ucwords(str_replace('_', ' ', $type)) }}</th>
          @foreach(['deciding_loss','close_loss','close_game_margin'] as $field)<td><input class="form-control" style="min-width:80px" type="number" min="0" max="100" step="{{ $field === 'close_game_margin' ? 1 : '0.5' }}" required aria-label="{{ ucwords(str_replace('_',' ',$type.' '.$field)) }}" name="rules[rubbers][{{ $type }}][{{ $field }}]" value="{{ old('rules.rubbers.'.$type.'.'.$field, $values[$field]) }}"></td>@endforeach
        </tr>@endforeach
      </tbody></table></div>
    </div></details>
    <div class="card mb-3"><div class="card-body">
      <h2 class="h5">Team ties and ranking</h2>
      <p class="text-muted">A tie winner is the team winning more rubbers. A level total is a drawn tie. Optional tie points are added to rubber points.</p>
      <div class="row g-3">@foreach(['tie_win','tie_draw','tie_loss'] as $field)
        <div class="col-sm-4"><label class="form-label" for="{{ $field }}">{{ ucwords(str_replace('_',' ',$field)) }} points</label><input id="{{ $field }}" class="form-control" type="number" min="0" max="100" step="0.5" required name="rules[{{ $field }}]" value="{{ old('rules.'.$field,$rules[$field]) }}"></div>
      @endforeach</div>
      <p class="mt-3">Select ranking criteria in priority order. Teams still tied share the same rank.</p>
      <div class="row g-3">@foreach(range(0,4) as $index)
        <div class="col-sm-6 col-lg"><label class="form-label" for="order{{ $index }}">Priority {{ $index+1 }}</label><select class="form-select" id="order{{ $index }}" name="rules[standings_order][]">
          <option value="">Unused</option>@foreach($orderOptions as $option)<option value="{{ $option }}" @selected(old('rules.standings_order.'.$index,$rules['standings_order'][$index] ?? '') === $option)>{{ ucwords(str_replace('_',' ',$option)) }}</option>@endforeach
        </select></div>
      @endforeach</div>
      <button class="btn btn-primary mt-3">Save event rules</button>
    </div></div>
  </form>
  <details class="card"><summary class="card-header">Create a pairing format</summary><div class="card-body">
    <p>Start with a six-player or eight-player draft, or build your own. Select ranked roster positions for each side. Saving creates a new format; existing draws retain their pairings.</p>
    <div class="row g-2 mb-3 align-items-end"><div class="col-sm-8"><label class="form-label" for="format-preset">Starting preset</label><select id="format-preset" class="form-select">
      @foreach($presets as $key => $preset)<option value="{{ $key }}">{{ $preset['name'] }}</option>@endforeach
    </select></div><div class="col-sm-4"><button type="button" class="btn btn-outline-primary" id="apply-preset">Load preset into draft</button></div></div>
    <p class="text-muted">Presets are editable starting points, with same-rank singles, crossed reverse singles and adjacent doubles. Check your event's competition rules before saving.</p>
    <form id="team-format-form" action="{{ route('team-draw.formats.store',$event) }}">
      <div class="row g-3"><div class="col-sm-6"><label class="form-label" for="format-name">Format name</label><input id="format-name" name="name" class="form-control" required></div>
      <div class="col-sm-3"><label class="form-label" for="roster-min">Minimum roster</label><input id="roster-min" name="min_roster_size" type="number" min="1" max="12" value="6" class="form-control" required></div>
      <div class="col-sm-3"><label class="form-label" for="roster-max">Maximum roster</label><input id="roster-max" name="max_roster_size" type="number" min="1" max="12" value="6" class="form-control" required></div></div>
      <label class="form-check mt-3"><input class="form-check-input" type="checkbox" name="allow_player_reuse" checked> Players may play more than one rubber</label>
      <label class="form-check"><input class="form-check-input" type="checkbox" name="is_default"> Use as this event's default format</label>
      <div id="format-rubbers" class="mt-3" aria-label="Rubber pairing draft"></div>
      <p id="format-summary" class="text-muted" aria-live="polite"></p>
      <div id="format-warnings" class="alert alert-warning d-none" role="status"></div>
      <button class="btn btn-outline-secondary" type="button" id="add-rubber">Add rubber</button>
      <button class="btn btn-primary" type="submit">Create format</button>
      <p id="format-message" class="mt-3" role="status"></p>
    </form>
  </div></details>
</div>
@endsection
@section('page-script')
<script>document.addEventListener('invalid', event => { let detail = event.target.closest('details'); while (detail) { detail.open = true; detail = detail.parentElement.closest('details'); } }, true);</script>
<script type="application/json" id="team-format-presets">{!! json_encode($presets, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('js/team-event-rules.js') }}"></script>
@endsection
