@extends('layouts/layoutMaster')

@section('title', $event->name . ' – Finances')

@section('page-style')
<style>
  .finance-card { transition: all 0.2s ease; }
  .finance-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }

  .convenor-header { background: #fff9c4; border-left: 4px solid #f0c040; }
  .system-row td { background: #f8f9fa; font-style: italic; }
  .approved-badge { font-size: 0.7rem; }
  .budget-over { color: #dc3545; font-weight: 600; }
  .budget-under { color: #28a745; }
  .recon-table th { background: #343a40; color: #fff; }

  @media print {
    .no-print, .btn, .modal, .card-header .btn { display: none !important; }
    .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
    body { font-size: 12px; }
  }
</style>
@endsection

@section('content')
<div class="container-xl">

  {{-- ── HEADER ──────────────────────────────────────────────────────── --}}
  <div class="card mb-3 no-print">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
      <h4 class="mb-0">
        <i class="ti ti-report-money me-2 text-warning"></i>
        Finances — {{ $event->name }}
      </h4>
      <div class="d-flex gap-2 flex-wrap">
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

  @if($budgetCapWarning)
    <div class="alert alert-warning d-flex align-items-center no-print" role="alert">
      <i class="ti ti-alert-triangle me-2 fs-4"></i>
      <div>
        <strong>Budget Warning!</strong>
        Spending has reached 90% of the budget (R {{ number_format($event->budget_cap, 2) }}).
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
          <small class="text-muted d-block mb-1"><i class="ti ti-cash me-1 text-primary"></i>Total Income</small>
          <h5 class="mb-0">R {{ number_format($grandTotalIncome, 2) }}</h5>
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
          <small class="text-muted d-block mb-1"><i class="ti ti-shopping-cart me-1 text-danger"></i>Total Expenses</small>
          <h5 class="mb-0 text-danger">R {{ number_format($totalExpenses, 2) }}</h5>
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
            {{-- Registration income row --}}
            <tr>
              <td>
                <span class="badge bg-label-primary me-1">System</span>
                Registration Fees (PayFast)
              </td>
              <td class="text-center">{{ $totalEntries }}</td>
              <td class="text-end">—</td>
              <td><small class="text-muted">PayFast transactions</small></td>
              <td>—</td>
              <td class="text-end fw-semibold text-success">R {{ number_format($totalGross, 2) }}</td>
              <td class="no-print"></td>
            </tr>

            {{-- Manual income items --}}
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
              <td colspan="5" class="fw-bold">Total Income</td>
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
                      $isSystem = in_array($expense->expense_type, ['payfast', 'cape_tennis_fee']);
                    @endphp
                    <tr class="expense-row{{ $isSystem ? ' system-row' : '' }}">
                      <td>
                        <span class="badge bg-label-secondary">
                          {{ $expenseTypes[$expense->expense_type] ?? ucfirst($expense->expense_type) }}
                        </span>
                        @if($isSystem)
                          <span class="badge bg-label-info ms-1" title="Automatically synced">
                            <i class="ti ti-refresh"></i>
                          </span>
                        @endif
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
                        @if(!$expense->approved_at && !$isSystem)
                          <form action="{{ route('admin.events.finances.expense.approve', $expense) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-icon btn-sm btn-outline-success" title="Approve">
                              <i class="ti ti-check"></i>
                            </button>
                          </form>
                        @endif
                        @if($expense->approved_at && !$expense->reimbursed_at && !$isSystem)
                          <form action="{{ route('admin.events.finances.expense.reimburse', $expense) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-icon btn-sm btn-outline-info" title="Mark as reimbursed">
                              <i class="ti ti-coin"></i>
                            </button>
                          </form>
                        @endif
                        @if(!$isSystem)
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
                        @endif
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

  {{-- Unassigned expenses (no paid_by_convenor_id) --}}
  @php
    $unassigned = $expensesByConvenor->get(null, collect());
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
                <th class="text-end">Actual</th>
                <th class="no-print" style="width:100px"></th>
              </tr>
            </thead>
            <tbody>
              @foreach($unassigned as $expense)
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
                  <td class="text-end fw-semibold">R {{ number_format($expense->calculatedAmount(), 2) }}</td>
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
              <td colspan="2"><small class="text-muted">Total Income</small></td>
              <td colspan="3" class="text-end fw-semibold text-success">R {{ number_format($grandTotalIncome, 2) }}</td>
              <td></td>
            </tr>
            <tr class="table-secondary">
              <td colspan="2"><small class="text-muted">Total Expenses</small></td>
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

{{-- EDIT EXPENSE MODALS (one per expense) --}}
@foreach($expenses as $expense)
  @if(!in_array($expense->expense_type, ['payfast', 'cape_tennis_fee']))
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
  @endif
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
</script>
@endsection
