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
    @if(!empty($multiPaidBy))
      <label class="form-label">Paid by (Event Director(s))</label>
      <select name="paid_by_convenor_ids[]" id="expensePaidBySelect" class="form-select" multiple>
        @foreach($convenors as $c)
          <option value="{{ $c->id }}">
            {{ $c->user->name ?? 'Unknown' }}
            ({{ $c->isHoof() ? 'Head' : ($c->isHulp() ? 'Assist' : ucfirst($c->role)) }})
          </option>
        @endforeach
      </select>
      <small class="text-muted">Select one or more directors. One expense record will be created per person.</small>
    @else
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
    @endif
  </div>

  <div class="col-md-6 vc-col-wrapper{{ old('recipient_name', $expense?->recipient_name) ? '' : ' d-none' }}">
    <label class="form-label">Venue Convenor / Recipient</label>
    <div class="vc-field-wrapper">
      <div class="input-group">
        <select name="recipient_name" class="form-select vc-select"
                data-vc-store-url="{{ route('admin.events.finances.venue-convenor.store', $event) }}"
                data-vc-destroy-url="{{ route('admin.events.finances.venue-convenor.destroy', ['venueConvenor' => '__ID__']) }}">
          <option value="">— none —</option>
          @foreach($venueConvenors ?? [] as $vc)
            <option value="{{ $vc->name }}" data-vc-id="{{ $vc->id }}"
                    {{ old('recipient_name', $expense?->recipient_name) == $vc->name ? 'selected' : '' }}>
              {{ $vc->name }}
            </option>
          @endforeach
        </select>
        <button type="button" class="btn btn-outline-success vc-add-btn" title="Add venue convenor">
          <i class="ti ti-plus"></i>
        </button>
        <button type="button" class="btn btn-outline-danger vc-remove-btn d-none" title="Remove selected venue convenor">
          <i class="ti ti-minus"></i>
        </button>
      </div>
      <div class="vc-add-form mt-2 d-none">
        <div class="input-group input-group-sm">
          <input type="text" class="form-control vc-add-name" placeholder="New convenor name" maxlength="150">
          <button type="button" class="btn btn-success vc-add-save-btn">
            <i class="ti ti-check me-1"></i>Save
          </button>
          <button type="button" class="btn btn-outline-secondary vc-add-cancel-btn">Cancel</button>
        </div>
      </div>
    </div>
    <small class="text-muted">
      Person paid to convene a venue, or other payee.
      <a href="#" class="vc-hide-link ms-2 text-muted"><i class="ti ti-x"></i> Remove</a>
    </small>
  </div>

  <div class="col-md-6">
    <label class="form-label">Description</label>
    <input type="text" name="description" class="form-control"
           value="{{ old('description', $expense?->description) }}"
           placeholder="Optional description">
    @if(!old('recipient_name', $expense?->recipient_name))
      <small><a href="#" class="vc-show-link text-muted"><i class="ti ti-user-plus"></i> Add venue convenor / payee</a></small>
    @endif
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
