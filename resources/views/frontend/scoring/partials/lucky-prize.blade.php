@php($prizeDraws = collect($luckyPrizeDraws)->filter(fn ($candidate) => auth()->user()->can('awardLuckyPrize', $candidate))->values())
@if($prizeDraws->isNotEmpty())
<div class="my-3">
  <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#lucky-prize-modal">🎁 Lucky prize wheel</button>
</div>
<div class="modal fade" id="lucky-prize-modal" tabindex="-1" aria-labelledby="lucky-prize-title" data-lucky-prize>
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title fs-5" id="lucky-prize-title">Lucky prize wheel</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <p>Give every eligible player an equal chance. First-place winners and previous lucky-prize recipients are excluded. Spinning records the prize winner for this draw.</p>
      <label class="form-label" for="lucky-prize-draw">Draw</label>
      <select class="form-select mb-3" id="lucky-prize-draw" data-prize-draw>
        @foreach($prizeDraws as $prizeDraw)
        <option value="{{ $prizeDraw->id }}" data-pool-url="{{ route('frontend.scoring.lucky-prize.pool', [$prizeDraw->event_id, $prizeDraw]) }}" data-spin-url="{{ route('frontend.scoring.lucky-prize.spin', [$prizeDraw->event_id, $prizeDraw]) }}">{{ $prizeDraw->drawName }}</option>
        @endforeach
      </select>
      <p role="status" aria-live="polite" class="fw-semibold" data-prize-status>Open the wheel to review eligible players.</p>
      <div class="lucky-prize-wheel-wrap"><span class="lucky-prize-pointer" aria-hidden="true">▼</span><svg viewBox="0 0 400 400" class="lucky-prize-wheel" role="img" aria-label="Lucky prize names wheel" data-prize-wheel></svg></div>
      <div class="d-flex flex-wrap gap-2 my-3"><button type="button" class="btn btn-primary" data-prize-spin disabled>Spin for a lucky winner</button><button type="button" class="btn btn-outline-secondary" data-prize-refresh>Refresh players</button></div>
      <details open><summary class="py-2">Eligible players (<span data-prize-count>0</span>)</summary><ul class="lucky-prize-names" data-prize-players></ul></details>
      <details><summary class="py-2">Excluded prize winners</summary><p class="mb-1 fw-semibold">First place</p><ul data-prize-champions></ul><p class="mb-1 fw-semibold">Previous lucky prizes</p><ul data-prize-previous></ul></details>
    </div>
  </div></div>
</div>
<link rel="stylesheet" href="{{ asset('css/lucky-prize-wheel.css') }}?v={{ filemtime(public_path('css/lucky-prize-wheel.css')) }}">
<script defer src="{{ asset('js/lucky-prize-wheel.js') }}?v={{ filemtime(public_path('js/lucky-prize-wheel.js')) }}"></script>
@endif
