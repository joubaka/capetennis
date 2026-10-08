

<?php $__env->startSection('title', $event->name . ' – Transactions'); ?>

<?php $__env->startSection('vendor-style'); ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-style'); ?>
<style>
  td.dt-toggle { cursor:pointer; text-align:center; user-select:none; }
  tr.shown td.dt-toggle i { transform:rotate(90deg); }
  td.dt-toggle i { font-size:1.1rem; color:#696cff; transition:.2s; }

  .child-table {
    background:#f9fafc;
    border-radius:.375rem;
  }
  .child-table thead th {
    background:#eef1ff;
    font-size:.75rem;
    text-transform:uppercase;
  }
  .child-table td { font-size:.8rem; }

  tr.refund-row {
    background:#fff4f4 !important;
  }

  tr.admin-entry-row {
    background:#fffbf0 !important;
    opacity: 0.85;
  }
  tr.admin-entry-row td {
    color: #888 !important;
  }
  tr.admin-entry-row td.text-warning,
  tr.admin-entry-row td.text-danger {
    color: #aaa !important;
  }



  #transactionsTable td.text-end {
    font-variant-numeric: tabular-nums;
  }
  #transactionsTable {
    table-layout: fixed;
    width: 100%;
  }

  #transactionsTable th,
  #transactionsTable td {
    white-space: nowrap;
  }

  /* Tighten up the DataTables control row */
  .dt-controls-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: .5rem;
    padding: .75rem 1rem;
  }
</style>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xl">
  <?php echo $__env->make('backend.event.partials.header', [
    'eventWorkspaceActive' => 'more',
    'eventWorkspaceIcon' => 'ti-credit-card',
    'eventWorkspaceSubtitle' => 'Tournament transactions',
  ], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 no-print">
    <div><h2 class="h4 mb-1">Tournament transactions</h2><p class="text-muted mb-0">Registration and clothing receipts, withdrawals, fees and payouts.</p></div>
    <div class="d-flex gap-2">
        <a href="<?php echo e(route('transactions.pdf', $event)); ?>" class="btn btn-outline-primary btn-sm">
          Export Transactions
        </a>
    </div>
  </div>

  
  <?php $totalWithdrawnCount = ($refundCount ?? 0) + ($noRefundCount ?? 0); ?>
  <div class="row g-3 mb-4">

    
    <div class="col-md-2">
      <div class="card border-start border-primary h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">Total Received</small>
          <h4 class="mb-1">R <?php echo e(number_format($totalGross, 2)); ?></h4>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($clothingReceived ?? 0) > 0): ?>
            <small class="text-muted d-block">Registration R <?php echo e(number_format($registrationReceived ?? $totalGross, 2)); ?> · Clothing R <?php echo e(number_format($clothingReceived, 2)); ?></small>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <small class="text-muted d-block">
            <?php echo e($totalEntries); ?> total <?php echo e($totalEntries === 1 ? 'entry' : 'entries'); ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($totalWithdrawnCount ?? 0) > 0): ?> · <?php echo e($totalWithdrawnCount); ?> withdrew <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($refundCount > 0): ?> · <?php echo e($refundCount); ?> <?php echo e($refundCount === 1 ? 'refund' : 'refunds'); ?> <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </small>
          <?php $payfastEntries = $totalEntries - $adminEntriesCount; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($adminEntriesCount > 0): ?>
            <hr class="my-2">
            <small class="text-muted d-block">
              <i class="ti ti-credit-card text-primary me-1"></i>
              <?php echo e($payfastEntries); ?> via PayFast · <strong>R <?php echo e(number_format($totalGross, 2)); ?></strong>
            </small>
            <small class="text-warning d-block mt-1">
              <i class="ti ti-cash text-warning me-1"></i>
              <?php echo e($adminEntriesCount); ?> admin-entry fee <?php echo e($adminEntriesCount === 1 ? 'liability' : 'liabilities'); ?> · <strong>R 0.00 received or reconciled</strong>
            </small>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>

    
    <div class="col-md-2">
      <div class="card border-start border-danger h-100">
        <div class="card-body">
          <?php
            $activeEntries = $totalEntries - ($completedRefundCount ?? 0);
          ?>
          <small class="text-muted d-block mb-1">
            Withdrawals (<?php echo e($totalWithdrawnCount); ?>)
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($pendingRefundCount ?? 0) > 0): ?>
              <span class="badge bg-warning text-dark ms-1"><?php echo e($pendingRefundCount); ?> pending</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </small>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($totalWithdrawals ?? 0) > 0): ?>
            <h4 class="text-danger mb-1">− R <?php echo e(number_format($totalWithdrawals, 2)); ?></h4>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($completedWithdrawalsTotal ?? 0) > 0): ?>
              <small class="text-muted d-block">R <?php echo e(number_format($completedWithdrawalsTotal, 2)); ?> refunded</small>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($pendingWithdrawalsTotal ?? 0) > 0): ?>
              <small class="text-muted d-block text-warning">R <?php echo e(number_format($pendingWithdrawalsTotal, 2)); ?> pending</small>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php else: ?>
            <h4 class="text-danger mb-1">− R 0.00</h4>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($noRefundCount ?? 0) > 0): ?>
            <small class="text-muted d-block mt-1">
              <?php echo e($noRefundCount); ?> withdrew · fees not refunded
            </small>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>

    
    <div class="col-md-2">
      <div class="card border-start border-warning h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">PayFast Fees (net)</small>
          <h4 class="text-warning mb-1">− R <?php echo e(number_format(abs($totalPayfastFees), 2)); ?></h4>
          <?php $activePayfastEntries = $totalEntries - $completedRefundCount - $adminEntriesCount; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($adminEntriesCount > 0): ?>
            <small class="text-muted d-block"><?php echo e($activePayfastEntries); ?> active PayFast <?php echo e($activePayfastEntries === 1 ? 'entry' : 'entries'); ?></small>
            <small class="text-muted d-block"><?php echo e($adminEntriesCount); ?> admin = R 0.00 fee</small>
          <?php else: ?>
            <small class="text-muted d-block"><?php echo e($totalEntries - $completedRefundCount); ?> active <?php echo e(($totalEntries - $completedRefundCount) === 1 ? 'entry' : 'entries'); ?></small>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($completedRefundCount > 0): ?>
            <small class="text-muted d-block"><?php echo e($completedRefundCount); ?> refunded — no PF fee</small>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>

    
    <div class="col-md-2">
      <div class="card border-start border-danger h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">Cape Tennis Fees (net)</small>
          <h4 class="text-danger mb-1">− R <?php echo e(number_format(abs($totalCapeTennisFees), 2)); ?></h4>
          <?php
            $chargedEntries = max(0, $totalEntries - ($completedRefundCount ?? 0));
          ?>
          <small class="text-muted d-block">
            <?php echo e($chargedEntries); ?> fee-bearing <?php echo e($chargedEntries === 1 ? 'entry' : 'entries'); ?> × R <?php echo e(number_format($feePerEntry, 2)); ?>

          </small>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($noRefundCount ?? 0) > 0): ?>
            <small class="text-muted d-block">Includes <?php echo e($noRefundCount); ?> withdrawn (not refunded)</small>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($adminEntriesCount > 0): ?>
            <small class="text-muted d-block">Includes <?php echo e($adminEntriesCount); ?> admin <?php echo e($adminEntriesCount === 1 ? 'entry' : 'entries'); ?> charged Cape fee</small>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
      </div>
    </div>

    
    <div class="col-md-2">
      <div class="card border-start border-secondary h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">Payouts</small>
          <h4 class="text-secondary mb-1"><?php echo e($totalPayouts > 0 ? '− ' : ''); ?>R <?php echo e(number_format(abs($totalPayouts), 2)); ?></h4>
          <small class="text-muted d-block">Paid to organiser</small>
        </div>
      </div>
    </div>

    
    <div class="col-md-2">
      <div class="card border-start border-success h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">Net Tournament Income</small>
          <h4 class="<?php echo e($netTournamentIncome >= 0 ? 'text-success' : 'text-danger'); ?> mb-1">R <?php echo e(number_format($netTournamentIncome, 2)); ?></h4>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($adminEntriesCount > 0): ?>
            <?php $unreconciledNominalValue = round($adminEntriesCount * (float) $event->entryFee, 2); ?>
            <small class="text-warning d-block">
              <i class="ti ti-alert-triangle me-1"></i>
              Nominal admin-entry value excluded: R <?php echo e(number_format($unreconciledNominalValue, 2)); ?> (not received; not payment or refund evidence)
            </small>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <small class="text-muted d-block">After all fees &amp; payouts</small>
        </div>
      </div>
    </div>

  </div>


  
  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
      <table id="transactionsTable" class="table table-striped mb-0 w-100">
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
  </tr>
