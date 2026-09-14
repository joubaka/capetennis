@extends('layouts.backend')

@section('title', 'Event Clothing Setup')

@section('content')
@php
  $selectedRegions = $visibleRegions->filter(fn($region) => $region->usesOnlineClothingOrders());
  $openRegions = $selectedRegions->filter(fn($region) => (bool) $region->clothing_order);
  $readyRegions = $selectedRegions->filter(fn($region) => $region->clothingItems->isNotEmpty());
@endphp

<div class="container-xxl py-4 clothing-setup">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
      <div class="text-uppercase text-muted small fw-semibold mb-1">{{ $isEventManager ? 'Event administration' : 'Regional administration' }}</div>
      <h3 class="mb-1">Clothing orders</h3>
      <p class="text-muted mb-0">{{ $event->name }}</p>
    </div>
    <a href="{{ $isEventManager ? route('admin.events.overview', $event) : route('backend.team-selection.index', $event) }}" class="btn btn-outline-secondary"><i class="ti ti-arrow-left me-1"></i>Back to event</a>
  </div>

  @if(session('success'))
    <div class="alert alert-success d-flex align-items-center gap-2" role="status"><i class="ti ti-circle-check fs-5"></i><span>{{ session('success') }}</span></div>
  @endif

  <div class="card mb-4">
    <div class="card-body py-3">
      <div class="row g-3 align-items-center">
        <div class="col-12 col-lg">
          <h5 class="mb-1">Setup overview</h5>
          <p class="text-muted small mb-0">{{ $isEventManager ? 'Choose participating regions, prepare each catalogue, then open ordering when it is ready.' : 'Prepare your assigned catalogue, then open ordering when it is ready.' }}</p>
        </div>
        <div class="col-12 col-lg-auto">
          <div class="d-flex flex-wrap gap-2 clothing-summary" aria-label="Clothing setup summary">
            @if($isEventManager)<span class="badge bg-label-primary"><strong>{{ $selectedRegions->count() }}</strong> of {{ $visibleRegions->count() }} regions selected</span>@endif
            <span class="badge bg-label-info"><strong>{{ $readyRegions->count() }}</strong> catalogues prepared</span>
            <span class="badge bg-label-success"><strong>{{ $openRegions->count() }}</strong> ordering open</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  @if($isEventManager)
  <form method="POST" action="{{ route('backend.event.clothing.regions.update', $event) }}" class="card mb-4">
    @csrf @method('PATCH')
    <div class="card-header d-flex gap-3 align-items-start">
      <span class="setup-step">1</span>
      <div><h5 class="mb-1">Choose regions</h5><p class="text-muted small mb-0">Select the player areas that should offer online clothing orders.</p></div>
    </div>
    <div class="card-body">
      <div class="row g-2">
        @foreach($visibleRegions as $region)
          <div class="col-12 col-md-6 col-xl-4">
            <label class="region-choice d-flex align-items-start gap-2 border rounded p-3 h-100" for="online-clothing-region-{{ $region->id }}">
              <input class="form-check-input flex-shrink-0 mt-0" type="checkbox" name="region_ids[]" value="{{ $region->id }}" id="online-clothing-region-{{ $region->id }}" @checked($region->usesOnlineClothingOrders())>
              <span class="fw-semibold">{{ $region->region_name }}</span>
            </label>
          </div>
        @endforeach
      </div>
      <p class="text-muted small mt-3 mb-0"><i class="ti ti-info-circle me-1"></i>Deselecting a region hides ordering from its player area. Existing catalogues and paid orders are kept.</p>
    </div>
    <div class="card-footer d-flex justify-content-end">
      <button class="btn btn-primary"><i class="ti ti-device-floppy me-1"></i>Save region selection</button>
    </div>
  </form>
  @endif

  <section aria-labelledby="catalogue-heading">
    <div class="d-flex gap-3 align-items-start mb-3 px-1">
      <span class="setup-step">{{ $isEventManager ? 2 : 1 }}</span>
      <div><h5 class="mb-1" id="catalogue-heading">Prepare catalogues</h5><p class="text-muted small mb-0">Open a region to review its items, prices, sizes, orders, and ordering status.</p></div>
    </div>

    <div class="accordion clothing-regions" id="clothing-regions">
      @foreach($visibleRegions as $region)
        @php($items = $region->clothingItems)
        @php($usesOnlineClothing = $region->usesOnlineClothingOrders())
        @php($recommended = $recommendedSources->get($region->id))
        @php($sizeCount = $items->sum(fn($item) => $item->sizes->count()))
        @php($notReady = $items->filter(fn($item) => (float)$item->price <= 0 || $item->sizes->isEmpty()))
        @php($statusType = !$usesOnlineClothing ? 'secondary' : ($region->clothing_order ? 'success' : ($items->isNotEmpty() ? 'warning' : 'secondary')))
        @php($statusLabel = !$usesOnlineClothing ? 'Not selected' : ($region->clothing_order ? 'Ordering open' : ($items->isNotEmpty() ? 'Ready · closed' : 'Needs setup')))

        <div class="accordion-item border rounded mb-3 overflow-hidden">
          <h2 class="accordion-header" id="clothing-region-heading-{{ $region->id }}">
            <button class="accordion-button collapsed gap-3" type="button" data-bs-toggle="collapse" data-bs-target="#clothing-region-{{ $region->id }}" aria-expanded="false" aria-controls="clothing-region-{{ $region->id }}">
              <span class="flex-grow-1 text-start">
                <span class="d-block fw-semibold text-body mb-1">{{ $region->region_name }}</span>
                <span class="d-block text-muted fw-normal small">{{ $items->count() }} {{ Str::plural('item', $items->count()) }} · {{ $sizeCount }} size {{ Str::plural('option', $sizeCount) }}</span>
              </span>
              <span class="badge bg-label-{{ $statusType }} flex-shrink-0">{{ $statusLabel }}</span>
            </button>
          </h2>
          <div id="clothing-region-{{ $region->id }}" class="accordion-collapse collapse" aria-labelledby="clothing-region-heading-{{ $region->id }}" data-bs-parent="#clothing-regions">
            <div class="accordion-body border-top">
              @if(!$usesOnlineClothing)
                <div class="alert alert-light border mb-3"><i class="ti ti-info-circle me-1"></i>Select this region in step 1 and save before setting up or opening its catalogue.</div>
              @elseif($items->isNotEmpty())
                <div class="table-responsive mb-3">
                  <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Order</th><th>Item</th><th>Price</th><th>Sizes</th></tr></thead>
                    <tbody>
                      @foreach($items->sortBy([['ordering','asc'],['item_type_name','asc']]) as $item)
                        <tr><td>{{ $item->ordering ?? '—' }}</td><td>{{ $item->item_type_name }}</td><td>R{{ number_format((float)$item->price, 0) }}</td><td>{{ $item->sizes->count() }}</td></tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
                @if($notReady->isNotEmpty())
                  <div class="alert alert-warning py-2"><i class="ti ti-alert-triangle me-1"></i>{{ $notReady->count() }} {{ Str::plural('item', $notReady->count()) }} still need a positive price or at least one size before ordering can open.</div>
                @endif
              @elseif($recommended)
                <div class="alert alert-light border">Recommended previous setup: <strong>{{ $recommended->region_name }}</strong> · {{ $recommended->clothing_items_count }} items.</div>
              @else
                <div class="alert alert-light border">No matching previous clothing catalogue was found. Choose any saved region catalogue on the management page or create items manually.</div>
              @endif

              <div class="d-flex flex-wrap gap-2 region-actions">
                @if($usesOnlineClothing)
                  <a class="btn btn-primary" href="{{ route('backend.region.clothing.edit', array_filter(['region' => $region->id, 'source_region' => $recommended?->id, 'event_id' => $event->id])) }}"><i class="ti ti-settings me-1"></i>{{ $items->isEmpty() ? 'Set up clothing' : 'Manage clothing & prices' }}</a>
                @endif
                <a class="btn btn-outline-secondary" href="{{ route('backend.region.clothing.orders', ['region' => $region, 'event_id' => $event->id]) }}"><i class="ti ti-list me-1"></i>Paid orders</a>
                @if($usesOnlineClothing && $items->isNotEmpty())
                  <form method="POST" action="{{ route('backend.region.clothing.toggle', $region) }}">
                    @csrf @method('PATCH')
                    <button class="btn btn-{{ $region->clothing_order ? 'outline-danger' : 'success' }}" @disabled(!$region->clothing_order && $notReady->isNotEmpty())><i class="ti ti-{{ $region->clothing_order ? 'lock' : 'shopping-cart' }} me-1"></i>{{ $region->clothing_order ? 'Close ordering' : 'Open ordering' }}</button>
                  </form>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endforeach
    </div>
  </section>
