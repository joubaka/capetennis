@if($player)
@php
  $canOrder = $event->published && (int) $team->published === 1 && $region->clothingOrdersAvailable();
  $playerClothingOrders = ($myPaidClothingOrdersByPlayer ?? collect())->get($team->id.'-'.$player->id, collect());
  $clothingQuantity = $playerClothingOrders->sum(fn ($order) => $order->items->sum(fn ($item) => max(1, (int) ($item->qty ?? 1))));
  $clothingDetailsId = 'clothing-orders-'.$event->id.'-'.$team->id.'-'.$player->id;
@endphp
@if($canOrder || $playerClothingOrders->isNotEmpty())
<div class="d-flex flex-wrap gap-1 mt-2 team-player-clothing-controls">
                    {{-- CLOTHING --}}
                    @if($canOrder)
                      <a href="javascript:void(0)"
                         class="btn btn-sm btn-outline-secondary clothing-order"
                         title="Order clothing for {{ $playerName }}"
                         aria-label="Order clothing for {{ $playerName }}"
                         data-playerid="{{ $player->id }}"
                         data-name="{{ $playerName }}"
                         data-team="{{ $team->id }}"
                         data-region="{{ $region->id }}"
                         data-eventid="{{ $event->id }}"
                         data-bs-toggle="modal"
                         data-bs-target="#clothing-order-modal">
                        <i class="ti ti-shirt me-1" aria-hidden="true"></i>Order clothing
                      </a>
                    @endif

                    @if($playerClothingOrders->isNotEmpty())
                      <button type="button"
                              class="btn btn-sm btn-outline-success"
                              data-bs-toggle="collapse"
                              data-bs-target="#{{ $clothingDetailsId }}"
                              aria-expanded="false"
                              aria-controls="{{ $clothingDetailsId }}">
                        <i class="ti ti-shopping-bag-check me-1" aria-hidden="true"></i>
                        {{ $clothingQuantity }} {{ Str::plural('item', $clothingQuantity) }} ordered
                      </button>
                    @endif

</div>
@endif
              @if($playerClothingOrders->isNotEmpty())
                <div class="collapse mt-2" id="{{ $clothingDetailsId }}">
                  <div class="border rounded bg-light-subtle p-2" aria-label="Clothing ordered for {{ $playerName }}">
                    <div class="small fw-semibold mb-1">Your clothing order for {{ $playerName }}</div>
                    <ul class="list-unstyled small mb-0">
                      @foreach($playerClothingOrders as $clothingOrder)
                        @foreach($clothingOrder->items as $clothingItem)
                          @php
                            $clothingItemName = $clothingItem->item_name
                              ?: ($clothingItem->itemType?->item_type_name ?? 'Clothing item');
                            $clothingSizeName = $clothingItem->size_name
                              ?: $clothingItem->size?->size;
                            $itemQuantity = max(1, (int) ($clothingItem->qty ?? 1));
                          @endphp
                          <li class="d-flex flex-wrap justify-content-between gap-2 py-1">
                            <span>
                              <i class="ti ti-shirt me-1 text-success" aria-hidden="true"></i>
                              {{ $clothingItemName }}
                              @if($clothingSizeName)
                                <span class="text-muted">({{ $clothingSizeName }})</span>
                              @endif
                            </span>
                            <span class="fw-semibold">Qty {{ $itemQuantity }}</span>
                          </li>
                        @endforeach
                      @endforeach
                    </ul>
                  </div>
                </div>
              @endif
@endif
