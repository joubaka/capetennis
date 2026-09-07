@php
    $placementService = app(\App\Services\Draw\DrawFinalPlacementService::class);
    $finalPositions = isset($placements) ? collect($placements) : $placementService->forDraw($draw);
    $placementAdjustments = isset($adjustments)
        ? collect($adjustments)
        : $placementService->adjustmentsForDraw($draw);
@endphp

@if($finalPositions->isNotEmpty() || $placementAdjustments->isNotEmpty())
<section class="ct-final-positions" aria-labelledby="ct-final-positions-title-{{ $draw->id }}">
    <style>
      .ct-final-positions{margin:24px 16px 8px;padding:20px;border:1px solid #dce5ea;border-radius:14px;background:#fff;color:#172033;font-family:'Noto Sans JP',Arial,sans-serif}
      .ct-final-positions h2{margin:0;font-size:18px;font-weight:800}.ct-final-positions p{margin:4px 0 16px;color:#64748b;font-size:12px}
      .ct-final-position-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px}
      .ct-final-position{display:flex;align-items:center;gap:10px;min-height:44px;padding:7px 10px;border:1px solid #b9d8ee;border-radius:9px;background:#eaf5fc}
      .ct-final-position strong{display:grid;place-items:center;flex:0 0 30px;height:30px;border-radius:50%;background:#fff;color:#176448;font-size:13px}
      .ct-final-position span{font-size:13px;font-weight:700;color:#155d91}.ct-final-position.is-awaiting,.ct-final-position.is-bye{border-color:#e2e8f0;background:#f8fafc}
      .ct-final-position.is-awaiting span,.ct-final-position.is-bye span{color:#64748b;font-weight:600}.ct-final-position.is-bye span{font-style:italic}
      .ct-placement-adjustment{margin:0 0 16px;padding:14px;border:1px solid #f0c36a;border-radius:10px;background:#fff8e7}
      .ct-placement-adjustment h3{margin:0 0 4px;font-size:14px;font-weight:800;color:#7c4a03}.ct-placement-adjustment p{margin:0 0 10px;color:#805b1b}
      .ct-placement-adjustment-result{display:flex;flex-wrap:wrap;align-items:center;gap:6px;font-size:13px;color:#172033}
      .ct-placement-adjustment-result strong{font-weight:800;color:#176448}.ct-placement-adjustment-score{padding:2px 8px;border-radius:999px;background:#fff;color:#7c4a03;font-weight:800}
      @media print{.ct-final-positions{break-inside:avoid;margin:18px 0 0}.ct-final-position-grid{grid-template-columns:repeat(4,1fr)}}
    </style>
    <h2 id="ct-final-positions-title-{{ $draw->id }}">Final positions</h2>
    <p>Positions update automatically from completed finals, placement matches and any recorded placement adjustment.</p>
    @foreach($placementAdjustments as $adjustment)
      @php
        $winnerId = (int) $adjustment->winner_registration;
        $winner = $winnerId === (int) $adjustment->registration1_id ? $adjustment->registration1 : $adjustment->registration2;
        $loser = $winnerId === (int) $adjustment->registration1_id ? $adjustment->registration2 : $adjustment->registration1;
        $winnerName = $winner?->display_name ?: $winner?->players?->pluck('full_name')->join(' / ');
        $loserName = $loser?->display_name ?: $loser?->players?->pluck('full_name')->join(' / ');
        $score = $adjustment->fixtureResults->sortBy('set_nr')
          ->map(fn($set) => $set->registration1_score.'-'.$set->registration2_score)
          ->implode(' ');
      @endphp
      <div class="ct-placement-adjustment">
        <h3>{{ $adjustment->playoff_type ?: 'Additional placement match' }}</h3>
        <p>This once-off match was played after the scheduled placement matches and determines the adjusted final order below.</p>
        <div class="ct-placement-adjustment-result">
          <strong>{{ $winnerName }}</strong><span>defeated</span><span>{{ $loserName }}</span>
          @if($score)<span class="ct-placement-adjustment-score">{{ $score }}</span>@endif
        </div>
      </div>
    @endforeach
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
