@extends('layouts/layoutMaster')
@section('title', 'Clothing — ' . $region->name)

@section('vendor-style')
  <link rel="stylesheet" href="{{ asset('assets/vendor/libs/select2/select2.css') }}">
@endsection
@section('vendor-script')
  <script src="{{ asset('assets/vendor/libs/select2/select2.js') }}"></script>
@endsection

@section('content')
<div class="container-xxl py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">{{ $region->name }} — Clothing</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddItem">
      + Add Item
    </button>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Items & Prices</h5>
      <button id="btn-save" class="btn btn-success btn-sm">Save Changes</button>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="items-table">
          <thead class="table-light">
            <tr>
              <th style="width: 40px">#</th>
              <th>Name</th>
              <th style="width:140px">Price (R)</th>
              <th style="width:120px">Ordering</th>
              <th>Sizes</th>
              <th style="width: 80px"></th>
            </tr>
          </thead>
          <tbody>
            @forelse($items as $i)
              <tr data-id="{{ $i->id }}">
                <td class="text-muted">{{ $i->id }}</td>
                <td>
                  <input type="text" class="form-control form-control-sm item-name" value="{{ $i->item_type_name }}">
                </td>
                <td>
                  <input type="number" min="0" class="form-control form-control-sm item-price" value="{{ (int)($i->price ?? 0) }}">
                </td>
                <td>
                  <input type="number" min="0" class="form-control form-control-sm item-ordering" value="{{ $i->ordering }}">
                </td>
                <td>
                  <div class="d-flex flex-wrap gap-1 sizes-wrap">
                    @foreach($i->sizes as $sz)
                      <span class="badge bg-label-primary d-flex align-items-center gap-2" data-size-id="{{ $sz->id }}">
                        <span>{{ $sz->size }}</span>
                        <button type="button" class="btn btn-xs btn-link text-danger p-0 btn-del-size" title="Delete size">×</button>
                      </span>
                    @endforeach
                  </div>
                  <div class="input-group input-group-sm mt-1" style="max-width:320px">
                    <input type="text" class="form-control new-size" placeholder="Add size e.g. S / 10-11">
                    <input type="number" class="form-control" placeholder="Order" style="max-width:100px">
                    <button class="btn btn-outline-primary btn-add-size">Add</button>
                  </div>
                </td>
                <td class="text-end">
                  <button class="btn btn-sm btn-outline-danger btn-del-item">Delete</button>
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center p-4 text-muted">No items yet</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

