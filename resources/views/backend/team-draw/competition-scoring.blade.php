@extends('layouts.backend')
@section('title', 'Confirm competition scoring')
@section('content')
<div class="container-xxl">
<h1 class="h4">{{ $draw->drawName }}: confirm scoring</h1>
<p>These suggested values are not agreed rules yet. Review match lengths, points and ranking criteria, then confirm before play. Once ties are published or results recorded, scoring is fixed.</p>
@if($errors->any())<div class="alert alert-danger"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <form id="team-event-rules-form" method="POST" action="{{ route('team-draw.competition.scoring', $draw) }}">
    @foreach($rules['rubbers'] as $type => $values)
      @unless(in_array($type, $availableRubberTypes, true))
        @foreach($values as $field => $value)<input type="hidden" name="rules[rubbers][{{ $type }}][{{ $field }}]" value="{{ $value }}">@endforeach
      @endunless
    @endforeach
    @csrf
    <div class="card mb-3"><div class="card-body">
      <h2 class="h5">Points per rubber</h2>
      <p class="text-muted">Set wins required determines when a match is complete. Straight wins award the straight-win points; a deciding-set win and loss use their own points. Close losses use the greater of normal loss points and close-loss points.</p>
      <p class="text-muted">Only rubber types included in this phase can be configured.</p>
      <div class="table-responsive"><table class="table align-middle">
        <thead><tr><th>Rubber</th><th>Sets to win</th><th>Straight win</th><th>Deciding win</th><th>Loss</th></tr></thead>
        <tbody>@foreach($rules['rubbers'] as $type => $values)
          @php($available = in_array($type, $availableRubberTypes, true))
          <tr data-rubber-type="{{ $type }}" @class(['text-muted table-light' => !$available])>
          <th>{{ ucwords(str_replace('_', ' ', $type)) }}@unless($available)<small class="d-block fw-normal">Not used in this phase</small>@endunless</th>
          @foreach(['sets_to_win','straight_win','deciding_win','loss'] as $field)@php($value = $values[$field])<td>
            <input class="form-control" style="min-width:80px" type="number" min="{{ $field === 'sets_to_win' ? 1 : 0 }}" max="{{ $field === 'sets_to_win' ? 2 : 100 }}" step="{{ in_array($field, ['sets_to_win','close_game_margin']) ? 1 : '0.5' }}" required
              aria-label="{{ ucwords(str_replace('_',' ',$type.' '.$field)) }}" data-rule-name="rules[rubbers][{{ $type }}][{{ $field }}]" @if($available) name="rules[rubbers][{{ $type }}][{{ $field }}]" @else disabled @endif value="{{ $available ? old('rules.rubbers.'.$type.'.'.$field, $value) : $value }}">
          </td>@endforeach
        </tr>@endforeach</tbody>
      </table></div>
    </div></div>
    <details class="card mb-3" @if($errors->any()) open @endif><summary class="card-header">Advanced rubber points</summary><div class="card-body">
      <p class="text-muted">Deciding-set losses and close-loss bonuses use the saved values below. Close losses use the greater of normal loss points and close-loss points.</p>
      <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Rubber</th><th>Deciding loss</th><th>Close loss</th><th>Close game margin</th></tr></thead><tbody>
        @foreach($rules['rubbers'] as $type => $values)
          @php($available = in_array($type, $availableRubberTypes, true))
          <tr data-rubber-type="{{ $type }}" @class(['text-muted table-light' => !$available])><th>{{ ucwords(str_replace('_', ' ', $type)) }}@unless($available)<small class="d-block fw-normal">Not used in this phase</small>@endunless</th>
          @foreach(['deciding_loss','close_loss','close_game_margin'] as $field)<td><input class="form-control" style="min-width:80px" type="number" min="0" max="100" step="{{ $field === 'close_game_margin' ? 1 : '0.5' }}" required aria-label="{{ ucwords(str_replace('_',' ',$type.' '.$field)) }}" data-rule-name="rules[rubbers][{{ $type }}][{{ $field }}]" @if($available) name="rules[rubbers][{{ $type }}][{{ $field }}]" @else disabled @endif value="{{ $available ? old('rules.rubbers.'.$type.'.'.$field, $values[$field]) : $values[$field] }}"></td>@endforeach
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
      <button class="btn btn-primary mt-3">Confirm scoring for this phase</button>
    </div></div>
  </form>
</div>
@endsection
@section('page-script')
<script src="{{ asset('js/team-event-rules.js') }}"></script>
@endsection
