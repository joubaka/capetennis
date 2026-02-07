@forelse($clothingItems as $item)
  <div class="card mb-2 clothing-item"
       data-item="{{ $item->id }}"
       data-price="{{ $item->price }}">

    <div class="card-body py-2">

      <div class="form-check">
        <input class="form-check-input item-toggle"
               type="checkbox"
               id="item-{{ $item->id }}">

        <label class="form-check-label fw-semibold"
               for="item-{{ $item->id }}">
          {{ $item->item_type_name }}
          <span class="text-muted ms-2">
            (R{{ number_format($item->price, 2) }})
          </span>
        </label>
      </div>

      <div class="row g-2 mt-2 item-options d-none">
        <div class="col-md-6">
          <label class="form-label small">Size</label>
          <select class="form-select form-select-sm size-select">
            <option value="">Select size</option>
            @foreach($item->sizes as $size)
              <option value="{{ $size->id }}">{{ $size->size }}</option>
            @endforeach
          </select>
        </div>

        <div class="col-md-3">
          <label class="form-label small">Qty</label>
          <input type="number"
                 min="1"
                 value="1"
                 class="form-control form-control-sm qty-input">
        </div>

        <div class="col-md-3 text-end align-self-end">
          <div class="small text-muted">Item total</div>
          <strong class="item-total">R0.00</strong>
        </div>
      </div>

    </div>
  </div>
@empty
  <div class="alert alert-warning mb-0">
    No clothing items configured for this region.
  </div>
@endforelse
