

<hr class="my-4">

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">
    <i class="ti ti-report-money me-2 text-warning"></i>Finances
  </h4>
  <div class="d-flex gap-2 no-print">
    <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
      <i class="ti ti-printer me-1"></i>Print / PDF
    </button>
    <a href="<?php echo e(route('admin.events.transactions', $event)); ?>" class="btn btn-outline-primary btn-sm">
      <i class="ti ti-credit-card me-1"></i>Transactions
    </a>
  </div>
</div>


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
        <small class="text-muted">Registration gross: R <?php echo e(number_format($totalGross, 2)); ?></small>
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
              Registration Fees (PayFast gross)
            </td>
            <td class="text-center"><?php echo e($totalEntries); ?></td>
            <td class="text-end">—</td>
            <td><small class="text-muted">PayFast transactions</small></td>
            <td>—</td>
            <td class="text-end fw-semibold text-success">R <?php echo e(number_format($totalGross, 2)); ?></td>
            <td class="no-print"></td>
          </tr>

          
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(abs($totalPayfastFees) > 0): ?>
            <?php $payfastPerEntry = $totalEntries > 0 ? abs($totalPayfastFees) / $totalEntries : 0; ?>
            <tr style="background:#fff5f5; font-style:italic; color:#dc3545">
              <td class="ps-4">
                <i class="ti ti-minus me-1"></i>PayFast fees deducted
                <small class="text-muted ms-1">(<?php echo e($totalEntries); ?> × ~R<?php echo e(number_format($payfastPerEntry, 2)); ?>)</small>
              </td>
              <td class="text-center"><?php echo e($totalEntries); ?></td>
              <td class="text-end">~R <?php echo e(number_format($payfastPerEntry, 2)); ?></td>
              <td><small class="text-muted">PayFast</small></td>
              <td>—</td>
              <td class="text-end fw-semibold">−R <?php echo e(number_format(abs($totalPayfastFees), 2)); ?></td>
              <td class="no-print"></td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($totalCapeTennisFees > 0): ?>
            <tr style="background:#fff5f5; font-style:italic; color:#dc3545">
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

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(abs($registrationRefundAdjustment ?? 0) > 0): ?>
            <tr>
              <td colspan="5">Completed registration refunds (net adjustment)</td>
              <td class="text-end">R <?php echo e(number_format($registrationRefundAdjustment, 2)); ?></td>
              <td class="no-print"></td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(abs($totalPayfastFees) > 0 || $totalCapeTennisFees > 0 || abs($registrationRefundAdjustment ?? 0) > 0): ?>
            <tr class="table-light fw-semibold">
              <td colspan="5" class="text-end text-muted" style="font-size:0.85rem">Net Registration Income</td>
              <td class="text-end text-success">R <?php echo e(number_format($netRegistrationIncome, 2)); ?></td>
              <td class="no-print"></td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($clothingReceipts['count'] ?? 0) > 0): ?>
            <tr class="table-light">
              <td colspan="7" class="fw-semibold">Clothing income by region (paid orders)</td>
            </tr>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $clothingReceipts['groups']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $region => $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <tr>
                <td><?php echo e($region); ?></td>
                <td class="text-center"><?php echo e($group['rows']->count()); ?> orders</td>
                <td class="text-end">—</td>
                <td><small class="text-muted">Paid clothing orders</small></td>
                <td>—</td>
                <td class="text-end text-success">R <?php echo e(number_format($group['totals']['gross'], 2)); ?></td>
                <td class="no-print"></td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            <tr class="text-danger">
              <td colspan="5">Clothing PayFast fees deducted</td>
              <td class="text-end">−R <?php echo e(number_format(abs($clothingReceipts['totals']['fees']), 2)); ?></td>
              <td class="no-print"></td>
            </tr>
            <tr class="table-light fw-semibold">
              <td colspan="5" class="text-end">Net Clothing Income</td>
              <td class="text-end text-success">R <?php echo e(number_format($clothingReceipts['totals']['net'], 2)); ?></td>
              <td class="no-print"></td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

          
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!$event->isTeam() && $incomeByCategory->isNotEmpty()): ?>
            <tr class="no-print">
              <td colspan="7" class="py-1 px-3">
                <button class="btn btn-link btn-sm p-0 text-decoration-none text-muted"
                        data-bs-toggle="collapse" data-bs-target="#incomeByCat">
                  <i class="ti ti-chevron-down me-1"></i>Show income by <?php echo e($event->isTeam() ? 'team category' : 'category'); ?>

                </button>
              </td>
            </tr>
            <tr class="no-print">
              <td colspan="7" class="p-0">
                <div class="collapse" id="incomeByCat">
                  <table class="table table-sm mb-0 border-top">
                    <thead class="table-secondary">
                      <tr>
                        <th class="ps-5"><?php echo e($event->isTeam() ? 'Team Category' : 'Category'); ?></th>
                        <th class="text-center">Entries</th>
                        <th class="text-end">Est. Income</th>
                        <th class="text-end">% of Gross</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $incomeByCategory; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $catName => $catData): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                          <td class="ps-5"><?php echo e($catName); ?></td>
                          <td class="text-center"><?php echo e($catData['entries']); ?></td>
                          <td class="text-end">R <?php echo e(number_format($catData['amount'], 2)); ?></td>
                          <td class="text-end">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($totalGross > 0): ?>
                              <span class="badge bg-label-secondary" style="font-size:0.75rem">
                                <?php echo e(round($catData['amount'] / $totalGross * 100)); ?>%
                              </span>
                            <?php else: ?> —
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                    <tfoot class="table-secondary">
                      <tr>
                        <td class="ps-5 fw-semibold">Total</td>
                        <td class="text-center fw-semibold"><?php echo e($totalEntries); ?></td>
                        <td class="text-end fw-semibold">R <?php echo e(number_format($totalGross, 2)); ?></td>
                        <td></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
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
                      onsubmit="return confirm('Delete this income item?')">
                  <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                  <button class="btn btn-icon btn-sm btn-outline-danger"><i class="ti ti-trash"></i></button>
                </form>
              </td>
            </tr>

            
            <div class="modal fade" id="editIncomeModal<?php echo e($item->id); ?>" tabindex="-1">
              <div class="modal-dialog">
                <div class="modal-content">
                  <form action="<?php echo e(route('admin.events.finances.income.update', $item)); ?>" method="POST">
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
  <h5 class="mb-0"><i class="ti ti-list me-2"></i>Expenses per Event Director</h5>
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
                  $typeActual   = $typeExpenses->sum(fn($e) => $e->calculatedAmount());
                  $typeBudget   = $typeExpenses->whereNotNull('budget_amount')->sum('budget_amount');
                  $typeVariance = $typeBudget > 0 ? $typeBudget - $typeActual : null;
                  $typePct      = $totalExpenses > 0 ? round($typeActual / $totalExpenses * 100) : 0;
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
                    <span class="badge bg-label-secondary" style="font-size:0.75rem"><?php echo e($typePct); ?>%</span>
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
                        <form action="<?php echo e(route('admin.events.finances.expense.approve', $expense)); ?>" method="POST" class="d-inline">
                          <?php echo csrf_field(); ?>
                          <button type="submit" class="btn btn-icon btn-sm btn-outline-success" title="Approve">
                            <i class="ti ti-check"></i>
                          </button>
                        </form>
                      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($expense->approved_at && !$expense->reimbursed_at): ?>
                        <form action="<?php echo e(route('admin.events.finances.expense.reimburse', $expense)); ?>" method="POST" class="d-inline">
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
                            onsubmit="return confirm('Delete this expense?')">
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
                        onsubmit="return confirm('Delete?')">
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
            <th>Event Director</th>
            <th>Role</th>
            <th class="text-end">Paid Out</th>
            <th class="text-end">Reimbursed</th>
            <th class="text-end">Outstanding</th>
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
            <td></td>
          </tr>
          <tr class="table-secondary">
            <td colspan="2"><small class="text-muted">Gross Registration Income</small></td>
            <td colspan="3" class="text-end fw-semibold text-success">R <?php echo e(number_format($totalGross, 2)); ?></td>
            <td></td>
          </tr>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($clothingReceipts['count'] ?? 0) > 0): ?>
            <tr class="table-secondary">
              <td colspan="2"><small class="text-muted">Gross Clothing Income</small></td>
              <td colspan="3" class="text-end fw-semibold text-success">R <?php echo e(number_format($clothingReceipts['totals']['gross'], 2)); ?></td>
              <td></td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(abs($registrationRefundAdjustment ?? 0) > 0): ?>
            <tr class="table-secondary">
              <td colspan="2"><small class="text-muted">Completed registration refunds (net adjustment)</small></td>
              <td colspan="3" class="text-end">R <?php echo e(number_format($registrationRefundAdjustment, 2)); ?></td>
              <td></td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($totalSystemFees > 0): ?>
            <tr class="table-secondary">
              <td colspan="2"><small class="text-muted">System Fees (PayFast + Cape Tennis – deducted from gross)</small></td>
              <td colspan="3" class="text-end fw-semibold text-danger">−R <?php echo e(number_format($totalSystemFees, 2)); ?></td>
              <td></td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($totalIncomeItems > 0): ?>
            <tr class="table-secondary">
              <td colspan="2"><small class="text-muted">Other Income Items</small></td>
              <td colspan="3" class="text-end fw-semibold text-success">R <?php echo e(number_format($totalIncomeItems, 2)); ?></td>
              <td></td>
            </tr>
          <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          <tr class="table-secondary">
            <td colspan="2"><small class="text-muted">Total Net Income</small></td>
            <td colspan="3" class="text-end fw-semibold text-success">R <?php echo e(number_format($grandTotalIncome, 2)); ?></td>
            <td></td>
          </tr>
          <tr class="table-secondary">
            <td colspan="2"><small class="text-muted">Operational Expenses</small></td>
            <td colspan="3" class="text-end fw-semibold text-danger">R <?php echo e(number_format($totalExpenses, 2)); ?></td>
            <td></td>
          </tr>
          <tr class="<?php echo e($netProfit >= 0 ? 'table-success' : 'table-danger'); ?>">
            <td colspan="2" class="fw-bold">Net <?php echo e($netProfit >= 0 ? 'Profit' : 'Loss'); ?></td>
            <td colspan="3" class="text-end fw-bold">R <?php echo e(number_format(abs($netProfit), 2)); ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>




<div class="modal fade" id="addExpenseModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="<?php echo e(route('admin.events.finances.expense.store', $event)); ?>" method="POST" enctype="multipart/form-data">
        <?php echo csrf_field(); ?>
        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Expense</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <?php echo $__env->make('backend.event._expense_fields', ['expense' => null, 'convenors' => $convenors, 'expenseTypes' => $expenseTypes], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
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
        <form action="<?php echo e(route('admin.events.finances.expense.update', $expense)); ?>" method="POST" enctype="multipart/form-data">
          <?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
          <div class="modal-header">
            <h5 class="modal-title">Edit Expense</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <?php echo $__env->make('backend.event._expense_fields', ['expense' => $expense, 'convenors' => $convenors, 'expenseTypes' => $expenseTypes], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
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
      <form action="<?php echo e(route('admin.events.finances.income.store', $event)); ?>" method="POST">
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
<?php /**PATH C:\wamp64\www\ct\resources\views\backend\event\partials\finances.blade.php ENDPATH**/ ?>