</thead>


        <tbody>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $transactions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tx): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

          <?php
            $payload = collect();

            // PAYMENT CHILD DATA
          // =========================
// PAYMENT CHILD DATA
// =========================
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
    collect($tx->order->items ?? [])->map(fn ($item) => [
      'mode'     => 'payment_item',
      'player'   => trim(($item->player?->name ?? '') . ' ' . ($item->player?->surname ?? '')),
      'category' => $item->category_event?->category?->name ?? '—',
      'price'    => number_format($item->item_price ?? 0, 2),
    ])
  );
}

if ($tx->type === 'clothing_payment') {
  $payload = collect($tx->registrationDetails ?? [])->map(fn ($item) => [
    'mode' => 'payment_item',
    'player' => $item['player'] ?? '—',
    'category' => trim(($item['item'] ?? 'Clothing') . ' · ' . ($item['size'] ?? '—')),
    'price' => number_format($item['price'] ?? 0, 2),
  ]);
}


            // REFUND CHILD DATA
            if ($tx->type === 'refund') {
              $payload = collect([[
                'mode'              => 'refund',
                'refund_status'     => $tx->refund_status ?? '—',
                'pf_payment_id'     => $tx->pf_payment_id ?? '—',
                'paid_at'           => optional($tx->paid_at)->format('Y-m-d'),
                'category'          => $tx->category ?? '—',
                'gross_original'    => number_format(abs($tx->gross), 2),
                'payfast_fee'       => number_format($tx->displayFee ?? 0, 2),
                'cape_fee'          => number_format($tx->displayCapeFee ?? 0, 2),
                'withdrawal_fee'    => number_format($tx->withdrawalFee ?? 0, 2),
                'refund_total'      => number_format(abs($tx->net), 2),
              ]]);
            }

            // WITHDRAWAL (NO-REFUND) CHILD DATA
            if ($tx->type === 'withdrawal') {
              $payload = collect([[
                'mode'           => 'withdrawal',
                'category'       => $tx->category ?? '—',
                'paid_at'        => optional($tx->paid_at)->format('Y-m-d'),
                'original_gross' => number_format($tx->original_gross ?? 0, 2),
                'refund_status'  => 'No Refund Issued',
              ]]);
            }
          ?>

          <tr class="<?php echo e($tx->type === 'refund' ? 'refund-row' : ($tx->type === 'withdrawal' ? 'withdrawal-row text-muted' : ($tx->type === 'admin_entry_fee' ? 'admin-entry-row' : ''))); ?>"
              <?php if($payload->count()): ?> data-items='<?php echo json_encode($payload, 15, 512) ?>' <?php endif; ?>
              <?php if($tx->type === 'admin_entry_fee'): ?> title="Operational admin-entry fee liability only — no received payment or refund evidence" <?php endif; ?>
              <?php if($tx->type === 'withdrawal'): ?> title="Withdrawn — no refund issued" <?php endif; ?>>

            <td class="dt-toggle">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($payload->count()): ?>
                <i class="ti ti-chevron-right"></i>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>

            <td><?php echo e(\Carbon\Carbon::parse($tx->created_at)->format('Y-m-d')); ?></td>

            <td>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tx->type === 'admin_entry_fee'): ?>
                <span class="badge bg-warning text-dark">Admin fee liability</span>
              <?php elseif($tx->type === 'payment'): ?>
                <span class="badge bg-success">Payment</span>
              <?php elseif($tx->type === 'clothing_payment'): ?>
                <span class="badge bg-primary">Clothing received</span>
              <?php elseif($tx->type === 'refund'): ?>
                <span class="badge bg-danger">Refunded</span>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($tx->refund_status ?? '') === 'pending'): ?>
                  <span class="badge bg-warning text-dark ms-1"><i class="ti ti-clock me-1"></i>Bank Pending</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php elseif($tx->type === 'withdrawal'): ?>
                <span class="badge bg-secondary">Withdrawn</span>
              <?php elseif($tx->type === 'payout'): ?>
                <span class="badge bg-secondary">Payout</span>
              <?php else: ?>
                <span class="badge bg-secondary"><?php echo e(ucfirst($tx->type)); ?></span>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>

            <td><?php echo e($tx->player ?? '—'); ?></td>
            <td>
              <?php
                $m = $tx->method ?? '';
              ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tx->type === 'admin_entry_fee'): ?>
                <span class="badge bg-secondary">No payment evidence</span>
              <?php elseif($m === 'PayFast'): ?>
                <span class="badge bg-success">PayFast</span>
              <?php elseif($m === 'Wallet'): ?>
                <span class="badge bg-info text-dark">Wallet</span>
              <?php elseif(str_contains($m, 'Wallet')): ?>
                <span class="badge bg-success">PayFast</span>
                <span class="badge bg-info text-dark">+ Wallet</span>
              <?php elseif($m): ?>
                <span class="badge bg-light text-dark border"><?php echo e($m); ?></span>
              <?php else: ?>
                —
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>

            
            <td class="text-end <?php echo e($tx->type === 'payout' ? 'text-secondary' : ($tx->type === 'withdrawal' ? 'text-muted' : '')); ?>">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tx->type === 'withdrawal'): ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($tx->original_gross ?? 0) > 0): ?>
                  <span class="text-muted">R <?php echo e(number_format($tx->original_gross, 2)); ?></span>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php else: ?>
                <?php echo e(in_array($tx->type, ['refund', 'payout']) ? '− ' : ''); ?>

                R <?php echo e(number_format(abs($tx->gross), 2)); ?>

              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>

            
            <td class="text-end">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tx->fee != 0): ?>
                <span class="<?php echo e($tx->fee > 0 ? 'text-success' : 'text-warning'); ?>">
                  <?php echo e($tx->fee > 0 ? '+ ' : '− '); ?>R <?php echo e(number_format(abs($tx->fee), 2)); ?>

                </span>
              <?php else: ?>
                —
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>

            
            <td class="text-end">
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($tx->capeFee != 0): ?>
                <span class="<?php echo e($tx->capeFee > 0 ? 'text-success' : 'text-danger'); ?>">
                  <?php echo e($tx->capeFee > 0 ? '+ ' : '− '); ?>R <?php echo e(number_format(abs($tx->capeFee), 2)); ?>

                </span>
              <?php else: ?>
                —
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </td>


            
            <td class="text-end <?php echo e($tx->net < 0 ? 'text-danger' : 'text-success'); ?>">
              <?php echo e($tx->net < 0 ? '− ' : ''); ?>

              R <?php echo e(number_format(abs($tx->net), 2)); ?>

            </td>
          </tr>

        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>

