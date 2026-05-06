@extends('layouts/layoutMaster')

@section('title', 'Bank Details Received')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5 text-center">

      <div class="card shadow-sm border-success">
        <div class="card-body py-5">
          <div class="mb-3">
            <i class="ti ti-circle-check text-success" style="font-size: 4rem;"></i>
          </div>
          <h4 class="text-success mb-2">Bank Details Received</h4>
          <p class="text-muted mb-3">
            Thank you, <strong>{{ $user->name }}</strong>! We have received your bank details for
            {{ $registrations->count() === 1 ? '1 refund' : $registrations->count() . ' refunds' }}
            totalling <strong>R{{ number_format($registrations->sum('refund_net'), 2) }}</strong>.
            These will be processed within <strong>1–3 business days</strong>.
          </p>
          <ul class="list-unstyled text-start small text-muted mx-auto" style="max-width:320px">
            @foreach($registrations as $reg)
              @php
                $player    = $reg->players->first();
                $pName     = $player ? trim($player->name . ' ' . $player->surname) : 'Player';
                $eventName = optional($reg->categoryEvent?->event)->name ?? 'Event';
              @endphp
              <li><i class="ti ti-check text-success me-1"></i> {{ $pName }} — {{ $eventName }} — R{{ number_format($reg->refund_net, 2) }}</li>
            @endforeach
          </ul>
          <p class="small text-muted mt-3">
            Questions? Email <a href="mailto:support@capetennis.co.za">support@capetennis.co.za</a>
          </p>
        </div>
      </div>

    </div>
  </div>
</div>
@endsection
