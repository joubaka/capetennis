@props(['priceCents' => null])
@php($priceCents = $priceCents ?? \App\Services\Performance\PlayerPlusService::priceCents())
<aside {{ $attributes->class(['rounded p-3']) }} style="background:#fff;border:1px solid #e5ece9;color:#20384b" aria-label="Player Plus membership benefits" data-player-plus-benefits>
  <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2">
    <strong>Optional Player Plus</strong>
    <span style="color:#14675c;font-size:1.25rem;font-weight:600">R{{ \App\Services\Performance\PlayerPlusService::amount($priceCents) }} <small style="font-size:.8rem;font-weight:400">per player / year</small></span>
  </div>
  <p class="small mt-2 mb-2">12 months for one named player, shared with the player and their linked parents or guardians. Renew manually; no automatic renewal.</p>
  <p class="fw-semibold mb-2">Understand their playing strength. Follow their progress.</p>
  <p class="small text-muted mb-3">Eligible results are needed for a rating; purchasing Plus does not guarantee one.</p>
    <ul class="small ps-3 mb-2">
      <li>View the player's saved Cape Tennis Rating (CTR), an estimate of singles playing strength, and 12-month progress history where eligible results are available.</li>
      <li>Explore the recorded singles results and evidence behind the player's estimates.</li>
      <li>See CTR comparisons and Top 10 lists among eligible published series participants.</li>
      <li>Understand your tennis pathway: ball-level progress, published benchmarks and suggested next steps where suitable evidence is available.</li>
    </ul>
  <details>
    <summary class="small py-2" style="min-height:44px; cursor:pointer">How membership works</summary>
    <p class="small mb-2">Ratings can be provisional or unavailable. Membership does not change a rating, ranking, selection or tournament entry.</p>
    <p class="small mb-2">Pathway guidance supports discussion with a coach. It does not automatically promote a player or guarantee selection.</p>
    <p class="small mb-2">Paying for a player does not link them to your account or give you access to their private information.</p>
    <p class="small mb-0">Draw alerts are planned and are not included yet. Account signup is free; Player Plus is a separate purchase when available. Essential tournament information remains free.</p>
  </details>
  <a class="small d-inline-block py-2" style="min-height:44px" href="{{ route('player-plus.terms') }}">Purchase details and payment support</a>
</aside>
