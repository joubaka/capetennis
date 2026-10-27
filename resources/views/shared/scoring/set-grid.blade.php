@php($scoreGridPrefix = $scoreGridPrefix ?? 'score')
<link rel="stylesheet" href="{{ asset('css/set-score-grid.css') }}?v={{ filemtime(public_path('css/set-score-grid.css')) }}">
<div class="ct-set-score-grid" data-set-score-grid>
  <div class="ct-set-score-labels">
    <strong id="{{ $scoreGridPrefix }}-home-label" data-score-home-label>Player 1</strong>
    <strong id="{{ $scoreGridPrefix }}-away-label" data-score-away-label>Player 2</strong>
  </div>
  @for($setNumber = 1; $setNumber <= 5; $setNumber++)
    <div class="score-set-row ct-set-score-row" data-score-row data-set-row="{{ $setNumber }}">
      <input type="number" min="0" step="1" inputmode="numeric" class="form-control score-input" data-side="home" data-set="{{ $setNumber }}" aria-label="Set {{ $setNumber }} home score">
      <span>Set {{ $setNumber }}</span>
      <input type="number" min="0" step="1" inputmode="numeric" class="form-control score-input" data-side="away" data-set="{{ $setNumber }}" aria-label="Set {{ $setNumber }} away score">
    </div>
  @endfor
</div>
<script src="{{ asset('js/set-score-grid.js') }}?v={{ filemtime(public_path('js/set-score-grid.js')) }}" defer></script>
