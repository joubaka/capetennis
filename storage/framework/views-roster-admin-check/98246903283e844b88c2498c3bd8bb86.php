

<?php $__env->startSection('title', $event->name . ' – Finances (Super Admin)'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables.bootstrap5.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/datatables-responsive-bs5/responsive.bootstrap5.css')); ?>">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
<style>
  td.dt-toggle { cursor:pointer; text-align:center; user-select:none; }
  tr.shown td.dt-toggle i { transform:rotate(90deg); }
  td.dt-toggle i { font-size:1.1rem; color:#696cff; transition:.2s; }

  .child-table { background:#f9fafc; border-radius:.375rem; }
  .child-table thead th { background:#eef1ff; font-size:.75rem; text-transform:uppercase; }
  .child-table td { font-size:.8rem; }

  tr.refund-row      { background:#fff4f4 !important; }
  tr.payout-row      { background:#f0f7ff !important; }
  tr.withdrawal-row  { background:#f9f9f9 !important; opacity:.75; }

  #txTable td.text-end { font-variant-numeric: tabular-nums; }
  #txTable { table-layout: fixed; width: 100%; }
  #txTable th, #txTable td { white-space: nowrap; }

  /* The horizontal Super Admin submenu is positioned over the page. Keep it
     above the content, but move this page down while it is open so long event
     names and finance controls are not hidden underneath it. */
  @media (min-width: 1200px) {
    body:has(#layout-menu .menu-item.open) .event-finances-page {
      padding-top: 510px;
    }
  }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl event-finances-page">

  
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h4 class="mb-0">
        <i class="ti ti-report-money me-2 text-warning"></i>
        <?php echo e($event->name); ?>

      </h4>
      <div class="d-flex gap-2 flex-wrap">
        <a href="<?php echo e(route('transactions.pdf', $event)); ?>" class="btn btn-outline-primary btn-sm">
          Export Transactions
        </a>
        <a href="<?php echo e(route('superadmin.finances')); ?>" class="btn btn-outline-secondary btn-sm">
          <i class="ti ti-arrow-left me-1"></i>Back to Dashboard
        </a>
      </div>
    </div>
  </div>

  
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
      <i class="ti ti-circle-check me-1"></i><?php echo e(session('success')); ?>

      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card border-start border-primary">
        <div class="card-body">
          <small class="text-muted">
            Total Received (<?php echo e($totalEntries); ?> entries
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($refundCount) && $refundCount > 0): ?>, <?php echo e($refundCount); ?> refund<?php echo e($refundCount !== 1 ? 's' : ''); ?><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(isset($noRefundCount) && $noRefundCount > 0): ?>, <?php echo e($noRefundCount); ?> withdrawn (no refund)<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            )
          </small>
          <h4>R <?php echo e(number_format($totalGross, 2)); ?></h4>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($clothingReceived ?? 0) > 0): ?>
            <small class="text-muted d-block">Registration R <?php echo e(number_format($registrationReceived ?? $totalGross, 2)); ?> · Clothing R <?php echo e(number_format($clothingReceived, 2)); ?></small>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-start border-warning">
        <div class="card-body">
          <small class="text-muted">PayFast Fees (net)</small>
          <h4 class="text-warning">− R <?php echo e(number_format(abs($totalPayfastFees), 2)); ?></h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-start border-danger">
        <div class="card-body">
          <small class="text-muted">Cape Tennis Fees (net)</small>
          <h4 class="text-danger">− R <?php echo e(number_format(abs($totalCapeTennisFees), 2)); ?></h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-start border-success">
        <div class="card-body">
          <small class="text-muted">Net Tournament Income</small>
          <h4 class="text-success">R <?php echo e(number_format($netTournamentIncome, 2)); ?></h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-start border-danger">
        <div class="card-body">
          <small class="text-muted">Entry Payouts to Convenors</small>
          <h4 class="text-danger">− R <?php echo e(number_format($totalPaidOut, 2)); ?></h4>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-start border-info">
        <div class="card-body">
          <small class="text-muted">Unpaid Balance</small>
          <h4 class="<?php echo e($balance < 0 ? 'text-danger' : 'text-info'); ?>">R <?php echo e(number_format($balance, 2)); ?></h4>
        </div>
      </div>
    </div>
  </div>

  
  <div class="card mb-3 border-start border-4 <?php echo e($withdrawalOpen ? 'border-warning' : 'border-secondary'); ?>">
    <div class="card-body py-3">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
        <h6 class="mb-0 fw-semibold">
          <i class="ti ti-info-circle me-1 <?php echo e($withdrawalOpen ? 'text-warning' : 'text-secondary'); ?>"></i>
          Withdrawal &amp; Refund Policy
        </h6>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($withdrawalOpen): ?>
          <span class="badge bg-success">Withdrawals Open</span>
        <?php else: ?>
          <span class="badge bg-secondary">Withdrawals Closed</span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </div>
      <div class="row g-3 small">
        <div class="col-6 col-md-3">
          <div class="text-muted">Entry Deadline</div>
          <div class="fw-semibold"><?php echo e($entryDeadline->format('d M Y')); ?></div>
        </div>
        <div class="col-6 col-md-3">
          <div class="text-muted">Withdrawal Deadline</div>
          <div class="fw-semibold"><?php echo e($withdrawalDeadline->format('d M Y H:i')); ?></div>
        </div>
        <div class="col-6 col-md-3">
          <div class="text-muted">Entry Fee</div>
          <div class="fw-semibold">R <?php echo e(number_format($event->entryFee, 2)); ?></div>
        </div>
        <div class="col-12 col-md-6">
          <div class="text-muted mb-1">Player-initiated withdrawal refund (before deadline)</div>
          <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-label-success">
              <i class="ti ti-wallet me-1"></i>Wallet — R <?php echo e(number_format($walletRefundNet, 2)); ?>

              <span class="text-muted ms-1">(after <?php echo e($handlingFeePercent); ?>% handling fee)</span>
            </span>
            <span class="badge bg-label-primary">
              <i class="ti ti-building-bank me-1"></i>Bank — same net, manual EFT
            </span>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="text-muted mb-1">Super-admin full refund (no handling fee)</div>
          <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-label-warning">
              <i class="ti ti-wallet me-1"></i>Wallet — R <?php echo e(number_format($event->entryFee, 2)); ?> instant
            </span>
            <span class="badge bg-label-info">
              <i class="ti ti-building-bank me-1"></i>Bank — marked pending, manual process
            </span>
          </div>
        </div>
        <div class="col-12 col-md-6">
          <div class="text-muted mb-1">Withdrawal after deadline / no refund</div>
          <div class="d-flex flex-wrap gap-2">
            <span class="badge bg-label-secondary">
              <i class="ti ti-ban me-1"></i>Fee retained — R <?php echo e(number_format($event->entryFee, 2)); ?> not refunded
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

  
  <ul class="nav nav-tabs mb-0" id="financesTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="tab-transactions" data-bs-toggle="tab" data-bs-target="#pane-transactions" type="button" role="tab">
        <i class="ti ti-list me-1"></i>Transactions
        <span class="badge bg-secondary ms-1"><?php echo e($transactions->count()); ?></span>
      </button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-payouts" data-bs-toggle="tab" data-bs-target="#pane-payouts" type="button" role="tab">
        <i class="ti ti-cash-banknote me-1"></i>Payouts
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payoutModels->count()): ?>
          <span class="badge bg-info ms-1"><?php echo e($payoutModels->count()); ?></span>
          <span class="badge bg-danger ms-1">R <?php echo e(number_format($totalPaidOut, 2)); ?></span>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </button>
    </li>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eligibleForRefund->count() || $eligibleTeamOrders->count()): ?>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="tab-refunds" data-bs-toggle="tab" data-bs-target="#pane-refunds" type="button" role="tab">
        <i class="ti ti-receipt-refund me-1"></i>Full Refunds
        <span class="badge bg-warning text-dark ms-1"><?php echo e($eligibleForRefund->count() + $eligibleTeamOrders->count()); ?></span>
      </button>
    </li>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  </ul>

  <div class="tab-content border border-top-0 rounded-bottom mb-4">

    
    <div class="tab-pane fade show active p-0" id="pane-transactions" role="tabpanel">
      <div class="card border-0 mb-0 rounded-0">
        <div class="card-body p-0">
          <table id="txTable" class="table table-striped mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:32px;"></th>
            <th style="width:90px;">Date</th>
            <th style="width:80px;">Type</th>
            <th style="width:180px;">Participant</th>
            <th style="width:90px;">Method</th>
            <th style="width:80px;" class="text-end">Gross</th>
            <th style="width:100px;" class="text-end">PayFast Fee</th>
            <th style="width:110px;" class="text-end">Cape Tennis Fee</th>
            <th style="width:110px;" class="text-end">Net to Event</th>
            <th style="width:80px;" class="text-center">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <?php
            $payload = collect();

            if ($tx->type === 'payment' && isset($tx->order)) {
              $payload = collect([
                [
                  'mode'           => 'payment_summary',
                  'pf_payment_id'  => $tx->pf_payment_id ?? '—',
                  'entries'        => $tx->entryCount ?? 1,
                  'gross'          => number_format($tx->gross, 2),
                  'payfast_gross'  => number_format($tx->payfastGross ?? $tx->gross, 2),
                  'wallet_used'    => number_format($tx->walletUsed ?? 0, 2),
                  'pf_fee'         => number_format(abs($tx->fee), 2),
                  'cape_fee'       => number_format(abs($tx->capeFee), 2),
                  'net'            => number_format($tx->net, 2),
                ]
              ])->merge(
                collect($tx->registrationDetails ?? collect($tx->order->items ?? [])->map(fn ($item) => [
                  'player' => trim(($item->player?->name ?? '') . ' ' . ($item->player?->surname ?? '')),
                  'category' => $item->category_event?->category?->name ?? '—',
                  'price' => $item->item_price ?? 0,
                ]))->map(fn ($item) => [
                  'mode'     => 'payment_item',
                  'player'   => $item['player'] ?? '—',
                  'category' => $item['category'] ?? '—',
                  'price'    => number_format($item['price'] ?? 0, 2),
                ])
              );
            }

            if ($tx->type === 'refund') {
              $refundInitiator = ($tx->refund_status ?? '') === 'completed'
                  ? (str_contains(strtolower($tx->method ?? ''), 'admin') ? 'Super-admin full refund (no handling fee)' : 'Player-initiated (' . $handlingFeePercent . '% handling fee deducted)')
                  : 'Pending';
              $payload = collect([[
                'mode'            => 'refund',
                'pf_payment_id'   => $tx->pf_payment_id ?? '—',
                'paid_at'         => optional($tx->paid_at)->format('Y-m-d'),
                'category'        => $tx->category ?? '—',
                'gross_original'  => number_format(abs($tx->gross), 2),
                'payfast_fee'     => number_format(abs($tx->fee), 2),
                'cape_fee'        => number_format(abs($tx->capeFee), 2),
                'refund_total'    => number_format(abs($tx->net), 2),
                'refund_method'   => $tx->method ?? '—',
                'refund_status'   => $tx->refund_status ?? '—',
                'initiator'       => $refundInitiator,
                'handling_fee'    => number_format($handlingFeeExample, 2),
                'handling_pct'    => $handlingFeePercent,
              ]]);
            }

            if ($tx->type === 'withdrawal') {
              $wDeadlinePassed = now()->gt($withdrawalDeadline);
              $payload = collect([[
                'mode'              => 'withdrawal',
                'withdrawn_at'      => \Carbon\Carbon::parse($tx->created_at)->format('Y-m-d H:i'),
                'category'          => $tx->category ?? '—',
                'original_paid'     => number_format($tx->original_gross ?? 0, 2),
                'withdrawal_deadline' => $withdrawalDeadline->format('d M Y H:i'),
                'deadline_passed'   => $wDeadlinePassed,
                'refund_status'     => 'No refund issued',
                'reason'            => $wDeadlinePassed ? 'Withdrawal after deadline — fee retained' : 'Withdrawal before deadline — no refund chosen',
                'admin_can_refund'  => ($tx->original_gross ?? 0) > 0,
              ]]);
            }

            if ($tx->type === 'payout') {
              $payload = collect([[
                'mode'        => 'payout',
                'description' => $tx->description ?? '—',
                'reference'   => $tx->reference ?? '—',
                'amount'      => number_format(abs($tx->amount ?? $tx->gross), 2),
              ]]);
            }
          ?>

          <tr class="<?php echo e($tx->type === 'refund' ? 'refund-row' : ($tx->type === 'withdrawal' ? 'withdrawal-row text-muted' : ($tx->type === 'payout' ? 'payout-row' : ''))); ?>"
              <?php if($payload->count()): ?> data-items='<?php echo json_encode($payload, 15, 512) ?>' <?php endif; ?>
              <?php if($tx->type === 'withdrawal'): ?> title="Withdrawn — no refund issued" <?php endif; ?>>

            <td class="dt-toggle">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payload->count()): ?>
                <i class="ti ti-chevron-right"></i>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>

            <td><?php echo e(\Carbon\Carbon::parse($tx->created_at)->format('Y-m-d')); ?></td>

            <td>
              <?php
                $badgeClass = match($tx->type) {
                  'payment'    => 'bg-success',
                  'clothing_payment' => 'bg-primary',
                  'refund'     => 'bg-danger',
                  'withdrawal' => 'bg-secondary',
                  'payout'     => 'bg-info',
                  default      => 'bg-secondary',
                };
                $badgeLabel = match($tx->type) {
                  'clothing_payment' => 'Clothing received',
                  'refund'     => 'Refunded',
                  'withdrawal' => 'Withdrawn',
                  default      => ucfirst($tx->type),
                };
              ?>
              <span class="badge <?php echo e($badgeClass); ?>"><?php echo e($badgeLabel); ?></span>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tx->type === 'refund' && ($tx->refund_status ?? '') === 'pending'): ?>
                <span class="badge bg-warning text-dark ms-1"><i class="ti ti-clock me-1"></i>Bank Pending</span>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>

            <td class="<?php echo e($payload->count() ? 'dt-toggle' : ''); ?>">
              <span class="<?php echo e($payload->count() ? 'text-primary fw-semibold' : ''); ?>"><?php echo e($tx->player ?? '—'); ?></span>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payload->count()): ?><small class="text-muted d-block">Click for transaction details</small><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>
            <td>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tx->type === 'withdrawal'): ?>
                <span class="text-muted small">No Refund
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(now()->gt($withdrawalDeadline)): ?>
                    <i class="ti ti-clock-x ms-1 text-danger" title="After deadline — fee retained"></i>
                  <?php else: ?>
                    <i class="ti ti-clock-check ms-1 text-secondary" title="Before deadline — no refund chosen"></i>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </span>
              <?php elseif($tx->type === 'refund'): ?>
                <?php
                  $isAdminRefund = str_contains(strtolower($tx->method ?? ''), 'admin') ||
                                   str_contains(strtolower($tx->method ?? ''), 'wallet') && ($tx->refund_fee ?? 0) == 0;
                ?>
                <span class="small">
                  <?php echo e(ucfirst($tx->method ?? '—')); ?>

                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isAdminRefund): ?>
                    <i class="ti ti-shield-check ms-1 text-success" title="Admin full refund — no handling fee"></i>
                  <?php else: ?>
                    <i class="ti ti-percentage ms-1 text-warning" title="<?php echo e($handlingFeePercent); ?>% handling fee deducted"></i>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </span>
              <?php else: ?>
                <?php echo e($tx->method); ?>

              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>

            <td class="text-end">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tx->type === 'withdrawal'): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($tx->original_gross ?? 0) > 0): ?>
                  <span class="text-muted">R <?php echo e(number_format($tx->original_gross, 2)); ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php elseif($tx->type === 'refund' || $tx->type === 'payout'): ?>
                − R <?php echo e(number_format(abs($tx->gross), 2)); ?>

              <?php else: ?>
                R <?php echo e(number_format($tx->gross, 2)); ?>

              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>

            <td class="text-end <?php echo e($tx->fee >= 0 ? 'text-success' : 'text-warning'); ?>">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tx->fee != 0): ?>
                <?php echo e($tx->fee > 0 ? '+ ' : '− '); ?> R <?php echo e(number_format(abs($tx->fee), 2)); ?>

              <?php else: ?> —
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>

            <td class="text-end <?php echo e($tx->capeFee >= 0 ? 'text-success' : 'text-danger'); ?>">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tx->capeFee != 0): ?>
                <?php echo e($tx->capeFee > 0 ? '+ ' : '− '); ?> R <?php echo e(number_format(abs($tx->capeFee), 2)); ?>

              <?php else: ?> —
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>

            <td class="text-end <?php echo e($tx->net < 0 ? 'text-danger' : 'text-success'); ?>">
              <?php echo e($tx->net < 0 ? '− ' : ''); ?> R <?php echo e(number_format(abs($tx->net), 2)); ?>

            </td>
            <td class="text-center">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tx->type === 'withdrawal' && ($tx->original_gross ?? 0) > 0 && ($tx->cer_id ?? null)): ?>
                <button type="button"
                        class="btn btn-xs btn-outline-warning py-0 px-1"
                        data-bs-toggle="modal"
                        data-bs-target="#fullRefundModal"
                        data-player="<?php echo e($tx->player); ?>"
                        data-amount="<?php echo e($tx->original_gross); ?>"
                        data-route="<?php echo e(route('superadmin.finances.full-refund.registration', [$event, $tx->cer_id])); ?>"
                        title="Issue refund for this withdrawal">
                  <i class="ti ti-cash-banknote" style="font-size:.85rem;"></i>
                </button>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
        <tfoot class="table-light fw-bold">
          <tr>
            <td colspan="5" class="text-end">All Received Totals</td>
            <td class="text-end">R <?php echo e(number_format($totalGross, 2)); ?></td>
            <td class="text-end text-warning">− R <?php echo e(number_format(abs($totalPayfastFees), 2)); ?></td>
            <td class="text-end text-danger">− R <?php echo e(number_format(abs($totalCapeTennisFees), 2)); ?></td>
            <td class="text-end <?php echo e($netTournamentIncome < 0 ? 'text-danger' : 'text-success'); ?>">R <?php echo e(number_format($netTournamentIncome, 2)); ?></td>
            <td></td>
          </tr>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($totalPaidOut > 0): ?>
          <tr class="payout-row">
            <td colspan="5" class="text-end">Total Paid Out to Convenors</td>
            <td class="text-end text-danger" colspan="3">− R <?php echo e(number_format($totalPaidOut, 2)); ?></td>
            <td class="text-end text-danger">− R <?php echo e(number_format($totalPaidOut, 2)); ?></td>
            <td></td>
          </tr>
          <tr>
            <td colspan="5" class="text-end fw-bold">Balance</td>
            <td colspan="4" class="text-end fw-bold <?php echo e($balance < 0 ? 'text-danger' : 'text-success'); ?>">R <?php echo e(number_format($balance, 2)); ?></td>
            <td></td>
          </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tfoot>
      </table>
        </div>
      </div>
    </div>

    
    <div class="tab-pane fade" id="pane-payouts" role="tabpanel">
      <div class="card border-0 mb-0 rounded-0">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0"><i class="ti ti-cash-banknote me-2 text-info"></i>Convenor Payouts</h5>
          <button class="btn btn-success btn-sm" data-bs-toggle="collapse" data-bs-target="#payoutFormCollapse">
            <i class="ti ti-plus me-1"></i>Add Payout
          </button>
        </div>

    
    <div class="collapse" id="payoutFormCollapse">
      <div class="card-body border-bottom bg-light">
        <form method="POST" action="<?php echo e(route('superadmin.finances.payout.store', $event)); ?>">
          <?php echo csrf_field(); ?>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Recipient</label>
              <select id="payoutRecipient" class="form-select" required>
                <option value="">— Select recipient —</option>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $convenors; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="convenor:<?php echo e($c->id); ?>"
                          data-convenor-id="<?php echo e($c->id); ?>"
                          data-recipient-name=""
                          <?php if((string) old('convenor_id', $defaultConvenor?->id) === (string) $c->id): echo 'selected'; endif; ?>>
                    <?php echo e($c->user->name ?? 'Unknown'); ?> (Convenor<?php echo e($c->role ? ' · '.ucfirst($c->role) : ''); ?>)
                  </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eventAdmins->reject(fn ($admin) => $convenors->contains('user_id', $admin->id)); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $admin): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <option value="admin:<?php echo e($admin->id); ?>"
                          data-convenor-id=""
                          data-recipient-name="<?php echo e($admin->name); ?>"
                          <?php if(!$defaultConvenor && (string) $defaultAdmin?->id === (string) $admin->id): echo 'selected'; endif; ?>>
                    <?php echo e($admin->name); ?> (Event admin)
                  </option>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </select>
              <input type="hidden" id="payoutConvenorId" name="convenor_id" value="<?php echo e(old('convenor_id', $defaultConvenor?->id)); ?>">
              <input type="hidden" id="payoutRecipientName" name="recipient_name" value="<?php echo e(old('recipient_name', $defaultAdmin?->name)); ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Amount (R) <span class="text-danger">*</span></label>
              <input type="number" name="amount" step="0.01" min="0.01" class="form-control"
                     value="<?php echo e(old('amount', $defaultPayoutAmount > 0 ? number_format($defaultPayoutAmount, 2, '.', '') : '')); ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Payment Method <span class="text-danger">*</span></label>
              <select name="payment_method" class="form-select" required>
                <option value="bank_transfer" <?php if(old('payment_method', 'bank_transfer') === 'bank_transfer'): echo 'selected'; endif; ?>>Bank Transfer</option>
                <option value="cash" <?php if(old('payment_method') === 'cash'): echo 'selected'; endif; ?>>Cash</option>
                <option value="eft" <?php if(old('payment_method') === 'eft'): echo 'selected'; endif; ?>>EFT</option>
                <option value="other" <?php if(old('payment_method') === 'other'): echo 'selected'; endif; ?>>Other</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label">Date</label>
              <input type="date" name="paid_at" class="form-control" value="<?php echo e(old('paid_at', now()->format('Y-m-d'))); ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Description</label>
              <input type="text" name="description" class="form-control" value="<?php echo e(old('description', 'Entry fees')); ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Reference / Proof</label>
              <input type="text" name="reference" class="form-control" placeholder="e.g. EFT#12345">
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-success">
                <i class="ti ti-check me-1"></i>Save Payout
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>

    
    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead class="table-light">
          <tr>
            <th>Date</th>
            <th>Recipient</th>
            <th>Method</th>
            <th>Reference</th>
            <th>Description</th>
            <th class="text-end">Amount</th>
            <th>Paid By</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $payoutModels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $payout): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <tr>
              <td><?php echo e(optional($payout->paid_at)->format('Y-m-d') ?? '—'); ?></td>
              <td class="fw-semibold"><?php echo e($payout->display_name); ?></td>
              <td><?php echo e(ucfirst(str_replace('_', ' ', $payout->payment_method))); ?></td>
              <td><code><?php echo e($payout->reference ?? '—'); ?></code></td>
              <td><?php echo e($payout->description ?? '—'); ?></td>
              <td class="text-end text-danger fw-bold">R <?php echo e(number_format($payout->amount, 2)); ?></td>
              <td><?php echo e(optional($payout->paidByUser)->name ?? '—'); ?></td>
              <td>
                <form method="POST" action="<?php echo e(route('superadmin.finances.payout.destroy', $payout)); ?>"
                      onsubmit="return confirm('Delete this payout?')">
                  <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                  <button type="submit" class="btn btn-icon btn-sm btn-outline-danger" title="Delete">
                    <i class="ti ti-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <tr>
              <td colspan="8" class="text-center text-muted py-3">No payouts recorded yet.</td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payoutModels->count()): ?>
          <tfoot class="table-light fw-bold">
            <tr>
              <td colspan="5">Total Paid Out</td>
              <td class="text-end text-danger">R <?php echo e(number_format($totalPaidOut, 2)); ?></td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      </table>
      </div>
      </div>
    </div>

    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($eligibleForRefund->count() || $eligibleTeamOrders->count()): ?>
    <div class="tab-pane fade" id="pane-refunds" role="tabpanel">
      <div class="card border-0 mb-0 rounded-0">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">
            <i class="ti ti-receipt-refund me-2 text-warning"></i>
            Full Player Refunds
            <span class="badge bg-warning text-dark ms-2"><?php echo e($eligibleForRefund->count() + $eligibleTeamOrders->count()); ?></span>
          </h5>
          <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="collapse" data-bs-target="#fullRefundCollapse">
            <i class="ti ti-chevron-down me-1"></i>Show / Hide
          </button>
        </div>

        <div class="collapse show" id="fullRefundCollapse">
          <div class="card-body pb-1">
            <p class="text-muted small mb-3">
              Issue a <strong>full refund (no handling fee deducted)</strong> to a player's wallet or via bank transfer.
              Normal player-initiated refunds deduct a handling fee; this option bypasses that fee.
            </p>
            <div class="input-group input-group-sm" style="max-width:320px;">
              <span class="input-group-text"><i class="ti ti-search"></i></span>
              <input type="text" id="fullRefundSearch" class="form-control" placeholder="Search player or category…">
            </div>
          </div>

          <div class="table-responsive">
        <table class="table table-sm mb-0" id="fullRefundTable">
          <thead class="table-light">
            <tr>
              <th>Player(s)</th>
              <th>Category</th>
              <th>Status</th>
              <th class="text-end">Amount Paid</th>
              <th class="text-end">Refund Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eligibleForRefund; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $reg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php
                $payment = $reg->paymentInfo();
                $refundGross = round(($payment['gross'] ?? 0) + ($payment['wallet_paid'] ?? 0), 2);
              ?>
              <tr>
                <td class="fw-semibold"><?php echo e($reg->display_name); ?></td>
                <td><?php echo e(optional($reg->categoryEvent->category)->name ?? '—'); ?></td>
                <td>
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reg->status === 'withdrawn'): ?>
                    <span class="badge bg-secondary">Withdrawn</span>
                  <?php else: ?>
                    <span class="badge bg-success">Active</span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
                <td class="text-end">R <?php echo e(number_format($refundGross, 2)); ?></td>
                <td class="text-end">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($reg->refund_status === 'pending'): ?>
                    <span class="badge bg-warning text-dark">Pending</span>
                  <?php else: ?>
                    <span class="badge bg-light text-muted border">None</span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
                <td class="text-end">
                  <button type="button"
                          class="btn btn-sm btn-outline-warning"
                          data-bs-toggle="modal"
                          data-bs-target="#fullRefundModal"
                          data-player="<?php echo e($reg->display_name); ?>"
                          data-amount="<?php echo e(number_format($refundGross, 2)); ?>"
                          data-route="<?php echo e(route('superadmin.finances.full-refund.registration', [$event, $reg])); ?>">
                    <i class="ti ti-cash-banknote me-1"></i>Full Refund
                  </button>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $eligibleTeamOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $order): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <td class="fw-semibold"><?php echo e(optional($order->player)->full_name ?? '—'); ?></td>
                <td><span class="badge bg-info">Team</span></td>
                <td><span class="badge bg-success">Active</span></td>
                <td class="text-end">R <?php echo e(number_format($order->total_amount, 2)); ?></td>
                <td class="text-end">
                  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($order->refund_status === 'pending'): ?>
                    <span class="badge bg-warning text-dark">Pending</span>
                  <?php else: ?>
                    <span class="badge bg-light text-muted border">None</span>
                  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </td>
                <td class="text-end">
                  <button type="button"
                          class="btn btn-sm btn-outline-warning"
                          data-bs-toggle="modal"
                          data-bs-target="#fullRefundModal"
                          data-player="<?php echo e(optional($order->player)->full_name ?? '—'); ?>"
                          data-amount="<?php echo e(number_format($order->total_amount, 2)); ?>"
                          data-route="<?php echo e(route('superadmin.finances.full-refund.team', [$event, $order])); ?>">
                    <i class="ti ti-cash-banknote me-1"></i>Full Refund
                  </button>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          </tbody>
        </table>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  </div>

  
  <div class="modal fade" id="fullRefundModal" tabindex="-1" aria-labelledby="fullRefundModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="fullRefundForm" method="POST" action="">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="percentage" id="modalPercentageInput" value="0">
          <input type="hidden" name="preview_token" id="refundPreviewToken">
          <div class="modal-header">
            <h5 class="modal-title" id="fullRefundModalLabel">
              <i class="ti ti-receipt-refund me-2 text-warning"></i>Issue Full Refund
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>

          <div class="modal-body">
            <div class="alert alert-warning d-flex align-items-center gap-2 mb-3" id="modalAlertBox">
              <i class="ti ti-alert-triangle fs-5"></i>
              <span id="modalAlertText">This will refund the <strong>full amount</strong> — no handling fee will be deducted.</span>
            </div>

            <dl class="row mb-3">
              <dt class="col-sm-4">Player</dt>
              <dd class="col-sm-8 fw-semibold" id="modalPlayerName">—</dd>
              <dt class="col-sm-4">Original Amount</dt>
              <dd class="col-sm-8 fw-semibold" id="modalOriginalAmount">R 0.00</dd>
              <dt class="col-sm-4">Refund Amount</dt>
              <dd class="col-sm-8">
                <span class="fs-5 text-success fw-bold">R <span id="modalAmount">0.00</span></span>
                <small class="text-muted d-block" id="modalAmountNote">No handling fee deducted</small>
              </dd>
            </dl>

            <hr>

            
            <div class="mb-3">
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" id="enablePercentage" role="switch">
                <label class="form-check-label fw-semibold" for="enablePercentage">
                  Deduct a percentage (cancellation / handling fee)
                </label>
              </div>
              <div id="percentageField" class="d-none mt-2">
                <label for="percentageRange" class="form-label">
                  Deduction: <strong><span id="percentageDisplay">0</span>%</strong>
                  <span class="text-muted ms-2">( − R <span id="modalFeeAmount">0.00</span> )</span>
                </label>
                <input type="range" class="form-range" id="percentageRange" min="0" max="100" step="1" value="0">
                <div class="d-flex justify-content-between">
                  <small class="text-muted">0%</small>
                  <small class="text-muted">25%</small>
                  <small class="text-muted">50%</small>
                  <small class="text-muted">75%</small>
                  <small class="text-muted">100%</small>
                </div>
              </div>
            </div>

            <hr>

            <div class="mb-3">
              <label class="form-label fw-semibold">Refund Method <span class="text-danger">*</span></label>

              <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="method" id="methodWallet" value="wallet" required>
                <label class="form-check-label" for="methodWallet">
                  <i class="ti ti-wallet me-1 text-success"></i>
                  <strong>Wallet</strong> — instant credit to the payer's Cape Tennis wallet
                </label>
              </div>

              <div class="form-check">
                <input class="form-check-input" type="radio" name="method" id="methodBank" value="bank">
                <label class="form-check-label" for="methodBank">
                  <i class="ti ti-building-bank me-1 text-primary"></i>
                  <strong>Bank Transfer</strong> — marked as pending; process payment manually (or via PayFast if applicable)
                </label>
              </div>
            </div>
            <div id="walletRefundEmail" class="d-none">
              <label for="refundReason" class="form-label fw-semibold">Reason for refund</label>
              <textarea name="reason" id="refundReason" class="form-control mb-2" maxlength="2000" rows="3"></textarea>
              <button type="button" id="previewRefundEmail" class="btn btn-outline-primary mb-2">Preview refund and email</button>
              <div id="refundPreviewStatus" class="small mb-2" role="status"></div>
              <div id="refundRecipients" class="small mb-2 text-break"></div>
              <iframe id="refundEmailFrame" title="Refund confirmation email preview" class="w-100 border d-none" sandbox="" style="height: 350px"></iframe>
              <div class="form-check mt-2">
                <input type="checkbox" id="confirmRefundEmail" class="form-check-input">
                <label for="confirmRefundEmail" class="form-check-label">I checked the amount, reason and email recipients.</label>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" id="fullRefundSubmit" class="btn btn-warning fw-semibold">
              <i class="ti ti-cash-banknote me-1"></i><span id="fullRefundSubmitLabel">Confirm Full Refund</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="<?php echo e(asset('assets/vendor/libs/datatables-bs5/datatables-bootstrap5.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