</div>
@endsection

@section('page-style')
<style>
  .clothing-setup { max-width: 1440px; }
  .setup-step { align-items: center; background: var(--bs-primary); border-radius: 50%; color: #fff; display: inline-flex; flex: 0 0 2rem; font-size: .875rem; font-weight: 700; height: 2rem; justify-content: center; }
  .clothing-summary .badge { font-size: .8125rem; padding: .55rem .7rem; }
  .region-choice { cursor: pointer; transition: border-color .15s ease, background-color .15s ease; }
  .region-choice:has(input:checked) { background: rgba(var(--bs-primary-rgb), .06); border-color: var(--bs-primary) !important; }
  .region-choice:hover { border-color: var(--bs-primary) !important; }
  .clothing-regions .accordion-button { box-shadow: none; min-height: 5rem; padding: 1rem 1.25rem; }
  .clothing-regions .accordion-button:not(.collapsed) { background: rgba(var(--bs-primary-rgb), .04); }
  .clothing-regions .accordion-button::after { margin-left: .5rem; }
  @media (max-width: 575.98px) {
    .clothing-setup { padding-top: 1.25rem !important; }
    .clothing-summary { display: grid !important; grid-template-columns: 1fr; width: 100%; }
    .clothing-summary .badge { text-align: left; }
    .clothing-regions .accordion-button { align-items: flex-start; flex-wrap: wrap; padding: 1rem; }
    .clothing-regions .accordion-button .badge { order: 2; }
    .clothing-regions .accordion-button::after { margin-left: auto; margin-top: .25rem; }
    .clothing-regions .accordion-body { padding: 1rem; }
    .region-actions, .region-actions form, .region-actions .btn { width: 100%; }
  }
</style>
@endsection
