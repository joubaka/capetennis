

<?php $__env->startSection('title', $event->name . ' – Finances'); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<style>
  .finance-card { transition: all 0.2s ease; }
  .finance-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

  .convenor-header { background: #fff9c4; border-left: 4px solid #f0c040; }
  .system-row td { background: #f8f9fa; font-style: italic; }
  .deduction-row td { background: #fff5f5; color: #dc3545; font-style: italic; }
  .approved-badge { font-size: 0.7rem; }
  .budget-over { color: #dc3545; font-weight: 600; }
  .budget-under { color: #28a745; }
  .recon-table th { background: #343a40; color: #fff; }
  .cat-summary-badge { font-size: 0.75rem; min-width: 4rem; }

  .registration-transactions-toggle {
    cursor: pointer;
    transition: background-color 0.2s ease, box-shadow 0.2s ease;
  }
  .registration-transactions-toggle:hover { background: #f4fbfb !important; }
  .registration-transactions-toggle:focus-visible {
    outline: 3px solid rgba(0, 150, 136, 0.25);
    outline-offset: -3px;
  }
  .registration-transactions-action {
    color: var(--bs-primary);
    background: var(--bs-primary-bg-subtle, #e8f7f5);
    border: 1px solid rgba(0, 150, 136, 0.25);
    border-radius: 0.5rem;
    padding: 0.55rem 0.75rem;
    white-space: nowrap;
  }
  .registration-transactions-toggle .ti-chevron-down { transition: transform 0.2s ease; }
  .registration-transactions-toggle[aria-expanded="true"] .ti-chevron-down { transform: rotate(180deg); }
  .registration-transactions-toggle[aria-expanded="true"] .registration-transactions-action-label::before { content: 'Hide registrations'; }
  .registration-transactions-toggle[aria-expanded="false"] .registration-transactions-action-label::before { content: 'View registrations'; }

  @media (max-width: 575.98px) {
    .registration-transactions-toggle { align-items: flex-start !important; gap: 0.75rem; }
    .registration-transactions-action-label { display: none; }
  }

  /* Print styles */
  @media print {
    .no-print, .btn, .modal, .card-header .btn, nav, .navbar,
    .layout-menu, .layout-overlay, .layout-navbar { display: none !important; }
    .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; page-break-inside: avoid; }
    .print-header { display: block !important; }
    .print-only-row { display: table-row !important; }
    #expenseSummaryAccordion { display: block !important; }
    body { font-size: 11px; }
    .table td, .table th { padding: 4px 6px !important; }
    .container-xl { max-width: 100% !important; padding: 0 !important; }
    h5 { font-size: 13px !important; }
  }
  .print-header { display: none; }
  .print-only-row { display: none; }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">

  
  <div class="print-header mb-4 pb-3 border-bottom">
    <h3 class="mb-1"><?php echo e($event->name); ?> – Budget / Expense Statement</h3>
    <div class="d-flex gap-4 text-muted" style="font-size:0.85rem">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->start_date): ?>
        <span><strong>Date:</strong> <?php echo e($event->start_date->format('d M Y')); ?><?php echo e($event->end_date && $event->end_date->ne($event->start_date) ? ' – '.$event->end_date->format('d M Y') : ''); ?></span>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->organizer): ?>
        <span><strong>Organizer:</strong> <?php echo e($event->organizer); ?></span>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <span><strong>Generated:</strong> <?php echo e(now()->format('d M Y H:i')); ?></span>
    </div>
  </div>

  <?php echo $__env->make('backend.event.partials.header', [
    'eventWorkspaceActive' => 'finances',
    'eventWorkspaceIcon' => 'ti-report-money',
    'eventWorkspaceSubtitle' => 'Event finances',
  ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 no-print">
    <div><h2 class="h4 mb-1">Budget and expenses</h2><p class="text-muted mb-0">Income, costs, reimbursements and event payees.</p></div>
    <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#manageConvenorsModal">
          <i class="ti ti-users me-1"></i>Finance Payees
        </button>
        <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#manageVenueConvenorsModal">
          <i class="ti ti-map-pin me-1"></i>Venue Convenors
        </button>
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#manageTypesModal">
          <i class="ti ti-tags me-1"></i>Expense Types
        </button>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
          <i class="ti ti-printer me-1"></i>Print / PDF
        </button>
    </div>
  </div>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
      <i class="ti ti-circle-check me-1"></i><?php echo e(session('success')); ?>

      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
      <i class="ti ti-alert-circle me-1"></i><?php echo e(session('error')); ?>

      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($budgetCapWarning): ?>
    <div class="alert alert-warning d-flex align-items-center no-print" role="alert">
      <i class="ti ti-alert-triangle me-2 fs-4"></i>
      <div>
        <strong>Budget Warning!</strong>
        Operational spending has reached 90% of the budget cap (R <?php echo e(number_format($event->budget_cap, 2)); ?>).
      </div>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($pendingApproval > 0): ?>
    <div class="alert alert-info d-flex align-items-center no-print" role="alert">
      <i class="ti ti-clock me-2"></i>
      <?php echo e($pendingApproval); ?> expense(s) awaiting approval.
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card finance-card border-start border-primary border-3 h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1"><i class="ti ti-cash me-1 text-primary"></i>Net Income</small>
          <h5 class="mb-0">R <?php echo e(number_format($grandTotalIncome, 2)); ?></h5>
          <small class="text-muted">Gross: R <?php echo e(number_format($totalGross, 2)); ?></small>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->target_income): ?>
            <div class="progress mt-2" style="height:4px" title="R<?php echo e(number_format($grandTotalIncome,2)); ?> of R<?php echo e(number_format($event->target_income,2)); ?>">
              <div class="progress-bar bg-primary" style="width: <?php echo e(min(100, round($grandTotalIncome / $event->target_income * 100))); ?>%"></div>
            </div>
            <small class="text-muted"><?php echo e(round($grandTotalIncome / $event->target_income * 100)); ?>% of target</small>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-6 col-md-3">
      <div class="card finance-card border-start border-danger border-3 h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1"><i class="ti ti-shopping-cart me-1 text-danger"></i>Operational Expenses</small>
          <h5 class="mb-0 text-danger">R <?php echo e(number_format($totalExpenses, 2)); ?></h5>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($totalSystemFees > 0): ?>
            <small class="text-muted">+ R <?php echo e(number_format($totalSystemFees, 2)); ?> system fees</small>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->budget_cap): ?>
            <div class="progress mt-2" style="height:4px" title="R<?php echo e(number_format($totalExpenses,2)); ?> of R<?php echo e(number_format($event->budget_cap,2)); ?>">
              <div class="progress-bar <?php echo e(($totalExpenses/$event->budget_cap) >= 0.9 ? 'bg-danger' : 'bg-warning'); ?>"
                   style="width: <?php echo e(min(100, round($totalExpenses / $event->budget_cap * 100))); ?>%"></div>
            </div>
            <small class="text-muted"><?php echo e(round($totalExpenses / $event->budget_cap * 100)); ?>% of budget (R <?php echo e(number_format($event->budget_cap, 2)); ?>)</small>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-6 col-md-3">
      <div class="card finance-card border-start <?php echo e($netProfit >= 0 ? 'border-success' : 'border-danger'); ?> border-3 h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">
            <i class="ti ti-trending-<?php echo e($netProfit >= 0 ? 'up text-success' : 'down text-danger'); ?> me-1"></i>
            Net <?php echo e($netProfit >= 0 ? 'Profit' : 'Loss'); ?>

          </small>
          <h5 class="mb-0 <?php echo e($netProfit >= 0 ? 'text-success' : 'text-danger'); ?>">
            R <?php echo e(number_format(abs($netProfit), 2)); ?>

          </h5>
        </div>
      </div>
    </div>

    <div class="col-6 col-md-3">
      <div class="card finance-card border-start border-secondary border-3 h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1"><i class="ti ti-users me-1"></i>Entries</small>
          <h5 class="mb-0"><?php echo e($totalEntries); ?></h5>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($event->target_entries): ?>
            <div class="progress mt-2" style="height:4px">
              <div class="progress-bar bg-secondary" style="width: <?php echo e(min(100, round($totalEntries / $event->target_entries * 100))); ?>%"></div>
            </div>
            <small class="text-muted"><?php echo e($totalEntries); ?> of <?php echo e($event->target_entries); ?> target</small>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  
  <div class="card mb-4">
    <button class="card-header registration-transactions-toggle d-flex justify-content-between align-items-center border-0 bg-transparent text-start w-100"
            type="button" data-bs-toggle="collapse" data-bs-target="#registrationTransactionsCollapse"
            aria-expanded="false" aria-controls="registrationTransactionsCollapse">
      <div class="pe-2">
        <h5 class="mb-1"><i class="ti ti-receipt me-2 text-primary"></i>Received Transactions</h5>
        <small class="text-muted">Open to see registration and clothing receipts, payment method, fees and refunds.</small>
      </div>
      <span class="registration-transactions-action d-inline-flex align-items-center gap-2">
        <span class="badge bg-primary"><?php echo e($eventTransactions->count()); ?></span>
        <span class="registration-transactions-action-label fw-semibold"></span>
        <i class="ti ti-chevron-down" aria-hidden="true"></i>
      </span>
    </button>
    <div class="collapse" id="registrationTransactionsCollapse">
    <div class="table-responsive border-top">
      <table class="table table-hover mb-0">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>User / participant</th>
            <th>Type</th>
            <th>Method</th>
            <th>Reference</th>
            <th class="text-end">Gross</th>
            <th class="text-end">Fees</th>
            <th class="text-end">Net to event</th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $eventTransactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
              $isWithdrawal = $transaction->type === 'withdrawal';
              $gross = $isWithdrawal ? ($transaction->original_gross ?? 0) : ($transaction->gross ?? 0);
              $fees = ($transaction->fee ?? 0) + ($transaction->capeFee ?? 0);
              $details = collect($transaction->registrationDetails ?? []);
              $detailId = 'event-transaction-detail-'.$loop->index;
            ?>
            <tr>
              <td class="text-nowrap"><?php echo e(optional($transaction->created_at)->format('d M Y H:i') ?? '—'); ?></td>
              <td>
                <button type="button" class="btn btn-link p-0 text-start fw-semibold"
                        data-bs-toggle="collapse" data-bs-target="#<?php echo e($detailId); ?>"
                        aria-expanded="false" aria-controls="<?php echo e($detailId); ?>">
                  <i class="ti ti-chevron-right me-1"></i><?php echo e($transaction->user_name ?? $transaction->player ?? '—'); ?>

                </button>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($transaction->type ?? null) === 'payment' && ($transaction->entryCount ?? 1) > 1): ?>
                  <small class="text-muted d-block"><?php echo e($transaction->entryCount); ?> entries</small>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </td>
              <td>
                <span class="badge bg-label-<?php echo e($transaction->status_colour ?? 'secondary'); ?>">
                  <?php echo e($transaction->type === 'payment' ? 'Registration payment' : ($transaction->status_label ?? ucfirst($transaction->type))); ?>

                </span>
              </td>
              <td><?php echo e($transaction->method ?? $transaction->payment_method ?? '—'); ?></td>
              <td><small class="text-muted"><?php echo e($transaction->source_pf_id ?? $transaction->pf_payment_id ?? ($transaction->source_tx_id ? '#'.$transaction->source_tx_id : '—')); ?></small></td>
              <td class="text-end text-nowrap <?php echo e(!$isWithdrawal && $gross < 0 ? 'text-danger' : ''); ?>">R <?php echo e(number_format(abs($gross), 2)); ?></td>
              <td class="text-end text-nowrap <?php echo e($fees < 0 ? 'text-danger' : ''); ?>"><?php echo e($fees < 0 ? '−' : ''); ?>R <?php echo e(number_format(abs($fees), 2)); ?></td>
              <td class="text-end text-nowrap fw-semibold <?php echo e(($transaction->net ?? 0) < 0 ? 'text-danger' : 'text-success'); ?>">
                <?php echo e(($transaction->net ?? 0) < 0 ? '−' : ''); ?>R <?php echo e(number_format(abs($transaction->net ?? 0), 2)); ?>

              </td>
            </tr>
            <tr class="border-0">
              <td colspan="8" class="p-0 border-0">
                <div class="collapse" id="<?php echo e($detailId); ?>">
                  <div class="p-3 bg-light border-bottom">
                    <div class="row g-2 mb-3 small">
                      <div class="col-md-3"><span class="text-muted">Payment reference</span><div class="fw-semibold"><?php echo e($transaction->source_pf_id ?? $transaction->pf_payment_id ?? '—'); ?></div></div>
                      <div class="col-md-3"><span class="text-muted">Transaction ID</span><div class="fw-semibold"><?php echo e($transaction->source_tx_id ? '#'.$transaction->source_tx_id : '—'); ?></div></div>
                      <div class="col-md-3"><span class="text-muted">Payment method</span><div class="fw-semibold"><?php echo e($transaction->method ?? $transaction->payment_method ?? '—'); ?></div></div>
                      <div class="col-md-3"><span class="text-muted">Entries</span><div class="fw-semibold"><?php echo e($details->count() ?: ($transaction->entryCount ?? 1)); ?></div></div>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-sm table-bordered bg-white mb-0">
                        <thead><tr><th>Registered player</th><th>Category</th><th class="text-end">Entry amount</th></tr></thead>
                        <tbody>
                          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $details; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                            <tr>
                              <td><?php echo e($detail['player'] ?? '—'); ?></td>
                              <td><?php echo e($detail['category'] ?? '—'); ?></td>
                              <td class="text-end">R <?php echo e(number_format($detail['price'] ?? 0, 2)); ?></td>
                            </tr>
                          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                            <tr><td colspan="3" class="text-center text-muted">No linked player details were recorded for this transaction.</td></tr>
                          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No registration transactions have been recorded for this event.</td></tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
    </div>
  </div>

  
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="ti ti-cash me-2 text-success"></i>Income</h5>
      <button class="btn btn-success btn-sm no-print" data-bs-toggle="modal" data-bs-target="#addIncomeModal">
        <i class="ti ti-plus me-1"></i>Add Income
      </button>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Description</th>
              <th class="text-center">Quantity</th>
              <th class="text-end">Unit Price</th>
              <th>Source</th>
              <th>Date</th>
              <th class="text-end">Total</th>
              <th class="no-print" style="width:80px"></th>
            </tr>
          </thead>
          <tbody>
            
            <tr>
              <td>
                <span class="badge bg-label-primary me-1">System</span>
                Registration received
              </td>
              <td class="text-center"><?php echo e($totalEntries); ?></td>
              <td class="text-end">—</td>
              <td><small class="text-muted">PayFast transactions</small></td>
              <td>—</td>
              <td class="text-end fw-semibold text-success">R <?php echo e(number_format($registrationReceived, 2)); ?></td>
              <td class="no-print"></td>
            </tr>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothingReceived > 0): ?>
              <tr>
                <td><span class="badge bg-label-primary me-1">System</span>Clothing received</td>
                <td class="text-center"><?php echo e($eventTransactions->where('type', 'clothing_payment')->count()); ?></td>
                <td class="text-end">—</td>
                <td><small class="text-muted">Paid clothing orders</small></td>
                <td>—</td>
                <td class="text-end fw-semibold text-success">R <?php echo e(number_format($clothingReceived, 2)); ?></td>
                <td class="no-print"></td>
              </tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(abs($totalPayfastFees) > 0): ?>
              <tr class="deduction-row">
                <td class="ps-4">
                  <i class="ti ti-minus me-1"></i>PayFast fees deducted (registrations and clothing)
                </td>
                <td class="text-center">&mdash;</td>
                <td class="text-end">&mdash;</td>
                <td><small class="text-muted">Recorded PayFast fees</small></td>
                <td>&mdash;</td>
                <td class="text-end fw-semibold">&minus;R <?php echo e(number_format(abs($totalPayfastFees), 2)); ?></td>
                <td class="no-print"></td>
              </tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($totalCapeTennisFees > 0): ?>
              <tr class="deduction-row">
                <td class="ps-4">
                  <i class="ti ti-minus me-1"></i>Cape Tennis fee deducted
                  <small class="text-muted ms-1">(<?php echo e($totalEntries); ?> × R<?php echo e(number_format($feePerEntry, 2)); ?>)</small>
                </td>
                <td class="text-center"><?php echo e($totalEntries); ?></td>
                <td class="text-end">R <?php echo e(number_format($feePerEntry, 2)); ?></td>
                <td><small class="text-muted">Cape Tennis</small></td>
                <td>—</td>
                <td class="text-end fw-semibold">−R <?php echo e(number_format($totalCapeTennisFees, 2)); ?></td>
                <td class="no-print"></td>
              </tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(abs($totalPayfastFees) > 0 || $totalCapeTennisFees > 0): ?>
              <tr class="table-light fw-semibold">
                <td colspan="5" class="text-end text-muted" style="font-size:0.85rem">Net Registration and Clothing Income</td>
                <td class="text-end text-success">R <?php echo e(number_format($netRegistrationIncome + $clothingNet, 2)); ?></td>
                <td class="no-print"></td>
              </tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothingReceipts['count'] > 0): ?>
              <tr>
                <td colspan="7" class="p-0">
                  <div class="px-3 py-3 border-top">
                    <h6 class="mb-1">Clothing payable by region</h6>
                    <p class="text-muted small mb-0">Paid clothing receipts minus the PayFast fee recorded on each order. Net amounts are shown for each region's convenor.</p>
                  </div>
                  <div class="table-responsive">
                    <table class="table table-sm mb-0" aria-label="Clothing payable by region">
                      <thead class="table-light">
                        <tr>
                          <th class="ps-3">Region</th>
                          <th class="text-center">Paid orders</th>
                          <th class="text-end">Clothing received</th>
                          <th class="text-end">Recorded PayFast fee</th>
                          <th class="text-end pe-3">Net payable to convenor</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $clothingReceipts['groups']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region => $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                          <tr>
                            <td class="ps-3"><?php echo e($region); ?></td>
                            <td class="text-center"><?php echo e($group['rows']->count()); ?></td>
                            <td class="text-end text-nowrap">R <?php echo e(number_format($group['totals']['gross'], 2)); ?></td>
                            <td class="text-end text-nowrap">&minus;R <?php echo e(number_format(abs($group['totals']['fees']), 2)); ?></td>
                            <td class="text-end text-nowrap fw-semibold pe-3">R <?php echo e(number_format($group['totals']['net'], 2)); ?></td>
                          </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </tbody>
                      <tfoot class="table-light fw-semibold">
                        <tr>
                          <td class="ps-3">Total clothing</td>
                          <td class="text-center"><?php echo e($clothingReceipts['count']); ?></td>
                          <td class="text-end text-nowrap">R <?php echo e(number_format($clothingReceipts['totals']['gross'], 2)); ?></td>
                          <td class="text-end text-nowrap">&minus;R <?php echo e(number_format(abs($clothingReceipts['totals']['fees']), 2)); ?></td>
                          <td class="text-end text-nowrap pe-3">R <?php echo e(number_format($clothingReceipts['totals']['net'], 2)); ?></td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothingReceipts['groups']->has('Region not recorded')): ?>
                    <p class="small text-warning px-3 py-2 mb-0">Confirm the region for unassigned receipts before paying a convenor.</p>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
              </tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $incomeItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <td><?php echo e($item->label); ?></td>
                <td class="text-center"><?php echo e($item->quantity ? number_format($item->quantity, 0) : '—'); ?></td>
                <td class="text-end"><?php echo e($item->unit_price ? 'R '.number_format($item->unit_price, 2) : '—'); ?></td>
                <td><small class="text-muted"><?php echo e($item->source ?? '—'); ?></small></td>
                <td><?php echo e($item->date?->format('d M Y') ?? '—'); ?></td>
                <td class="text-end fw-semibold text-success">R <?php echo e(number_format($item->calculatedTotal(), 2)); ?></td>
                <td class="text-center no-print">
                  <button class="btn btn-icon btn-sm btn-outline-primary"
                          data-bs-toggle="modal" data-bs-target="#editIncomeModal<?php echo e($item->id); ?>">
                    <i class="ti ti-edit"></i>
                  </button>
                  <form action="<?php echo e(route('admin.events.finances.income.destroy', $item)); ?>" method="POST" class="d-inline"
                        data-ajax="1" data-confirm="Delete this income item?">
                    <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                    <button class="btn btn-icon btn-sm btn-outline-danger"><i class="ti ti-trash"></i></button>
                  </form>
                </td>
              </tr>

              
              <div class="modal fade" id="editIncomeModal<?php echo e($item->id); ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form action="<?php echo e(route('admin.events.finances.income.update', $item)); ?>" method="POST"
                          data-ajax="1" data-modal="editIncomeModal<?php echo e($item->id); ?>">
                      <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
                      <div class="modal-header">
                        <h5 class="modal-title">Edit Income Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <?php echo $__env->make('backend.event._income_item_fields', ['item' => $item], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
          <tfoot class="table-light">
            <tr>
              <td colspan="5" class="fw-bold">Total Net Income</td>
              <td class="text-end fw-bold text-success">R <?php echo e(number_format($grandTotalIncome, 2)); ?></td>
              <td class="no-print"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0"><i class="ti ti-list me-2"></i>Expenses per Convenor</h5>
    <button class="btn btn-primary btn-sm no-print" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
      <i class="ti ti-plus me-1"></i>Add Expense
    </button>
  </div>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expensesByType->isNotEmpty()): ?>
    <div class="card mb-3 no-print">
      <div class="card-header py-2">
        <button class="btn btn-link p-0 text-decoration-none fw-semibold text-dark"
                data-bs-toggle="collapse" data-bs-target="#expenseSummaryAccordion">
          <i class="ti ti-chart-pie me-1 text-muted"></i>Expense Summary by Category
          <i class="ti ti-chevron-down ms-1 text-muted" style="font-size:0.8rem"></i>
        </button>
      </div>
      <div class="collapse" id="expenseSummaryAccordion">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-sm mb-0">
              <thead class="table-secondary">
                <tr>
                  <th>Category</th>
                  <th class="text-center">Items</th>
                  <th class="text-end">Budget</th>
                  <th class="text-end">Actual</th>
                  <th class="text-end">Variance</th>
                  <th class="text-end">% of Total</th>
                </tr>
              </thead>
              <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $expensesByType->sortKeys(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type => $typeExpenses): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <?php
                    $typeActual  = $typeExpenses->sum(fn($e) => $e->calculatedAmount());
                    $typeBudget  = $typeExpenses->whereNotNull('budget_amount')->sum('budget_amount');
                    $typeVariance = $typeBudget > 0 ? $typeBudget - $typeActual : null;
                    $typePct     = $totalExpenses > 0 ? round($typeActual / $totalExpenses * 100) : 0;
                  ?>
                  <tr>
                    <td>
                      <span class="badge bg-label-secondary">
                        <?php echo e($expenseTypes[$type] ?? ucfirst($type)); ?>

                      </span>
                    </td>
                    <td class="text-center"><?php echo e($typeExpenses->count()); ?></td>
                    <td class="text-end"><?php echo e($typeBudget > 0 ? 'R '.number_format($typeBudget, 2) : '—'); ?></td>
                    <td class="text-end fw-semibold">R <?php echo e(number_format($typeActual, 2)); ?></td>
                    <td class="text-end">
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($typeVariance !== null): ?>
                        <span class="<?php echo e($typeVariance >= 0 ? 'budget-under' : 'budget-over'); ?>">
                          <?php echo e($typeVariance >= 0 ? '+' : ''); ?>R <?php echo e(number_format($typeVariance, 2)); ?>

                        </span>
                      <?php else: ?>
                        —
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </td>
                    <td class="text-end">
                      <span class="badge bg-label-secondary cat-summary-badge"><?php echo e($typePct); ?>%</span>
                    </td>
                  </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </tbody>
              <tfoot class="table-light">
                <tr>
                  <td class="fw-bold">Total</td>
                  <td class="text-center fw-bold"><?php echo e($expensesByType->flatten()->count()); ?></td>
                  <td class="text-end fw-bold"><?php echo e($totalBudget > 0 ? 'R '.number_format($totalBudget, 2) : '—'); ?></td>
                  <td class="text-end fw-bold text-danger">R <?php echo e(number_format($totalExpenses, 2)); ?></td>
                  <td class="text-end fw-bold">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($totalBudget > 0): ?>
                      <?php $totalVar = $totalBudget - $totalExpenses; ?>
                      <span class="<?php echo e($totalVar >= 0 ? 'budget-under' : 'budget-over'); ?>">
                        <?php echo e($totalVar >= 0 ? '+' : ''); ?>R <?php echo e(number_format($totalVar, 2)); ?>

                      </span>
                    <?php else: ?>
                      —
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($convenors->count() > 0): ?>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $convenors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $convenor): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <?php
        $cExpenses = $expensesByConvenor->get($convenor->id, collect());
        $cTotal    = $cExpenses->sum(fn($e) => $e->calculatedAmount());
        $roleLabel = match($convenor->role) {
          'hoof'  => 'Head Director',
          'hulp'  => 'Assist Director',
          default => ucfirst($convenor->role),
        };
      ?>
      <div class="card mb-3">
        <div class="card-header convenor-header d-flex justify-content-between align-items-center">
          <div>
            <strong>Paid by <?php echo e($convenor->user->name ?? 'Unknown'); ?></strong>
            <span class="badge bg-warning text-dark ms-2"><?php echo e($roleLabel); ?></span>
          </div>
          <span class="fw-bold">R <?php echo e(number_format($cTotal, 2)); ?></span>
        </div>
        <div class="card-body p-0">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($cExpenses->count() > 0): ?>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Type</th>
                    <th>Recipient / Description</th>
                    <th class="text-center">Quantity × Price</th>
                    <th class="text-end">Budget</th>
                    <th class="text-end">Actual</th>
                    <th class="text-end">Variance</th>
                    <th>Status</th>
                    <th class="no-print" style="width:120px"></th>
                  </tr>
                </thead>
                <tbody>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $cExpenses->sortBy('expense_type'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $expense): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                      $calcAmt  = $expense->calculatedAmount();
                      $variance = $expense->budgetVariance();
                    ?>
                    <tr class="expense-row">
                      <td>
                        <span class="badge bg-label-secondary">
                          <?php echo e($expenseTypes[$expense->expense_type] ?? ucfirst($expense->expense_type)); ?>

                        </span>
                      </td>
                      <td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expense->recipient_name): ?>
                          <strong><?php echo e($expense->recipient_name); ?></strong><br>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php echo e($expense->description ?? '—'); ?>

                      </td>
                      <td class="text-center">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expense->quantity && $expense->unit_price): ?>
                          <?php echo e(number_format($expense->quantity, 0)); ?> × R<?php echo e(number_format($expense->unit_price, 2)); ?>

                        <?php else: ?>
                          —
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </td>
                      <td class="text-end">
                        <?php echo e($expense->budget_amount ? 'R '.number_format($expense->budget_amount, 2) : '—'); ?>

                      </td>
                      <td class="text-end fw-semibold">R <?php echo e(number_format($calcAmt, 2)); ?></td>
                      <td class="text-end">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($variance !== null): ?>
                          <span class="<?php echo e($variance >= 0 ? 'budget-under' : 'budget-over'); ?>">
                            <?php echo e($variance >= 0 ? '+' : ''); ?>R <?php echo e(number_format($variance, 2)); ?>

                          </span>
                        <?php else: ?>
                          —
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </td>
                      <td>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expense->approved_at): ?>
                          <span class="badge bg-success approved-badge" title="Approved by <?php echo e($expense->approvedByUser?->name); ?>">
                            <i class="ti ti-check"></i> Approved
                          </span>
                        <?php else: ?>
                          <span class="badge bg-label-warning approved-badge">Pending</span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expense->reimbursed_at): ?>
                          <span class="badge bg-label-success approved-badge mt-1 d-block" title="Reimbursed to <?php echo e($convenor->user->name); ?>">
                            <i class="ti ti-coin"></i> Reimbursed
                          </span>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expense->receipt_path): ?>
                          <a href="<?php echo e(asset('storage/'.$expense->receipt_path)); ?>" target="_blank"
                             class="badge bg-label-primary approved-badge mt-1 d-block">
                            <i class="ti ti-paperclip"></i> Receipt
                          </a>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      </td>
                      <td class="text-center no-print">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$expense->approved_at): ?>
                          <form action="<?php echo e(route('admin.events.finances.expense.approve', $expense)); ?>" method="POST" class="d-inline" data-ajax="1">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-icon btn-sm btn-outline-success" title="Approve">
                              <i class="ti ti-check"></i>
                            </button>
                          </form>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expense->approved_at && !$expense->reimbursed_at): ?>
                          <form action="<?php echo e(route('admin.events.finances.expense.reimburse', $expense)); ?>" method="POST" class="d-inline" data-ajax="1">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-icon btn-sm btn-outline-info" title="Mark as reimbursed">
                              <i class="ti ti-coin"></i>
                            </button>
                          </form>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <button type="button"
                                class="btn btn-icon btn-sm btn-outline-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#editExpenseModal<?php echo e($expense->id); ?>">
                          <i class="ti ti-edit"></i>
                        </button>
                        <form action="<?php echo e(route('admin.events.finances.expense.destroy', $expense)); ?>"
                              method="POST" class="d-inline"
                              data-ajax="1" data-confirm="Delete this expense?">
                          <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                          <button class="btn btn-icon btn-sm btn-outline-danger"><i class="ti ti-trash"></i></button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </tbody>
                <tfoot class="table-light">
                  <tr>
                    <td colspan="4" class="fw-bold">Subtotal – <?php echo e($convenor->user->name ?? 'Unknown'); ?></td>
                    <td class="text-end fw-bold">R <?php echo e(number_format($cTotal, 2)); ?></td>
                    <td colspan="3"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          <?php else: ?>
            <div class="text-center py-3 text-muted">
              No expenses.
            </div>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <?php
    $unassigned      = $expensesByConvenor->get(null, collect());
    $unassignedTotal = $unassigned->sum(fn($e) => $e->calculatedAmount());
  ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($unassigned->count() > 0): ?>
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <strong><i class="ti ti-question-mark me-1 text-muted"></i>No Event Director Assigned</strong>
        <span class="fw-bold">R <?php echo e(number_format($unassignedTotal, 2)); ?></span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Type</th>
                <th>Description</th>
                <th class="text-center">Quantity × Price</th>
                <th class="text-end">Budget</th>
                <th class="text-end">Actual</th>
                <th class="text-end">Variance</th>
                <th class="no-print" style="width:100px"></th>
              </tr>
            </thead>
            <tbody>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $unassigned; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $expense): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                  $calcAmt  = $expense->calculatedAmount();
                  $variance = $expense->budgetVariance();
                ?>
                <tr>
                  <td><span class="badge bg-label-secondary"><?php echo e($expenseTypes[$expense->expense_type] ?? ucfirst($expense->expense_type)); ?></span></td>
                  <td><?php echo e($expense->description ?? '—'); ?></td>
                  <td class="text-center">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expense->quantity && $expense->unit_price): ?>
                      <?php echo e(number_format($expense->quantity,0)); ?> × R<?php echo e(number_format($expense->unit_price,2)); ?>

                    <?php else: ?>
                      —
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </td>
                  <td class="text-end"><?php echo e($expense->budget_amount ? 'R '.number_format($expense->budget_amount, 2) : '—'); ?></td>
                  <td class="text-end fw-semibold">R <?php echo e(number_format($calcAmt, 2)); ?></td>
                  <td class="text-end">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($variance !== null): ?>
                      <span class="<?php echo e($variance >= 0 ? 'budget-under' : 'budget-over'); ?>">
                        <?php echo e($variance >= 0 ? '+' : ''); ?>R <?php echo e(number_format($variance, 2)); ?>

                      </span>
                    <?php else: ?>
                      —
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </td>
                  <td class="text-center no-print">
                    <button class="btn btn-icon btn-sm btn-outline-primary"
                            data-bs-toggle="modal" data-bs-target="#editExpenseModal<?php echo e($expense->id); ?>">
                      <i class="ti ti-edit"></i>
                    </button>
                    <form action="<?php echo e(route('admin.events.finances.expense.destroy', $expense)); ?>" method="POST" class="d-inline"
                          data-ajax="1" data-confirm="Delete this expense?">
                      <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                      <button class="btn btn-icon btn-sm btn-outline-danger"><i class="ti ti-trash"></i></button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <div class="card mb-4">
    <div class="card-header">
      <h5 class="mb-0"><i class="ti ti-arrows-exchange me-2"></i>Reconciliation</h5>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table recon-table mb-0">
          <thead>
            <tr>
              <th>Convenor</th>
              <th>Role</th>
              <th class="text-end">Paid Out</th>
              <th class="text-end">Reimbursed</th>
              <th class="text-end">Outstanding</th>
              <th class="text-end">Profit Share</th>
              <th class="text-end">Final Payout</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $recon; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php
                $outstanding = $row['owed_back'] - $row['reimbursed'];
              ?>
              <tr>
                <td class="fw-semibold"><?php echo e($row['convenor']->user->name ?? 'Unknown'); ?></td>
                <td>
                  <span class="badge <?php echo e($row['convenor']->isHoof() ? 'bg-warning text-dark' : 'bg-label-secondary'); ?>">
                    <?php echo e($row['convenor']->isHoof() ? 'Head Director' : ($row['convenor']->isHulp() ? 'Assist Director' : ucfirst($row['convenor']->role))); ?>

                  </span>
                </td>
                <td class="text-end">R <?php echo e(number_format($row['total_paid'], 2)); ?></td>
                <td class="text-end text-success">R <?php echo e(number_format($row['reimbursed'], 2)); ?></td>
                <td class="text-end <?php echo e($outstanding > 0 ? 'text-danger fw-bold' : 'text-success'); ?>">
                  R <?php echo e(number_format($outstanding, 2)); ?>

                </td>
                <td class="text-end">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['profit_share_pct'] > 0): ?>
                    <span class="text-info"><?php echo e(number_format($row['profit_share_pct'], 1)); ?>%</span>
                    <small class="text-muted d-block">R <?php echo e(number_format($row['profit_share_amount'], 2)); ?></small>
                  <?php else: ?>
                    <span class="text-muted">—</span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
                <td class="text-end fw-bold <?php echo e($row['final_payout'] > 0 ? 'text-primary' : 'text-muted'); ?>">
                  R <?php echo e(number_format($row['final_payout'], 2)); ?>

                </td>
                <td>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($outstanding <= 0): ?>
                    <span class="badge bg-success"><i class="ti ti-check me-1"></i>Settled</span>
                  <?php else: ?>
                    <span class="badge bg-danger"><i class="ti ti-alert-circle me-1"></i>Outstanding</span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
          <tfoot class="table-light">
            <tr>
              <td colspan="2" class="fw-bold">Recon Total</td>
              <td class="text-end fw-bold">R <?php echo e(number_format($recon->sum('total_paid'), 2)); ?></td>
              <td class="text-end fw-bold text-success">R <?php echo e(number_format($recon->sum('reimbursed'), 2)); ?></td>
              <td class="text-end fw-bold <?php echo e($recon->sum(fn($r) => $r['owed_back'] - $r['reimbursed']) > 0 ? 'text-danger' : 'text-success'); ?>">
                R <?php echo e(number_format($recon->sum(fn($r) => $r['owed_back'] - $r['reimbursed']), 2)); ?>

              </td>
              <td class="text-end fw-bold text-info">
                R <?php echo e(number_format($recon->sum('profit_share_amount'), 2)); ?>

              </td>
              <td class="text-end fw-bold text-primary">
                R <?php echo e(number_format($recon->sum('final_payout'), 2)); ?>

              </td>
              <td></td>
            </tr>
            <tr class="table-secondary">
              <td colspan="2"><small class="text-muted">Gross Registration Income</small></td>
              <td colspan="5" class="text-end fw-semibold text-success">R <?php echo e(number_format($totalGross, 2)); ?></td>
              <td></td>
            </tr>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($totalSystemFees > 0): ?>
              <tr class="table-secondary">
                <td colspan="2"><small class="text-muted">System Fees (PayFast + Cape Tennis – deducted from gross)</small></td>
                <td colspan="5" class="text-end fw-semibold text-danger">−R <?php echo e(number_format($totalSystemFees, 2)); ?></td>
                <td></td>
              </tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($totalIncomeItems > 0): ?>
              <tr class="table-secondary">
                <td colspan="2"><small class="text-muted">Other Income Items</small></td>
                <td colspan="5" class="text-end fw-semibold text-success">R <?php echo e(number_format($totalIncomeItems, 2)); ?></td>
                <td></td>
              </tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <tr class="table-secondary">
              <td colspan="2"><small class="text-muted">Total Net Income</small></td>
              <td colspan="5" class="text-end fw-semibold text-success">R <?php echo e(number_format($grandTotalIncome, 2)); ?></td>
              <td></td>
            </tr>
            <tr class="table-secondary">
              <td colspan="2"><small class="text-muted">Operational Expenses</small></td>
              <td colspan="5" class="text-end fw-semibold text-danger">R <?php echo e(number_format($totalExpenses, 2)); ?></td>
              <td></td>
            </tr>
            <tr class="<?php echo e($netProfit >= 0 ? 'table-success' : 'table-danger'); ?>">
              <td colspan="2" class="fw-bold">Net <?php echo e($netProfit >= 0 ? 'Profit' : 'Loss'); ?></td>
              <td colspan="5" class="text-end fw-bold">R <?php echo e(number_format(abs($netProfit), 2)); ?></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

