<aside {{ $attributes->class(['border rounded p-3']) }} aria-label="Player Plus membership benefits" data-player-plus-benefits>
  <div class="d-flex flex-wrap justify-content-between align-items-baseline gap-2">
    <strong>Optional Player Plus</strong>
    <span>R{{ number_format(\App\Services\Performance\PlayerPlusService::PRICE_CENTS / 100, 0) }} per player / year</span>
  </div>
  <p class="small mt-2 mb-2">12 months for one named player, shared with the player and their linked parents or guardians. Renew manually; no automatic renewal.</p>
  <details>
    <summary class="small py-2" style="min-height:44px; cursor:pointer">What do you get with Plus?</summary>
    <ul class="small ps-3 mb-2">
      <li>View the player's saved CTR rating and 12-month progress history, where eligible results are available.</li>
      <li>Explore the recorded singles results and evidence behind the player's estimates.</li>
      <li>See CTR comparisons and Top 10 lists among eligible published series participants.</li>
    </ul>
    <p class="small mb-2">Ratings can be provisional or unavailable. Membership does not change a rating, ranking, selection or tournament entry.</p>
    <p class="small mb-2">Paying for a player does not link them to your account or give you access to their private information.</p>
    <p class="small mb-0">Draw alerts are planned and are not included yet. Account signup is free; Player Plus is a separate purchase when available. Essential tournament information remains free.</p>
  </details>
</aside>
