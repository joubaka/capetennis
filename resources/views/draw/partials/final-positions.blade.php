@php
    $finalPositions = isset($placements)
        ? collect($placements)
        : app(\App\Services\Draw\DrawFinalPlacementService::class)->forDraw($draw);
@endphp

@if($finalPositions->isNotEmpty())
<section class="ct-final-positions" aria-labelledby="ct-final-positions-title-{{ $draw->id }}">
    <style>
      .ct-final-positions{margin:24px 16px 8px;padding:20px;border:1px solid #dce5ea;border-radius:14px;background:#fff;color:#172033;font-family:'Noto Sans JP',Arial,sans-serif}
      .ct-final-positions h2{margin:0;font-size:18px;font-weight:800}.ct-final-positions p{margin:4px 0 16px;color:#64748b;font-size:12px}
      .ct-final-position-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px}
      .ct-final-position{display:flex;align-items:center;gap:10px;min-height:44px;padding:7px 10px;border:1px solid #b9d8ee;border-radius:9px;background:#eaf5fc}
      .ct-final-position strong{display:grid;place-items:center;flex:0 0 30px;height:30px;border-radius:50%;background:#fff;color:#176448;font-size:13px}
      .ct-final-position span{font-size:13px;font-weight:700;color:#155d91}.ct-final-position.is-awaiting,.ct-final-position.is-bye{border-color:#e2e8f0;background:#f8fafc}
      .ct-final-position.is-awaiting span,.ct-final-position.is-bye span{color:#64748b;font-weight:600}.ct-final-position.is-bye span{font-style:italic}
      @media print{.ct-final-positions{break-inside:avoid;margin:18px 0 0}.ct-final-position-grid{grid-template-columns:repeat(4,1fr)}}
    </style>
    <h2 id="ct-final-positions-title-{{ $draw->id }}">Final positions</h2>
    <p>Positions update automatically from completed finals and placement matches.</p>
    <div class="ct-final-position-grid">
      @foreach($finalPositions as $placement)
        <div class="ct-final-position is-{{ $placement['status'] }}">
          <strong>{{ $placement['position'] }}</strong>
          <span>{{ $placement['name'] }}</span>
        </div>
      @endforeach
    </div>
</section>
@endif
