@extends('layouts/contentNavbarLayout')
@section('title', 'Player Plus purchase details')
@section('content')
<div class="container-xxl container-p-y pp-journey" style="max-width:900px">
  @include('frontend.player-plus.styles')
  <a href="{{ route('player-plus.index') }}">Back to Player Plus</a>
  <header class="pp-visual-hero mt-3">
    <span class="pp-hero-icon"><i class="ti ti-file-description" aria-hidden="true"></i></span>
    <span class="pp-visual-eyebrow">Player Plus · Before you buy</span>
    <h1>Player Plus purchase details</h1>
    <p class="mb-0">Purchase details version {{ $version }}</p>
  </header>
  <x-player-plus-benefits />
  <div class="pp-terms-body mt-4">
  <h2 class="h4 mt-0"><i class="ti ti-calendar" aria-hidden="true"></i>Annual automatic renewal</h2>
  <p>New annual purchases require separate recurring-payment consent: the annual price is charged now and the same agreed price is charged each year through PayFast until cancelled. Each verified annual payment buys a year for the named player. Check the player name, profile number and birth year before paying. Stop future renewals from My Plus orders; paid access remains until its paid expiry.</p>
  <p>Existing once-off annual checkouts and paid terms keep their original conditions and do not become subscriptions. Their order pages continue to identify them as once-off purchases with no automatic renewal.</p>
  <h2 class="h4"><i class="ti ti-refresh" aria-hidden="true"></i>Monthly automatic renewal</h2>
  <p>If you choose the monthly plan, your separate recurring-payment consent authorises the first monthly price now and the same agreed price each month through PayFast until cancelled. Later price changes apply only to new subscriptions. The next scheduled billing date appears in your order after verified payment. Month-end dates use the last day of shorter months. Only verified payment extends access; failed or missing payments do not grant a free period.</p>
  <p>The original payer can stop either plan's automatic renewal from My Plus orders, including while new purchases are paused. Cancellation must be confirmed by PayFast; a pending or failed request does not mean charges have stopped. Verified access remains until the paid period ends. A charge arriving after a cancellation request, an unexpected extra charge or a payment outside the expected billing period is held for reconciliation and does not extend access. Contact support promptly if cancellation needs confirmation.</p>
  <h2 class="h4"><i class="ti ti-chart-line" aria-hidden="true"></i>Ratings and pathway guidance depend on evidence</h2>
  <p>Payment does not create results, guarantee a rating, improve a rating or guarantee selection. History may be provisional, incomplete, withheld after publication changes, or unavailable until suitable published singles results and saved calculations exist. Pathway guidance needs suitable ball-level evidence and uses the configured development benchmarks. Discuss development decisions with a coach; guidance does not automatically promote a player.</p>
  <h2 class="h4"><i class="ti ti-shield-lock" aria-hidden="true"></i>Sponsorship and privacy</h2>
  <p>You may pay for another player. Only that player and their currently linked accounts can use private benefits. Purchase does not create an account link. Payer receipts and order access remain with the payer.</p>
  <h2 class="h4"><i class="ti ti-help" aria-hidden="true"></i>Payment issues and requests for help</h2>
  <p>Returning from PayFast does not prove payment. Initial payments received after checkout expiry or cancellation, unexpected additional payments, and recurring payments outside the expected billing period need Cape Tennis review and do not automatically activate or extend membership. Normal verified monthly renewals follow the subscription schedule, not the initial checkout expiry. Do not pay again while a payment is being checked.</p>
  <p>For an incorrect-player purchase, missing data, duplicate or delayed payment, or a refund enquiry, contact <a href="mailto:support@capetennis.co.za">support@capetennis.co.za</a> with your P order number. Support must verify the payer and payment evidence before deciding or processing a remedy. Do not email bank credentials, passwords or full card details. This page does not authorise an automatic refund or transfer, and does not limit rights that otherwise apply.</p>
  <p>Essential tournament information remains free. Draw alerts are planned and are not part of this purchase.</p>
  <p><a href="{{ route('player-plus.orders.index') }}">Find my Plus orders</a></p>
  </div>
</div>
@endsection