<div class="toast-container position-fixed bottom-0 end-0 p-3" id="financeToastContainer" style="z-index:1200"></div>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($recon->count()): ?>
  <div class="card mb-4 no-print">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="ti ti-cash me-2"></i>Convenor Payout Breakdown</h5>
      <small class="text-muted">Net Profit: <strong class="<?php echo e($netProfit >= 0 ? 'text-success' : 'text-danger'); ?>">R <?php echo e(number_format(abs($netProfit), 2)); ?></strong></small>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $recon; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
            $outstanding = $row['owed_back'] - $row['reimbursed'];
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
                <span class="badge <?php echo e($row['final_payout'] > 0 ? 'bg-primary' : 'bg-success'); ?> fs-6">
                  R <?php echo e(number_format($row['final_payout'], 2)); ?>

                </span>
              </div>
              <ul class="list-unstyled mb-0 small text-muted">
                <li class="d-flex justify-content-between">
                  <span>Expenses paid out:</span>
                  <span class="fw-semibold text-body">R <?php echo e(number_format($row['total_paid'], 2)); ?></span>
                </li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['reimbursed'] > 0): ?>
                <li class="d-flex justify-content-between">
                  <span>Already reimbursed:</span>
                  <span class="text-success">−R <?php echo e(number_format($row['reimbursed'], 2)); ?></span>
                </li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($outstanding > 0): ?>
                <li class="d-flex justify-content-between">
                  <span>Expenses still owed:</span>
                  <span class="text-danger fw-semibold">R <?php echo e(number_format($outstanding, 2)); ?></span>
                </li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['profit_share_pct'] > 0): ?>
                <li class="d-flex justify-content-between mt-1 pt-1 border-top">
                  <span>Profit share (<?php echo e(number_format($row['profit_share_pct'], 1)); ?>% of R <?php echo e(number_format($netProfit, 2)); ?>):</span>
                  <span class="text-info fw-semibold">R <?php echo e(number_format($row['profit_share_amount'], 2)); ?></span>
                </li>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <li class="d-flex justify-content-between mt-1 pt-1 border-top fw-bold text-body">
                  <span>Total to pay:</span>
                  <span class="text-primary">R <?php echo e(number_format($row['final_payout'], 2)); ?></span>
                </li>
              </ul>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($venueEntrySummary->count()): ?>
  <div class="card mb-4 no-print">
    <div class="card-header">
      <h5 class="mb-0"><i class="ti ti-map-pin me-2"></i>Venue Entry Summary</h5>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venueEntrySummary; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vs): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <div class="col-md-4 col-sm-6">
            <div class="border rounded p-3 bg-light">
              <div class="fw-bold mb-1"><i class="ti ti-map-pin text-primary me-1"></i><?php echo e($vs->name); ?></div>
              <div class="text-muted small">
                <span class="fw-semibold text-body fs-5"><?php echo e(number_format($vs->entry_count)); ?></span>
                entr<?php echo e($vs->entry_count === 1 ? 'y' : 'ies'); ?>

              </div>
            </div>
          </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <p class="text-muted small mt-3 mb-0">
        <i class="ti ti-info-circle me-1"></i>Entry counts are based on registrations in draw categories assigned to each venue.
      </p>
    </div>
  </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div>