</div>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('vendor-script'); ?>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-script'); ?>
<script>
$(function () {

  const table = $('#transactionsTable').DataTable({
    order: [[1, 'desc']],
    columnDefs: [
      { orderable: false, targets: 0 }
    ],
    autoWidth: false,
    dom:
      "<'dt-controls-row'<'d-flex align-items-center gap-2'l><'ms-auto'f>>" +
      "<'row'<'col-12'tr>>" +
      "<'dt-controls-row'<'text-muted small'i><'ms-auto'p>>",
    language: {
      search:         '',
      searchPlaceholder: 'Search…',
      lengthMenu:     '_MENU_ per page',
      info:           'Showing _START_–_END_ of _TOTAL_',
      paginate: {
        previous: '<i class="ti ti-chevron-left"></i>',
        next:     '<i class="ti ti-chevron-right"></i>'
      }
    }
  });


  function renderItems(items) {
    if (!items.length) return '';

    /* =========================
       REFUND
    ========================= */
    if (items[0].mode === 'refund') {
      const r = items[0];
      const statusBadge = r.refund_status === 'pending'
        ? `<span class="badge bg-warning text-dark">Pending</span>`
        : `<span class="badge bg-success">Completed</span>`;
      return `
        <table class="table table-sm mb-0 child-table">
          <tbody>
            <tr><th>Status</th><td>${statusBadge}</td></tr>
            <tr><th>PayFast ID</th><td><code>${r.pf_payment_id}</code></td></tr>
            <tr><th>Original Payment Date</th><td>${r.paid_at}</td></tr>
            <tr><th>Category</th><td>${r.category}</td></tr>
            <tr><th>Gross Paid</th><td>R ${r.gross_original}</td></tr>
            <tr><th>PayFast Fee (recovered)</th><td>R ${r.payfast_fee}</td></tr>
            <tr><th>Cape Tennis Fee (recovered)</th><td>R ${r.cape_fee}</td></tr>
            <tr class="fw-bold text-danger">
              <th>Total Refund Impact</th>
              <td>R ${r.refund_total}</td>
            </tr>
          </tbody>
        </table>
      `;
    }

    /* =========================
       WITHDRAWAL (NO REFUND)
    ========================= */
    if (items[0].mode === 'withdrawal') {
      const w = items[0];
      return `
        <table class="table table-sm mb-0 child-table">
          <tbody>
            <tr><th>Status</th><td><span class="badge bg-secondary">No Refund Issued</span></td></tr>
            <tr><th>Category</th><td>${w.category}</td></tr>
            <tr><th>Original Payment Date</th><td>${w.paid_at || '—'}</td></tr>
            <tr><th>Original Amount Paid</th><td class="text-muted">R ${w.original_gross}</td></tr>
            <tr><td colspan="2" class="text-muted small">This withdrawal was processed without a refund. The entry fee is retained.</td></tr>
          </tbody>
        </table>
      `;
    }

    /* =========================
       PAYMENT (SUMMARY + ITEMS)
    ========================= */
    if (items[0].mode === 'payment_summary') {

      const s = items[0];
      const players = items.filter(i => i.mode === 'payment_item');

      const walletRow = parseFloat(s.wallet_used) > 0
        ? `<tr><th>Wallet Credit Applied</th><td class="text-info">R ${s.wallet_used}</td></tr>
           <tr><th>PayFast Amount</th><td>R ${s.payfast_gross}</td></tr>`
        : '';

      let html = `
        <table class="table table-sm mb-0 child-table">
          <tbody>
            ${s.pf_payment_id ? `<tr><th>PayFast Reference</th><td><code>${s.pf_payment_id}</code></td></tr>` : ''}
            <tr><th>Entries</th><td>${s.entries}</td></tr>
            <tr><th>Gross Paid</th><td>R ${s.gross}</td></tr>
            ${walletRow}
            <tr><th>PayFast Fee</th><td class="text-danger">− R ${s.pf_fee}</td></tr>
            <tr><th>Cape Tennis Fee</th><td class="text-danger">− R ${s.cape_fee}</td></tr>
            <tr class="fw-bold text-success">
              <th>Net to Event</th>
              <td>R ${s.net}</td>
            </tr>
          </tbody>
        </table>
      `;

      if (players.length) {
        html += `
          <table class="table table-sm mb-0 child-table mt-2">
            <thead>
              <tr>
                <th>Player</th>
                <th>Category</th>
                <th class="text-end">Entry Price</th>
              </tr>
            </thead>
            <tbody>
        `;

        players.forEach(p => {
          html += `
            <tr>
              <td>${p.player || '—'}</td>
              <td>${p.category || '—'}</td>
              <td class="text-end">R ${p.price}</td>
            </tr>
          `;
        });

        html += `</tbody></table>`;
      }

      return html;
    }

    return '';
  }

  $('#transactionsTable tbody').on('click', 'td.dt-toggle', function () {
    const tr = $(this).closest('tr');
    const row = table.row(tr);
    const items = tr.data('items') || [];

    if (!items.length) return;

    row.child.isShown()
      ? (row.child.hide(), tr.removeClass('shown'))
      : (row.child(renderItems(items)).show(), tr.addClass('shown'));
  });

});
</script>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\transactions.blade.php ENDPATH**/ ?>