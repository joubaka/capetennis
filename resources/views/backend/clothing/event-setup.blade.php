@extends('layouts.backend')

@section('title', 'Event Clothing Setup')

@section('content')
<div class="container-xxl py-4">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
    <div><h3 class="mb-1">Event Clothing Setup</h3><p class="text-muted mb-0">{{ $event->name }}</p></div>
    <a href="{{ route('admin.events.overview', $event) }}" class="btn btn-outline-secondary">Back to event</a>
  </div>

  <div class="alert alert-info">
    First select the regions that will use online clothing orders. Then set up their catalogues, review this year’s prices, and keep ordering closed until each catalogue is approved. Previous events are never changed.
  </div>
  @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

  <form method="POST" action="{{ route('backend.event.clothing.regions.update', $event) }}" class="card mb-4">
    @csrf @method('PATCH')
    <div class="card-header">
      <h5 class="mb-1">Regions using online clothing orders</h5>
      <p class="text-muted small mb-0">Untick a region to remove ordering from its player area. Existing catalogues and paid orders will be kept.</p>
    </div>
    <div class="card-body">
      <div class="row g-3">
        @foreach($event->regions as $region)
          <div class="col-12 col-md-6 col-xl-4">
            <div class="form-check border rounded p-3 ps-5 h-100">
              <input class="form-check-input" type="checkbox" name="region_ids[]" value="{{ $region->id }}" id="online-clothing-region-{{ $region->id }}" @checked($region->usesOnlineClothingOrders())>
              <label class="form-check-label fw-semibold" for="online-clothing-region-{{ $region->id }}">{{ $region->region_name }}</label>
            </div>
          </div>
        @endforeach
      </div>
    </div>
    <div class="card-footer d-flex justify-content-end">
      <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save online clothing regions</button>
    </div>
  </form>

  <div class="row g-3">
    @foreach($event->regions as $region)
      @php($items = $region->clothingItems)
      @php($usesOnlineClothing = $region->usesOnlineClothingOrders())
      @php($recommended = $recommendedSources->get($region->id))
      @php($sizeCount = $items->sum(fn($item) => $item->sizes->count()))
      @php($notReady = $items->filter(fn($item) => (float)$item->price <= 0 || $item->sizes->isEmpty()))
      <div class="col-12">
        <div class="card">
          <div class="card-header d-flex flex-wrap justify-content-between align-items-start gap-2">
            <div>
              <h5 class="mb-1">{{ $region->region_name }}</h5>
              <div class="text-muted small">{{ $items->count() }} items · {{ $sizeCount }} size options</div>
            </div>
            <span class="badge bg-label-{{ !$usesOnlineClothing ? 'secondary' : ($region->clothing_order ? 'success' : ($items->isNotEmpty() ? 'warning' : 'secondary')) }}">
              {{ !$usesOnlineClothing ? 'Online orders not used' : ($region->clothing_order ? 'Ordering open' : ($items->isNotEmpty() ? 'Catalogue ready · ordering closed' : 'Not set up')) }}
            </span>
          </div>
          <div class="card-body">
            @if(!$usesOnlineClothing)
              <div class="alert alert-light border mb-3">This region is not selected for online clothing orders. Select it above and save before setting up or opening its catalogue.</div>
            @elseif($items->isNotEmpty())
              <div class="table-responsive mb-3">
                <table class="table table-sm mb-0"><thead><tr><th>Order</th><th>Item</th><th>Price</th><th>Sizes</th></tr></thead><tbody>
                  @foreach($items->sortBy([['ordering','asc'],['item_type_name','asc']]) as $item)
                    <tr><td>{{ $item->ordering ?? '—' }}</td><td>{{ $item->item_type_name }}</td><td>R{{ number_format((float)$item->price, 0) }}</td><td>{{ $item->sizes->count() }}</td></tr>
                  @endforeach
                </tbody></table>
              </div>
              @if($notReady->isNotEmpty())
                <div class="alert alert-warning py-2">{{ $notReady->count() }} item(s) still need a positive price or at least one size before ordering can open.</div>
              @endif
            @elseif($recommended)
              <div class="alert alert-light border">
                Recommended previous setup: <strong>{{ $recommended->region_name }}</strong> · {{ $recommended->clothing_items_count }} items.
              </div>
            @else
              <div class="alert alert-light border">No matching previous clothing catalogue was found. Choose any saved region catalogue on the management page or create items manually.</div>
            @endif

            <div class="d-flex flex-wrap gap-2">
              @if($usesOnlineClothing)
                <a class="btn btn-primary" href="{{ route('backend.region.clothing.edit', array_filter(['region' => $region->id, 'source_region' => $recommended?->id, 'event_id' => $event->id])) }}">
                  <i class="ti ti-settings me-1"></i>{{ $items->isEmpty() ? 'Set up clothing' : 'Manage clothing & prices' }}
                </a>
              @endif
              <a class="btn btn-outline-secondary" href="{{ route('backend.region.clothing.orders', ['region' => $region, 'event_id' => $event->id]) }}"><i class="ti ti-list me-1"></i>Paid orders</a>
              @if($usesOnlineClothing && $items->isNotEmpty())
                <form method="POST" action="{{ route('backend.region.clothing.toggle', $region) }}">
                  @csrf @method('PATCH')
                  <button class="btn btn-{{ $region->clothing_order ? 'outline-danger' : 'success' }}" @disabled(!$region->clothing_order && $notReady->isNotEmpty())>
                    <i class="ti ti-{{ $region->clothing_order ? 'lock' : 'shopping-cart' }} me-1"></i>{{ $region->clothing_order ? 'Close ordering' : 'Open ordering' }}
                  </button>
                </form>
              @endif
            </div>
          </div>
        </div>
      </div>
    @endforeach
  </div>
</div>
@endsection
