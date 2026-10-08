<div id="clothingPricingSettings" class="d-none" data-payfast-percentage="<?php echo e($payfastSettings['percentage']); ?>" data-payfast-flat="<?php echo e($payfastSettings['flat']); ?>" data-payfast-vat="<?php echo e($payfastSettings['vat']); ?>"></div>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $clothingItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
  <div class="card mb-2 clothing-item"
       data-item="<?php echo e($item->id); ?>"
       data-price="<?php echo e($item->price); ?>">

    <div class="card-body py-2">

      <div class="form-check">
        <input class="form-check-input item-toggle"
               type="checkbox"
               id="item-<?php echo e($item->id); ?>">

        <label class="form-check-label fw-semibold"
               for="item-<?php echo e($item->id); ?>">
          <?php echo e($item->item_type_name); ?>

          <span class="text-muted ms-2">
            (R<?php echo e(number_format($item->customer_price, 2)); ?>)
          </span>
        </label>
      </div>

      <div class="row g-2 mt-2 item-options d-none">
        <div class="col-md-6">
          <label class="form-label small">Size <span class="text-danger">(required)</span></label>
          <select class="form-select form-select-sm size-select">
            <option value="">Select size to calculate price</option>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $item->sizes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <option value="<?php echo e($size->id); ?>"><?php echo e($size->size); ?></option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </select>
          <div class="form-text">The item total remains R0.00 until a size is selected.</div>
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
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
  <div class="alert alert-warning mb-0">
    No clothing items configured for this region.
  </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\clothing\partials\items.blade.php ENDPATH**/ ?>