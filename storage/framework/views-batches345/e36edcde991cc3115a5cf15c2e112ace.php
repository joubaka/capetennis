
<?php $__env->startSection('title', 'Clothing — ' . $region->region_name); ?>

<?php $__env->startSection('vendor-style'); ?>
  <link rel="stylesheet" href="<?php echo e(asset('assets/vendor/libs/select2/select2.css')); ?>">
<?php $__env->stopSection(); ?>
<?php $__env->startSection('vendor-script'); ?>
  <script src="<?php echo e(asset('assets/vendor/libs/select2/select2.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="container-xxl py-4 clothing-setup-admin">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($backEvent): ?><p class="text-muted mb-1"><?php echo e($backEvent->name); ?></p><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><h3 class="mb-0"><?php echo e($region->region_name); ?> — Clothing</h3><p class="small text-muted mb-0">Regional clothing catalog shared across events using this region.</p></div>
    <div class="d-flex flex-wrap gap-2">
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($backUrl): ?><a class="btn btn-outline-secondary" href="<?php echo e($backUrl); ?>">Back to event</a><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <a class="btn btn-outline-secondary" href="<?php echo e(route('backend.region.clothing.orders', ['region' => $region, 'event_id' => $backEvent?->id])); ?>">Paid orders</a>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($items->isNotEmpty()): ?>
        <form method="POST" action="<?php echo e(route('backend.region.clothing.toggle', $region)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?>
          <button class="btn btn-<?php echo e($region->clothing_order ? 'outline-danger' : 'success'); ?>"><?php echo e($region->clothing_order ? 'Close ordering' : 'Open ordering'); ?></button>
        </form>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddItem">+ Add Item</button>
    </div>
  </div>

  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
    <div class="alert alert-success"><?php echo e(session('success')); ?></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
  <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($errors->any()): ?>
    <div class="alert alert-danger"><strong>Clothing setup was not copied.</strong><ul class="mb-0 mt-2"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></ul></div>
  <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center gap-2">
      <div>
      <h5 class="mb-1">Copy and review last year’s clothing</h5>
      <p class="text-muted mb-0">Load another region’s items and sizes, review every selling price, then copy only the selected items. Existing items are never overwritten.</p>
      </div>
      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($items->isNotEmpty()): ?><button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#copy-clothing-panel" aria-expanded="<?php echo e($copySource ? 'true' : 'false'); ?>">Copy another setup</button><?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    <div id="copy-clothing-panel" class="collapse <?php echo e($items->isEmpty() || $copySource ? 'show' : ''); ?>">
    <div class="card-body border-top">
      <form method="GET" action="<?php echo e(route('backend.region.clothing.edit', $region)); ?>" class="row g-2 align-items-end mb-3">
        <div class="col-lg-8">
          <label class="form-label" for="source-region">Previous clothing setup</label>
          <select class="form-select" name="source_region" id="source-region" required>
            <option value="">Choose a region…</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $sourceRegions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sourceRegion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <?php ($sourceEvent = $sourceRegion->events->first()); ?>
              <option value="<?php echo e($sourceRegion->id); ?>" <?php if($copySource?->id === $sourceRegion->id): echo 'selected'; endif; ?>>
                <?php echo e($sourceRegion->region_name); ?> · <?php echo e($sourceRegion->clothing_items_count); ?> items<?php echo e($sourceEvent ? ' · '.$sourceEvent->name : ''); ?>

              </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
        </div>
        <div class="col-lg-4 d-grid"><button class="btn btn-outline-primary">Preview clothing setup</button></div>
      </form>

      <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($copySource): ?>
        <div class="alert alert-warning">
          <strong>Price review required:</strong> these are the saved prices from <?php echo e($copySource->region_name); ?>. Edit the <?php echo e($targetYear); ?> selling prices below before confirming. Clothing ordering will remain closed after copying.
          The final customer preview includes the PayFast fee from the current system settings.
        </div>
        <form method="POST" action="<?php echo e(route('backend.region.clothing.copy', $region)); ?>">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="source_region_id" value="<?php echo e($copySource->id); ?>">
          <div class="table-responsive">
            <table class="table align-middle">
              <thead class="table-light"><tr><th style="width:48px">Copy</th><th><?php echo e($targetYear); ?> item name</th><th style="width:145px">Buying amount (R)</th><th style="width:155px">Clothing amount (R)</th><th style="width:135px">Vendor profit</th><th style="width:135px">PayFast fee</th><th style="width:155px">Final amount</th><th style="width:110px">Display order</th><th>Sizes copied</th></tr></thead>
              <tbody>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $copySource->clothingItems->sortBy([['ordering','asc'],['item_type_name','asc']])->values(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rowIndex => $sourceItem): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                  <tr class="clothing-pricing-row">
                    <td>
                      <input type="hidden" name="items[<?php echo e($rowIndex); ?>][selected]" value="0">
                      <input class="form-check-input clothing-copy-toggle" type="checkbox" name="items[<?php echo e($rowIndex); ?>][selected]" value="1" checked aria-label="Copy <?php echo e($sourceItem->item_type_name); ?>">
                      <input type="hidden" name="items[<?php echo e($rowIndex); ?>][source_item_id]" value="<?php echo e($sourceItem->id); ?>">
                    </td>
                    <td><input class="form-control" name="items[<?php echo e($rowIndex); ?>][item_type_name]" value="<?php echo e(old("items.$rowIndex.item_type_name", preg_replace('/\b20\d{2}\b/u', (string) $targetYear, $sourceItem->item_type_name))); ?>" required maxlength="191"></td>
                    <td><input class="form-control clothing-cost-price" type="number" step="0.01" name="items[<?php echo e($rowIndex); ?>][cost_price]" value="<?php echo e(old("items.$rowIndex.cost_price", $sourceItem->cost_price !== null ? number_format((float) $sourceItem->cost_price, 2, '.', '') : '')); ?>" min="0" inputmode="decimal" aria-label="Buying amount for <?php echo e($sourceItem->item_type_name); ?>"></td>
                    <td>
                      <input class="form-control clothing-preview-price" type="number" step="0.01" name="items[<?php echo e($rowIndex); ?>][price]" value="<?php echo e(old("items.$rowIndex.price", number_format((float) $sourceItem->price, 2, '.', ''))); ?>" min="0" required inputmode="decimal" aria-label="Clothing amount for <?php echo e($sourceItem->item_type_name); ?>">
                      <input class="clothing-pricing-source" type="hidden" name="items[<?php echo e($rowIndex); ?>][pricing_source]" value="<?php echo e(old("items.$rowIndex.pricing_source", 'price')); ?>">
                    </td>
                    <td class="fw-semibold clothing-preview-profit">—</td>
                    <td class="text-muted clothing-preview-fee">R0.00</td>
                    <td><input class="form-control fw-semibold clothing-preview-total" type="number" step="0.01" name="items[<?php echo e($rowIndex); ?>][final_amount]" value="<?php echo e(old("items.$rowIndex.final_amount")); ?>" min="0" required inputmode="decimal" aria-label="Final customer amount for <?php echo e($sourceItem->item_type_name); ?>"></td>
                    <td><input class="form-control" type="number" name="items[<?php echo e($rowIndex); ?>][ordering]" value="<?php echo e(old("items.$rowIndex.ordering", $rowIndex + 1)); ?>" min="1"></td>
                    <td><div class="d-flex flex-wrap gap-1"><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $sourceItem->sizes->sortBy([['ordering','asc'],['id','asc']]); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span class="badge bg-label-primary"><?php echo e($size->size); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?></div></td>
                  </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="form-check border rounded p-3 ps-5 mb-3">
            <input class="form-check-input" type="checkbox" name="confirm_prices" value="1" id="confirm-clothing-prices" required>
            <label class="form-check-label" for="confirm-clothing-prices"><strong>I reviewed and approve these <?php echo e($targetYear); ?> selling prices.</strong> Copy the selected items and sizes without changing the source setup.</label>
          </div>
          <button class="btn btn-primary">Copy approved clothing to <?php echo e($region->region_name); ?></button>
        </form>
      <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Items & sizes</h5>
      <button id="btn-save" class="btn btn-success btn-sm">Save Changes</button>
    </div>
    <div class="card-body p-0">
      <div class="alert alert-info rounded-0 border-start-0 border-end-0 mb-0">
        <strong>Pricing:</strong> vendor profit is the clothing amount less the buying amount. The final customer amount adds the PayFast fee using <?php echo e(number_format($payfastSettings['percentage'], 2)); ?>% + R<?php echo e(number_format($payfastSettings['flat'], 2)); ?>, including <?php echo e(number_format($payfastSettings['vat'], 2)); ?>% VAT. Checkout recalculates the fee once on the complete basket.
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="items-table">
          <thead class="table-light">
            <tr>
              <th style="width: 40px">#</th>
              <th>Name</th>
              <th style="width:155px">Final amount (R)</th>
              <th>Pricing details</th>
              <th style="width:120px">Ordering</th>
              <th>Sizes</th>
              <th style="width: 80px"></th>
            </tr>
          </thead>
          <tbody>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
              <tr data-id="<?php echo e($i->id); ?>" class="clothing-pricing-row">
                <td data-label="Item ID" class="text-muted"><?php echo e($i->id); ?></td>
                <td data-label="Name">
                  <input type="text" class="form-control form-control-sm item-name" value="<?php echo e($i->item_type_name); ?>">
                </td>
                <td data-label="Final amount"><input type="number" step="0.01" min="0" class="form-control form-control-sm fw-semibold item-final-amount clothing-preview-total" value="<?php echo e($i->final_amount !== null ? number_format((float) $i->final_amount, 2, '.', '') : ''); ?>" inputmode="decimal" aria-label="Final customer amount for <?php echo e($i->item_type_name); ?>"></td>
                <td data-label="Pricing details"><details><summary>Pricing details</summary><div class="mt-2"><div class="mb-2">
                  <label class="form-label">Buying amount (R)</label><input type="number" step="0.01" min="0" class="form-control form-control-sm item-cost-price clothing-cost-price" value="<?php echo e($i->cost_price !== null ? number_format((float) $i->cost_price, 2, '.', '') : ''); ?>" aria-label="Buying amount for <?php echo e($i->item_type_name); ?>">
                  </div>
                  <div class="mb-2">
                  <label class="form-label">Clothing amount (R)</label><input type="number" step="0.01" min="0" class="form-control form-control-sm item-price clothing-preview-price" value="<?php echo e(number_format((float)($i->price ?? 0), 2, '.', '')); ?>" aria-label="Clothing amount for <?php echo e($i->item_type_name); ?>">
                  <input type="hidden" class="item-pricing-source clothing-pricing-source" value="<?php echo e($i->final_amount !== null ? 'final_amount' : 'price'); ?>">
                  </div>
                <div>Vendor profit: <span class="fw-semibold clothing-preview-profit">—</span></div>
                <div>PayFast fee: <span class="text-muted clothing-preview-fee">R0.00</span></div>
                  </div></details></td>
                <td data-label="Ordering">
                  <input type="number" min="0" class="form-control form-control-sm item-ordering" value="<?php echo e($i->ordering); ?>">
                </td>
                <td data-label="Sizes">
                  <div class="d-flex flex-wrap gap-1 sizes-wrap">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $i->sizes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sz): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                      <span class="badge bg-label-primary d-flex align-items-center gap-2" data-size-id="<?php echo e($sz->id); ?>">
                        <span><?php echo e($sz->size); ?></span>
                        <button type="button" class="btn btn-xs btn-link text-danger p-0 btn-del-size" title="Delete size">×</button>
                      </span>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                  </div>
                  <div class="input-group input-group-sm mt-1" style="max-width:320px">
                    <input type="text" class="form-control new-size" placeholder="Add size e.g. S / 10-11">
                    <input type="number" class="form-control" placeholder="Order" style="max-width:100px">
                    <button class="btn btn-outline-primary btn-add-size">Add</button>
                  </div>
                </td>
                <td data-label="Actions" class="text-end">
                  <button class="btn btn-sm btn-outline-danger btn-del-item">Delete</button>
                </td>
              </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
              <tr><td colspan="7" class="text-center p-4 text-muted">No items yet</td></tr>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<style>
