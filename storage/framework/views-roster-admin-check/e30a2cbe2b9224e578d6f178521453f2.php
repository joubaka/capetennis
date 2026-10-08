

<?php $__env->startSection('title', 'Checkout'); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl flex-grow-1 container-p-y">
  <div class="row justify-content-center">
    <div class="col-xl-8 col-lg-10 mb-3">
      <div class="card shadow-sm">
        <div class="card-header">
          Payment for
        </div>
        <div class="card-body">
          <h5 class="card-title"><?php echo e($payfast->item_name); ?></h5>
          <h6 class="card-subtitle text-muted"><?php echo e($payfast->custom_str4); ?></h6>

          <div class="border rounded bg-label-primary p-3 mt-3" aria-label="Registration details">
            <div class="row g-3">
              <div class="col-md-6">
                <div class="text-muted small mb-1">Player</div>
                <div class="fw-semibold"><?php echo e($player->name); ?> <?php echo e($player->surname); ?></div>
                <div class="text-muted small">Profile #<?php echo e($player->id); ?></div>
              </div>
              <div class="col-md-6">
                <div class="text-muted small mb-1">Team</div>
                <div class="fw-semibold"><?php echo e($team->name); ?></div>
              </div>
            </div>
          </div>

          <ul class="list-group list-group-flush my-3">
            <li class="list-group-item d-flex justify-content-between">
              <span>Entry Fee</span>
              <span>R<?php echo e(number_format($event->entryFee, 2)); ?></span>
            </li>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($regionFee) && $regionFee > 0): ?>
              <li class="list-group-item d-flex justify-content-between">
                <span>Provincial Region Fee</span>
                <span>R<?php echo e(number_format($regionFee, 2)); ?></span>
              </li>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <li class="list-group-item d-flex justify-content-between fw-bold">
              <span>Total</span>
              <span>R<?php echo e(number_format($total, 2)); ?></span>
            </li>
          </ul>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="border rounded p-3 h-100">
                <p class="text-muted mb-1">Wallet Balance Available:</p>
                <h5 class="mb-0">R<?php echo e(number_format($walletBalance, 2)); ?></h5>
              </div>
            </div>
            <div class="col-md-6">
              <div class="border rounded p-3 h-100">
                <p class="text-muted mb-1">PayFast Amount Due:</p>
                <h5 class="mb-0 <?php echo e($payfastDue > 0 ? 'text-danger' : 'text-success'); ?>" id="payfastDueDisplay">R<?php echo e(number_format($payfastDue, 2)); ?></h5>
              </div>
            </div>
          </div>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($walletReserved > 0): ?>
            <div class="alert alert-success mb-3" role="alert">
              <i class="ti ti-circle-check me-2"></i>
              <strong>Wallet Applied:</strong> R<?php echo e(number_format($walletReserved, 2)); ?>

              <div class="small mt-1 ms-4">
                <strong>Wallet Balance After Payment:</strong>
                R<?php echo e(number_format(max(0, $walletBalance - $walletReserved), 2)); ?>

              </div>
            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payfastDue > 0 && round($walletBalance - $walletReserved, 2) > 0): ?>
            <form id="teamHybridForm" action="<?php echo e(route('team.hybrid.pay')); ?>" method="post" class="mb-3">
              <?php echo csrf_field(); ?>
              <input type="hidden" name="custom_int5" value="<?php echo e($order->id); ?>">
              <input type="hidden" name="wallet_applied" value="<?php echo e(number_format(min($walletBalance, $total), 2, '.', '')); ?>">
              <input type="hidden" name="remaining_amount" value="<?php echo e(number_format(max(0, $total - min($walletBalance, $total)), 2, '.', '')); ?>">
              <input type="hidden" name="type" value="team">
              <button type="submit" class="btn btn-primary w-100" id="applyWalletBtn">
                <i class="ti ti-wallet me-1"></i>
                <?php echo e($walletBalance >= $total ? 'Use Wallet for Full Payment' : 'Apply Updated Wallet Balance'); ?>

              </button>
            </form>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          <?php
            $returnUrl = route('event.success', ['id' => $event->id]);
            $cancelUrl = $cancelUrl ?? route('team.payment.payfast', ['team' => $team->id, 'player' => $player->id, 'event' => $event->id]);
            $notifyUrl = route('notify.team');
          ?>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payfastDue > 0): ?>
            <div class="border rounded p-4">
              <form id="teamPayfastForm" action="<?php echo e($autoSubmitPayfast ? $payfast->url : route('team.payment.payfast.handoff', ['team' => $team->id, 'player' => $player->id, 'event' => $event->id])); ?>" method="post">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($autoSubmitPayfast)): ?>
                  <?php echo csrf_field(); ?>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <input type="hidden" name="merchant_id" value="<?php echo e($payfast->id); ?>">
                <input type="hidden" name="merchant_key" value="<?php echo e($payfast->key); ?>">
                <input type="hidden" name="return_url" value="<?php echo e($returnUrl); ?>">
                <input type="hidden" name="cancel_url" value="<?php echo e($cancelUrl); ?>">
                <input type="hidden" name="notify_url" value="<?php echo e($notifyUrl); ?>">
                <input type="hidden" name="amount" value="<?php echo e(number_format($payfastDue, 2, '.', '')); ?>">
                <input type="hidden" name="item_name" value="<?php echo e($payfast->item_name); ?>">

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payfast->custom_int1): ?>
                  <input type="hidden" name="custom_int1" value="<?php echo e($payfast->custom_int1); ?>">
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payfast->custom_str1): ?>
                  <input type="hidden" name="custom_str1" value="<?php echo e($payfast->custom_str1); ?>">
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payfast->custom_int2): ?>
                  <input type="hidden" name="custom_int2" value="<?php echo e($payfast->custom_int2); ?>">
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payfast->custom_str2): ?>
                  <input type="hidden" name="custom_str2" value="<?php echo e($payfast->custom_str2); ?>">
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payfast->custom_int3): ?>
                  <input type="hidden" name="custom_int3" value="<?php echo e($payfast->custom_int3); ?>">
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payfast->custom_str3): ?>
                  <input type="hidden" name="custom_str3" value="<?php echo e($payfast->custom_str3); ?>">
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payfast->custom_int4): ?>
                  <input type="hidden" name="custom_int4" value="<?php echo e($payfast->custom_int4); ?>">
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payfast->custom_str4): ?>
                  <input type="hidden" name="custom_str4" value="<?php echo e($payfast->custom_str4); ?>">
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payfast->custom_int5): ?>
                  <input type="hidden" name="custom_int5" value="<?php echo e($payfast->custom_int5); ?>">
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <input type="hidden" name="custom_str5" value="TeamOrder">

                <?php
                  $formFields = array_filter([
                    'merchant_id'  => $payfast->id,
                    'merchant_key' => $payfast->key,
                    'return_url'   => $returnUrl,
                    'cancel_url'   => $cancelUrl,
                    'notify_url'   => $notifyUrl,
                    'amount'       => number_format($payfastDue, 2, '.', ''),
                    'item_name'    => $payfast->item_name,
                    'custom_int1'  => $payfast->custom_int1 ? (string)$payfast->custom_int1 : null,
                    'custom_str1'  => $payfast->custom_str1 ?: null,
                    'custom_int2'  => $payfast->custom_int2 ? (string)$payfast->custom_int2 : null,
                    'custom_str2'  => $payfast->custom_str2 ?: null,
                    'custom_int3'  => $payfast->custom_int3 ? (string)$payfast->custom_int3 : null,
                    'custom_str3'  => $payfast->custom_str3 ?: null,
                    'custom_int4'  => $payfast->custom_int4 ? (string)$payfast->custom_int4 : null,
                    'custom_str4'  => $payfast->custom_str4 ?: null,
                    'custom_int5'  => $payfast->custom_int5 ? (string)$payfast->custom_int5 : null,
                    'custom_str5'  => 'TeamOrder',
                  ], fn($v) => $v !== null && $v !== '');
                ?>
                <input type="hidden" name="signature" value="<?php echo e($payfast->generateFormSignature($formFields)); ?>">

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if (! ($autoSubmitPayfast)): ?>
                  <button class="btn btn-danger btn-lg w-100">Pay now with Payfast</button>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </form>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($autoSubmitPayfast): ?>
                <div class="text-center text-muted" role="status">Redirecting securely to PayFast…</div>
                <script>document.getElementById('teamPayfastForm').submit();</script>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
          <?php else: ?>
            <div class="alert alert-success mb-3">
              <i class="ti ti-circle-check me-2"></i>
              No additional payment required. Your wallet covers the full amount.
            </div>
            <form action="<?php echo e(route('team.hybrid.complete', ['order' => $order->id])); ?>" method="post">
              <?php echo csrf_field(); ?>
              <button type="submit" class="btn btn-success btn-lg w-100">Confirm Wallet Payment</button>
            </form>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts/layoutMaster', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\frontend\payfast\team_payment.blade.php ENDPATH**/ ?>