$(function () {
  const table = $('#txTable').DataTable({
    order: [[1, 'desc']],
    columnDefs: [{ orderable: false, targets: [0, 9] }],
    autoWidth: false
  });

  function renderItems(items) {
    if (!items.length) return '';

    if (items[0].mode === 'refund') {
      const r = items[0];
      const handlingRow = parseFloat(r.payfast_fee) > 0 || r.initiator.includes('handling')
        ? `<tr><th>Handling Fee (${r.handling_pct}%)</th><td class="text-warning">− R ${r.handling_fee}</td></tr>`
        : `<tr><th>Handling Fee</th><td class="text-success">None (admin full refund)</td></tr>`;
      const statusBadge = r.refund_status === 'completed'
        ? `<span class="badge bg-success">Completed</span>`
        : `<span class="badge bg-warning text-dark">Pending</span>`;
      return `<table class="table table-sm mb-0 child-table">
        <tbody>
          <tr><th>Initiator</th><td>${r.initiator}</td></tr>
          <tr><th>Status</th><td>${statusBadge}</td></tr>
          <tr><th>Method</th><td>${r.refund_method}</td></tr>
          <tr><th>PayFast ID</th><td><code>${r.pf_payment_id}</code></td></tr>
          <tr><th>Original Payment Date</th><td>${r.paid_at}</td></tr>
          <tr><th>Category</th><td>${r.category}</td></tr>
          <tr><th>Gross Paid</th><td>R ${r.gross_original}</td></tr>
          ${handlingRow}
          <tr><th>PayFast Fee (recovered)</th><td>R ${r.payfast_fee}</td></tr>
          <tr><th>Cape Tennis Fee (recovered)</th><td>R ${r.cape_fee}</td></tr>
          <tr class="fw-bold text-danger"><th>Total Refunded to Player</th><td>R ${r.refund_total}</td></tr>
        </tbody></table>`;
    }

    if (items[0].mode === 'withdrawal') {
      const w = items[0];
      const deadlineBadge = w.deadline_passed
        ? `<span class="badge bg-danger">After deadline — fee retained by event</span>`
        : `<span class="badge bg-secondary">Before deadline — no refund chosen / admin withdrawal</span>`;
      const adminRefundHint = w.admin_can_refund
        ? `<tr><th>Super-admin Action</th><td><span class="badge bg-warning text-dark">Use the Refund button on this row to issue a full refund</span></td></tr>`
        : `<tr><th>Super-admin Action</th><td class="text-muted">No payment on file — nothing to refund</td></tr>`;
      return `<table class="table table-sm mb-0 child-table">
        <tbody>
          <tr><th>Withdrawn At</th><td>${w.withdrawn_at}</td></tr>
          <tr><th>Category</th><td>${w.category}</td></tr>
          <tr><th>Original Amount Paid</th><td>R ${w.original_paid}</td></tr>
          <tr><th>Withdrawal Deadline</th><td>${w.withdrawal_deadline}</td></tr>
          <tr><th>Deadline Status</th><td>${deadlineBadge}</td></tr>
          <tr><th>Refund Status</th><td class="text-muted">${w.refund_status}</td></tr>
          <tr><th>Reason</th><td>${w.reason}</td></tr>
          ${adminRefundHint}
        </tbody></table>`;
    }

    if (items[0].mode === 'payout') {
      const p = items[0];
      return `<table class="table table-sm mb-0 child-table">
        <tbody>
          <tr><th>Description</th><td>${p.description}</td></tr>
          <tr><th>Reference</th><td><code>${p.reference}</code></td></tr>
          <tr class="fw-bold text-info"><th>Amount Paid Out</th><td>R ${p.amount}</td></tr>
        </tbody></table>`;
    }

    if (items[0].mode === 'payment_summary') {
      const s = items[0];
      const players = items.filter(i => i.mode === 'payment_item');

      const walletRow = parseFloat(s.wallet_used) > 0
        ? `<tr><th>Wallet Credit Applied</th><td class="text-info">R ${s.wallet_used}</td></tr>
           <tr><th>PayFast Amount</th><td>R ${s.payfast_gross}</td></tr>`
        : '';

      let html = `<table class="table table-sm mb-0 child-table">
        <tbody>
          <tr><th>PayFast Reference</th><td><code>${s.pf_payment_id}</code></td></tr>
          <tr><th>Entries</th><td>${s.entries}</td></tr>
          <tr><th>Gross Paid</th><td>R ${s.gross}</td></tr>
          ${walletRow}
          <tr><th>PayFast Fee</th><td class="text-danger">− R ${s.pf_fee}</td></tr>
          <tr><th>Cape Tennis Fee</th><td class="text-danger">− R ${s.cape_fee}</td></tr>
          <tr class="fw-bold text-success"><th>Net to Event</th><td>R ${s.net}</td></tr>
        </tbody></table>`;

      if (players.length) {
        html += `<table class="table table-sm mb-0 child-table mt-2">
          <thead><tr><th>Player</th><th>Category</th><th class="text-end">Entry Price</th></tr></thead>
          <tbody>`;
        players.forEach(p => {
          html += `<tr><td>${p.player || '—'}</td><td>${p.category || '—'}</td><td class="text-end">R ${p.price}</td></tr>`;
        });
        html += `</tbody></table>`;
      }
      return html;
    }
    return '';
  }

  $('#txTable tbody').on('click', 'td.dt-toggle', function () {
    const tr = $(this).closest('tr');
    const row = table.row(tr);
    const items = tr.data('items') || [];
    if (!items.length) return;
    row.child.isShown()
      ? (row.child.hide(), tr.removeClass('shown'))
      : (row.child(renderItems(items)).show(), tr.addClass('shown'));
  });
});