.clothing-setup-admin summary { min-height:44px; cursor:pointer; align-content:center; }
.clothing-setup-admin :is(.btn, input:not([type=hidden]), select) { min-height:44px; }
.clothing-setup-admin input[type=checkbox] { min-height:0; }
@media(max-width:767px) {
 #items-table thead { display:none; }
 #items-table tbody, #items-table tr { display:block; }
 #items-table tr { border-bottom:1px solid #d9e1eb; padding:.75rem; }
 #items-table td { display:grid; grid-template-columns:85px minmax(0,1fr); gap:.5rem; border:0; padding:.4rem 0; white-space:normal; }
 #items-table td::before { content:attr(data-label); font-weight:600; }
 #items-table td[colspan] { display:block; }
 #items-table td[colspan]::before { display:none; }
 #items-table .input-group { grid-column:2; }
}
</style>

<div class="modal fade" id="modalAddItem" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" id="formAddItem">
      <?php echo csrf_field(); ?>
      <div class="modal-header">
        <h5 class="modal-title">Add Clothing Item</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">Name</label>
          <input type="text" class="form-control" name="item_type_name" required>
        </div>
        <div class="mb-2 clothing-pricing-row">
          <div class="row g-2">
            <div class="col-sm-6"><label class="form-label">Buying amount (R)</label><input type="number" step="0.01" class="form-control clothing-cost-price" name="cost_price" min="0"></div>
            <div class="col-sm-6"><label class="form-label">Clothing amount (R)</label><input type="number" step="0.01" class="form-control clothing-preview-price" name="price" min="0" value="0"><input class="clothing-pricing-source" type="hidden" name="pricing_source" value="price"></div>
          </div>
          <div class="form-text">Vendor profit is calculated before the PayFast fee is added.</div>
          <div class="row g-2 mt-1">
            <div class="col-4"><span class="form-text">Vendor profit</span><div class="fw-semibold clothing-preview-profit">—</div></div>
            <div class="col-4"><span class="form-text">PayFast fee</span><div class="clothing-preview-fee">R0.00</div></div>
            <div class="col-4"><label class="form-text mb-0">Final amount</label><input type="number" step="0.01" min="0" class="form-control form-control-sm fw-semibold clothing-preview-total" name="final_amount" value="0" inputmode="decimal"></div>
          </div>
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
  const payfast = <?php echo json_encode($payfastSettings, 15, 512) ?>;

  function feeFor(total) {
    return total > 0
      ? Math.min(total, Math.round((((total * Number(payfast.percentage) / 100) + Number(payfast.flat)) * (1 + Number(payfast.vat) / 100)) * 100) / 100)
      : 0;
  }

  function totalsFromFinalAmount(finalAmount) {
    const requestedTotal = Math.round(Math.max(0, finalAmount) * 100) / 100;
    if (requestedTotal === 0) return { subtotal: 0, fee: 0, total: 0 };

    const vatMultiplier = 1 + (Number(payfast.vat) / 100);
    const multiplier = 1 + ((Number(payfast.percentage) / 100) * vatMultiplier);
    const grossFlat = Number(payfast.flat) * vatMultiplier;
    const estimate = Math.max(0, (requestedTotal - grossFlat) / multiplier);
    let bestSubtotal = Math.round(estimate * 100) / 100;
    let bestFee = feeFor(bestSubtotal);
    let bestTotal = Math.round((bestSubtotal + bestFee) * 100) / 100;

    for (let offset = -5; offset <= 5; offset++) {
      const subtotal = Math.round(Math.max(0, estimate + (offset / 100)) * 100) / 100;
      const fee = feeFor(subtotal);
      const total = Math.round((subtotal + fee) * 100) / 100;
      if (Math.abs(total - requestedTotal) < Math.abs(bestTotal - requestedTotal)) {
        bestSubtotal = subtotal;
        bestFee = fee;
        bestTotal = total;
      }
    }

    return {
      subtotal: bestSubtotal,
      fee: Math.round((requestedTotal - bestSubtotal) * 100) / 100,
      total: requestedTotal
    };
  }

  function refreshProfit(row, subtotal) {
    const costInput = row.querySelector('.clothing-cost-price');
    const profit = row.querySelector('.clothing-preview-profit');
    if (!profit) return;

    const hasCost = costInput && costInput.value.trim() !== '';
    const amount = subtotal - Math.max(0, Number(costInput?.value) || 0);
    profit.textContent = hasCost ? `R${amount.toFixed(2)}` : '—';
    profit.classList.toggle('text-danger', hasCost && amount < 0);
    profit.classList.toggle('text-success', hasCost && amount >= 0);
  }

  function refreshPricePreview(input) {
    const row = input.closest('.clothing-pricing-row');
    const priceInput = row.querySelector('.clothing-preview-price');
    const total = Math.max(0, Number(priceInput?.value) || 0);
    const fee = feeFor(total);
    row.querySelector('.clothing-preview-fee').textContent = `R${fee.toFixed(2)}`;
    row.querySelector('.clothing-preview-total').value = (total + fee).toFixed(2);
    refreshProfit(row, total);
  }

  function refreshFromFinalAmount(input, normaliseFinal = false) {
    const row = input.closest('.clothing-pricing-row');
    const result = totalsFromFinalAmount(Number(input.value) || 0);
    row.querySelector('.clothing-preview-price').value = result.subtotal.toFixed(2);
    row.querySelector('.clothing-preview-fee').textContent = `R${result.fee.toFixed(2)}`;
    if (normaliseFinal) input.value = result.total.toFixed(2);
    refreshProfit(row, result.subtotal);
  }

  document.querySelectorAll('.clothing-pricing-row').forEach(row => {
    const priceInput = row.querySelector('.clothing-preview-price');
    const finalInput = row.querySelector('.clothing-preview-total');
    const sourceInput = row.querySelector('.clothing-pricing-source');
    priceInput.addEventListener('input', () => {
      sourceInput.value = 'price';
      refreshPricePreview(priceInput);
    });
    finalInput.addEventListener('input', () => {
      sourceInput.value = 'final_amount';
      refreshFromFinalAmount(finalInput);
    });
    finalInput.addEventListener('change', () => refreshFromFinalAmount(finalInput, true));
    const costInput = row.querySelector('.clothing-cost-price');
    if (costInput) costInput.addEventListener('input', () => refreshProfit(row, Math.max(0, Number(priceInput.value) || 0)));
    if (sourceInput.value === 'final_amount' && finalInput.value !== '') {
      refreshFromFinalAmount(finalInput, true);
    } else {
      refreshPricePreview(priceInput);
    }
  });

  // ---------- helpers ----------
  const csrf = '<?php echo e(csrf_token()); ?>';
  const base = <?php echo json_encode(route('backend.region.clothing.edit', $region), 512) ?>; // e.g. "/backend/region/5/clothing"

  const routes = {
    storeItem:  `${base}/items`,
    bulkUpdate: `${base}/items/bulk`,
    destroyItem: (itemId) => `${base}/items/${itemId}`,
    storeSize:   (itemId) => `${base}/${itemId}/sizes`,
    destroySize: (itemId, sizeId) => `${base}/${itemId}/sizes/${sizeId}`,
  };

  $('.clothing-copy-toggle').on('change', function(){
    const disabled = !this.checked;
    $(this).closest('tr').find('input[name$="[item_type_name]"], input[name$="[cost_price]"], input[name$="[price]"], input[name$="[final_amount]"], input[name$="[pricing_source]"], input[name$="[ordering]"]').prop('disabled', disabled);
  });

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
        cost_price: $(this).find('.item-cost-price').val() || null,
        price: Number($(this).find('.item-price').val() || 0),
        final_amount: Number($(this).find('.item-final-amount').val() || 0),
        pricing_source: $(this).find('.item-pricing-source').val(),
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
<?php $__env->stopSection(); ?>

<?php $__env->startPush('page-script'); ?>

<?php $__env->stopPush(); ?>




<?php echo $__env->make('layouts.backend', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\wamp64\www\ct\resources\views\backend\clothing\region-items.blade.php ENDPATH**/ ?>