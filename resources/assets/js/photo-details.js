'use strict';

$(function () {
  $.ajaxSetup({
    headers:
      { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
  });


  var productDetails = $('#product-select-body'),
    backButton = $('#backButton'),
    selectProductButton = $('.productSelect');




  $('#backButton').on('click', function () {
   clear();
  })
  selectProductButton.on('click', function () {
    var product = $(this).data('product');
    console.log('product button pressed');

    console.log(product);
    loadProductOptions(product);

  })
$(document).on('change','#smallSelect',function(){
  $('.card-footer').removeClass('d-none');
})

$('#buy-button').on('click',function(){
  var image = $(this).data('image');
  $('#offcanvasEndLabel').text(image.name);
  $('#imageName').val(image.name)
})

$('#add-to-cart-button').on('click',function(){
  var selected = $(this).find('option:selected');
  console.log('selected',selected)
  var body = $('#cartTable').find('tbody');
var tr = $('<tr/>');
var td = $('<td/>');
var nameTd = td.clone().text($('#imageName').val());

var quentityTd = td.clone().text($('#quantity').val());
var priceTd = td.clone().text($(selected.data('price')));

tr.append([nameTd,priceTd,quentityTd]);
console.log(tr,td);

body.append(tr);
 console.log(body);
 clear();
})

})
function loadProductOptions(product) {
  console.log('clicked on prouduct');
  $('#productCard').addClass('d-none');
  $('#backButton').removeClass('d-none');
  var select = newSelect(product);
  console.log(select);

  $('#image-options').append(select);
}

function showProductOptions() {
  console.log('yebo');


}

function loadOptions() {
  console.log('options');
}
function newSelect(product) {
  console.log('product', product);

  var html = ' <div><label for="smallSelect" class="form-label">Small select</label><select id="smallSelect"class="form-select form-select-sm"><option>Please select</option></div>';

  var div = $('<div/>');
  var label = $('<label/>').attr({
    'for': 'smallSelect',



  }).addClass('form-label').text('Please select '+product.name+' options');
  var select = $('<select/>').attr({ 'id': 'smallSelect' }).addClass('form-select form-select-sm');
console.log(product);
  select.append(new Option('Please select', 0, true));
  $.each(product.options, function (key, value) {
select.append($('<option/>').attr({'value':key,'data-price':value.price}).text(value.option))
   
  });

  div.append(label);
  div.append(select);

  return div;

}

function clear(){
  $('#productCard').removeClass('d-none');
  $('#image-options').empty();
  $('#image-options').append('<h6>Price <span>R450</span></h6>')
}

