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
            @foreach($products as $key => $product)
            <div class="col-md mb-md-0 mb-4 productSelect" data-product="{{$product}}">
              <div class="form-check custom-option custom-option-icon checked mb-2">
                <label class="form-check-label custom-option-content" for="customCheckboxSvg{{$key}}">
                  <span class="custom-option-body">
                    <i class="fa-solid {{$product->icon}}"></i>
                    <span class="custom-option-title"> {{$product->name}} </span>
                    <small>Cake sugar plum fruitcake I love sweet roll jelly-o.</small>
                  </span>

                </label>
              </div>
            </div>
            @endforeach
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
@include('frontend.photo._includes.cart')
    </div>
  </div>
  
</div>