<div class="modal fade" id="addExpenseModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="<?php echo e(route('admin.events.finances.expense.store', $event)); ?>" method="POST" enctype="multipart/form-data"
            data-ajax="1" data-modal="addExpenseModal">
        <?php echo csrf_field(); ?>
        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Expense</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php echo $__env->make('backend.event._expense_fields', ['expense' => null, 'convenors' => $convenors, 'expenseTypes' => $expenseTypes, 'multiPaidBy' => true, 'venueConvenors' => $venueConvenors], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i>Add</button>
        </div>
      </form>
    </div>
  </div>
</div>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $expenses->reject(fn($e) => in_array($e->expense_type, ['payfast', 'cape_tennis_fee'])); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $expense): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div class="modal fade" id="editExpenseModal<?php echo e($expense->id); ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form action="<?php echo e(route('admin.events.finances.expense.update', $expense)); ?>" method="POST" enctype="multipart/form-data"
              data-ajax="1" data-modal="editExpenseModal<?php echo e($expense->id); ?>">
          <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
          <div class="modal-header">
            <h5 class="modal-title">Edit Expense</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <?php echo $__env->make('backend.event._expense_fields', ['expense' => $expense, 'convenors' => $convenors, 'expenseTypes' => $expenseTypes, 'venueConvenors' => $venueConvenors], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<div class="modal fade" id="addIncomeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="<?php echo e(route('admin.events.finances.income.store', $event)); ?>" method="POST"
            data-ajax="1" data-modal="addIncomeModal">
        <?php echo csrf_field(); ?>
        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Income</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php echo $__env->make('backend.event._income_item_fields', ['item' => null], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="ti ti-check me-1"></i>Add</button>
        </div>
      </form>
    </div>
  </div>
</div>


<div class="modal fade" id="manageConvenorsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-users me-2"></i>Manage Event Directors – <?php echo e($event->name); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($convenors->count()): ?>
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Profit %</th>
                <th>Active From</th>
                <th>Expires</th>
                <th style="width:90px"></th>
              </tr>
            </thead>
            <tbody>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $convenors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                  <td class="align-middle"><?php echo e($c->user->name ?? '—'); ?></td>
                  <td class="align-middle">
                    <span class="badge <?php echo e($c->isHoof() ? 'bg-warning text-dark' : 'bg-label-secondary'); ?>">
                      <?php echo e($c->isHoof() ? 'Head' : ($c->isHulp() ? 'Assist' : ucfirst($c->role))); ?>

                    </span>
                  </td>
                  <td class="align-middle">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c->profit_share_pct !== null && $c->profit_share_pct > 0): ?>
                      <span class="badge bg-label-info"><?php echo e(number_format((float)$c->profit_share_pct, 1)); ?>%</span>
                    <?php else: ?>
                      <span class="text-muted">—</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </td>
                  <td class="align-middle">
                    <small><?php echo e($c->starts_at ? $c->starts_at->format('d M Y') : '—'); ?></small>
                  </td>
                  <td class="align-middle">
                    <small><?php echo e($c->expires_at ? $c->expires_at->format('d M Y') : '—'); ?></small>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($c->expires_at && !$c->isActive()): ?>
                      <span class="badge bg-danger ms-1" style="font-size:0.65rem">Expired</span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </td>
                  <td class="text-end align-middle">
                    <button type="button" class="btn btn-icon btn-sm btn-outline-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#editConvenorModal<?php echo e($c->id); ?>"
                            title="Edit">
                      <i class="ti ti-edit"></i>
                    </button>
                    <form action="<?php echo e(route('admin.events.finances.convenor.destroy', $c)); ?>"
                          method="POST" class="d-inline"
                          data-ajax="1" data-confirm="Remove <?php echo e($c->user->name ?? 'this event director'); ?> from this event?">
                      <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                      <button class="btn btn-icon btn-sm btn-outline-danger" title="Remove">
                        <i class="ti ti-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </tbody>
          </table>
        <?php else: ?>
          <p class="text-muted text-center py-3">No event directors assigned yet.</p>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        <hr class="m-0">

        
        <div class="p-3">
          <h6 class="mb-3"><i class="ti ti-plus me-1"></i>Add Event Director(s)</h6>
          <form action="<?php echo e(route('admin.events.finances.convenor.store', $event)); ?>" method="POST"
                data-ajax="1" data-modal="manageConvenorsModal">
            <?php echo csrf_field(); ?>
            <div class="row g-2">
              <div class="col-md-6">
                <label class="form-label">User(s) <span class="text-danger">*</span></label>
                <select name="user_ids[]" id="convenorUserSelect" class="form-select convenor-user-select" multiple required>
                </select>
                <small class="text-muted">Search and select one or more people.</small>
              </div>
              <div class="col-md-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                  <option value="hulp">Assist Director</option>
                  <option value="hoof">Head Director</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Profit Share %</label>
                <div class="input-group">
                  <input type="number" name="profit_share_pct" class="form-control"
                         min="0" max="100" step="0.1" placeholder="e.g. 25">
                  <span class="input-group-text">%</span>
                </div>
              </div>
              <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary btn-sm">
                  <i class="ti ti-user-plus me-1"></i>Add Event Director(s)
                </button>
              </div>
            </div>
          </form>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $convenors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
  <div class="modal fade" id="editConvenorModal<?php echo e($c->id); ?>" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form action="<?php echo e(route('admin.events.finances.convenor.update', $c)); ?>" method="POST"
              data-ajax="1" data-modal="editConvenorModal<?php echo e($c->id); ?>">
          <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
          <div class="modal-header">
            <h5 class="modal-title">Edit Event Director – <?php echo e($c->user->name ?? '?'); ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                  <option value="hulp"  <?php echo e($c->role === 'hulp'  ? 'selected' : ''); ?>>Assist Director</option>
                  <option value="hoof"  <?php echo e($c->role === 'hoof'  ? 'selected' : ''); ?>>Head Director</option>
                  <option value="admin" <?php echo e($c->role === 'admin' ? 'selected' : ''); ?>>Admin</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Profit Share %
                  <small class="text-muted ms-1">(0–100, leave blank for none)</small>
                </label>
                <div class="input-group">
                  <input type="number" name="profit_share_pct" class="form-control"
                         min="0" max="100" step="0.1"
                         value="<?php echo e($c->profit_share_pct !== null ? number_format((float)$c->profit_share_pct, 1) : ''); ?>"
                         placeholder="e.g. 25">
                  <span class="input-group-text">%</span>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Active From</label>
                <input type="date" name="starts_at" class="form-control"
                       value="<?php echo e($c->starts_at?->format('Y-m-d')); ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label">Expires</label>
                <input type="date" name="expires_at" class="form-control"
                       value="<?php echo e($c->expires_at?->format('Y-m-d')); ?>">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<div class="modal fade" id="manageVenueConvenorsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-map-pin me-2"></i>Venue Convenors – <?php echo e($event->name); ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">

        
        <div id="venueConvenorList">
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($venueConvenors->count()): ?>
            <table class="table table-sm mb-0" id="vcTable">
              <thead class="table-light">
                <tr>
                  <th>Name</th>
                  <th style="width:80px"></th>
                </tr>
              </thead>
              <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $venueConvenors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $vc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <tr id="vc-row-<?php echo e($vc->id); ?>">
                    <td class="align-middle"><?php echo e($vc->name); ?></td>
                    <td class="text-end align-middle">
                      <form action="<?php echo e(route('admin.events.finances.venue-convenor.destroy', $vc)); ?>"
                            method="POST" class="d-inline"
                            data-ajax="1" data-confirm="Remove <?php echo e($vc->name); ?> from this event?">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                        <button class="btn btn-icon btn-sm btn-outline-danger" title="Remove">
                          <i class="ti ti-trash"></i>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="text-muted text-center py-3" id="vcEmptyMsg">No venue convenors added yet.</p>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>

        <hr class="m-0">

        
        <div class="p-3">
          <h6 class="mb-3"><i class="ti ti-plus me-1"></i>Add Venue Convenor</h6>
          <form action="<?php echo e(route('admin.events.finances.venue-convenor.store', $event)); ?>" method="POST"
                data-ajax="1" data-modal="manageVenueConvenorsModal" id="addVenueConvenorForm">
            <?php echo csrf_field(); ?>
            <div class="row g-2 align-items-end">
              <div class="col">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" name="name" class="form-control"
                       placeholder="e.g. Ingrid Le Roux" required maxlength="150">
                <small class="text-muted">The person hired to convene a venue on behalf of the directors.</small>
              </div>
              <div class="col-auto">
                <button type="submit" class="btn btn-primary btn-sm">
                  <i class="ti ti-plus me-1"></i>Add
                </button>
              </div>
            </div>
          </form>
        </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>


<div class="modal fade" id="manageTypesModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-tags me-2"></i>Manage Expense Types</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">

        
        <table class="table table-sm mb-0" id="expenseTypesTable">
          <thead class="table-light">
            <tr>
              <th>Key</th>
              <th>Label</th>
              <th class="text-center">Sort</th>
              <th class="text-center">System</th>
              <th style="width:100px"></th>
            </tr>
          </thead>
          <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $allExpenseTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $et): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr id="et-row-<?php echo e($et->id); ?>">
                <td><code><?php echo e($et->key); ?></code></td>
                <td>
                  <span class="et-label-display"><?php echo e($et->label); ?></span>
                  <input type="text" class="form-control form-control-sm et-label-input d-none"
                         value="<?php echo e($et->label); ?>" style="max-width:160px">
                </td>
                <td class="text-center">
                  <span class="et-sort-display"><?php echo e($et->sort_order); ?></span>
                  <input type="number" class="form-control form-control-sm et-sort-input d-none text-center"
                         value="<?php echo e($et->sort_order); ?>" style="max-width:70px" min="0">
                </td>
                <td class="text-center">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($et->is_system): ?>
                    <span class="badge bg-label-info">System</span>
                  <?php else: ?>
                    —
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
                <td class="text-end">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$et->is_system): ?>
                    <button type="button" class="btn btn-icon btn-sm btn-outline-primary et-edit-btn"
                            data-id="<?php echo e($et->id); ?>" title="Edit">
                      <i class="ti ti-edit"></i>
                    </button>
                    <button type="button" class="btn btn-icon btn-sm btn-outline-success et-save-btn d-none"
                            data-id="<?php echo e($et->id); ?>"
                            data-url="<?php echo e(route('admin.expense-types.update', $et)); ?>" title="Save">
                      <i class="ti ti-check"></i>
                    </button>
                    <button type="button" class="btn btn-icon btn-sm btn-outline-danger et-delete-btn"
                            data-id="<?php echo e($et->id); ?>"
                            data-url="<?php echo e(route('admin.expense-types.destroy', $et)); ?>"
                            data-label="<?php echo e($et->label); ?>" title="Delete">
                      <i class="ti ti-trash"></i>
                    </button>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
        </table>

        <hr class="m-0">

        
        <div class="p-3">
          <h6 class="mb-2"><i class="ti ti-plus me-1"></i>Add Expense Type</h6>
          <div class="row g-2 align-items-end">
            <div class="col-md-7">
              <label class="form-label">Label <span class="text-danger">*</span></label>
              <input type="text" id="newTypeLabel" class="form-control" placeholder="e.g. Toerusting">
            </div>
            <div class="col-md-2">
              <label class="form-label">Sort</label>
              <input type="number" id="newTypeSort" class="form-control" value="100" min="0">
            </div>
            <div class="col-md-3">
              <button type="button" id="addExpenseTypeBtn" class="btn btn-primary w-100">
                <i class="ti ti-plus me-1"></i>Add
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
'use strict';

