@extends('layouts/layoutMaster')

@section('title', $event->name . ' – Convenor Finances')

@section('page-style')
<style>
  .finance-card {
    transition: all 0.2s ease;
  }
  .finance-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  }
  .expense-row:hover {
    background-color: #f8f9fa;
  }
  .profit { color: #28a745; }
  .loss { color: #dc3545; }
</style>
@endsection

@section('content')
<div class="container-xl">

  {{-- HEADER --}}
  <div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="mb-0">
        <i class="ti ti-report-money me-2 text-warning"></i>
        Convenor Finances
      </h4>
      <div class="d-flex gap-2">
        <a href="{{ route('admin.events.transactions', $event) }}" class="btn btn-outline-primary btn-sm">
          <i class="ti ti-credit-card me-1"></i>View Transactions
        </a>
        <a href="{{ route('admin.events.overview', $event) }}" class="btn btn-outline-secondary btn-sm">
          <i class="ti ti-arrow-left me-1"></i>Back to Event
        </a>
      </div>
    </div>
  </div>

  {{-- SUMMARY CARDS --}}
  <div class="row g-3 mb-4">
    {{-- Registration Income --}}
    <div class="col-md-3">
      <div class="card finance-card border-start border-primary border-3 h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2">
            <i class="ti ti-cash text-primary me-2"></i>
            <small class="text-muted">Registration Income</small>
          </div>
          <h4 class="mb-0">R {{ number_format($totalGross, 2) }}</h4>
        </div>
      </div>
    </div>

    {{-- Net After Fees --}}
    <div class="col-md-3">
      <div class="card finance-card border-start border-success border-3 h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2">
            <i class="ti ti-receipt text-success me-2"></i>
            <small class="text-muted">Net After Fees</small>
          </div>
          <h4 class="mb-0 text-success">R {{ number_format($netRegistrationIncome, 2) }}</h4>
          <small class="text-muted">
            (PayFast: -R{{ number_format(abs($totalPayfastFees), 2) }}, CT: -R{{ number_format($totalCapeTennisFees, 2) }})
          </small>
        </div>
      </div>
    </div>

    {{-- Total Expenses --}}
    <div class="col-md-3">
      <div class="card finance-card border-start border-danger border-3 h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2">
            <i class="ti ti-shopping-cart text-danger me-2"></i>
            <small class="text-muted">Total Expenses</small>
          </div>
          <h4 class="mb-0 text-danger">R {{ number_format($totalExpenses, 2) }}</h4>
        </div>
      </div>
    </div>

    {{-- Net Profit/Loss --}}
    <div class="col-md-3">
      <div class="card finance-card border-start {{ $netProfit >= 0 ? 'border-success' : 'border-danger' }} border-3 h-100">
        <div class="card-body">
          <div class="d-flex align-items-center mb-2">
            <i class="ti ti-trending-{{ $netProfit >= 0 ? 'up' : 'down' }} {{ $netProfit >= 0 ? 'text-success' : 'text-danger' }} me-2"></i>
            <small class="text-muted">Net {{ $netProfit >= 0 ? 'Profit' : 'Loss' }}</small>
          </div>
          <h4 class="mb-0 {{ $netProfit >= 0 ? 'profit' : 'loss' }}">
            R {{ number_format(abs($netProfit), 2) }}
          </h4>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    {{-- EXPENSES LIST --}}
    <div class="col-lg-8">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <h5 class="mb-0">
            <i class="ti ti-list me-2"></i>Expenses
          </h5>
          <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
            <i class="ti ti-plus me-1"></i>Add Expense
          </button>
        </div>
        <div class="card-body p-0">
          @if($expenses->count() > 0)
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th>Type</th>
                    <th>Convenor</th>
                    <th>Description</th>
                    <th>Date</th>
                    <th class="text-end">Amount</th>
                    <th class="text-center" style="width: 100px;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($expenses as $expense)
                    <tr class="expense-row">
                      <td>
                        <span class="badge bg-label-secondary">
                          {{ $expenseTypes[$expense->expense_type] ?? ucfirst($expense->expense_type) }}
                        </span>
                      </td>
                      <td>{{ $expense->convenor_name ?? '-' }}</td>
                      <td>{{ $expense->description ?? '-' }}</td>
                      <td>{{ $expense->date?->format('d M Y') ?? '-' }}</td>
                      <td class="text-end fw-semibold">R {{ number_format($expense->amount, 2) }}</td>
                      <td class="text-center">
                        <button type="button" 
                                class="btn btn-icon btn-sm btn-outline-primary"
                                data-bs-toggle="modal" 
                                data-bs-target="#editExpenseModal{{ $expense->id }}">
                          <i class="ti ti-edit"></i>
                        </button>
                        <form action="{{ route('admin.events.finances.expense.destroy', $expense) }}" 
                              method="POST" 
                              class="d-inline"
                              onsubmit="return confirm('Delete this expense?')">
                          @csrf
                          @method('DELETE')
                          <button type="submit" class="btn btn-icon btn-sm btn-outline-danger">
                            <i class="ti ti-trash"></i>
                          </button>
                        </form>
                      </td>
                    </tr>

                    {{-- Edit Modal for this expense --}}
                    <div class="modal fade" id="editExpenseModal{{ $expense->id }}" tabindex="-1">
                      <div class="modal-dialog">
                        <div class="modal-content">
                          <form action="{{ route('admin.events.finances.expense.update', $expense) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <div class="modal-header">
                              <h5 class="modal-title">Edit Expense</h5>
                              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                              <div class="mb-3">
                                <label class="form-label">Expense Type</label>
                                <select name="expense_type" class="form-select" required>
                                  @foreach($expenseTypes as $key => $label)
                                    <option value="{{ $key }}" {{ $expense->expense_type == $key ? 'selected' : '' }}>
                                      {{ $label }}
                                    </option>
                                  @endforeach
                                </select>
                              </div>
                              <div class="mb-3">
                                <label class="form-label">Convenor Name</label>
                                <input type="text" name="convenor_name" class="form-control" 
                                       value="{{ $expense->convenor_name }}" placeholder="Name of convenor">
                              </div>
                              <div class="mb-3">
                                <label class="form-label">Description</label>
                                <input type="text" name="description" class="form-control" 
                                       value="{{ $expense->description }}" placeholder="Optional description">
                              </div>
                              <div class="mb-3">
                                <label class="form-label">Amount (R)</label>
                                <input type="number" name="amount" class="form-control" 
                                       step="0.01" min="0" value="{{ $expense->amount }}" required>
                              </div>
                              <div class="mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control" 
                                       value="{{ $expense->date?->format('Y-m-d') }}">
                              </div>
                            </div>
                            <div class="modal-footer">
                              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                              <button type="submit" class="btn btn-primary">Update Expense</button>
                            </div>
                          </form>
                        </div>
                      </div>
                    </div>
                  @endforeach
                </tbody>
                <tfoot class="table-light">
                  <tr>
                    <td colspan="4" class="fw-bold">Total</td>
                    <td class="text-end fw-bold">R {{ number_format($totalExpenses, 2) }}</td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          @else
            <div class="text-center py-5">
              <i class="ti ti-receipt-off ti-xl text-muted mb-3"></i>
              <p class="text-muted mb-0">No expenses recorded yet.</p>
              <button type="button" class="btn btn-primary btn-sm mt-3" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="ti ti-plus me-1"></i>Add First Expense
              </button>
            </div>
          @endif
        </div>
      </div>
    </div>

    {{-- EXPENSE SUMMARY BY TYPE --}}
    <div class="col-lg-4">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">
            <i class="ti ti-chart-pie me-2"></i>Expenses by Type
          </h5>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            @foreach($expenseTypes as $key => $label)
              @php
                $typeTotal = $expensesByType->get($key)?->sum('amount') ?? 0;
              @endphp
              @if($typeTotal > 0)
                <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                  <span>{{ $label }}</span>
                  <span class="badge bg-label-danger">R {{ number_format($typeTotal, 2) }}</span>
                </li>
              @endif
            @endforeach

            @if($totalExpenses == 0)
              <li class="text-center text-muted py-3">
                No expenses recorded
              </li>
            @endif
          </ul>
        </div>
      </div>

      {{-- INCOME SUMMARY --}}
      <div class="card mt-3">
        <div class="card-header">
          <h5 class="mb-0">
            <i class="ti ti-cash me-2"></i>Income Summary
          </h5>
        </div>
        <div class="card-body">
          <ul class="list-unstyled mb-0">
            <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
              <span>Total Registrations</span>
              <span class="badge bg-label-primary">{{ $totalEntries }}</span>
            </li>
            <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
              <span>Gross Income</span>
              <span class="badge bg-label-success">R {{ number_format($totalGross, 2) }}</span>
            </li>
            <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
              <span>PayFast Fees</span>
              <span class="badge bg-label-warning">-R {{ number_format(abs($totalPayfastFees), 2) }}</span>
            </li>
            <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
              <span>Cape Tennis Fees</span>
              <span class="badge bg-label-warning">-R {{ number_format($totalCapeTennisFees, 2) }}</span>
            </li>
            <li class="d-flex justify-content-between align-items-center py-2 fw-bold">
              <span>Net Income</span>
              <span class="badge bg-success">R {{ number_format($netRegistrationIncome, 2) }}</span>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>

</div>

{{-- ADD EXPENSE MODAL --}}
<div class="modal fade" id="addExpenseModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form action="{{ route('admin.events.finances.expense.store', $event) }}" method="POST">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">
            <i class="ti ti-plus me-2"></i>Add Expense
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Expense Type <span class="text-danger">*</span></label>
            <select name="expense_type" class="form-select" required>
              <option value="">Select type...</option>
              @foreach($expenseTypes as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Convenor Name</label>
            <input type="text" name="convenor_name" class="form-control" placeholder="Name of convenor">
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <input type="text" name="description" class="form-control" placeholder="Optional description">
          </div>
          <div class="mb-3">
            <label class="form-label">Amount (R) <span class="text-danger">*</span></label>
            <input type="number" name="amount" class="form-control" step="0.01" min="0" required placeholder="0.00">
          </div>
          <div class="mb-3">
            <label class="form-label">Date</label>
            <input type="date" name="date" class="form-control" value="{{ now()->format('Y-m-d') }}">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">
            <i class="ti ti-check me-1"></i>Add Expense
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection
