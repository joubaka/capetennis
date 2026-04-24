{{-- Shared income item form fields for add/edit income item modals --}}
<div class="row g-3">

  <div class="col-12">
    <label class="form-label">Description / Label <span class="text-danger">*</span></label>
    <input type="text" name="label" class="form-control" required
           value="{{ old('label', $item?->label) }}"
           placeholder="e.g. CT Entry Fee">
  </div>

  <div class="col-md-4">
    <label class="form-label">Quantity</label>
    <input type="number" name="quantity" class="form-control"
           step="0.01" min="0"
           value="{{ old('quantity', $item?->quantity) }}"
           placeholder="e.g. 48">
  </div>

  <div class="col-md-4">
    <label class="form-label">Unit Price (R)</label>
    <input type="number" name="unit_price" class="form-control"
           step="0.01" min="0"
           value="{{ old('unit_price', $item?->unit_price) }}"
           placeholder="e.g. 50.00">
  </div>

  <div class="col-md-4">
    <label class="form-label">Total (R)</label>
    <input type="number" name="total" class="form-control"
           step="0.01" min="0"
           value="{{ old('total', $item?->total) }}"
           placeholder="Or enter directly">
    <small class="text-muted">Auto-calculated when Quantity × Price is filled in.</small>
  </div>

  <div class="col-md-6">
    <label class="form-label">Source</label>
    <input type="text" name="source" class="form-control"
           value="{{ old('source', $item?->source) }}"
           placeholder="e.g. CT invoice">
  </div>

  <div class="col-md-6">
    <label class="form-label">Date</label>
    <input type="date" name="date" class="form-control"
           value="{{ old('date', $item?->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
  </div>

</div>