/* ── CSRF token ─────────────────────────────────────────────────────────── */
const _csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

/* ═══════════════════════════════════════════════════════════════════════════
   TOAST HELPER
   ═══════════════════════════════════════════════════════════════════════════ */
function showFinanceToast(message, type = 'success') {
  const container = document.getElementById('financeToastContainer');
  if (!container) return;
  const id      = 'ft-' + Date.now();
  const icons   = { success: 'ti-circle-check', danger: 'ti-alert-circle', warning: 'ti-alert-triangle', info: 'ti-info-circle' };
  const icon    = icons[type] || 'ti-info-circle';
  const textCls = type === 'warning' ? 'text-dark' : 'text-white';
  container.insertAdjacentHTML('beforeend',
    `<div id="${id}" class="toast align-items-center ${textCls} bg-${type} border-0" role="alert" aria-live="assertive">
       <div class="d-flex">
         <div class="toast-body"><i class="ti ${icon} me-2"></i>${message}</div>
         <button type="button" class="btn-close ${textCls === 'text-white' ? 'btn-close-white' : ''} me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
       </div>
     </div>`);
  const toastEl = document.getElementById(id);
  const bsToast = new bootstrap.Toast(toastEl, { delay: 3500 });
  bsToast.show();
  toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}

/* ═══════════════════════════════════════════════════════════════════════════
   GENERIC AJAX FORM SUBMISSION
   ═══════════════════════════════════════════════════════════════════════════ */
function submitFinanceForm(form) {
  const confirmMsg = form.dataset.confirm;
  if (confirmMsg && !confirm(confirmMsg)) return;

  /* Disable submit button(s) and show a spinner */
  const submitBtns = Array.from(form.querySelectorAll('[type="submit"]'));
  submitBtns.forEach(btn => {
    btn.disabled        = true;
    btn.dataset.origHtml = btn.innerHTML;
    btn.innerHTML       = '<span class="spinner-border spinner-border-sm" role="status"></span>';
  });

  fetch(form.action, {
    method:  form.method.toUpperCase(),   /* always POST (PATCH/DELETE via _method) */
    body:    new FormData(form),
    headers: {
      'X-Requested-With': 'XMLHttpRequest',
      'Accept':           'application/json',
      'X-CSRF-TOKEN':     _csrf,
    },
  })
  .then(async r => {
    const data = await r.json().catch(() => ({}));
    if (!r.ok) {
      const err    = new Error(data.message || 'An error occurred.');
      err.status   = r.status;
      err.errors   = data.errors ?? null;
      throw err;
    }
    return data;
  })
  .then(data => {
    /* Close the nearest Bootstrap modal if the form specifies one */
    const modalId = form.dataset.modal;
    const modalEl = modalId ? document.getElementById(modalId) : form.closest('.modal');
    if (modalEl) {
      const bsModal = bootstrap.Modal.getInstance(modalEl);
      if (bsModal) bsModal.hide();
    }
    showFinanceToast(data.message || 'Done!', 'success');
    /* Reload after the toast is visible for a moment */
    setTimeout(() => location.reload(), 1200);
  })
  .catch(err => {
    /* Re-enable buttons */
    submitBtns.forEach(btn => {
      btn.disabled  = false;
      if (btn.dataset.origHtml) btn.innerHTML = btn.dataset.origHtml;
    });
    if (err.errors) {
      const msgs = Object.values(err.errors).flat().join(' | ');
      showFinanceToast(msgs, 'danger');
    } else {
      showFinanceToast(err.message || 'An error occurred.', 'danger');
    }
  });
}

