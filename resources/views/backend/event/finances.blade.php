@extends('layouts/layoutMaster')

@section('title', $event->name . ' – Finances')

@section('page-style')
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

  /* Print styles */
  @media print {
    .no-print, .btn, .modal, .card-header .btn, nav, .navbar,
    .layout-menu, .layout-overlay, .layout-navbar { display: none !important; }
    .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; page-break-inside: avoid; }
    .print-header { display: block !important; }
    .print-only-row { display: table-row !important; }
    #incomeByCat, #expenseSummaryAccordion { display: block !important; }
    body { font-size: 11px; }
    .table td, .table th { padding: 4px 6px !important; }
    .container-xl { max-width: 100% !important; padding: 0 !important; }
    h5 { font-size: 13px !important; }
  }
  .print-header { display: none; }
  .print-only-row { display: none; }
</style>
@endsection

@section('content')
<div class="container-xl">

  {{-- ── PRINT-ONLY HEADER ────────────────────────────────────────────── --}}
  <div class="print-header mb-4 pb-3 border-bottom">
    <h3 class="mb-1">{{ $event->name }} – Budget / Expense Statement</h3>
    <div class="d-flex gap-4 text-muted" style="font-size:0.85rem">
      @if($event->start_date)
        <span><strong>Date:</strong> {{ $event->start_date->format('d M Y') }}{{ $event->end_date && $event->end_date->ne($event->start_date) ? ' – '.$event->end_date->format('d M Y') : '' }}</span>
      @endif
      @if($event->organizer)
        <span><strong>Organizer:</strong> {{ $event->organizer }}</span>
      @endif
      <span><strong>Generated:</strong> {{ now()->format('d M Y H:i') }}</span>
    </div>
  </div>

  {{-- ── PAGE HEADER ──────────────────────────────────────────────────── --}}
  <div class="card mb-3 no-print">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h4 class="mb-0">
        <i class="ti ti-report-money me-2 text-warning"></i>
        Finances — {{ $event->name }}
      </h4>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#manageConvenorsModal">
          <i class="ti ti-users me-1"></i>Convenors
        </button>
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#manageTypesModal">
          <i class="ti ti-tags me-1"></i>Expense Types
        </button>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
          <i class="ti ti-printer me-1"></i>Print / PDF
        </button>
        <a href="{{ route('admin.events.transactions', $event) }}" class="btn btn-outline-primary btn-sm">
          <i class="ti ti-credit-card me-1"></i>Transactions
        </a>
        <a href="{{ route('admin.events.overview', $event) }}" class="btn btn-outline-secondary btn-sm">
          <i class="ti ti-arrow-left me-1"></i>Back
        </a>
      </div>
    </div>
  </div>

  {{-- ── ALERTS ──────────────────────────────────────────────────────── --}}
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
      <i class="ti ti-circle-check me-1"></i>{{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
      <i class="ti ti-alert-circle me-1"></i>{{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if($budgetCapWarning)
    <div class="alert alert-warning d-flex align-items-center no-print" role="alert">
      <i class="ti ti-alert-triangle me-2 fs-4"></i>
      <div>
        <strong>Budget Warning!</strong>
        Operational spending has reached 90% of the budget cap (R {{ number_format($event->budget_cap, 2) }}).
      </div>
    </div>
  @endif

  @if($pendingApproval > 0)
    <div class="alert alert-info d-flex align-items-center no-print" role="alert">
      <i class="ti ti-clock me-2"></i>
      {{ $pendingApproval }} expense(s) awaiting approval.
    </div>
  @endif

  {{-- ── SUMMARY CARDS ───────────────────────────────────────────────── --}}
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card finance-card border-start border-primary border-3 h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1"><i class="ti ti-cash me-1 text-primary"></i>Net Income</small>
          <h5 class="mb-0">R {{ number_format($grandTotalIncome, 2) }}</h5>
          <small class="text-muted">Gross: R {{ number_format($totalGross, 2) }}</small>
          @if($event->target_income)
            <div class="progress mt-2" style="height:4px" title="R{{ number_format($grandTotalIncome,2) }} of R{{ number_format($event->target_income,2) }}">
              <div class="progress-bar bg-primary" style="width: {{ min(100, round($grandTotalIncome / $event->target_income * 100)) }}%"></div>
            </div>
            <small class="text-muted">{{ round($grandTotalIncome / $event->target_income * 100) }}% of target</small>
          @endif
        </div>
      </div>
    </div>

    <div class="col-6 col-md-3">
      <div class="card finance-card border-start border-danger border-3 h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1"><i class="ti ti-shopping-cart me-1 text-danger"></i>Operational Expenses</small>
          <h5 class="mb-0 text-danger">R {{ number_format($totalExpenses, 2) }}</h5>
          @if($totalSystemFees > 0)
            <small class="text-muted">+ R {{ number_format($totalSystemFees, 2) }} system fees</small>
          @endif
          @if($event->budget_cap)
            <div class="progress mt-2" style="height:4px" title="R{{ number_format($totalExpenses,2) }} of R{{ number_format($event->budget_cap,2) }}">
              <div class="progress-bar {{ ($totalExpenses/$event->budget_cap) >= 0.9 ? 'bg-danger' : 'bg-warning' }}"
                   style="width: {{ min(100, round($totalExpenses / $event->budget_cap * 100)) }}%"></div>
            </div>
            <small class="text-muted">{{ round($totalExpenses / $event->budget_cap * 100) }}% of budget (R {{ number_format($event->budget_cap, 2) }})</small>
          @endif
        </div>
      </div>
    </div>

    <div class="col-6 col-md-3">
      <div class="card finance-card border-start {{ $netProfit >= 0 ? 'border-success' : 'border-danger' }} border-3 h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1">
            <i class="ti ti-trending-{{ $netProfit >= 0 ? 'up text-success' : 'down text-danger' }} me-1"></i>
            Net {{ $netProfit >= 0 ? 'Profit' : 'Loss' }}
          </small>
          <h5 class="mb-0 {{ $netProfit >= 0 ? 'text-success' : 'text-danger' }}">
            R {{ number_format(abs($netProfit), 2) }}
          </h5>
        </div>
      </div>
    </div>

    <div class="col-6 col-md-3">
      <div class="card finance-card border-start border-secondary border-3 h-100">
        <div class="card-body">
          <small class="text-muted d-block mb-1"><i class="ti ti-users me-1"></i>Entries</small>
          <h5 class="mb-0">{{ $totalEntries }}</h5>
          @if($event->target_entries)
            <div class="progress mt-2" style="height:4px">
              <div class="progress-bar bg-secondary" style="width: {{ min(100, round($totalEntries / $event->target_entries * 100)) }}%"></div>
            </div>
            <small class="text-muted">{{ $totalEntries }} of {{ $event->target_entries }} target</small>
          @endif
        </div>
      </div>
    </div>
  </div>

  {{-- ══════════════════════════════════════════════════════════════════════
       SECTION 1 – INCOME
  ══════════════════════════════════════════════════════════════════════ --}}
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
            {{-- ── Registration gross row ── --}}
            <tr>
              <td>
                <span class="badge bg-label-primary me-1">System</span>
                Registration Fees (PayFast gross)
              </td>
              <td class="text-center">{{ $totalEntries }}</td>
              <td class="text-end">—</td>
              <td><small class="text-muted">PayFast transactions</small></td>
              <td>—</td>
              <td class="text-end fw-semibold text-success">R {{ number_format($totalGross, 2) }}</td>
              <td class="no-print"></td>
            </tr>

            {{-- ── PayFast fee deduction row ── --}}
            @if(abs($totalPayfastFees) > 0)
              @php $payfastPerEntry = $totalEntries > 0 ? abs($totalPayfastFees) / $totalEntries : 0; @endphp
              <tr class="deduction-row">
                <td class="ps-4">
                  <i class="ti ti-minus me-1"></i>PayFast fees deducted
                  <small class="text-muted ms-1">({{ $totalEntries }} × ~R{{ number_format($payfastPerEntry, 2) }})</small>
                </td>
                <td class="text-center">{{ $totalEntries }}</td>
                <td class="text-end">~R {{ number_format($payfastPerEntry, 2) }}</td>
                <td><small class="text-muted">PayFast</small></td>
                <td>—</td>
                <td class="text-end fw-semibold">−R {{ number_format(abs($totalPayfastFees), 2) }}</td>
                <td class="no-print"></td>
              </tr>
            @endif

            {{-- ── Cape Tennis fee deduction row ── --}}
            @if($totalCapeTennisFees > 0)
              <tr class="deduction-row">
                <td class="ps-4">
                  <i class="ti ti-minus me-1"></i>Cape Tennis fee deducted
                  <small class="text-muted ms-1">({{ $totalEntries }} × R{{ number_format($feePerEntry, 2) }})</small>
                </td>
                <td class="text-center">{{ $totalEntries }}</td>
                <td class="text-end">R {{ number_format($feePerEntry, 2) }}</td>
                <td><small class="text-muted">Cape Tennis</small></td>
                <td>—</td>
                <td class="text-end fw-semibold">−R {{ number_format($totalCapeTennisFees, 2) }}</td>
                <td class="no-print"></td>
              </tr>
            @endif

            {{-- ── Net registration income subtotal ── --}}
            @if(abs($totalPayfastFees) > 0 || $totalCapeTennisFees > 0)
              <tr class="table-light fw-semibold">
                <td colspan="5" class="text-end text-muted" style="font-size:0.85rem">Net Registration Income</td>
                <td class="text-end text-success">R {{ number_format($netRegistrationIncome, 2) }}</td>
                <td class="no-print"></td>
              </tr>
            @endif

            {{-- ── Income by category / team breakdown (collapsible) ── --}}
            @if($incomeByCategory->isNotEmpty())
              <tr class="no-print">
                <td colspan="7" class="py-1 px-3">
                  <button class="btn btn-link btn-sm p-0 text-decoration-none text-muted"
                          data-bs-toggle="collapse" data-bs-target="#incomeByCat">
                    <i class="ti ti-chevron-down me-1"></i>Show income by {{ $event->isTeam() ? 'team category' : 'category' }}
                  </button>
                </td>
              </tr>
              <tr class="no-print">
                <td colspan="7" class="p-0">
                  <div class="collapse" id="incomeByCat">
                    <table class="table table-sm mb-0 border-top">
                      <thead class="table-secondary">
                        <tr>
                          <th class="ps-5">{{ $event->isTeam() ? 'Team Category' : 'Category' }}</th>
                          <th class="text-center">Entries</th>
                          <th class="text-end">Est. Income</th>
                          <th class="text-end">% of Gross</th>
                        </tr>
                      </thead>
                      <tbody>
                        @foreach($incomeByCategory as $catName => $catData)
                          <tr>
                            <td class="ps-5">{{ $catName }}</td>
                            <td class="text-center">{{ $catData['entries'] }}</td>
                            <td class="text-end">R {{ number_format($catData['amount'], 2) }}</td>
                            <td class="text-end">
                              @if($totalGross > 0)
                                <span class="badge bg-label-secondary cat-summary-badge">
                                  {{ round($catData['amount'] / $totalGross * 100) }}%
                                </span>
                              @else
                                —
                              @endif
                            </td>
                          </tr>
                        @endforeach
                      </tbody>
                      <tfoot class="table-secondary">
                        <tr>
                          <td class="ps-5 fw-semibold">Total</td>
                          <td class="text-center fw-semibold">{{ $totalEntries }}</td>
                          <td class="text-end fw-semibold">R {{ number_format($totalGross, 2) }}</td>
                          <td></td>
                        </tr>
                      </tfoot>
                    </table>
                  </div>
                </td>
              </tr>
              {{-- Print version: always visible --}}
              <tr class="print-only-row">
                <td colspan="7" class="p-0">
                  <table class="table table-sm mb-0">
                    <thead class="table-secondary">
                      <tr>
                        <th class="ps-5">{{ $event->isTeam() ? 'Team Category' : 'Category' }}</th>
                        <th class="text-center">Entries</th>
                        <th class="text-end">Est. Income</th>
                        <th class="text-end">%</th>
                      </tr>
                    </thead>
                    <tbody>
                      @foreach($incomeByCategory as $catName => $catData)
                        <tr>
                          <td class="ps-5">{{ $catName }}</td>
                          <td class="text-center">{{ $catData['entries'] }}</td>
                          <td class="text-end">R {{ number_format($catData['amount'], 2) }}</td>
                          <td class="text-end">{{ $totalGross > 0 ? round($catData['amount'] / $totalGross * 100) : 0 }}%</td>
                        </tr>
                      @endforeach
                    </tbody>
                  </table>
                </td>
              </tr>
            @endif

            {{-- ── Manual income items ── --}}
            @foreach($incomeItems as $item)
              <tr>
                <td>{{ $item->label }}</td>
                <td class="text-center">{{ $item->quantity ? number_format($item->quantity, 0) : '—' }}</td>
                <td class="text-end">{{ $item->unit_price ? 'R '.number_format($item->unit_price, 2) : '—' }}</td>
                <td><small class="text-muted">{{ $item->source ?? '—' }}</small></td>
                <td>{{ $item->date?->format('d M Y') ?? '—' }}</td>
                <td class="text-end fw-semibold text-success">R {{ number_format($item->calculatedTotal(), 2) }}</td>
                <td class="text-center no-print">
                  <button class="btn btn-icon btn-sm btn-outline-primary"
                          data-bs-toggle="modal" data-bs-target="#editIncomeModal{{ $item->id }}">
                    <i class="ti ti-edit"></i>
                  </button>
                  <form action="{{ route('admin.events.finances.income.destroy', $item) }}" method="POST" class="d-inline"
                        onsubmit="return confirm('Delete this income item?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-icon btn-sm btn-outline-danger"><i class="ti ti-trash"></i></button>
                  </form>
                </td>
              </tr>

              {{-- Edit income modal --}}
              <div class="modal fade" id="editIncomeModal{{ $item->id }}" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form action="{{ route('admin.events.finances.income.update', $item) }}" method="POST">
                      @csrf @method('PATCH')
                      <div class="modal-header">
                        <h5 class="modal-title">Edit Income Item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        @include('backend.event._income_item_fields', ['item' => $item])
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            @endforeach
          </tbody>
          <tfoot class="table-light">
            <tr>
              <td colspan="5" class="fw-bold">Total Net Income</td>
              <td class="text-end fw-bold text-success">R {{ number_format($grandTotalIncome, 2) }}</td>
              <td class="no-print"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  {{-- ══════════════════════════════════════════════════════════════════════
       SECTION 2 – EXPENSES (per convenor)
  ══════════════════════════════════════════════════════════════════════ --}}
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h5 class="mb-0"><i class="ti ti-list me-2"></i>Expenses per Convenor</h5>
    <button class="btn btn-primary btn-sm no-print" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
      <i class="ti ti-plus me-1"></i>Add Expense
    </button>
  </div>

  {{-- Expense category summary accordion --}}
  @if($expensesByType->isNotEmpty())
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
                @foreach($expensesByType->sortKeys() as $type => $typeExpenses)
                  @php
                    $typeActual  = $typeExpenses->sum(fn($e) => $e->calculatedAmount());
                    $typeBudget  = $typeExpenses->whereNotNull('budget_amount')->sum('budget_amount');
                    $typeVariance = $typeBudget > 0 ? $typeBudget - $typeActual : null;
                    $typePct     = $totalExpenses > 0 ? round($typeActual / $totalExpenses * 100) : 0;
                  @endphp
                  <tr>
                    <td>
                      <span class="badge bg-label-secondary">
                        {{ $expenseTypes[$type] ?? ucfirst($type) }}
                      </span>
                    </td>
                    <td class="text-center">{{ $typeExpenses->count() }}</td>
                    <td class="text-end">{{ $typeBudget > 0 ? 'R '.number_format($typeBudget, 2) : '—' }}</td>
                    <td class="text-end fw-semibold">R {{ number_format($typeActual, 2) }}</td>
                    <td class="text-end">
                      @if($typeVariance !== null)
                        <span class="{{ $typeVariance >= 0 ? 'budget-under' : 'budget-over' }}">
                          {{ $typeVariance >= 0 ? '+' : '' }}R {{ number_format($typeVariance, 2) }}
                        </span>
                      @else
                        —
                      @endif
                    </td>
                    <td class="text-end">
                      <span class="badge bg-label-secondary cat-summary-badge">{{ $typePct }}%</span>
                    </td>
                  </tr>
                @endforeach
              </tbody>
              <tfoot class="table-light">
                <tr>
                  <td class="fw-bold">Total</td>
                  <td class="text-center fw-bold">{{ $expensesByType->flatten()->count() }}</td>
                  <td class="text-end fw-bold">{{ $totalBudget > 0 ? 'R '.number_format($totalBudget, 2) : '—' }}</td>
                  <td class="text-end fw-bold text-danger">R {{ number_format($totalExpenses, 2) }}</td>
                  <td class="text-end fw-bold">
                    @if($totalBudget > 0)
                      @php $totalVar = $totalBudget - $totalExpenses; @endphp
                      <span class="{{ $totalVar >= 0 ? 'budget-under' : 'budget-over' }}">
                        {{ $totalVar >= 0 ? '+' : '' }}R {{ number_format($totalVar, 2) }}
                      </span>
                    @else
                      —
                    @endif
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  @endif

  @if($convenors->count() > 0)
    @foreach($convenors as $convenor)
      @php
        $cExpenses = $expensesByConvenor->get($convenor->id, collect());
        $cTotal    = $cExpenses->sum(fn($e) => $e->calculatedAmount());
        $roleLabel = match($convenor->role) {
          'hoof'  => 'HeadConvenor',
          'hulp'  => 'AssistConvenor',
          default => ucfirst($convenor->role),
        };
      @endphp
      <div class="card mb-3">
        <div class="card-header convenor-header d-flex justify-content-between align-items-center">
          <div>
            <strong>Paid by {{ $convenor->user->name ?? 'Unknown' }}</strong>
            <span class="badge bg-warning text-dark ms-2">{{ $roleLabel }}</span>
          </div>
          <span class="fw-bold">R {{ number_format($cTotal, 2) }}</span>
        </div>
        <div class="card-body p-0">
          @if($cExpenses->count() > 0)
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
                  @foreach($cExpenses->sortBy('expense_type') as $expense)
                    @php
                      $calcAmt  = $expense->calculatedAmount();
                      $variance = $expense->budgetVariance();
                    @endphp
                    <tr class="expense-row">
                      <td>
                        <span class="badge bg-label-secondary">
                          {{ $expenseTypes[$expense->expense_type] ?? ucfirst($expense->expense_type) }}
                        </span>
                      </td>
                      <td>
                        @if($expense->recipient_name)
                          <strong>{{ $expense->recipient_name }}</strong><br>
                        @endif
                        {{ $expense->description ?? '—' }}
                      </td>
                      <td class="text-center">
                        @if($expense->quantity && $expense->unit_price)
                          {{ number_format($expense->quantity, 0) }} × R{{ number_format($expense->unit_price, 2) }}
                        @else
                          —
                        @endif
                      </td>
                      <td class="text-end">
                        {{ $expense->budget_amount ? 'R '.number_format($expense->budget_amount, 2) : '—' }}
                      </td>
                      <td class="text-end fw-semibold">R {{ number_format($calcAmt, 2) }}</td>
                      <td class="text-end">
                        @if($variance !== null)
                          <span class="{{ $variance >= 0 ? 'budget-under' : 'budget-over' }}">
                            {{ $variance >= 0 ? '+' : '' }}R {{ number_format($variance, 2) }}
                          </span>
                        @else
                          —
                        @endif
                      </td>
                      <td>
                        @if($expense->approved_at)
                          <span class="badge bg-success approved-badge" title="Approved by {{ $expense->approvedByUser?->name }}">
                            <i class="ti ti-check"></i> Approved
                          </span>
                        @else
                          <span class="badge bg-label-warning approved-badge">Pending</span>
                        @endif
                        @if($expense->reimbursed_at)
                          <span class="badge bg-label-success approved-badge mt-1 d-block" title="Reimbursed to {{ $convenor->user->name }}">
                            <i class="ti ti-coin"></i> Reimbursed
                          </span>
                        @endif
                        @if($expense->receipt_path)
                          <a href="{{ asset('storage/'.$expense->receipt_path) }}" target="_blank"
                             class="badge bg-label-primary approved-badge mt-1 d-block">
                            <i class="ti ti-paperclip"></i> Receipt
                          </a>
                        @endif
                      </td>
                      <td class="text-center no-print">
                        @if(!$expense->approved_at)
                          <form action="{{ route('admin.events.finances.expense.approve', $expense) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-icon btn-sm btn-outline-success" title="Approve">
                              <i class="ti ti-check"></i>
                            </button>
                          </form>
                        @endif
                        @if($expense->approved_at && !$expense->reimbursed_at)
                          <form action="{{ route('admin.events.finances.expense.reimburse', $expense) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-icon btn-sm btn-outline-info" title="Mark as reimbursed">
                              <i class="ti ti-coin"></i>
                            </button>
                          </form>
                        @endif
                        <button type="button"
                                class="btn btn-icon btn-sm btn-outline-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#editExpenseModal{{ $expense->id }}">
                          <i class="ti ti-edit"></i>
                        </button>
                        <form action="{{ route('admin.events.finances.expense.destroy', $expense) }}"
                              method="POST" class="d-inline"
                              onsubmit="return confirm('Delete this expense?')">
                          @csrf @method('DELETE')
                          <button class="btn btn-icon btn-sm btn-outline-danger"><i class="ti ti-trash"></i></button>
                        </form>
                      </td>
                    </tr>
                  @endforeach
                </tbody>
                <tfoot class="table-light">
                  <tr>
                    <td colspan="4" class="fw-bold">Subtotal – {{ $convenor->user->name ?? 'Unknown' }}</td>
                    <td class="text-end fw-bold">R {{ number_format($cTotal, 2) }}</td>
                    <td colspan="3"></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          @else
            <div class="text-center py-3 text-muted">
              No expenses.
            </div>
          @endif
        </div>
      </div>
    @endforeach
  @endif

  {{-- Unassigned operational expenses (no paid_by_convenor_id) --}}
  @php
    $unassigned      = $expensesByConvenor->get(null, collect());
    $unassignedTotal = $unassigned->sum(fn($e) => $e->calculatedAmount());
  @endphp
  @if($unassigned->count() > 0)
    <div class="card mb-3">
      <div class="card-header d-flex justify-content-between align-items-center bg-light">
        <strong><i class="ti ti-question-mark me-1 text-muted"></i>No Convenor Assigned</strong>
        <span class="fw-bold">R {{ number_format($unassignedTotal, 2) }}</span>
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
              @foreach($unassigned as $expense)
                @php
                  $calcAmt  = $expense->calculatedAmount();
                  $variance = $expense->budgetVariance();
                @endphp
                <tr>
                  <td><span class="badge bg-label-secondary">{{ $expenseTypes[$expense->expense_type] ?? ucfirst($expense->expense_type) }}</span></td>
                  <td>{{ $expense->description ?? '—' }}</td>
                  <td class="text-center">
                    @if($expense->quantity && $expense->unit_price)
                      {{ number_format($expense->quantity,0) }} × R{{ number_format($expense->unit_price,2) }}
                    @else
                      —
                    @endif
                  </td>
                  <td class="text-end">{{ $expense->budget_amount ? 'R '.number_format($expense->budget_amount, 2) : '—' }}</td>
                  <td class="text-end fw-semibold">R {{ number_format($calcAmt, 2) }}</td>
                  <td class="text-end">
                    @if($variance !== null)
                      <span class="{{ $variance >= 0 ? 'budget-under' : 'budget-over' }}">
                        {{ $variance >= 0 ? '+' : '' }}R {{ number_format($variance, 2) }}
                      </span>
                    @else
                      —
                    @endif
                  </td>
                  <td class="text-center no-print">
                    <button class="btn btn-icon btn-sm btn-outline-primary"
                            data-bs-toggle="modal" data-bs-target="#editExpenseModal{{ $expense->id }}">
                      <i class="ti ti-edit"></i>
                    </button>
                    <form action="{{ route('admin.events.finances.expense.destroy', $expense) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('Delete?')">
                      @csrf @method('DELETE')
                      <button class="btn btn-icon btn-sm btn-outline-danger"><i class="ti ti-trash"></i></button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  {{-- ══════════════════════════════════════════════════════════════════════
       SECTION 3 – RECONCILIATION / RECON
  ══════════════════════════════════════════════════════════════════════ --}}
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
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @foreach($recon as $row)
              @php
                $outstanding = $row['owed_back'] - $row['reimbursed'];
              @endphp
              <tr>
                <td class="fw-semibold">{{ $row['convenor']->user->name ?? 'Unknown' }}</td>
                <td>
                  <span class="badge {{ $row['convenor']->isHoof() ? 'bg-warning text-dark' : 'bg-label-secondary' }}">
                    {{ $row['convenor']->isHoof() ? 'HeadConvenor' : ($row['convenor']->isHulp() ? 'AssistConvenor' : ucfirst($row['convenor']->role)) }}
                  </span>
                </td>
                <td class="text-end">R {{ number_format($row['total_paid'], 2) }}</td>
                <td class="text-end text-success">R {{ number_format($row['reimbursed'], 2) }}</td>
                <td class="text-end {{ $outstanding > 0 ? 'text-danger fw-bold' : 'text-success' }}">
                  R {{ number_format($outstanding, 2) }}
                </td>
                <td>
                  @if($outstanding <= 0)
                    <span class="badge bg-success"><i class="ti ti-check me-1"></i>Settled</span>
                  @else
                    <span class="badge bg-danger"><i class="ti ti-alert-circle me-1"></i>Outstanding</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
          <tfoot class="table-light">
            <tr>
              <td colspan="2" class="fw-bold">Recon Total</td>
              <td class="text-end fw-bold">R {{ number_format($recon->sum('total_paid'), 2) }}</td>
              <td class="text-end fw-bold text-success">R {{ number_format($recon->sum('reimbursed'), 2) }}</td>
              <td class="text-end fw-bold {{ $recon->sum(fn($r) => $r['owed_back'] - $r['reimbursed']) > 0 ? 'text-danger' : 'text-success' }}">
                R {{ number_format($recon->sum(fn($r) => $r['owed_back'] - $r['reimbursed']), 2) }}
              </td>
              <td></td>
            </tr>
            <tr class="table-secondary">
              <td colspan="2"><small class="text-muted">Gross Registration Income</small></td>
              <td colspan="3" class="text-end fw-semibold text-success">R {{ number_format($totalGross, 2) }}</td>
              <td></td>
            </tr>
            @if($totalSystemFees > 0)
              <tr class="table-secondary">
                <td colspan="2"><small class="text-muted">System Fees (PayFast + Cape Tennis – deducted from gross)</small></td>
                <td colspan="3" class="text-end fw-semibold text-danger">−R {{ number_format($totalSystemFees, 2) }}</td>
                <td></td>
              </tr>
            @endif
            @if($totalIncomeItems > 0)
              <tr class="table-secondary">
                <td colspan="2"><small class="text-muted">Other Income Items</small></td>
                <td colspan="3" class="text-end fw-semibold text-success">R {{ number_format($totalIncomeItems, 2) }}</td>
                <td></td>
              </tr>
            @endif
            <tr class="table-secondary">
              <td colspan="2"><small class="text-muted">Total Net Income</small></td>
              <td colspan="3" class="text-end fw-semibold text-success">R {{ number_format($grandTotalIncome, 2) }}</td>
              <td></td>
            </tr>
            <tr class="table-secondary">
              <td colspan="2"><small class="text-muted">Operational Expenses</small></td>
              <td colspan="3" class="text-end fw-semibold text-danger">R {{ number_format($totalExpenses, 2) }}</td>
              <td></td>
            </tr>
            <tr class="{{ $netProfit >= 0 ? 'table-success' : 'table-danger' }}">
              <td colspan="2" class="fw-bold">Net {{ $netProfit >= 0 ? 'Profit' : 'Loss' }}</td>
              <td colspan="3" class="text-end fw-bold">R {{ number_format(abs($netProfit), 2) }}</td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

</div>{{-- /container --}}

{{-- ════════════════════════════════════════════════════════════════════════
     MODALS
════════════════════════════════════════════════════════════════════════ --}}

{{-- ADD EXPENSE --}}
<div class="modal fade" id="addExpenseModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form action="{{ route('admin.events.finances.expense.store', $event) }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Expense</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          @include('backend.event._expense_fields', ['expense' => null, 'convenors' => $convenors, 'expenseTypes' => $expenseTypes])
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="ti ti-check me-1"></i>Add</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- EDIT EXPENSE MODALS (one per operational expense) --}}
@foreach($expenses->reject(fn($e) => in_array($e->expense_type, ['payfast', 'cape_tennis_fee'])) as $expense)
  <div class="modal fade" id="editExpenseModal{{ $expense->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form action="{{ route('admin.events.finances.expense.update', $expense) }}" method="POST" enctype="multipart/form-data">
          @csrf @method('PATCH')
          <div class="modal-header">
            <h5 class="modal-title">Edit Expense</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            @include('backend.event._expense_fields', ['expense' => $expense, 'convenors' => $convenors, 'expenseTypes' => $expenseTypes])
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endforeach

{{-- ADD INCOME ITEM MODAL --}}
<div class="modal fade" id="addIncomeModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="{{ route('admin.events.finances.income.store', $event) }}" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title"><i class="ti ti-plus me-2"></i>Add Income</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          @include('backend.event._income_item_fields', ['item' => null])
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-success"><i class="ti ti-check me-1"></i>Add</button>
        </div>
      </form>
    </div>
  </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════════
     MANAGE CONVENORS MODAL
════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="manageConvenorsModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-users me-2"></i>Manage Convenors – {{ $event->name }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">

        {{-- Current convenors list --}}
        @if($convenors->count())
          <table class="table table-sm mb-0">
            <thead class="table-light">
              <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Active From</th>
                <th>Expires</th>
                <th style="width:90px"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($convenors as $c)
                <tr>
                  <td class="align-middle">{{ $c->user->name ?? '—' }}</td>
                  <td class="align-middle">
                    <span class="badge {{ $c->isHoof() ? 'bg-warning text-dark' : 'bg-label-secondary' }}">
                      {{ $c->isHoof() ? 'Head' : ($c->isHulp() ? 'Assist' : ucfirst($c->role)) }}
                    </span>
                  </td>
                  <td class="align-middle">
                    <small>{{ $c->starts_at ? $c->starts_at->format('d M Y') : '—' }}</small>
                  </td>
                  <td class="align-middle">
                    <small>{{ $c->expires_at ? $c->expires_at->format('d M Y') : '—' }}</small>
                    @if($c->expires_at && !$c->isActive())
                      <span class="badge bg-danger ms-1" style="font-size:0.65rem">Expired</span>
                    @endif
                  </td>
                  <td class="text-end align-middle">
                    <button type="button" class="btn btn-icon btn-sm btn-outline-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#editConvenorModal{{ $c->id }}"
                            title="Edit">
                      <i class="ti ti-edit"></i>
                    </button>
                    <form action="{{ route('admin.events.finances.convenor.destroy', $c) }}"
                          method="POST" class="d-inline"
                          onsubmit="return confirm('Remove {{ addslashes($c->user->name ?? 'this convenor') }} from this event?')">
                      @csrf @method('DELETE')
                      <button class="btn btn-icon btn-sm btn-outline-danger" title="Remove">
                        <i class="ti ti-trash"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        @else
          <p class="text-muted text-center py-3">No convenors assigned yet.</p>
        @endif

        <hr class="m-0">

        {{-- Add new convenor --}}
        <div class="p-3">
          <h6 class="mb-3"><i class="ti ti-plus me-1"></i>Add Convenor</h6>
          <form action="{{ route('admin.events.finances.convenor.store', $event) }}" method="POST">
            @csrf
            <div class="row g-2">
              <div class="col-md-5">
                <label class="form-label">User <span class="text-danger">*</span></label>
                <select name="user_id" class="form-select convenor-user-select" required>
                  <option value="">Search user...</option>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                  <option value="hulp">Assist Convenor</option>
                  <option value="hoof">Head Convenor</option>
                  <option value="admin">Admin</option>
                </select>
              </div>
              <div class="col-md-2">
                <label class="form-label">Active From</label>
                <input type="date" name="starts_at" class="form-control">
              </div>
              <div class="col-md-2">
                <label class="form-label">Expires</label>
                <input type="date" name="expires_at" class="form-control">
              </div>
              <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary btn-sm">
                  <i class="ti ti-user-plus me-1"></i>Add Convenor
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

{{-- Edit Convenor modals (one per convenor) --}}
@foreach($convenors as $c)
  <div class="modal fade" id="editConvenorModal{{ $c->id }}" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form action="{{ route('admin.events.finances.convenor.update', $c) }}" method="POST">
          @csrf @method('PATCH')
          <div class="modal-header">
            <h5 class="modal-title">Edit Convenor – {{ $c->user->name ?? '?' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Role</label>
                <select name="role" class="form-select">
                  <option value="hulp"  {{ $c->role === 'hulp'  ? 'selected' : '' }}>Assist Convenor</option>
                  <option value="hoof"  {{ $c->role === 'hoof'  ? 'selected' : '' }}>Head Convenor</option>
                  <option value="admin" {{ $c->role === 'admin' ? 'selected' : '' }}>Admin</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Active From</label>
                <input type="date" name="starts_at" class="form-control"
                       value="{{ $c->starts_at?->format('Y-m-d') }}">
              </div>
              <div class="col-md-6">
                <label class="form-label">Expires</label>
                <input type="date" name="expires_at" class="form-control"
                       value="{{ $c->expires_at?->format('Y-m-d') }}">
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
@endforeach

{{-- ════════════════════════════════════════════════════════════════════════
     MANAGE EXPENSE TYPES MODAL
════════════════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="manageTypesModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="ti ti-tags me-2"></i>Manage Expense Types</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">

        {{-- Existing types --}}
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
            @foreach($allExpenseTypes as $et)
              <tr id="et-row-{{ $et->id }}">
                <td><code>{{ $et->key }}</code></td>
                <td>
                  <span class="et-label-display">{{ $et->label }}</span>
                  <input type="text" class="form-control form-control-sm et-label-input d-none"
                         value="{{ $et->label }}" style="max-width:160px">
                </td>
                <td class="text-center">
                  <span class="et-sort-display">{{ $et->sort_order }}</span>
                  <input type="number" class="form-control form-control-sm et-sort-input d-none text-center"
                         value="{{ $et->sort_order }}" style="max-width:70px" min="0">
                </td>
                <td class="text-center">
                  @if($et->is_system)
                    <span class="badge bg-label-info">System</span>
                  @else
                    —
                  @endif
                </td>
                <td class="text-end">
                  @if(!$et->is_system)
                    <button type="button" class="btn btn-icon btn-sm btn-outline-primary et-edit-btn"
                            data-id="{{ $et->id }}" title="Edit">
                      <i class="ti ti-edit"></i>
                    </button>
                    <button type="button" class="btn btn-icon btn-sm btn-outline-success et-save-btn d-none"
                            data-id="{{ $et->id }}"
                            data-url="{{ route('admin.expense-types.update', $et) }}" title="Save">
                      <i class="ti ti-check"></i>
                    </button>
                    <button type="button" class="btn btn-icon btn-sm btn-outline-danger et-delete-btn"
                            data-id="{{ $et->id }}"
                            data-url="{{ route('admin.expense-types.destroy', $et) }}"
                            data-label="{{ $et->label }}" title="Delete">
                      <i class="ti ti-trash"></i>
                    </button>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>

        <hr class="m-0">

        {{-- Add new type --}}
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
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"
                onclick="location.reload()">Close &amp; Refresh</button>
      </div>
    </div>
  </div>
</div>

@endsection

@section('page-script')
<script>
  // Auto-calculate amount from quantity × unit_price
  document.querySelectorAll('input[name="quantity"], input[name="unit_price"]').forEach(function(el) {
    el.addEventListener('input', function() {
      const form = el.closest('form');
      const qty  = parseFloat(form.querySelector('input[name="quantity"]')?.value) || 0;
      const up   = parseFloat(form.querySelector('input[name="unit_price"]')?.value) || 0;
      const amtInput = form.querySelector('input[name="amount"]');
      if (amtInput && qty > 0 && up > 0) {
        amtInput.value = (qty * up).toFixed(2);
      }
    });
  });

  // ── Convenor user search (Select2 AJAX) ─────────────────────────────────
  document.addEventListener('DOMContentLoaded', function () {
    if (typeof $ !== 'undefined' && $.fn.select2) {
      $('.convenor-user-select').select2({
        ajax: {
          url: '{{ route('convenor.search-users') }}',
          dataType: 'json',
          delay: 250,
          data: function (params) { return { q: params.term }; },
          processResults: function (data) { return { results: data }; },
          cache: true,
        },
        placeholder: 'Search by name or email...',
        minimumInputLength: 2,
        dropdownParent: $('#manageConvenorsModal'),
      });
    }
  });

  // ── Expense Types CRUD (inline, AJAX) ────────────────────────────────────
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

  // Edit mode toggle
  document.querySelectorAll('.et-edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const row = document.getElementById('et-row-' + btn.dataset.id);
      row.querySelector('.et-label-display').classList.add('d-none');
      row.querySelector('.et-label-input').classList.remove('d-none');
      row.querySelector('.et-sort-display').classList.add('d-none');
      row.querySelector('.et-sort-input').classList.remove('d-none');
      row.querySelector('.et-save-btn').classList.remove('d-none');
      btn.classList.add('d-none');
    });
  });

  // Save edited type
  document.querySelectorAll('.et-save-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      const row   = document.getElementById('et-row-' + btn.dataset.id);
      const label = row.querySelector('.et-label-input').value.trim();
      const sort  = parseInt(row.querySelector('.et-sort-input').value) || 0;
      if (!label) return;

      fetch(btn.dataset.url, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ label, sort_order: sort }),
      })
      .then(r => r.json())
      .then(data => {
        row.querySelector('.et-label-display').textContent = data.label;
        row.querySelector('.et-sort-display').textContent  = data.sort_order;
        row.querySelector('.et-label-display').classList.remove('d-none');
        row.querySelector('.et-label-input').classList.add('d-none');
        row.querySelector('.et-sort-display').classList.remove('d-none');
        row.querySelector('.et-sort-input').classList.add('d-none');
        btn.classList.add('d-none');
        row.querySelector('.et-edit-btn').classList.remove('d-none');
      })
      .catch(() => alert('Save failed.'));
    });
  });

  // Delete type
  document.querySelectorAll('.et-delete-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      if (!confirm('Delete expense type "' + btn.dataset.label + '"?')) return;

      fetch(btn.dataset.url, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
      })
      .then(r => r.json())
      .then(() => {
        document.getElementById('et-row-' + btn.dataset.id)?.remove();
      })
      .catch(r => r.json().then(d => alert(d.message ?? 'Delete failed.')));
    });
  });

  // Add new type
  document.getElementById('addExpenseTypeBtn')?.addEventListener('click', function() {
    const label = document.getElementById('newTypeLabel').value.trim();
    const sort  = parseInt(document.getElementById('newTypeSort').value) || 100;
    if (!label) { alert('Please enter a label.'); return; }

    fetch('{{ route('admin.expense-types.store') }}', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
      body: JSON.stringify({ label, sort_order: sort }),
    })
    .then(r => r.json())
    .then(function(et) {
      // Append new row to the table
      const tbody = document.querySelector('#expenseTypesTable tbody');
      const tr = document.createElement('tr');
      tr.id = 'et-row-' + et.id;
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
      // Wire up event listeners for the new row
      wireTypeRow(tr, et.id);
      document.getElementById('newTypeLabel').value = '';
    })
    .catch(() => alert('Failed to add type.'));
  });

  function wireTypeRow(tr, id) {
    tr.querySelector('.et-edit-btn')?.addEventListener('click', function() {
      tr.querySelector('.et-label-display').classList.add('d-none');
      tr.querySelector('.et-label-input').classList.remove('d-none');
      tr.querySelector('.et-sort-display').classList.add('d-none');
      tr.querySelector('.et-sort-input').classList.remove('d-none');
      tr.querySelector('.et-save-btn').classList.remove('d-none');
      tr.querySelector('.et-edit-btn').classList.add('d-none');
    });
    tr.querySelector('.et-save-btn')?.addEventListener('click', function() {
      const label = tr.querySelector('.et-label-input').value.trim();
      const sort  = parseInt(tr.querySelector('.et-sort-input').value) || 0;
      if (!label) return;
      const url = tr.querySelector('.et-save-btn').dataset.url;
      fetch(url, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({ label, sort_order: sort }),
      })
      .then(r => r.json())
      .then(data => {
        tr.querySelector('.et-label-display').textContent = data.label;
        tr.querySelector('.et-sort-display').textContent  = data.sort_order;
        tr.querySelector('.et-label-display').classList.remove('d-none');
        tr.querySelector('.et-label-input').classList.add('d-none');
        tr.querySelector('.et-sort-display').classList.remove('d-none');
        tr.querySelector('.et-sort-input').classList.add('d-none');
        tr.querySelector('.et-save-btn').classList.add('d-none');
        tr.querySelector('.et-edit-btn').classList.remove('d-none');
      });
    });
    tr.querySelector('.et-delete-btn')?.addEventListener('click', function() {
      const lbl = tr.querySelector('.et-delete-btn').dataset.label;
      if (!confirm('Delete expense type "' + lbl + '"?')) return;
      const url = tr.querySelector('.et-delete-btn').dataset.url;
      fetch(url, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
      })
      .then(() => tr.remove())
      .catch(r => r.json().then(d => alert(d.message ?? 'Delete failed.')));
    });
  }
</script>
@endsection
