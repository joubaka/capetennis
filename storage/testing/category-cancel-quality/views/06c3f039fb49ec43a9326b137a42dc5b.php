<?php ($reimbursementErrors = $errors ?? new \Illuminate\Support\ViewErrorBag()); ?>
<details class="card mb-4" id="expense-reimbursements" <?php if($reimbursementErrors->any()): ?> open <?php endif; ?>>
  <summary class="card-header"><h5 class="mb-0">Record payment to director</h5></summary>
  <div class="card-body">
    <p>Record money already paid to a director from registration funds for expenses, profit share, or both. This reduces the total still owed without changing event profit or the calculated profit share. Use “Full outstanding balance” to include linked clothing amounts and deductions in one recorded settlement.</p>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recon->contains('payment_resolution_required', true)): ?>
      <div class="alert alert-warning" role="alert">Some recorded payout recipients need reconciliation. New payments for affected directors are blocked until their recorded payment identities are resolved.</div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reimbursementErrors->any()): ?>
      <div class="alert alert-danger" role="alert"><ul class="mb-0"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $reimbursementErrors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($message); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event-finance.manage', $event)): ?>
    <form method="POST" action="<?php echo e(route('admin.events.expense-reimbursements.store', $event)); ?>" class="row g-3" data-director-payment-form>
      <?php echo csrf_field(); ?>
      <input type="hidden" name="idempotency_key" value="<?php echo e(old('idempotency_key', (string) \Illuminate\Support\Str::uuid())); ?>">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($directorSettlementPreviews)): ?>
      <div class="col-12"><label for="director-payment-mode" class="form-label">Payment to record</label>
        <select name="payment_mode" id="director-payment-mode" class="form-select" data-payment-mode>
          <option value="partial" <?php if(old('payment_mode', 'partial') === 'partial'): echo 'selected'; endif; ?>>Amount already paid from registration entries</option>
          <option value="full_net" <?php if(old('payment_mode') === 'full_net'): echo 'selected'; endif; ?>>Full outstanding balance, including clothing deductions</option>
        </select>
      </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <div class="col-md-6"><label for="reimbursement-director" class="form-label">Director</label>
        <select id="reimbursement-director" name="convenor_id" class="form-select" required>
          <option value="">Choose director</option>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $recon; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($row['convenor']->id); ?>" data-settlement="<?php echo e(json_encode($directorSettlementPreviews[$row['convenor']->id] ?? null)); ?>" <?php if($row['payment_resolution_required'] ?? false): echo 'disabled'; endif; ?> <?php if((string) old('convenor_id') === (string) $row['convenor']->id): echo 'selected'; endif; ?>>
              <?php echo e($row['convenor']->user->name ?? 'Unknown'); ?> — <?php echo e(($row['payment_resolution_required'] ?? false) ? 'Review recorded payout identity' : 'R '.number_format($row['final_payout'], 2).' total still owed'); ?>

            </option>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </select>
      </div>
      <div class="col-md-6"><label for="reimbursement-amount" class="form-label">Amount already paid (R)</label><input id="reimbursement-amount" name="amount" type="number" min="0.01" max="99999999.99" step="0.01" value="<?php echo e(old('amount')); ?>" class="form-control" required></div>
      <div class="col-md-6"><label class="form-label">Paid from</label><p class="form-control-plaintext" data-funding-label>Registration entries</p></div>
      <div class="col-12" data-net-settlement hidden>
        <div class="alert alert-info mb-3" data-net-preview aria-live="polite"></div>
        <input type="hidden" name="preview_token" value="<?php echo e(old('preview_token')); ?>" disabled>
        <label for="director-settled-at" class="form-label">Actual payment date and time</label>
        <input id="director-settled-at" name="settled_at" type="datetime-local" value="<?php echo e(old('settled_at')); ?>" max="<?php echo e(now()->format('Y-m-d\TH:i')); ?>" class="form-control mb-3" disabled>
        <label class="form-check-label"><input type="checkbox" name="settlement_confirmed" value="1" class="form-check-input me-2" disabled> I confirm the net amount was already paid and the clothing deductions were withheld from this settlement.</label>
        <small class="d-block text-muted mt-2">This records the payment and reconciles the linked balances. It does not transfer money. Pending clothing refunds remain reserved.</small>
      </div>
      <div class="col-md-6"><label for="reimbursement-date" class="form-label">Payment date (optional)</label><input id="reimbursement-date" name="paid_at" type="date" max="<?php echo e(now()->toDateString()); ?>" value="<?php echo e(old('paid_at')); ?>" class="form-control"><small class="text-muted">Leave blank if the payment date is unknown.</small></div>
      <div class="col-md-6"><label for="reimbursement-reference" class="form-label" data-reference-label>Payment reference (optional)</label><input id="reimbursement-reference" name="reference" maxlength="150" value="<?php echo e(old('reference')); ?>" class="form-control"></div>
      <div class="col-md-6"><label for="reimbursement-note" class="form-label">Note (optional)</label><input id="reimbursement-note" name="description" maxlength="255" value="<?php echo e(old('description')); ?>" class="form-control"></div>
      <div class="col-12"><button class="btn btn-primary" type="submit" data-payment-submit>Record payment</button></div>
    </form>
    <?php endif; ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reimbursementPayments->isNotEmpty()): ?>
      <h6 class="mt-4">Recorded registration payments &amp; corrections</h6>
      <div class="table-responsive"><table class="table"><thead><tr><th>Director</th><th>Purpose</th><th>Amount</th><th>Paid from</th><th>Payment date</th><th>Reference / note</th><th>Recorded by</th></tr></thead><tbody>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $reimbursementPayments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payment): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <tr><td><?php echo e($payment->recipient_name ?? $payment->convenor?->user?->name ?? 'Unknown'); ?></td><td><?php echo e($payment->purpose === 'payout_reversal' ? 'Accounting correction (no transfer)' : ($payment->settlement_details ? 'Full net settlement' : ($payment->purpose === 'expense_reimbursement' ? 'Expense reimbursement' : 'Expenses / profit share'))); ?></td><td>R <?php echo e(number_format($payment->settlement_details ? $payment->settlement_details['cash_cents'] / 100 : (float) $payment->amount, 2)); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payment->settlement_details): ?><small class="d-block text-muted">Registration settled: R <?php echo e(number_format((float) $payment->amount, 2)); ?>; clothing adjustment: R <?php echo e(number_format(($payment->settlement_details['cash_cents'] - $payment->settlement_details['registration_cents']) / 100, 2)); ?></small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></td><td><?php echo e($payment->settlement_details ? 'Registration and linked clothing' : 'Registration entries'); ?></td><td><?php echo e($payment->paid_at?->format('d M Y') ?? 'Unknown'); ?></td><td><?php echo e($payment->reference); ?> <?php echo e($payment->description); ?></td><td><?php echo e($payment->recorded_by_name ?? $payment->paidByUser?->name ?? 'Unknown'); ?><small class="d-block"><?php echo e($payment->created_at->format('d M Y H:i')); ?></small></td></tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </tbody></table></div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </div>
</details>

<?php if (! $__env->hasRenderedOnce('9d046916-a4cf-4bed-b286-72ce4d3ced58')): $__env->markAsRenderedOnce('9d046916-a4cf-4bed-b286-72ce4d3ced58'); ?>
<script src="<?php echo e(asset('js/event-director-payments.js')); ?>" defer></script>
<?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\backend\event\partials\expense-reimbursements.blade.php ENDPATH**/ ?>