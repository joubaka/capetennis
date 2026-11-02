@props(['priceCents' => null, 'plan' => 'offer'])
@php($priceCents = $priceCents ?? \App\Services\Performance\PlayerPlusService::priceCents())
<aside {{ $attributes->class(['rounded p-3 plus-benefits-visual']) }} style="background:#fff;border:1px solid #dce5ef;color:#20384b" aria-label="Player Plus membership benefits" data-player-plus-benefits>
  <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2">
    <strong>Optional Player Plus</strong>
    @if($plan !== 'offer')
    <span style="color:#004177;font-size:1.25rem;font-weight:600">R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }} <small style="font-size:.8rem;font-weight:400">per player / {{ $plan === 'monthly' ? 'month' : 'year' }}</small></span>
    @endif
  </div>
  @if($plan === 'offer')<x-player-plus-pricing class="mt-2" />@endif
  <p class="small mt-2 mb-2">@if($plan === 'monthly')Monthly access for one named player, automatically renewed through PayFast until the payer cancels.@elseif($plan === 'annual_recurring')Annual access for one named player, automatically renewed through PayFast until the payer cancels.@elseif($plan === 'annual')12 months for one named player. This existing once-off purchase has no automatic renewal.@else Choose monthly or annual automatic renewal for one named player when available.@endif Shared with the player and their linked parents or guardians.</p>
  <p class="fw-semibold mb-2">Understand their playing strength. Follow their progress.</p>
  <p class="small text-muted mb-3">Eligible results are needed for a rating; purchasing Plus does not guarantee one.</p>
    <ul class="small ps-3 mb-2">
      <li><strong class="plus-benefit-title"><i class="ti ti-chart-line" aria-hidden="true"></i>See their progress</strong>View the player's saved Cape Tennis Rating (CTR), an estimate of singles playing strength, and 12-month progress history where eligible results are available.</li>
      <li><strong class="plus-benefit-title"><i class="ti ti-list-details" aria-hidden="true"></i>Explore the results</strong>Explore the recorded singles results and evidence behind the player's estimates.</li>
      <li><strong class="plus-benefit-title"><i class="ti ti-trophy" aria-hidden="true"></i>Put it in perspective</strong>See CTR comparisons and Top 10 lists among eligible published series participants.</li>
      <li><strong class="plus-benefit-title"><i class="ti ti-route" aria-hidden="true"></i>Find the next step</strong>Understand your tennis pathway: ball-level progress, published benchmarks and suggested next steps where suitable evidence is available.</li>
    </ul>
  <details>
    <summary class="small py-2" style="min-height:44px; cursor:pointer">How membership works</summary>
    <p class="small mb-2">Ratings can be provisional or unavailable. Membership does not change a rating, ranking, selection or tournament entry.</p>
    <p class="small mb-2">Pathway guidance supports discussion with a coach. It does not automatically promote a player or guarantee selection.</p>
    <p class="small mb-2">Paying for a player does not link them to your account or give you access to their private information.</p>
    <p class="small mb-0">Draw alerts are planned and are not included yet. Account signup is free; Player Plus is a separate purchase when available. Essential tournament information remains free.</p>
  </details>
  <a class="small d-inline-block py-2" style="min-height:44px" href="{{ route('player-plus.terms') }}">Purchase details and payment support</a>
  <style>
    .plus-benefits-visual { border-radius:16px!important;padding:clamp(1.25rem,3vw,1.75rem)!important; }
    .plus-benefits-visual > ul { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem;list-style:none;padding:0!important;margin:1.25rem 0!important; }
    .plus-benefits-visual > ul > li { padding:1.1rem;background:#f2f6fb;border:1px solid #dce5ef;border-radius:12px;line-height:1.7;min-width:0; }
    .plus-benefits-visual .plus-benefit-title { display:flex;align-items:center;gap:.6rem;color:#172e45;font-size:.95rem;margin-bottom:.65rem; }
    .plus-benefits-visual .plus-benefit-title .ti { display:grid;place-items:center;flex:0 0 2.1rem;height:2.1rem;background:#e3edf7;color:#004177;border-radius:9px;font-size:1.25rem; }
    .plus-benefits-visual > ul > li:nth-child(4) { background:#fff0e1;border-color:#f5cfaa; }
    .plus-benefits-visual > ul > li:nth-child(4) .ti { background:#ffdfbf;color:#794313; }
    .plus-benefits-visual details { border-top:1px solid #dce5ef;margin-top:1rem;padding-top:.4rem; }
    @media(max-width:575.98px) { .plus-benefits-visual > ul { grid-template-columns:1fr; } }
  </style>
</aside>
