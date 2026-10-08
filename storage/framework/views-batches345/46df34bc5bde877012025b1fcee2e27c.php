<?php
  $trialItem = $order->items->first();
  $trialEvent = $trialItem?->category_event?->event;
  $trialProgramme = $trialEvent?->isInterprovincialTrials() && \Illuminate\Support\Facades\Schema::hasTable('trial_programmes')
    ? \App\Models\TrialProgramme::where('event_id', $trialEvent->id)->first() : null;
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($trialProgramme && filled($trialProgramme->bank_details) && !$order->pay_status): ?>
<div class="card my-3"><div class="card-body">
  <h5>Pay by EFT</h5><p class="text-break" style="white-space: pre-line"><?php echo e($trialProgramme->bank_details); ?></p>
  <p>Amount: <strong>R <?php echo e(number_format($order->total_fee, 2)); ?></strong>. Use order <strong><?php echo e($order->id); ?></strong> as your reference. An administrator must verify payment before registration is confirmed.</p>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->payfast_handed_off_at): ?><p class="text-warning">Your online payment is being processed. Wait for it to resolve before using EFT.</p>
  <?php else: ?><form method="POST" enctype="multipart/form-data" action="<?php echo e(route('interprovincial-trials.proof.upload', [$trialEvent, $order])); ?>"><?php echo csrf_field(); ?>
    <label for="trial-proof" class="form-label">Proof of payment (PDF, JPG or PNG; up to 5 MB)</label><input id="trial-proof" type="file" name="proof" class="form-control mb-3" accept="application/pdf,image/jpeg,image/png" required><button class="btn btn-outline-primary">Upload proof for verification</button>
  </form><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div></div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\event\partials\trial-eft-checkout.blade.php ENDPATH**/ ?>