{{-- Add Item Modal --}}
<div class="modal fade" id="modalAddItem" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="formAddItem">
      @csrf
      <div class="modal-header">
        <h5 class="modal-title">Add Clothing Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Name</label>
          <input type="text" class="form-control" name="item_type_name" required>
        </div>
        <div class="mb-2">
          <label class="form-label">Price (R)</label>
          <input type="number" class="form-control" name="price" min="0" value="0">
        </div>
        <div class="mb-2">
          <label class="form-label">Ordering</label>
          <input type="number" class="form-control" name="ordering" min="0">
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>
<script>
(function(){
  const DEBUG = true;

  // ---------- helpers ----------
  const csrf = '{{ csrf_token() }}';
  const base = @json(route('backend.region.clothing.edit', $region)); // e.g. "/backend/region/5/clothing"

  const routes = {
    storeItem:  `${base}/items`,
    bulkUpdate: `${base}/items/bulk`,
    destroyItem: (itemId) => `${base}/items/${itemId}`,
    storeSize:   (itemId) => `${base}/${itemId}/sizes`,
    destroySize: (itemId, sizeId) => `${base}/${itemId}/sizes/${sizeId}`,
  };

  function logClick(msg, extra={}) {
    if (!DEBUG) return;
    console.groupCollapsed(`🖱️ CLICK: ${msg}`);
    if (Object.keys(extra).length) console.log('data:', extra);
    console.groupEnd();
  }
  function logReq(phase, settings, data) {
    if (!DEBUG) return;
    const tag = phase === 'SEND' ? '📤' : phase === 'SUCCESS' ? '✅' : phase === 'ERROR' ? '❌' : '📪';
    console.groupCollapsed(`${tag} AJAX ${phase}: ${settings.type || settings.method} ${settings.url}`);
    if (data !== undefined) {
      try { console.log('payload:', typeof data === 'string' ? JSON.parse(data) : data); }
      catch { console.log('payload(raw):', data); }
    }
    console.log('settings:', settings);
    console.groupEnd();
  }
  function logRespOk(url, res) {
    if (!DEBUG) return;
    console.groupCollapsed(`✅ SUCCESS: ${url}`);
    console.log('response:', res);
    console.groupEnd();
  }
  function logRespErr(url, jq, thrown) {
    if (!DEBUG) return;
    console.groupCollapsed(`❌ ERROR: ${url}`);
    console.error('status:', jq.status, jq.statusText);
    // Try hard to show meaningful server error
    let body = jq.responseJSON ?? (()=>{ try { return JSON.parse(jq.responseText) } catch { return jq.responseText } })();
    console.error('response:', body);
    if (thrown) console.error('thrown:', thrown);
    console.groupEnd();
  }
  function toastOk(msg='Saved') {
    const el = document.createElement('div');
    el.className = 'alert alert-success position-fixed shadow';
    el.style.right='16px'; el.style.bottom='16px'; el.style.zIndex=2000;
    el.innerText = msg;
    document.body.appendChild(el);
    setTimeout(()=>el.remove(), 1500);
  }
  function toastErr(msg='Action failed') {
    const el = document.createElement('div');
    el.className = 'alert alert-danger position-fixed shadow';
    el.style.right='16px'; el.style.bottom='16px'; el.style.zIndex=2000;
    el.innerText = msg;
    document.body.appendChild(el);
    setTimeout(()=>el.remove(), 3000);
  }

  // ---------- global ajax debug hooks ----------
  $(document).ajaxSend((e, jqXHR, settings) => logReq('SEND', settings, settings.data));
  $(document).ajaxSuccess((e, jqXHR, settings) => logReq('SUCCESS', settings));
  $(document).ajaxError((e, jqXHR, settings, thrownError) => logReq('ERROR', settings));
  $(document).ajaxComplete((e, jqXHR, settings) => logReq('COMPLETE', settings));

  // ---------- Add Item ----------
  $('#formAddItem').on('submit', function(e){
    e.preventDefault();
    logClick('Add Item (modal Save)');
    const payload = $(this).serialize();
    $.post(routes.storeItem, payload)
      .done((res) => { logRespOk(routes.storeItem, res); location.reload(); })
      .fail((xhr, _s, t) => { logRespErr(routes.storeItem, xhr, t); toastErr('Failed to add'); });
  });

  // ---------- Delete Item ----------
  $('#items-table').on('click','.btn-del-item', function(){
    logClick('Delete Item button');
    if(!confirm('Delete this item?')) return;
    const $tr = $(this).closest('tr');
    const id  = $tr.data('id');

    $.ajax({
      url: routes.destroyItem(id),
      method: 'DELETE',
      data: {_token: csrf}
    })
    .done((res) => { logRespOk(routes.destroyItem(id), res); $tr.remove(); toastOk('Item deleted'); })
    .fail((xhr, _s, t) => { logRespErr(routes.destroyItem(id), xhr, t); toastErr('Delete failed'); });
  });

  // ---------- Add Size ----------
  $('#items-table').on('click','.btn-add-size', function(){
    logClick('Add Size button');
    const $tr = $(this).closest('tr');
    const itemId  = $tr.data('id');
    const sizeVal = $tr.find('.new-size').val().trim();
    const orderVal= $tr.find('.new-size').next('input[type=number]').val();

    if(!sizeVal) { toastErr('Enter a size'); return; }

    $.post(routes.storeSize(itemId), { _token: csrf, size: sizeVal, ordering: orderVal || null })
      .done((res) => {
        logRespOk(routes.storeSize(itemId), res);
        const s = res.size || res; // accept either shape
        $tr.find('.sizes-wrap').append(`
          <span class="badge bg-label-primary d-flex align-items-center gap-2" data-size-id="${s.id}">
            <span>${s.size}</span>
            <button type="button" class="btn btn-xs btn-link text-danger p-0 btn-del-size" title="Delete size">×</button>
          </span>
        `);
        $tr.find('.new-size').val('');
        $tr.find('.new-size').next('input[type=number]').val('');
        toastOk('Size added');
      })
      .fail((xhr, _s, t) => { logRespErr(routes.storeSize(itemId), xhr, t); toastErr('Failed to add size'); });
  });

  // ---------- Delete Size ----------
  $('#items-table').on('click','.btn-del-size', function(){
    logClick('Delete Size button');
    const $badge = $(this).closest('[data-size-id]');
    const $tr    = $(this).closest('tr');
    const itemId = $tr.data('id');
    const sizeId = $badge.data('size-id');

    $.ajax({
      url: routes.destroySize(itemId, sizeId),
      method: 'DELETE',
      data: {_token: csrf}
    })
    .done((res) => { logRespOk(routes.destroySize(itemId, sizeId), res); $badge.remove(); toastOk('Size deleted'); })
    .fail((xhr, _s, t) => { logRespErr(routes.destroySize(itemId, sizeId), xhr, t); toastErr('Failed to delete size'); });
  });

  // ---------- Bulk Save ----------
  $('#btn-save').on('click', function(){
    logClick('Save Changes (bulk)');
    const rows = [];
    $('#items-table tbody tr').each(function(){
      rows.push({
        id: $(this).data('id'),
        item_type_name: $(this).find('.item-name').val().trim(),
        price: Number($(this).find('.item-price').val() || 0),
        ordering: $(this).find('.item-ordering').val() || null,
      });
    });

    $.ajax({
      url: routes.bulkUpdate,
      method: 'PATCH',
      data: {_token: csrf, items: rows}
    })
    .done((res) => { logRespOk(routes.bulkUpdate, res); toastOk('Saved'); })
    .fail((xhr, _s, t) => { logRespErr(routes.bulkUpdate, xhr, t); toastErr('Save failed'); });
  });

})();
</script>
@endsection

@push('page-script')

@endpush



