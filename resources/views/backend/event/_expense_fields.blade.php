{{-- Shared expense form fields for add/edit expense modals --}}
<div class="row g-3">

  <div class="col-md-6">
    <label class="form-label">Tipe Uitgawe <span class="text-danger">*</span></label>
    <select name="expense_type" class="form-select" required>
      <option value="">Kies tipe...</option>
      @foreach($expenseTypes as $key => $label)
        <option value="{{ $key }}" {{ old('expense_type', $expense?->expense_type) == $key ? 'selected' : '' }}>
          {{ $label }}
        </option>
      @endforeach
    </select>
  </div>

  <div class="col-md-6">
    <label class="form-label">Betaal deur (Convenor)</label>
    <select name="paid_by_convenor_id" class="form-select">
      <option value="">— Geen convenor toegeken —</option>
      @foreach($convenors as $c)
        <option value="{{ $c->id }}"
                {{ old('paid_by_convenor_id', $expense?->paid_by_convenor_id) == $c->id ? 'selected' : '' }}>
          {{ $c->user->name ?? 'Onbekend' }}
          ({{ $c->isHoof() ? 'Hoof' : ($c->isHulp() ? 'Hulp' : ucfirst($c->role)) }})
        </option>
      @endforeach
    </select>
  </div>

  <div class="col-md-6">
    <label class="form-label">Ontvanger Naam</label>
    <input type="text" name="recipient_name" class="form-control"
           value="{{ old('recipient_name', $expense?->recipient_name) }}"
           placeholder="bv. Ingrid Le Roux">
  </div>

  <div class="col-md-6">
    <label class="form-label">Beskrywing</label>
    <input type="text" name="description" class="form-control"
           value="{{ old('description', $expense?->description) }}"
           placeholder="Opsionele beskrywing">
  </div>

  <div class="col-md-4">
    <label class="form-label">Hoeveelheid</label>
    <input type="number" name="quantity" class="form-control"
           step="0.01" min="0"
           value="{{ old('quantity', $expense?->quantity) }}"
           placeholder="bv. 96">
  </div>

  <div class="col-md-4">
    <label class="form-label">Eenheidsprys (R)</label>
    <input type="number" name="unit_price" class="form-control"
           step="0.01" min="0"
           value="{{ old('unit_price', $expense?->unit_price) }}"
           placeholder="bv. 100.00">
  </div>

  <div class="col-md-4">
    <label class="form-label">Bedrag (R) <span class="text-danger">*</span></label>
    <input type="number" name="amount" class="form-control"
           step="0.01" min="0" required
           value="{{ old('amount', $expense?->amount) }}"
           placeholder="0.00">
    <small class="text-muted">Word outomaties bereken as Hoeveelheid × Prys ingevul is.</small>
  </div>

  <div class="col-md-6">
    <label class="form-label">Begrotingsbedrag (R)</label>
    <input type="number" name="budget_amount" class="form-control"
           step="0.01" min="0"
           value="{{ old('budget_amount', $expense?->budget_amount) }}"
           placeholder="Geskatte begroting">
  </div>

  <div class="col-md-6">
    <label class="form-label">Datum</label>
    <input type="date" name="date" class="form-control"
           value="{{ old('date', $expense?->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
  </div>

  <div class="col-12">
    <label class="form-label">Kwitansie / Strokie</label>
    <input type="file" name="receipt" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
    @if($expense?->receipt_path)
      <small class="text-muted">
        Huidig: <a href="{{ asset('storage/'.$expense->receipt_path) }}" target="_blank">
          <i class="ti ti-paperclip"></i> Sien kwitansie
        </a>
      </small>
    @endif
  </div>

</div>
