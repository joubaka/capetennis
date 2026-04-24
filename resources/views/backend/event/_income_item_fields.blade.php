{{-- Shared income item form fields for add/edit income item modals --}}
<div class="row g-3">

  <div class="col-12">
    <label class="form-label">Beskrywing / Label <span class="text-danger">*</span></label>
    <input type="text" name="label" class="form-control" required
           value="{{ old('label', $item?->label) }}"
           placeholder="bv. CT Inskrywingsgeld">
  </div>

  <div class="col-md-4">
    <label class="form-label">Hoeveelheid</label>
    <input type="number" name="quantity" class="form-control"
           step="0.01" min="0"
           value="{{ old('quantity', $item?->quantity) }}"
           placeholder="bv. 48">
  </div>

  <div class="col-md-4">
    <label class="form-label">Eenheidsprys (R)</label>
    <input type="number" name="unit_price" class="form-control"
           step="0.01" min="0"
           value="{{ old('unit_price', $item?->unit_price) }}"
           placeholder="bv. 50.00">
  </div>

  <div class="col-md-4">
    <label class="form-label">Totaal (R)</label>
    <input type="number" name="total" class="form-control"
           step="0.01" min="0"
           value="{{ old('total', $item?->total) }}"
           placeholder="Of voer direk in">
    <small class="text-muted">Word outomaties bereken as Hoeveelheid × Prys ingevul is.</small>
  </div>

  <div class="col-md-6">
    <label class="form-label">Bron</label>
    <input type="text" name="source" class="form-control"
           value="{{ old('source', $item?->source) }}"
           placeholder="bv. faktuur CT">
  </div>

  <div class="col-md-6">
    <label class="form-label">Datum</label>
    <input type="date" name="date" class="form-control"
           value="{{ old('date', $item?->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}">
  </div>

</div>
