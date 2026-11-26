<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($player): ?>
<?php
  $canOrder = $event->isTeam()
    && (int) $team->category?->event_id === (int) $event->id
    && (int) $team->region_id === (int) $region->id
    && $event->published && (int) $team->published === 1 && $region->clothingOrdersAvailable();
  $playerClothingOrders = ($myPaidClothingOrdersByPlayer ?? collect())->get($team->id.'-'.$player->id, collect());
  $clothingQuantity = $playerClothingOrders->sum(fn ($order) => $order->items->sum(fn ($item) => max(1, (int) ($item->qty ?? 1))));
  $clothingDetailsId = 'clothing-orders-'.$event->id.'-'.$team->id.'-'.$player->id;
?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canOrder || $playerClothingOrders->isNotEmpty()): ?>
<div class="d-flex flex-wrap gap-1 mt-2 team-player-clothing-controls">
                    
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canOrder): ?>
                      <a href="javascript:void(0)"
                         class="btn btn-sm btn-outline-secondary clothing-order"
                         title="Order clothing for <?php echo e($playerName); ?>"
                         aria-label="Order clothing for <?php echo e($playerName); ?>"
                         data-playerid="<?php echo e($player->id); ?>"
                         data-name="<?php echo e($playerName); ?>"
                         data-team="<?php echo e($team->id); ?>"
                         data-region="<?php echo e($region->id); ?>"
                         data-eventid="<?php echo e($event->id); ?>"
                         data-bs-toggle="modal"
                         data-bs-target="#clothing-order-modal">
                        <i class="ti ti-shirt me-1" aria-hidden="true"></i>Order clothing
                      </a>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($playerClothingOrders->isNotEmpty()): ?>
                      <button type="button"
                              class="btn btn-sm btn-outline-success"
                              data-bs-toggle="collapse"
                              data-bs-target="#<?php echo e($clothingDetailsId); ?>"
                              aria-expanded="false"
                              aria-controls="<?php echo e($clothingDetailsId); ?>">
                        <i class="ti ti-shopping-bag-check me-1" aria-hidden="true"></i>
                        <?php echo e($clothingQuantity); ?> <?php echo e(Str::plural('item', $clothingQuantity)); ?> ordered
                      </button>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($playerClothingOrders->isNotEmpty()): ?>
                <div class="collapse mt-2" id="<?php echo e($clothingDetailsId); ?>">
                  <div class="border rounded bg-light-subtle p-2" aria-label="Clothing ordered for <?php echo e($playerName); ?>">
                    <div class="small fw-semibold mb-1">Your clothing order for <?php echo e($playerName); ?></div>
                    <ul class="list-unstyled small mb-0">
                      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $playerClothingOrders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $clothingOrder): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $clothingOrder->items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $clothingItem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                          <?php
                            $clothingItemName = $clothingItem->item_name
                              ?: ($clothingItem->itemType?->item_type_name ?? 'Clothing item');
                            $clothingSizeName = $clothingItem->size_name
                              ?: $clothingItem->size?->size;
                            $itemQuantity = max(1, (int) ($clothingItem->qty ?? 1));
                          ?>
                          <li class="d-flex flex-wrap justify-content-between gap-2 py-1">
                            <span>
                              <i class="ti ti-shirt me-1 text-success" aria-hidden="true"></i>
                              <?php echo e($clothingItemName); ?>

                              <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($clothingSizeName): ?>
                                <span class="text-muted">(<?php echo e($clothingSizeName); ?>)</span>
                              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </span>
                            <span class="fw-semibold">Qty <?php echo e($itemQuantity); ?></span>
                          </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </ul>
                  </div>
                </div>
              <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH D:\wamp64\www\ct\resources\views\frontend\event\partials\player-clothing-controls.blade.php ENDPATH**/ ?>