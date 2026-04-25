{{-- Shared expense form fields for add/edit expense modals --}}
<div class="row g-3">

  <div class="col-md-6">
    <label class="form-label">Expense Type <span class="text-danger">*</span></label>
    <select name="expense_type" class="form-select" required>
      <option value="">Select type...</option>
      @foreach($expenseTypes as $key => $label)
        <option value="{{ $key }}" {{ old('expense_type', $expense?->expense_type) == $key ? 'selected' : '' }}>
          {{ $label }}
        </option>
      @endforeach
    </select>
  </div>

  <div class="col-md-6">
    <label class="form-label">Paid by (Event Director)</label>
    <select name="paid_by_convenor_id" class="form-select">
      <option value="">— No event director assigned —</option>
      @foreach($convenors as $c)
        <option value="{{ $c->id }}"
                {{ old('paid_by_convenor_id', $expense?->paid_by_convenor_id) == $c->id ? 'selected' : '' }}>
          {{ $c->user->name ?? 'Unknown' }}
          ({{ $c->isHoof() ? 'Head' : ($c->isHulp() ? 'Assist' : ucfirst($c->role)) }})
        </option>
      @endforeach
    </select>
  </div>

  <div class="col-md-6">
    <label class="form-label">Recipient Name</label>
    <input type="text" name="recipient_name" class="form-control"
           value="{{ old('recipient_name', $expense?->recipient_name) }}"
           placeholder="e.g. Ingrid Le Roux">
  </div>

  <div class="col-md-6">
    <label class="form-label">Description</label>
    <input type="text" name="description" class="form-control"
           value="{{ old('description', $expense?->description) }}"
           placeholder="Optional description">
  </div>

  <div class="col-md-4">
    <label class="form-label">Quantity</label>
    <input type="number" name="quantity" class="form-control"
           step="0.01" min="0"
           value="{{ old('quantity', $expense?->quantity) }}"
           placeholder="e.g. 96">
  </div>

  <div class="col-md-4">
    <label class="form-label">Unit Price (R)</label>
    <input type="number" name="unit_price" class="form-control"
           step="0.01" min="0"
           value="{{ old('unit_price', $expense?->unit_price) }}"
           placeholder="e.g. 100.00">
  </div>

  <div class="col-md-4">
    <label class="form-label">Amount (R) <span class="text-danger">*</span></label>
    <input type="number" name="amount" class="form-control"
           step="0.01" min="0" required
           value="{{ old('amount', $expense?->amount) }}"
           placeholder="0.00">
    <small class="text-muted">Auto-calculated when Quantity × Price is filled in.</small>
  </div>

  <div class="col-md-6">
    <label class="form-label">Budget Amount (R)</label>
    <input type="number" name="budget_amount" class="form-control"
           step="0.01" min="0"
           value="{{ old('budget_amount', $expense?->budget_amount) }}"
           placeholder="Estimated budget">
  </div>

  <div class="col-md-6">
    <label class="form-label">Date</label>
    <input type="date" name="date" class="form-control"
           value="{{ old('date', $expense?->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
  </div>

  <div class="col-12">
    <label class="form-label">Receipt / Voucher</label>
    <input type="file" name="receipt" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
    @if($expense?->receipt_path)
      <small class="text-muted">
        Current: <a href="{{ asset('storage/'.$expense->receipt_path) }}" target="_blank">
          <i class="ti ti-paperclip"></i> View receipt
        </a>
      </small>
    @endif
  </div>

</div>