/* ─── Auto-calc amount from quantity × unit_price ─── */
document.querySelectorAll('input[name="quantity"], input[name="unit_price"]').forEach(el => {
  el.addEventListener('input', () => {
    const form    = el.closest('form');
    const qty     = parseFloat(form.querySelector('input[name="quantity"]')?.value)   || 0;
    const up      = parseFloat(form.querySelector('input[name="unit_price"]')?.value) || 0;
    const amtInput = form.querySelector('input[name="amount"]');
    if (amtInput && qty > 0 && up > 0) amtInput.value = (qty * up).toFixed(2);
  });
});

/* ─── Wire all [data-ajax] forms via event delegation ─── */
document.addEventListener('submit', function(e) {
  if (!e.target.hasAttribute('data-ajax')) return;
  e.preventDefault();
  submitFinanceForm(e.target);
});

/* ═══════════════════════════════════════════════════════════════════════════
   CONVENOR USER SEARCH (Select2 AJAX – multiple)
   ═══════════════════════════════════════════════════════════════════════════ */
function initConvenorUserSelect2() {
  var $sel = $('#convenorUserSelect');
  if (!$sel.length) return;
  if ($sel.data('select2')) $sel.select2('destroy');
  $sel.select2({
    ajax: {
      url:     '<?php echo e(route('convenor.search-users')); ?>',
      dataType: 'json',
      delay:    250,
      data:     function(params) { return { q: params.term }; },
      processResults: function(data) { return { results: data }; },
      cache:    true,
    },
    placeholder:        'Search by name or email…',
    minimumInputLength: 2,
    multiple:           true,
    dropdownParent:     $('#manageConvenorsModal'),
    width:              '100%',
  });
}

