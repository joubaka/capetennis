<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasEnd" aria-labelledby="offcanvasEndLabel">
  <div class="offcanvas-header">
    <h5 id="offcanvasEndLabel" class="offcanvas-title">Options</h5>
    <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    <button class="btn btn-primary btn-sm d-none" id="backButton">Back</button>
  </div>
  <div class="offcanvas-body my-auto mx-0  ">
    <div id="product-select-body">
      <div class="card" id="productCard">
        <h5 class="card-header">Please select products</h5>
        <div class="card-body">
          <div class="row">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $products; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $product): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="col-md mb-md-0 mb-4 productSelect" data-product="<?php echo e($product); ?>">
              <div class="form-check custom-option custom-option-icon checked mb-2">
                <label class="form-check-label custom-option-content" for="customCheckboxSvg<?php echo e($key); ?>">
                  <span class="custom-option-body">
                    <i class="fa-solid <?php echo e($product->icon); ?>"></i>
                    <span class="custom-option-title"> <?php echo e($product->name); ?> </span>
                    <small>Cake sugar plum fruitcake I love sweet roll jelly-o.</small>
                  </span>

                </label>
              </div>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
          </div>
        </div>
      </div>
      <input type="hidden" name="image" id="imageName">
      <div id="image-options">
<h6>Price <span>R450</span></h6>
      </div>
      <div class="card-footer d-none mt-5">
        <div>
          <label for="quantity" class="form-label">Quantity</label>
          <select id="quantity" class="form-select form-select-sm">
            <option>Small select</option>
            <option value="1">1</option>
            <option value="2">2</option>
            <option value="3">3</option>
          </select>
        </div>

        <button type="button" id="add-to-cart-button" class="btn btn-primary mt-5 mb-2 d-grid w-100">Add To Cart</button>
        <button type="button" class="btn btn-label-secondary d-grid w-100" data-bs-dismiss="offcanvas">Cancel</button>

      </div>
<?php echo $__env->make('frontend.photo._includes.cart', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </div>
  </div>
  
</div>
<?php /**PATH C:\wamp64\www\ct\resources\views\frontend\photo\_includes\buy-photo-modal.blade.php ENDPATH**/ ?>