// Full Player Refunds search filter
const payoutRecipient = document.getElementById('payoutRecipient');
if (payoutRecipient) {
  const syncPayoutRecipient = () => {
    const option = payoutRecipient.options[payoutRecipient.selectedIndex];
    document.getElementById('payoutConvenorId').value = option?.dataset.convenorId || '';
    document.getElementById('payoutRecipientName').value = option?.dataset.recipientName || '';
  };

  payoutRecipient.addEventListener('change', syncPayoutRecipient);
  syncPayoutRecipient();
}

// Full Player Refunds search filter
const fullRefundSearch = document.getElementById('fullRefundSearch');
if (fullRefundSearch) {
  fullRefundSearch.addEventListener('input', function () {
    const term = this.value.toLowerCase().trim();
    document.querySelectorAll('#fullRefundTable tbody tr').forEach(function (row) {
      const text = row.textContent.toLowerCase();
      row.style.display = term === '' || text.includes(term) ? '' : 'none';
    });
  });
}

// Full Refund Modal: populate form action and display fields from button data attributes
const fullRefundModal = document.getElementById('fullRefundModal');
if (fullRefundModal) {
  let modalGrossAmount = 0;
  let individualRefund = false;
  let previewRevision = 0;
  const form = document.getElementById('fullRefundForm');
  const reason = document.getElementById('refundReason');
  const token = document.getElementById('refundPreviewToken');
  const confirmed = document.getElementById('confirmRefundEmail');
  function invalidatePreview() {
    previewRevision++;
    token.value = '';
    confirmed.checked = false;
    document.getElementById('refundEmailFrame').classList.add('d-none');
    document.getElementById('refundRecipients').textContent = '';
    document.getElementById('refundPreviewStatus').textContent = '';
  }
  function walletEmailState() {
    const active = individualRefund && document.getElementById('methodWallet').checked;
    document.getElementById('walletRefundEmail').classList.toggle('d-none', !active);
    reason.required = active;
    document.getElementById('fullRefundSubmit').disabled = active && (!token.value || !confirmed.checked);
  }
  reason.addEventListener('input', () => { invalidatePreview(); walletEmailState(); });
  confirmed.addEventListener('change', walletEmailState);
  fullRefundModal.querySelectorAll('input[name="method"]').forEach(input => input.addEventListener('change', walletEmailState));
  document.getElementById('previewRefundEmail').addEventListener('click', async function () {
    if (!reason.reportValidity()) return;
    invalidatePreview();
    const revision = previewRevision;
    walletEmailState();
    this.disabled = true;
    try {
      const url = new URL(form.action + '/preview', window.location.origin);
      const response = await fetch(url, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value },
        body: JSON.stringify({ reason: reason.value, percentage: document.getElementById('modalPercentageInput').value })
      });
      const data = await response.json();
      if (revision !== previewRevision) return;
      if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || 'Unable to preview refund.');
      token.value = data.token;
      document.getElementById('refundRecipients').textContent = 'To: ' + data.to + ' | CC: ' + data.cc.join(', ') + ' | Reply-To: ' + data.reply_to.join(', ') + ' | Wallet credit: R' + Number(data.net).toFixed(2);
      const frame = document.getElementById('refundEmailFrame');
      frame.srcdoc = data.html;
      frame.classList.remove('d-none');
      document.getElementById('refundPreviewStatus').textContent = 'Review the email below. It will be queued after the wallet credit succeeds.';
    } catch (error) {
      if (revision === previewRevision) document.getElementById('refundPreviewStatus').textContent = error.message;
    } finally {
      this.disabled = false;
      walletEmailState();
    }
  });

  function updateRefundDisplay(percentage) {
    invalidatePreview();
    const fee = Math.round(modalGrossAmount * (percentage / 100) * 100) / 100;
    const net = Math.round((modalGrossAmount - fee) * 100) / 100;

    document.getElementById('modalAmount').textContent = net.toFixed(2);
    document.getElementById('modalFeeAmount').textContent = fee.toFixed(2);
    document.getElementById('percentageDisplay').textContent = percentage;
    document.getElementById('modalPercentageInput').value = percentage;
    walletEmailState();

    if (percentage > 0) {
      document.getElementById('modalAmountNote').textContent = percentage + '% deducted (R ' + fee.toFixed(2) + ' handling fee)';
      document.getElementById('modalAlertText').innerHTML = 'A <strong>' + percentage + '% handling fee</strong> will be deducted — player receives <strong>R ' + net.toFixed(2) + '</strong>.';
      document.getElementById('modalAlertBox').classList.remove('alert-warning');
      document.getElementById('modalAlertBox').classList.add('alert-info');
      document.getElementById('fullRefundSubmitLabel').textContent = 'Confirm Partial Refund (' + percentage + '% deducted)';
    } else {
      document.getElementById('modalAmountNote').textContent = 'No handling fee deducted';
      document.getElementById('modalAlertText').innerHTML = 'This will refund the <strong>full amount</strong> — no handling fee will be deducted.';
      document.getElementById('modalAlertBox').classList.remove('alert-info');
      document.getElementById('modalAlertBox').classList.add('alert-warning');
      document.getElementById('fullRefundSubmitLabel').textContent = 'Confirm Full Refund';
    }
  }

  fullRefundModal.addEventListener('show.bs.modal', function (event) {
    const btn = event.relatedTarget;
    const rawAmount = parseFloat((btn.dataset.amount || '0').replace(/[^0-9.]/g, ''));
    modalGrossAmount = isNaN(rawAmount) ? 0 : rawAmount;

    document.getElementById('modalPlayerName').textContent = btn.dataset.player || '—';
    document.getElementById('modalOriginalAmount').textContent = 'R ' + modalGrossAmount.toFixed(2);
    document.getElementById('fullRefundForm').action = btn.dataset.route || '';
    individualRefund = form.action.includes('/registration/');
    reason.value = '';
    invalidatePreview();

    // Reset percentage controls
    document.getElementById('enablePercentage').checked = false;
    document.getElementById('percentageField').classList.add('d-none');
    document.getElementById('percentageRange').value = 0;
    updateRefundDisplay(0);

    // Reset radio buttons and re-enable submit button on each open
    fullRefundModal.querySelectorAll('input[name="method"]').forEach(r => r.checked = false);
    document.getElementById('fullRefundSubmit').disabled = false;
    walletEmailState();
  });

  document.getElementById('enablePercentage').addEventListener('change', function () {
    const field = document.getElementById('percentageField');
    if (this.checked) {
      field.classList.remove('d-none');
    } else {
      field.classList.add('d-none');
      document.getElementById('percentageRange').value = 0;
      updateRefundDisplay(0);
    }
  });

  document.getElementById('percentageRange').addEventListener('input', function () {
    updateRefundDisplay(parseInt(this.value, 10));
  });

  document.getElementById('fullRefundForm').addEventListener('submit', function (event) {
    if (individualRefund && document.getElementById('methodWallet').checked && (!token.value || !confirmed.checked)) {
      event.preventDefault();
      walletEmailState();
      return;
    }
    document.getElementById('fullRefundSubmit').disabled = true;
  });
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\superadmin\event-finances.blade.php ENDPATH**/ ?>