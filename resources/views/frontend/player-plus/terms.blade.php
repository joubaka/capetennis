@extends('layouts/contentNavbarLayout')
@section('title', 'Player Plus purchase details')
@section('content')
<div class="container-xxl container-p-y" style="max-width:900px">
  <a href="{{ route('player-plus.index') }}">Back to Player Plus</a>
  <h1 class="mt-3">Player Plus purchase details</h1>
  <p class="text-muted">Purchase details version {{ $version }}</p>
  <x-player-plus-benefits />
  <h2 class="h4 mt-4">One player, one purchased term</h2>
  <p>The price shown at checkout buys 12 months for the named player, starting only after verified payment. Check the player name, profile number and birth year before paying. Renewal is manual after expiry at the price displayed then. No automatic renewal or charge is authorised.</p>
  <h2 class="h4">Ratings and pathway guidance depend on evidence</h2>
  <p>Payment does not create results, guarantee a rating, improve a rating or guarantee selection. History may be provisional, incomplete, withheld after publication changes, or unavailable until suitable published singles results and saved calculations exist. Pathway guidance needs suitable ball-level evidence and uses the configured development benchmarks. Discuss development decisions with a coach; guidance does not automatically promote a player.</p>
  <h2 class="h4">Sponsorship and privacy</h2>
  <p>You may pay for another player. Only that player and their currently linked accounts can use private benefits. Purchase does not create an account link. Payer receipts and order access remain with the payer.</p>
  <h2 class="h4">Payment issues and requests for help</h2>
  <p>Returning from PayFast does not prove payment. Payments received after expiry or cancellation, and additional payments, need Cape Tennis review and do not automatically activate or extend membership. Do not pay again while a payment is being checked.</p>
  <p>For an incorrect-player purchase, missing data, duplicate or delayed payment, or a refund enquiry, contact <a href="mailto:support@capetennis.co.za">support@capetennis.co.za</a> with your P order number. Support must verify the payer and payment evidence before deciding or processing a remedy. Do not email bank credentials, passwords or full card details. This page does not authorise an automatic refund or transfer, and does not limit rights that otherwise apply.</p>
  <p>Essential tournament information remains free. Draw alerts are planned and are not part of this purchase.</p>
  <p><a href="{{ route('player-plus.orders.index') }}">Find my Plus orders</a></p>
</div>
@endsection
