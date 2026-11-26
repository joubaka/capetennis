

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
  .registration-transactions-toggle[aria-expanded="true"] .registration-transactions-action-label::before { content: 'Hide transactions'; }
  .registration-transactions-toggle[aria-expanded="false"] .registration-transactions-action-label::before { content: 'View transactions'; }

  @media (max-width: 575.98px) {
    .registration-transactions-toggle { flex-direction: column; align-items: flex-start !important; gap: 0.75rem; }
  }

  .finance-workspace summary { min-height:44px; cursor:pointer; align-content:center; }
  .finance-workspace [id^="finance-"] { scroll-margin-top: 150px; }
  .finance-workspace .dropdown-item, .finance-workspace .btn { min-height:44px; }
  @media screen {
    .finance-toolbar { padding-block: .5rem; }
    .finance-toolbar-actions { align-items: center; }
    .finance-section-nav { padding: .5rem; background: #fff; border: 1px solid #e4eaf0; border-radius: .75rem; }
    .finance-section-nav a { display: inline-flex; align-items: center; gap: .5rem; padding: .65rem .85rem; min-height: 44px; border-radius: .5rem; color: #44586b; font-weight: 600; text-decoration: none; }
    .finance-section-nav a:hover, .finance-section-nav a[aria-selected="true"] { background: #eaf4f2; color: #105f57; }
    .finance-section-nav a[aria-selected="true"] { box-shadow: inset 0 -3px #14877b; }
    .finance-section-nav a:focus-visible { outline: 3px solid #14877b; outline-offset: 2px; }
    .finance-tab-panel[hidden] { display: none !important; }
    .finance-tab-panel[aria-busy="true"] { opacity: .6; }
    .finance-section-nav a[aria-busy="true"] { background: #f2f8f6; color: #105f57; }
    .finance-loading-spinner { color: #004177; }
    @media (prefers-reduced-motion: reduce) {
      .finance-loading-spinner { animation: none !important; }
    }
    .finance-workspace .finance-card { border: 1px solid #e4eaf0 !important; border-top: 3px solid #b7d6d0 !important; box-shadow: none; }
    .finance-card .card-body { padding: 1.25rem; }
    .finance-card h5 { font-size: 1.5rem; font-variant-numeric: tabular-nums; margin-block: .35rem !important; }
    .finance-workspace > .card > .card-header { padding: 1.1rem 1.25rem; }
    .finance-workspace .table th { font-size: .72rem; line-height: 1.5; white-space: nowrap; }
    .finance-workspace .table td { vertical-align: middle; }
    .finance-workspace .table .text-end { white-space: nowrap; font-variant-numeric: tabular-nums; }
    .finance-workspace .table td.no-print { min-width: 156px; white-space: nowrap; }
    .finance-workspace .table td.no-print .btn { margin: 2px; }
    .finance-workspace .table-responsive { border-radius: 0 0 .65rem .65rem; }
    .finance-expenses-heading { padding-top: .75rem; gap: 1rem; flex-wrap: wrap; }
    .finance-payee summary { list-style: none; gap: 1rem; padding: 1rem 1.25rem; background: #f2f6f8; border-left: 3px solid #b7c9d5; }
    .finance-payee summary::-webkit-details-marker { display: none; }
    .finance-payee summary .ti-chevron-down { transition: transform .15s ease; }
    .finance-payee:not([open]) summary .ti-chevron-down { transform: rotate(-90deg); }
    .finance-payee .finance-payee-total { white-space: nowrap; font-variant-numeric: tabular-nums; }
    @media (max-width: 767.98px) {
      .finance-toolbar-actions { width: 100%; }
      .finance-toolbar-actions > .btn:first-child { flex: 1; }
      .finance-section-nav { gap: .25rem !important; }
      .finance-section-nav a { flex: 1 1 calc(50% - .25rem); font-size: .85rem; padding: .6rem; }
      .finance-card .card-body { padding: 1rem; }
      .finance-card h5 { font-size: 1.2rem; overflow-wrap: anywhere; }
      .finance-workspace .card-header { flex-wrap: wrap; gap: .75rem; }
      .finance-workspace .table { min-width: 760px; }
      .finance-payee summary { padding: 1rem; }
    }
  }
  /* Print styles */
  @media print {
    .finance-tab-panel[hidden] { display: block !important; }
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
<div class="container-xl finance-workspace">

  
  <div class="print-header mb-4 pb-3 border-bottom">
    <h3 class="mb-1"><?php echo e($event->name); ?> – Income and Expense Statement</h3>
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
  <div class="finance-toolbar d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3 no-print">
    <div><h2 class="h4 mb-1">Event income and expenses</h2><p class="text-muted mb-0">Income, costs, reimbursements and event payees.</p></div>
    <div class="finance-toolbar-actions d-flex gap-2 flex-wrap">
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('event-finance.manage', $event)): ?><a class="btn btn-primary" href="<?php echo e(route('admin.events.expenses.edit', $event)); ?>"><i class="ti ti-edit me-1" aria-hidden="true"></i>Edit expenses</a><?php endif; ?>
        <div class="dropdown"><button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Setup tools</button><div class="dropdown-menu dropdown-menu-end">
        <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#manageConvenorsModal">
          <i class="ti ti-users me-1"></i>Finance Payees
        </button>
        <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#manageVenueConvenorsModal">
          <i class="ti ti-map-pin me-1"></i>Venue Convenors
        </button>
        <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#manageTypesModal">
          <i class="ti ti-tags me-1"></i>Expense Types
        </button>
        </div></div>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
          <i class="ti ti-printer me-1"></i>Print / PDF
        </button>
    </div>
  </div>

  <nav class="finance-section-nav d-flex flex-wrap gap-2 mb-3 no-print" role="tablist" aria-label="Finance sections" data-finance-tabs>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $financeTabs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $tab): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
      <a href="<?php echo e(route('admin.events.finances', ['event' => $event, 'tab' => $key])); ?>"
         id="finance-tab-<?php echo e($key); ?>" role="tab" data-finance-tab="<?php echo e($key); ?>"
         aria-controls="finance-panel-<?php echo e($key); ?>" aria-selected="<?php echo e($activeFinanceTab === $key ? 'true' : 'false'); ?>"
         tabindex="<?php echo e($activeFinanceTab === $key ? '0' : '-1'); ?>"><i class="ti <?php echo e($tab['icon']); ?>" aria-hidden="true"></i><?php echo e($tab['label']); ?></a>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </nav>
  <div class="no-print" data-finance-tab-feedback role="status" aria-live="polite"></div>

  
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


  <?php $__env->startFragment('finance-breakdown'); ?>
  <section class="finance-tab-panel" id="finance-panel-breakdown" role="tabpanel" aria-labelledby="finance-tab-breakdown" tabindex="0" <?php if($activeFinanceTab !== 'breakdown'): ?> hidden <?php endif; ?>>
  <div class="card mb-3 no-print">
    <div class="card-body">
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <a class="btn btn-outline-primary" href="<?php echo e(route('admin.events.finances.report.pdf', $event)); ?>"><i class="ti ti-download me-1" aria-hidden="true"></i>Download PDF</a>
        <button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#finance-report-email" aria-expanded="false" aria-controls="finance-report-email"><i class="ti ti-mail me-1" aria-hidden="true"></i>Email report</button>
      </div>
      <div class="collapse mt-3" id="finance-report-email">
        <p class="small text-muted">Send the income and expense breakdown as a PDF. It includes private financial figures, convenor payouts and separate clothing adjustments. Check the recipient before sending.</p>
        <form action="<?php echo e(route('admin.events.finances.report.email', $event)); ?>" method="post" data-finance-report-email>
          <?php echo csrf_field(); ?>
          <input type="hidden" name="request_key" value="<?php echo e((string) \Illuminate\Support\Str::uuid()); ?>">
          <label for="finance-report-recipient" class="form-label">Recipient email address</label>
          <div class="d-flex flex-wrap gap-2">
            <input class="form-control" style="flex:1 1 240px; min-width:0" id="finance-report-recipient" name="recipient" type="email" maxlength="254" required autocomplete="email">
            <button class="btn btn-primary" type="submit">Send PDF</button>
          </div>
          <div class="mt-2 small" role="status" aria-live="polite" data-report-email-feedback></div>
        </form>
      </div>
    </div>
  </div>
  
  <div class="card mb-4"><div class="card-body">
    <?php echo $__env->make('backend.event.partials.entry-withdrawal-summary', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </div></div>
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card finance-card border-start border-primary border-3 h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1"><i class="ti ti-cash me-1 text-primary"></i>Registration net income</small>
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
            Registration <?php echo e($netProfit >= 0 ? 'Profit' : 'Loss'); ?>

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
          <small class="text-muted d-block mb-1"><i class="ti ti-users me-1"></i>Entries in payment records</small>
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

  
  <?php echo $__env->make('backend.event.partials.convenor-payout-breakdown', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($venueEntrySummary->count()): ?>
  <details class="card mb-4 no-print" data-finance-details>
    <summary class="card-header">
      <h5 class="mb-0"><i class="ti ti-map-pin me-2"></i>Venue Entry Summary</h5>
    </summary>
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
  </details>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


  </section>
  <?php echo $__env->stopFragment(); ?>

  <?php $__env->startFragment('finance-transactions'); ?>
  <section class="finance-tab-panel" id="finance-panel-transactions" role="tabpanel" aria-labelledby="finance-tab-transactions" tabindex="0" <?php if($activeFinanceTab !== 'transactions'): ?> hidden <?php endif; ?>>
  <div class="d-flex justify-content-end mb-3 no-print">
    <a href="<?php echo e(route('transactions.pdf', $event)); ?>" class="btn btn-outline-primary btn-sm">
      <i class="ti ti-download me-1" aria-hidden="true"></i>Export transactions
    </a>
  </div>
  <div class="card mb-4" id="finance-receipts">
    <button class="card-header registration-transactions-toggle d-flex justify-content-between align-items-center border-0 bg-transparent text-start w-100"
            type="button" data-bs-toggle="collapse" data-bs-target="#registrationTransactionsCollapse"
            aria-expanded="true" aria-controls="registrationTransactionsCollapse">
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
    <div class="collapse show" id="registrationTransactionsCollapse">
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
            <th class="text-end">Net receipt / refund</th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $eventTransactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $transaction): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php
              $isWithdrawal = $transaction->type === 'withdrawal';
              $gross = $isWithdrawal ? ($transaction->original_gross ?? 0) : ($transaction->gross ?? 0);
              $fees = ($transaction->fee ?? 0) + ($transaction->capeFee ?? 0);
              $details = collect($transaction->registrationDetails ?? []);
              $isClothing = $transaction->type === 'clothing_payment' || ($transaction->subtype ?? null) === 'clothing_refund';
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
                  <?php echo e($transaction->type === 'payment' && ($transaction->subtype ?? null) !== 'superseded_payfast_receipt'
                    ? 'Registration payment' : ($transaction->status_label ?? ucfirst($transaction->type))); ?>

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
                      <div class="col-md-3"><span class="text-muted"><?php echo e($isClothing ? 'Clothing items' : 'Registration entries'); ?></span><div class="fw-semibold"><?php echo e($details->count() ?: ($transaction->entryCount ?? 1)); ?></div></div>
                    </div>
                    <div class="table-responsive">
                      <table class="table table-sm table-bordered bg-white mb-0">
                        <thead>
                          <tr>
                          <th><?php echo e($isClothing ? 'Clothing item' : 'Registered player'); ?></th>
                          <th><?php echo e($isClothing ? 'Size' : 'Category'); ?></th>
                          <th class="text-end"><?php echo e($isClothing ? 'Item amount' : 'Entry amount'); ?></th>
                          </tr>
                          </thead>
                        <tbody>
                          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_2 = true; $__currentLoopData = $details; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $detail): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                            <tr>
                              <td><?php echo e($isClothing ? ($detail['item'] ?? $detail['category'] ?? '—') : ($detail['player'] ?? '—')); ?></td>
                              <td><?php echo e($isClothing ? ($detail['size'] ?? '—') : ($detail['category'] ?? '—')); ?></td>
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
            <tr><td colspan="8" class="text-center text-muted py-4">No transactions have been recorded for this event.</td></tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
    </div>
    </div>
  </div>


  </section>
  <?php echo $__env->stopFragment(); ?>

  <?php $__env->startFragment('finance-income'); ?>
  <section class="finance-tab-panel" id="finance-panel-income" role="tabpanel" aria-labelledby="finance-tab-income" tabindex="0" <?php if($activeFinanceTab !== 'income'): ?> hidden <?php endif; ?>>
  
  <div class="card mb-4" id="finance-income">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0"><i class="ti ti-cash me-2 text-success"></i>Registration income</h5>
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
              <td><small class="text-muted">Registration payment ledger</small></td>
              <td>—</td>
              <td class="text-end fw-semibold text-success">R <?php echo e(number_format($registrationReceived, 2)); ?></td>
              <td class="no-print"></td>
            </tr>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(abs($totalPayfastFees) > 0): ?>
              <tr class="deduction-row">
                <td class="ps-4">
                  <i class="ti ti-minus me-1"></i>PayFast fees deducted (registrations)
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

            <tr class="deduction-row">
              <td colspan="5">Completed registration refunds (net paid back)</td>
              <td class="text-end">&minus;R <?php echo e(number_format($registrationReceipts['totals']['completed_refund_net'], 2)); ?></td>
              <td class="no-print"></td>
            </tr>
            <tr class="table-light fw-semibold">
              <td colspan="5">Registration net income</td>
              <td class="text-end">R <?php echo e(number_format($netRegistrationIncome, 2)); ?></td>
              <td class="no-print"></td>
            </tr>
            <tr>
              <td colspan="7" class="text-muted">Pending registration refund liability: R <?php echo e(number_format($registrationReceipts['totals']['pending_refund_net'], 2)); ?> (not yet deducted).</td>
            </tr>
            <tr class="table-light">
              <th colspan="7">Other event income — excluded from registration profit</th>
            </tr>
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
                          aria-label="Edit income item"
                          data-bs-toggle="modal" data-bs-target="#editIncomeModal<?php echo e($item->id); ?>">
                    <i class="ti ti-edit"></i>
                  </button>
                  <form action="<?php echo e(route('admin.events.finances.income.destroy', $item)); ?>" method="POST" class="d-inline"
                        data-ajax="1" data-confirm="Delete this income item?">
                    <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                    <button class="btn btn-icon btn-sm btn-outline-danger" aria-label="Delete income item" title="Delete income item"><i class="ti ti-trash"></i></button>
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
              <td colspan="5" class="fw-bold">Total other event income</td>
              <td class="text-end fw-bold text-success">R <?php echo e(number_format($totalIncomeItems, 2)); ?></td>
              <td class="no-print"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>


  </section>
  <?php echo $__env->stopFragment(); ?>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($financeTabs['clothing'])): ?>
  <?php $__env->startFragment('finance-clothing'); ?>
  <section class="finance-tab-panel" id="finance-panel-clothing" role="tabpanel" aria-labelledby="finance-tab-clothing" tabindex="0" <?php if($activeFinanceTab !== 'clothing'): ?> hidden <?php endif; ?>>
  <div class="card mb-4" id="finance-clothing">
    <div class="card-header">
      <h5 class="mb-1"><i class="ti ti-shirt me-2 text-primary" aria-hidden="true"></i>Regional clothing income and reconciliation</h5>
      <small class="text-muted">Clothing receipts and regional payables are separate from registration income.</small>
    </div>
    <?php echo $__env->make('backend.event._clothing_finance', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php echo $__env->make('backend.event.partials.clothing-settlements', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </div>

  </section>
  <?php echo $__env->stopFragment(); ?>

  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php $__env->startFragment('finance-expenses'); ?>
  <section class="finance-tab-panel" id="finance-panel-expenses" role="tabpanel" aria-labelledby="finance-tab-expenses" tabindex="0" <?php if($activeFinanceTab !== 'expenses'): ?> hidden <?php endif; ?>>
  
  <div class="finance-expenses-heading d-flex justify-content-between align-items-center mb-3" id="finance-expenses">
    <div>
      <h5 class="mb-1"><i class="ti ti-list me-2"></i>Expenses by payee</h5>
      <small class="text-muted">Review costs and receipts. Select a payee heading to collapse its expenses.</small>
    </div>
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
      <details class="card finance-payee mb-3" open data-finance-details>
        <summary class="card-header convenor-header d-flex justify-content-between align-items-center">
          <div>
            <strong><i class="ti ti-chevron-down me-2" aria-hidden="true"></i>Paid by <?php echo e($convenor->user->name ?? 'Unknown'); ?></strong>
            <span class="badge bg-label-secondary ms-2"><?php echo e($roleLabel); ?></span>
            <small class="text-muted d-block mt-1"><?php echo e($cExpenses->count()); ?> <?php echo e($cExpenses->count() === 1 ? 'expense' : 'expenses'); ?></small>
          </div>
          <span class="fw-bold finance-payee-total">R <?php echo e(number_format($cTotal, 2)); ?></span>
        </summary>
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
                        <span class="badge bg-label-secondary approved-badge">Submitted</span>
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
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$expense->reimbursed_at && $expense->paid_by_convenor_id): ?>
                          <form action="<?php echo e(route('admin.events.finances.expense.reimburse', $expense)); ?>" method="POST" class="d-inline" data-ajax="1">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="btn btn-icon btn-sm btn-outline-info" title="Mark as reimbursed" aria-label="Mark expense as reimbursed">
                              <i class="ti ti-coin"></i>
                            </button>
                          </form>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        <button type="button"
                                class="btn btn-icon btn-sm btn-outline-primary"
                                aria-label="Edit expense" title="Edit expense"
                                data-bs-toggle="modal"
                                data-bs-target="#editExpenseModal<?php echo e($expense->id); ?>">
                          <i class="ti ti-edit"></i>
                        </button>
                        <form action="<?php echo e(route('admin.events.finances.expense.destroy', $expense)); ?>"
                              method="POST" class="d-inline"
                              data-ajax="1" data-confirm="Delete this expense?">
                          <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                          <button class="btn btn-icon btn-sm btn-outline-danger" aria-label="Delete expense" title="Delete expense"><i class="ti ti-trash"></i></button>
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
      </details>
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
                            aria-label="Edit expense" title="Edit expense"
                            data-bs-toggle="modal" data-bs-target="#editExpenseModal<?php echo e($expense->id); ?>">
                      <i class="ti ti-edit"></i>
                    </button>
                    <form action="<?php echo e(route('admin.events.finances.expense.destroy', $expense)); ?>" method="POST" class="d-inline"
                          data-ajax="1" data-confirm="Delete this expense?">
                      <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                      <button class="btn btn-icon btn-sm btn-outline-danger" aria-label="Delete expense" title="Delete expense"><i class="ti ti-trash"></i></button>
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


  </section>
  <?php echo $__env->stopFragment(); ?>

  <?php $__env->startFragment('finance-reconciliation'); ?>
  <section class="finance-tab-panel" id="finance-panel-reconciliation" role="tabpanel" aria-labelledby="finance-tab-reconciliation" tabindex="0" <?php if($activeFinanceTab !== 'reconciliation'): ?> hidden <?php endif; ?>>
  
  <?php echo $__env->make('backend.event.partials.expense-reimbursements', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->user()?->hasRole('super-user') && \App\Support\EventCapabilities::teamPayments($event)): ?>
    <div class="alert alert-warning d-flex flex-wrap align-items-center justify-content-between gap-2">
      <span>Players showing “Payment being checked” need a late payment review.</span>
      <a class="btn btn-outline-primary" style="min-height:44px" href="<?php echo e(route('backend.late-team-payments.index', $event)); ?>">Review late team payments</a>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <details class="card mb-4" id="finance-reconciliation" data-finance-details open>
    <summary class="card-header">
      <h5 class="mb-0"><i class="ti ti-arrows-exchange me-2"></i>Registration reconciliation</h5>
    </summary>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table recon-table mb-0">
          <thead>
            <tr>
              <th>Convenor</th>
              <th>Role</th>
              <th class="text-end">Paid Out</th>
              <th class="text-end">Reimbursed</th>
              <th class="text-end">Expenses before general payments</th>
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

                  <small class="d-block text-muted">General payments deducted: R <?php echo e(number_format($row['director_paid'], 2)); ?></small>
                </td>
                <td>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($row['final_payout'] <= 0): ?>
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

                <small class="d-block text-muted">General payments deducted: R <?php echo e(number_format($recon->sum('director_paid'), 2)); ?></small>
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
            <tr class="table-secondary">
              <td colspan="2">Completed registration refunds (net paid back)</td>
              <td colspan="5" class="text-end">&minus;R <?php echo e(number_format($registrationReceipts['totals']['completed_refund_net'], 2)); ?></td>
              <td></td>
            </tr>
            <tr class="table-secondary">
              <td colspan="2">Pending registration refund liability (not deducted)</td>
              <td colspan="5" class="text-end">R <?php echo e(number_format($registrationReceipts['totals']['pending_refund_net'], 2)); ?></td>
              <td></td>
            </tr>
            <tr class="table-secondary">
              <td colspan="2"><small class="text-muted">Registration net income</small></td>
              <td colspan="5" class="text-end fw-semibold text-success">R <?php echo e(number_format($grandTotalIncome, 2)); ?></td>
              <td></td>
            </tr>
            <tr class="table-secondary">
              <td colspan="2"><small class="text-muted">Operational Expenses</small></td>
              <td colspan="5" class="text-end fw-semibold text-danger">R <?php echo e(number_format($totalExpenses, 2)); ?></td>
              <td></td>
            </tr>
            <tr class="<?php echo e($netProfit >= 0 ? 'table-success' : 'table-danger'); ?>">
              <td colspan="2" class="fw-bold">Registration <?php echo e($netProfit >= 0 ? 'Profit' : 'Loss'); ?></td>
              <td colspan="5" class="text-end fw-bold">R <?php echo e(number_format(abs($netProfit), 2)); ?></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </details>

  </section>
  <?php echo $__env->stopFragment(); ?>


<div class="toast-container position-fixed bottom-0 end-0 p-3" id="financeToastContainer" style="z-index:1200"></div>


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
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-map-pin me-2"></i>Venue Convenors <span class="badge bg-label-secondary" id="vcCount"><?php echo e($venueConvenors->count()); ?></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">

        <?php if (isset($component)) { $__componentOriginaldfca11e5d4c5e9011d0074746d814be3 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginaldfca11e5d4c5e9011d0074746d814be3 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.backend.court-convenor-guide','data' => ['class' => 'p-3 border-bottom']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('backend.court-convenor-guide'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'p-3 border-bottom']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginaldfca11e5d4c5e9011d0074746d814be3)): ?>
<?php $attributes = $__attributesOriginaldfca11e5d4c5e9011d0074746d814be3; ?>
<?php unset($__attributesOriginaldfca11e5d4c5e9011d0074746d814be3); ?>
<?php endif; ?>
<?php if (isset($__componentOriginaldfca11e5d4c5e9011d0074746d814be3)): ?>
<?php $component = $__componentOriginaldfca11e5d4c5e9011d0074746d814be3; ?>
<?php unset($__componentOriginaldfca11e5d4c5e9011d0074746d814be3); ?>
<?php endif; ?>

        
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
          <h6 class="mb-3"><i class="ti ti-plus me-1"></i>Add venue convenors</h6>
          <form action="<?php echo e(route('admin.events.finances.venue-convenor.store', $event)); ?>" method="POST"
                id="addVenueConvenorForm">
            <?php echo csrf_field(); ?>
            <div id="vcBatchErrors" class="alert alert-danger" role="alert" hidden></div>
            <div id="vcBatchStatus" class="text-success mb-2" role="status" aria-live="polite"></div>
            <div id="vcNameRows">
              <div class="d-flex align-items-end gap-2 mb-2" data-vc-name-row>
                <div class="flex-grow-1"><label class="form-label" for="vc-name-0">Name</label><input id="vc-name-0" type="text" name="names[]" class="form-control" placeholder="e.g. Ingrid Le Roux" required maxlength="150"></div>
                <button type="button" class="btn btn-outline-secondary" data-vc-remove-name aria-label="Remove name" disabled>Remove</button>
              </div>
            </div>
            <small class="text-muted d-block mb-3">Add the people hired to convene venues. Existing names will be kept once.</small>
            <div class="d-flex flex-wrap gap-2">
              <button type="button" id="vcAddAnother" class="btn btn-outline-primary">Add another</button>
              <button type="submit" class="btn btn-primary">Add convenors</button>
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
<?php echo $__env->make('backend.event.partials.clothing-settlement-script', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<script src="<?php echo e(asset('assets/js/event-finance-tabs.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/event-finance-tabs.js'))); ?>"></script>

<script>
document.addEventListener('submit', async function(event) {
  const form = event.target.closest('[data-finance-report-email]');
  if (!form) return;
  event.preventDefault();
  if (form.dataset.sending === 'true') return;
  form.dataset.sending = 'true';
  const button = form.querySelector('button[type="submit"]');
  const feedback = form.querySelector('[data-report-email-feedback]');
  button.disabled = true;
  button.textContent = 'Sending…';
  feedback.textContent = '';
  try {
    const response = await fetch(form.action, {method: 'POST', body: new FormData(form), headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}});
    const data = await response.json();
    if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Unable to send the report. Please try again.');
    feedback.className = 'mt-2 small text-success';
    feedback.textContent = data.message;
    form.elements.request_key.value = data.next_request_key;
  } catch (error) {
    feedback.className = 'mt-2 small text-danger';
    feedback.textContent = error.message || 'Unable to send the report. Please try again.';
  } finally {
    form.dataset.sending = 'false';
    button.disabled = false;
    button.textContent = 'Send PDF';
  }
});
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
  const safeMessage = document.createElement('span');
  safeMessage.textContent = message;
  container.insertAdjacentHTML('beforeend',
    `<div id="${id}" class="toast align-items-center ${textCls} bg-${type} border-0" role="alert" aria-live="assertive">
       <div class="d-flex">
         <div class="toast-body"><i class="ti ${icon} me-2"></i>${safeMessage.innerHTML}</div>
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
      if (['quantity', 'unit_price', 'budget_amount', 'receipt'].some(field => Object.hasOwn(err.errors, field))) {
        const optional = form.querySelector('[data-expense-optional]');
        if (optional) optional.open = true;
      }
      const msgs = Object.values(err.errors).flat().join(' | ');
      showFinanceToast(msgs, 'danger');
    } else {
      showFinanceToast(err.message || 'An error occurred.', 'danger');
    }
  });
}

// Reveal optional expense fields before the browser focuses an invalid input.
document.addEventListener('invalid', event => {
  const optional = event.target.closest('[data-expense-optional]');
  if (optional) optional.open = true;
}, true);

/* ─── Auto-calc amount from quantity × unit_price ─── */
document.addEventListener('input', event => {
  const el = event.target;
  if (!el.matches('.finance-tab-panel input[name="quantity"], .finance-tab-panel input[name="unit_price"], .modal input[name="quantity"], .modal input[name="unit_price"]')) return;
  const form = el.closest('form');
  if (!form) return;
  const qty = parseFloat(form.querySelector('input[name="quantity"]')?.value) || 0;
  const up = parseFloat(form.querySelector('input[name="unit_price"]')?.value) || 0;
  const amount = form.querySelector('input[name="amount"]');
  if (amount && qty > 0 && up > 0) amount.value = (qty * up).toFixed(2);
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
    vcSyncRecipientOptions(vc);
    $select.val(vc.name).trigger('change');
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
function vcSyncRecipientOptions(vc) {
  document.querySelectorAll('.vc-select').forEach(select => {
    let option = Array.from(select.options).find(item => item.dataset.vcId === String(vc.id) || item.value === vc.name);
    if (!option) { option = new Option(vc.name, vc.name); select.add(option); }
    option.dataset.vcId = String(vc.id);
  });
}
function vcAddRowToModal(vc) {
  if (document.getElementById('vc-row-' + vc.id)) return;
  const list = document.getElementById('venueConvenorList');
  if (!list) return;
  if (!document.getElementById('vcTable')) {
    list.innerHTML = '<table class="table table-sm mb-0" id="vcTable"><thead class="table-light"><tr><th>Name</th><th style="width:80px"></th></tr></thead><tbody></tbody></table>';
  }
  const row = document.createElement('tr'); row.id = 'vc-row-' + vc.id;
  const nameCell = document.createElement('td'); nameCell.className = 'align-middle'; nameCell.textContent = vc.name;
  const actionCell = document.createElement('td'); actionCell.className = 'text-end align-middle';
  const form = document.createElement('form'); form.method = 'POST'; form.action = vc.destroy_url; form.className = 'd-inline';
  form.dataset.ajax = '1'; form.dataset.confirm = 'Remove ' + vc.name + ' from this event?';
  const token = document.createElement('input'); token.type = 'hidden'; token.name = '_token'; token.value = _csrf;
  const method = document.createElement('input'); method.type = 'hidden'; method.name = '_method'; method.value = 'DELETE';
  const button = document.createElement('button'); button.type = 'submit'; button.className = 'btn btn-icon btn-sm btn-outline-danger'; button.title = 'Remove';
  button.innerHTML = '<i class="ti ti-trash"></i>';
  form.append(token, method, button); actionCell.append(form); row.append(nameCell, actionCell);
  document.querySelector('#vcTable tbody').append(row);
  document.getElementById('vcCount').textContent = document.querySelectorAll('#vcTable tbody tr').length;
}

(function () {
  const form = document.getElementById('addVenueConvenorForm');
  const list = document.getElementById('vcNameRows');
  const add = document.getElementById('vcAddAnother');
  const errors = document.getElementById('vcBatchErrors');
  const status = document.getElementById('vcBatchStatus');
  let next = 1;
  let busy = false;
  const refresh = () => {
    list.querySelectorAll('[data-vc-remove-name]').forEach(button => button.disabled = busy || list.children.length === 1);
    add.disabled = busy || list.children.length >= 50;
  };
  add.addEventListener('click', () => {
    const row = list.firstElementChild.cloneNode(true);
    const input = row.querySelector('input'); input.value = ''; input.id = 'vc-name-' + next++;
    row.querySelector('label').htmlFor = input.id;
    list.append(row); refresh(); input.focus();
  });
  list.addEventListener('click', event => {
    const button = event.target.closest('[data-vc-remove-name]');
    if(button && !busy && list.children.length > 1) { button.closest('[data-vc-name-row]').remove(); refresh(); }
  });
  form.addEventListener('submit', async event => {
    event.preventDefault();
    event.stopPropagation();
    if(busy) return;
    busy = true; errors.hidden = true; errors.textContent = ''; status.textContent = '';
    const body = new FormData(form);
    const submit = form.querySelector('[type="submit"]'); submit.disabled = true;
    list.querySelectorAll('input').forEach(input => input.readOnly = true); refresh();
    try {
      const response = await fetch(form.action, {method:'POST', body, headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});
      const data = await response.json();
      if(!response.ok) {
        const messages = data.errors ? Object.values(data.errors).flat() : [data.message || 'Could not add convenors. Try again.'];
        errors.textContent = messages.join(' '); errors.hidden = false; return;
      }
      (data.venueConvenors || [data.venueConvenor]).forEach(vc => { vcAddRowToModal(vc); vcSyncRecipientOptions(vc); });
      status.textContent = data.message; showFinanceToast(data.message, 'success');
      while(list.children.length > 1) list.lastElementChild.remove();
      list.querySelector('input').value = '';
    } catch (error) {
      errors.textContent = 'Could not add convenors. Your names are still here; please try again.'; errors.hidden = false;
    } finally {
      busy = false; submit.disabled = false; list.querySelectorAll('input').forEach(input => input.readOnly = false); refresh();
    }
  });
})();

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

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH D:\wamp64\www\ct\resources\views\backend\event\finances.blade.php ENDPATH**/ ?>