$(document).ready(function() {
  $('#manageConvenorsModal').on('shown.bs.modal', function() {
    initConvenorUserSelect2();
  });
  // If already visible on load
  if ($('#manageConvenorsModal').is(':visible')) {
    initConvenorUserSelect2();
  }

  /* ── Expense "Paid by" multi-select (Select2) ── */
  $('#addExpenseModal').on('shown.bs.modal', function() {
    var $paidBy = $(this).find('#expensePaidBySelect');
    if ($paidBy.length && !$paidBy.data('select2')) {
      $paidBy.select2({
        placeholder:    'Select director(s)…',
        multiple:       true,
        dropdownParent: $('#addExpenseModal'),
        width:          '100%',
      });
    }
  });
});

/* ═══════════════════════════════════════════════════════════════════════════
   VENUE CONVENORS CRUD (AJAX)
   ═══════════════════════════════════════════════════════════════════════════ */

/* ── Inline +/- buttons in expense form ── */

/* Show VC row when "Add venue convenor / payee" link is clicked */
$(document).on('click', '.vc-show-link', function(e) {
  e.preventDefault();
  var $row = $(this).closest('.row');
  $row.find('.vc-col-wrapper').removeClass('d-none');
  $(this).closest('small').remove();
});

/* Hide VC row and clear selection when "Remove" link is clicked */
$(document).on('click', '.vc-hide-link', function(e) {
  e.preventDefault();
  var $col = $(this).closest('.vc-col-wrapper');
  var $row = $col.closest('.row');
  // Clear select back to "— none —"
  $col.find('.vc-select').val('').trigger('change');
  $col.addClass('d-none');
  // Re-add the "Add venue convenor" toggle link to the description col
  if (!$row.find('.vc-show-link').length) {
    $row.find('[name="description"]').closest('.col-md-6')
      .append('<small><a href="#" class="vc-show-link text-muted"><i class="ti ti-user-plus"></i> Add venue convenor / payee</a></small>');
  }
});

/* Show/hide the − button based on whether a real VC is selected */
$(document).on('change', '.vc-select', function() {
  var $btn = $(this).closest('.input-group').find('.vc-remove-btn');
  var hasId = !!$(this).find('option:selected').data('vc-id');
  $btn.toggleClass('d-none', !hasId);
});

$(document).on('click', '.vc-add-btn', function() {
  var $wrapper = $(this).closest('.vc-field-wrapper');
  $wrapper.find('.vc-add-form').toggleClass('d-none');
  $wrapper.find('.vc-add-name').val('').focus();
});

$(document).on('click', '.vc-add-cancel-btn', function() {
  var $wrapper = $(this).closest('.vc-field-wrapper');
  $wrapper.find('.vc-add-form').addClass('d-none');
  $wrapper.find('.vc-add-name').val('');
});

$(document).on('keydown', '.vc-add-name', function(e) {
  if (e.key === 'Escape') $(this).closest('.vc-field-wrapper').find('.vc-add-cancel-btn').trigger('click');
  if (e.key === 'Enter')  { e.preventDefault(); $(this).closest('.vc-field-wrapper').find('.vc-add-save-btn').trigger('click'); }
});

$(document).on('click', '.vc-add-save-btn', function() {
  var $saveBtn = $(this);
  var $wrapper = $saveBtn.closest('.vc-field-wrapper');
  var $nameInput = $wrapper.find('.vc-add-name');
  var name = $nameInput.val().trim();
  if (!name) { $nameInput.focus(); return; }

  var $select   = $wrapper.find('.vc-select');
  var storeUrl  = $select.data('vc-store-url');
  var fd = new FormData();
  fd.append('name', name);
  fd.append('_token', '<?php echo e(csrf_token()); ?>');

  $saveBtn.prop('disabled', true);

  fetch(storeUrl, {
    method: 'POST',
    body: fd,
    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
  })
  .then(function(r) {
    return r.json().then(function(d) {
      if (!r.ok) throw new Error(d.message || 'Error adding venue convenor.');
      return d;
    });
  })
  .then(function(d) {
    var vc = d.venueConvenor;
    var safeHtml = $('<span>').text(vc.name).html();

    // Add option to every .vc-select on the page and select in the current one
    $('.vc-select').each(function() {
      $(this).append('<option value="' + safeHtml + '" data-vc-id="' + vc.id + '">' + safeHtml + '</option>');
    });
    $select.val(vc.name);

    // Sync manage-modal table if open
    vcAddRowToModal(vc);

    showFinanceToast(d.message, 'success');
    $nameInput.val('');
    $wrapper.find('.vc-add-form').addClass('d-none');
  })
  .catch(function(e) { showFinanceToast(e.message, 'danger'); })
  .finally(function() { $saveBtn.prop('disabled', false); });
});

