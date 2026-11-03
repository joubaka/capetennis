<details {{ $attributes->class(['card mb-3']) }} data-plus-renewal-help aria-label="Manage Player Plus">
  <summary style="padding:1rem;min-height:44px;color:#172e45;font-weight:600;cursor:pointer">Player Plus orders and renewal</summary>
  <div class="card-body">
    <h2 class="h6">Manage Player Plus or cancel renewal</h2>
    <p class="mb-2">To cancel a subscription, open My Plus orders, choose your order and select <strong>Stop automatic renewal</strong>.</p>
    <p class="small text-muted mb-3">Only the payer can stop renewal. Paid access continues until its paid expiry. Once-off purchases do not renew.</p>
    <a class="btn btn-outline-primary" style="min-height:44px;white-space:normal" href="{{ route('player-plus.orders.index') }}">My Plus orders · manage renewal</a>
  </div>
</details>
