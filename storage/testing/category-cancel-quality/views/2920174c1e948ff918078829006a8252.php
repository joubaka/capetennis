  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recon->count()): ?>
  <section class="card mb-4 no-print" id="finance-convenor-payouts" aria-labelledby="convenor-payout-title">
    <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
      <h5 class="mb-0" id="convenor-payout-title"><i class="ti ti-cash me-2"></i>Convenor Payout Breakdown</h5>
      <small class="text-muted">Registration <?php echo e($netProfit >= 0 ? 'profit' : 'loss'); ?>: <strong class="<?php echo e($netProfit >= 0 ? 'text-success' : 'text-danger'); ?>">R <?php echo e(number_format(abs($netProfit), 2)); ?></strong></small>
    </div>
    <div class="card-body">
      <p class="small text-muted mb-3">Clothing balances adjust the projected payment below; registration profit stays unchanged. Payments and recoveries must still be recorded when settled.</p>
      <div class="row g-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $recon; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
            $outstanding = $row['owed_back'] - $row['reimbursed'];
            $projection = ($convenorPayoutProjection['cards'] ?? collect())->get($row['convenor']->id);
            $combinedPayout = $projection ? $projection['combined_cents'] / 100 : $row['final_payout'];
            $clothingRegions = $projection['regions'] ?? collect();
          ?>
          <div class="col-md-6 col-xl-4">
            <div class="border rounded p-3 h-100 bg-light">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                  <div class="fw-bold"><?php echo e($row['convenor']->user->name ?? 'Unknown'); ?></div>
                  <span class="badge <?php echo e($row['convenor']->isHoof() ? 'bg-warning text-dark' : 'bg-label-secondary'); ?> mt-1">
                    <?php echo e($row['convenor']->isHoof() ? 'Head Director' : ($row['convenor']->isHulp() ? 'Assist Director' : ucfirst($row['convenor']->role))); ?>

                  </span>
                </div>
                <span class="badge <?php echo e($combinedPayout < 0 ? 'bg-danger' : 'bg-primary'); ?> fs-6 flex-shrink-0 ms-2">
                  R <?php echo e(number_format(abs($combinedPayout), 2)); ?>

                </span>
              </div>
              <ul class="list-unstyled mb-0 small text-muted">
                <li class="d-flex justify-content-between gap-2">
                  <span>Expenses paid out:</span>
                  <span class="fw-semibold text-body text-nowrap flex-shrink-0">R <?php echo e(number_format($row['total_paid'], 2)); ?></span>
                </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['reimbursed'] > 0): ?>
                <li class="d-flex justify-content-between gap-2">
                  <span>Already reimbursed:</span>
                  <span class="text-success text-nowrap flex-shrink-0">−R <?php echo e(number_format($row['reimbursed'], 2)); ?></span>
                </li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($outstanding > 0): ?>
                <li class="d-flex justify-content-between gap-2">
                  <span>Expenses before general payments:</span>
                  <span class="text-danger fw-semibold text-nowrap flex-shrink-0">R <?php echo e(number_format($outstanding, 2)); ?></span>
                </li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['profit_share_pct'] > 0): ?>
                <li class="d-flex justify-content-between gap-2 mt-1 pt-1 border-top">
                  <span>Profit share (<?php echo e(number_format($row['profit_share_pct'], 1)); ?>% of R <?php echo e(number_format($netProfit, 2)); ?>):</span>
                  <span class="text-info fw-semibold text-nowrap flex-shrink-0">R <?php echo e(number_format($row['profit_share_amount'], 2)); ?></span>
                </li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($row['director_paid'] ?? 0) > 0): ?>
                <li class="d-flex justify-content-between gap-2">
                  <span>General payments already made:</span>
                  <span class="text-success text-nowrap flex-shrink-0">−R <?php echo e(number_format($row['director_paid'], 2)); ?></span>
                </li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <li class="d-flex justify-content-between gap-2 mt-1 pt-1 border-top fw-semibold text-body">
                  <span>Registration subtotal:</span>
                  <span class="text-nowrap flex-shrink-0">R <?php echo e(number_format($row['final_payout'], 2)); ?></span>
                </li>
              </ul>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $clothingRegions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $clothing): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="mt-3 pt-2 border-top small">
                  <div class="d-flex justify-content-between gap-2 fw-semibold">
                    <span>Clothing — <?php echo e($clothing['region_name']); ?></span>
                    <span class="text-nowrap flex-shrink-0 <?php echo e($clothing['contribution_cents'] < 0 ? 'text-danger' : 'text-primary'); ?>"><?php echo e($clothing['contribution_cents'] < 0 ? '−' : '+'); ?>R <?php echo e(number_format(abs($clothing['contribution_cents']) / 100, 2)); ?></span>
                  </div>
                  <small class="d-block <?php echo e($clothing['contribution_cents'] < 0 ? 'text-danger' : 'text-muted'); ?>"><?php echo e($clothing['contribution_cents'] < 0 ? 'Proposed recovery against this payout' : ($clothing['contribution_cents'] > 0 ? 'Clothing still payable, after pending refund reserve' : 'No clothing amount added or recovered')); ?></small>
                  <a class="d-inline-block mt-1" href="<?php echo e(route('admin.events.finances', ['event' => $event->id, 'tab' => 'clothing'])); ?>#finance-clothing-region-<?php echo e($clothing['region_id']); ?>">View clothing breakdown<span class="visually-hidden"> for <?php echo e($clothing['region_name']); ?></span></a>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothing['confirmed_recipient_link']): ?>
                    <small class="d-block text-muted">Confirmed same-person account link · <?php echo e($clothing['confirmed_recipient_link']->source_name); ?> · <?php echo e($clothing['confirmed_recipient_link']->confirmed_at->format('d M Y')); ?></small>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  <ul class="list-unstyled text-muted mt-2 mb-0">
                    <li>Already paid out: R <?php echo e(number_format($clothing['payout_cents'] / 100, 2)); ?></li>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothing['completed_refund_cents'] > 0): ?>
                      <li>
                        Completed refunds: R <?php echo e(number_format($clothing['completed_refund_cents'] / 100, 2)); ?> · <?php echo e($clothing['refunded_line_count']); ?> refunded <?php echo e($clothing['refunded_line_count'] === 1 ? 'line' : 'lines'); ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothing['recorded_quantity'] > 0): ?>
                          · <?php echo e($clothing['recorded_quantity']); ?> <?php echo e($clothing['recorded_quantity'] === 1 ? 'unit' : 'units'); ?>

                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </li>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothing['partial_amount_lines'] > 0): ?>
                        <li>Includes <?php echo e($clothing['partial_amount_lines']); ?> partial amount <?php echo e($clothing['partial_amount_lines'] === 1 ? 'line' : 'lines'); ?> with no recorded unit quantity.</li>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothing['shortfall_cents'] > 0): ?>
                      <li>Original payout difference: R <?php echo e(number_format($clothing['shortfall_cents'] / 100, 2)); ?></li>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothing['recovery_cents'] > 0): ?>
                      <li>Already recovered: R <?php echo e(number_format($clothing['recovery_cents'] / 100, 2)); ?></li>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothing['offset_cents'] !== 0): ?>
                      <li>Recorded agreed offsets: <?php echo e($clothing['offset_cents'] < 0 ? '−' : '+'); ?>R <?php echo e(number_format(abs($clothing['offset_cents']) / 100, 2)); ?></li>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothing['pending_cents'] > 0): ?>
                      <li>Pending refund liability: R <?php echo e(number_format($clothing['pending_cents'] / 100, 2)); ?> (not a completed recovery).</li>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </ul>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $clothing['refunds']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $clothingRefund): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="border rounded bg-body p-2 mt-2 text-break">
                      <div class="d-flex align-items-baseline gap-2 fw-semibold text-body">
                        <span aria-hidden="true">&bull;</span>
                        <span><?php echo e($clothingRefund['player_name']); ?></span>
                      </div>
                      <div class="ps-3">
                      <small class="d-block text-muted mt-1">
                        <?php echo e($clothingRefund['refunded_at']?->format('d M Y')); ?>

                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothingRefund['after_payout']): ?>
                          · After recorded payout
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothingRefund['after_deadline']): ?>
                          · After recorded deadline
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </small>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $clothingRefund['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $refundedItem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <small class="d-block mt-1"><?php echo e($refundedItem['quantity'] > 0 ? $refundedItem['quantity'].' × ' : 'Partial amount: '); ?><?php echo e($refundedItem['name']); ?><?php echo e($refundedItem['size'] ? ' · '.$refundedItem['size'] : ''); ?> — <span class="fw-semibold text-nowrap">R <?php echo e(number_format($refundedItem['amount_cents'] / 100, 2)); ?></span></small>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <div class="d-flex justify-content-between gap-2 mt-3 pt-2 border-top fw-bold <?php echo e($combinedPayout < 0 ? 'text-danger' : 'text-body'); ?>">
                <span><?php echo e($combinedPayout < 0 ? 'Projected amount to recover:' : 'Projected total to pay:'); ?></span>
                <span class="text-nowrap flex-shrink-0">R <?php echo e(number_format(abs($combinedPayout), 2)); ?></span>
              </div>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($convenorPayoutProjection['unmatched_regions'] ?? collect())->isNotEmpty()): ?>
        <div class="alert alert-warning mt-3 mb-0" role="status">
          <strong>Regional clothing kept separate from these director totals</strong>
          <ul class="mb-0 mt-2">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $convenorPayoutProjection['unmatched_regions']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $unmatchedClothing): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <li>
                <?php echo e($unmatchedClothing['region_name']); ?><?php echo e($unmatchedClothing['manager_name'] ? ' — '.$unmatchedClothing['manager_name'] : ''); ?>: R <?php echo e(number_format(abs($unmatchedClothing['projected_cents']) / 100, 2)); ?> <?php echo e($unmatchedClothing['balance_cents'] < 0 ? 'to recover or offset' : 'available to pay after pending refunds'); ?>. <?php echo e($unmatchedClothing['reason']); ?>

                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($unmatchedClothing['pending_cents'] > 0): ?>
                  Pending refund liability: R <?php echo e(number_format($unmatchedClothing['pending_cents'] / 100, 2)); ?>.
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </li>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </ul>
        </div>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
  </section>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php /**PATH D:\wamp64\www\ct\resources\views\backend\event\partials\convenor-payout-breakdown.blade.php ENDPATH**/ ?>