$(document).on('click', '.vc-remove-btn', function() {
  var $select = $(this).closest('.input-group').find('.vc-select');
  var $selectedOpt = $select.find('option:selected');
  var id = $selectedOpt.data('vc-id');
  if (!id) { showFinanceToast('No venue convenor selected.', 'warning'); return; }

  var name = $selectedOpt.text().trim();
  if (!confirm('Remove "' + name + '" from this event?')) return;

  var destroyUrl = $select.data('vc-destroy-url').replace('__ID__', id);
  var fd = new FormData();
  fd.append('_token', '<?php echo e(csrf_token()); ?>');
  fd.append('_method', 'DELETE');

  fetch(destroyUrl, {
    method: 'POST',
    body: fd,
    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
  })
  .then(function(r) {
    return r.json().then(function(d) {
      if (!r.ok) throw new Error(d.message || 'Error removing venue convenor.');
      return d;
    });
  })
  .then(function(d) {
    $('.vc-select option[data-vc-id="' + id + '"]').remove();
    // Re-hide the − button on every select that no longer has a vc-id selected
    $('.vc-select').each(function() {
      var hasId = !!$(this).find('option:selected').data('vc-id');
      $(this).closest('.input-group').find('.vc-remove-btn').toggleClass('d-none', !hasId);
    });
    $('#vc-row-' + id).remove();
    showFinanceToast(d.message, 'success');
  })
  .catch(function(e) { showFinanceToast(e.message, 'danger'); });
});

/* ── Helper: add a new VC row to the manage-modal table ── */
function vcAddRowToModal(vc) {
  var $emptyMsg = $('#vcEmptyMsg');
  if ($emptyMsg.length) {
    $emptyMsg.replaceWith(
      '<table class="table table-sm mb-0" id="vcTable">' +
        '<thead class="table-light"><tr><th>Name</th><th style="width:80px"></th></tr></thead>' +
        '<tbody></tbody>' +
      '</table>'
    );
  }
  if (!$('#vcTable').length) return;
  var destroyForm =
    '<form method="POST" action="' + vc.destroy_url + '" class="d-inline" data-ajax="1"' +
    ' data-confirm="Remove ' + $('<span>').text(vc.name).html() + ' from this event?">' +
      '<input type="hidden" name="_token" value="<?php echo e(csrf_token()); ?>">' +
      '<input type="hidden" name="_method" value="DELETE">' +
      '<button class="btn btn-icon btn-sm btn-outline-danger" title="Remove"><i class="ti ti-trash"></i></button>' +
    '</form>';
  $('#vcTable tbody').append(
    '<tr id="vc-row-' + vc.id + '">' +
      '<td class="align-middle">' + $('<span>').text(vc.name).html() + '</td>' +
      '<td class="text-end align-middle">' + destroyForm + '</td>' +
    '</tr>'
  );
}

/* ── Add via manage-modal form ── */
$('#addVenueConvenorForm').on('submit', function(e) {
  e.preventDefault();
  var form = this;
  var $btn = $(form).find('[type=submit]');
  var $nameInput = $(form).find('[name=name]');
  var name = $nameInput.val().trim();
  if (!name) return;

  $btn.prop('disabled', true);

  var fd = new FormData(form);

  fetch(form.action, {
    method:  'POST',
    body:    fd,
    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
  })
  .then(function(r) {
    return r.json().then(function(d) {
      if (!r.ok) throw new Error(d.message || 'Error adding venue convenor.');
      return d;
    });
  })
  .then(function(d) {
    var vc = d.venueConvenor;
    var safeHtml = $('<span>').text(vc.name).html();

    // Sync modal table
    vcAddRowToModal(vc);

    // Add option to every .vc-select on the page
    $('.vc-select').each(function() {
      $(this).append('<option value="' + safeHtml + '" data-vc-id="' + vc.id + '">' + safeHtml + '</option>');
    });

    showFinanceToast(d.message, 'success');
    $nameInput.val('');
  })
  .catch(function(e) { showFinanceToast(e.message, 'danger'); })
  .finally(function() { $btn.prop('disabled', false); });
});

/* ═══════════════════════════════════════════════════════════════════════════
   EXPENSE TYPES CRUD (fully AJAX, no page reload)
   ═══════════════════════════════════════════════════════════════════════════ */

/* Toggle a type row into edit mode */
function etEditMode(row, on) {
  row.querySelector('.et-label-display').classList.toggle('d-none', on);
  row.querySelector('.et-label-input').classList.toggle('d-none',  !on);
  row.querySelector('.et-sort-display').classList.toggle('d-none', on);
  row.querySelector('.et-sort-input').classList.toggle('d-none',   !on);
  row.querySelector('.et-save-btn')?.classList.toggle('d-none',    !on);
  row.querySelector('.et-edit-btn')?.classList.toggle('d-none',    on);
}

/* Save edits for an expense type row */
function etSaveRow(row) {
  const label = row.querySelector('.et-label-input').value.trim();
  const sort  = parseInt(row.querySelector('.et-sort-input').value) || 0;
  if (!label) return;
  const url   = row.querySelector('.et-save-btn').dataset.url;

  fetch(url, {
    method:  'PATCH',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrf, 'Accept': 'application/json' },
    body:    JSON.stringify({ label, sort_order: sort }),
  })
  .then(r => r.json())
  .then(data => {
    row.querySelector('.et-label-display').textContent = data.label;
    row.querySelector('.et-sort-display').textContent  = data.sort_order;
    row.querySelector('.et-label-input').value         = data.label;
    row.querySelector('.et-sort-input').value          = data.sort_order;
    etEditMode(row, false);
    showFinanceToast('Expense type updated.', 'success');
  })
  .catch(() => showFinanceToast('Save failed.', 'danger'));
}

/* Wire up a freshly-created or server-rendered type row */
function wireTypeRow(row) {
  row.querySelector('.et-edit-btn')?.addEventListener('click',   () => etEditMode(row, true));
  row.querySelector('.et-save-btn')?.addEventListener('click',   () => etSaveRow(row));
  row.querySelector('.et-delete-btn')?.addEventListener('click', function() {
    const lbl = this.dataset.label;
    if (!confirm(`Delete expense type "${lbl}"?`)) return;
    fetch(this.dataset.url, {
      method:  'DELETE',
      headers: { 'X-CSRF-TOKEN': _csrf, 'Accept': 'application/json' },
    })
    .then(r => r.json())
    .then(() => {
      row.remove();
      showFinanceToast(`"${lbl}" deleted.`, 'success');
    })
    .catch(r => r.json?.().then(d => showFinanceToast(d.message ?? 'Delete failed.', 'danger')));
  });
}

/* Wire all pre-rendered type rows */
document.querySelectorAll('#expenseTypesTable tbody tr').forEach(wireTypeRow);

/* Add new expense type */
document.getElementById('addExpenseTypeBtn')?.addEventListener('click', function() {
  const label = document.getElementById('newTypeLabel').value.trim();
  const sort  = parseInt(document.getElementById('newTypeSort').value) || 100;
  if (!label) { showFinanceToast('Please enter a label.', 'warning'); return; }

  fetch('<?php echo e(route('admin.expense-types.store')); ?>', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': _csrf, 'Accept': 'application/json' },
    body:    JSON.stringify({ label, sort_order: sort }),
  })
  .then(r => r.json())
  .then(et => {
    const tbody = document.querySelector('#expenseTypesTable tbody');
    const tr    = document.createElement('tr');
    tr.id        = 'et-row-' + et.id;
    tr.innerHTML = `
      <td><code>${et.key}</code></td>
      <td>
        <span class="et-label-display">${et.label}</span>
        <input type="text" class="form-control form-control-sm et-label-input d-none" value="${et.label}" style="max-width:160px">
      </td>
      <td class="text-center">
        <span class="et-sort-display">${et.sort_order}</span>
        <input type="number" class="form-control form-control-sm et-sort-input d-none text-center" value="${et.sort_order}" style="max-width:70px" min="0">
      </td>
      <td class="text-center">—</td>
      <td class="text-end">
        <button type="button" class="btn btn-icon btn-sm btn-outline-primary et-edit-btn" data-id="${et.id}" title="Edit"><i class="ti ti-edit"></i></button>
        <button type="button" class="btn btn-icon btn-sm btn-outline-success et-save-btn d-none" data-id="${et.id}" data-url="/expense-types/${et.id}" title="Save"><i class="ti ti-check"></i></button>
        <button type="button" class="btn btn-icon btn-sm btn-outline-danger et-delete-btn" data-id="${et.id}" data-url="/expense-types/${et.id}" data-label="${et.label}" title="Delete"><i class="ti ti-trash"></i></button>
      </td>`;
    tbody.appendChild(tr);
    wireTypeRow(tr);
    document.getElementById('newTypeLabel').value = '';
    showFinanceToast(`"${et.label}" added.`, 'success');
  })
  .catch(() => showFinanceToast('Failed to add type.', 'danger'));
});
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\finances.blade.php ENDPATH**/ ?>