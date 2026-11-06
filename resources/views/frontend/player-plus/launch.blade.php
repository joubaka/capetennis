<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Continue to PayFast | Cape Tennis</title>
  <style>
    body { margin: 0; background: #f4f7fa; color: #18304b; font: 1rem/1.5 system-ui, sans-serif; }
    main { max-width: 42rem; margin: 2rem auto; padding: 1.5rem; background: white; border: 1px solid #d6e0ea; border-radius: .75rem; }
    h1 { margin-top: 0; font-size: 1.5rem; }
    button { min-height: 44px; padding: .5rem 1rem; border: 0; border-radius: .375rem; background: #003d70; color: white; font: inherit; cursor: pointer; }
    a { color: #003d70; }
  </style>
</head>
<body>
  <main data-plus-payfast-launch>
    <h1>Taking you to PayFast</h1>
    <p>{{ $order->player->full_name }} · R{{ \App\Services\Performance\PlayerPlusService::amount($order->price_cents) }} {{ $order->isRecurring() ? ($order->isMonthly() ? 'per month until cancelled' : 'per year until cancelled') : 'once off' }}.</p>
    <p>If PayFast does not open automatically, use the button below.</p>
    {!! $form !!}
    <button type="submit" form="payfastForm">Continue to PayFast · R{{ \App\Services\Performance\PlayerPlusService::amount($order->price_cents) }}</button>
    <p>Access starts only after verified payment. <a href="{{ route('player-plus.orders.show', $order) }}">View order P{{ $order->id }}</a></p>
  </main>
  <script src="{{ asset('assets/js/player-plus-checkout.js') }}"></script>
</body>
</html>
