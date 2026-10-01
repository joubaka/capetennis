@php
  $trialItem = $order->items->first();
  $trialEvent = $trialItem?->category_event?->event;
  $trialProgramme = $trialEvent?->isInterprovincialTrials() && \Illuminate\Support\Facades\Schema::hasTable('trial_programmes')
    ? \App\Models\TrialProgramme::where('event_id', $trialEvent->id)->first() : null;
@endphp
@if($trialProgramme && filled($trialProgramme->bank_details) && !$order->pay_status)
<div class="card my-3"><div class="card-body">
  <h5>Pay by EFT</h5><p class="text-break" style="white-space: pre-line">{{ $trialProgramme->bank_details }}</p>
  <p>Amount: <strong>R {{ number_format($order->total_fee, 2) }}</strong>. Use order <strong>{{ $order->id }}</strong> as your reference. An administrator must verify payment before registration is confirmed.</p>
  @if($order->payfast_handed_off_at)<p class="text-warning">Your online payment is being processed. Wait for it to resolve before using EFT.</p>
  @else<form method="POST" enctype="multipart/form-data" action="{{ route('interprovincial-trials.proof.upload', [$trialEvent, $order]) }}">@csrf
    <label for="trial-proof" class="form-label">Proof of payment (PDF, JPG or PNG; up to 5 MB)</label><input id="trial-proof" type="file" name="proof" class="form-control mb-3" accept="application/pdf,image/jpeg,image/png" required><button class="btn btn-outline-primary">Upload proof for verification</button>
  </form>@endif
</div></div>
@endif
