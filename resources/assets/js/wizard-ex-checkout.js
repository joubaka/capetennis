/**
 *  Form Wizard
 */

'use strict';



// rateyo (jquery)
$(function () {
  var readOnlyRating = $('.read-only-ratings');

  // Star rating
  if (readOnlyRating) {
    readOnlyRating.rateYo({
      rtl: isRtl,
      rating: 4,
      starWidth: '20px'
    });
  }
});

(function () {
  // Init custom option check
  window.Helpers.initCustomOptionCheck();

  // libs
  var url = APP_URL + '/reg';

  console.log(url)

  // Wizard Checkout
  // --------------------------------------------------------------------

  const wizardCheckout = document.querySelector('#wizard-checkout');
  if (typeof wizardCheckout !== undefined && wizardCheckout !== null) {
    // Wizard form
    const wizardCheckoutForm = wizardCheckout.querySelector('#wizard-checkout-form');
    // Wizard steps
    const wizardCheckoutFormStep1 = wizardCheckoutForm.querySelector('#checkout-cart');
    const wizardCheckoutFormStep2 = wizardCheckoutForm.querySelector('#checkout-address');
    const wizardCheckoutFormStep3 = wizardCheckoutForm.querySelector('#checkout-payment');
    const wizardCheckoutFormStep4 = wizardCheckoutForm.querySelector('#checkout-confirmation');
    // Wizard next prev button
    const wizardCheckoutNext = [].slice.call(wizardCheckoutForm.querySelectorAll('.btn-next'));
    const wizardCheckoutPrev = [].slice.call(wizardCheckoutForm.querySelectorAll('.btn-prev'));

    let validationStepper = new Stepper(wizardCheckout, {
      linear: false
    });

    // Cart
    const FormValidation1 = FormValidation.formValidation(wizardCheckoutFormStep1, {
      fields: {
        // * Validate the fields here based on your requirements
      },

      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          // Use this for enabling/changing valid/invalid class
          // eleInvalidClass: '',
          eleValidClass: ''
          // rowSelector: '.col-lg-6'
        }),
        autoFocus: new FormValidation.plugins.AutoFocus(),
        submitButton: new FormValidation.plugins.SubmitButton()
      }
    }).on('core.form.valid', function () {
      // Jump to the next step when all fields in the current step are valid
      validationStepper.next();
    });

    // Address
    const FormValidation2 = FormValidation.formValidation(wizardCheckoutFormStep2, {
      fields: {
        // * Validate the fields here based on your requirements
      },
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          // Use this for enabling/changing valid/invalid class
          // eleInvalidClass: '',
          eleValidClass: ''
          // rowSelector: '.col-lg-6'
        }),
        autoFocus: new FormValidation.plugins.AutoFocus(),
        submitButton: new FormValidation.plugins.SubmitButton()
      }
    }).on('core.form.valid', function () {
      // Jump to the next step when all fields in the current step are valid
      validationStepper.next();
    });

    // Payment
    const FormValidation3 = FormValidation.formValidation(wizardCheckoutFormStep3, {
      fields: {
        // * Validate the fields here based on your requirements
      },
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          // Use this for enabling/changing valid/invalid class
          // eleInvalidClass: '',
          eleValidClass: ''
          // rowSelector: '.col-lg-6'
        }),
        autoFocus: new FormValidation.plugins.AutoFocus(),
        submitButton: new FormValidation.plugins.SubmitButton()
      }
    }).on('core.form.valid', function () {
      validationStepper.next();
    });

    // Confirmation
    const FormValidation4 = FormValidation.formValidation(wizardCheckoutFormStep4, {
      fields: {
        // * Validate the fields here based on your requirements
      },
      plugins: {
        trigger: new FormValidation.plugins.Trigger(),
        bootstrap5: new FormValidation.plugins.Bootstrap5({
          // Use this for enabling/changing valid/invalid class
          // eleInvalidClass: '',
          eleValidClass: '',
          rowSelector: '.col-md-12'
        }),
        autoFocus: new FormValidation.plugins.AutoFocus(),
        submitButton: new FormValidation.plugins.SubmitButton()
      }
    }).on('core.form.valid', function () {
      // You can submit the form
      // wizardCheckoutForm.submit()
      // or send the form data to server via an Ajax request
      // To make the demo simple, I just placed an alert
      alert('Submitted..!!');
    });

    wizardCheckoutNext.forEach(item => {
      item.addEventListener('click', event => {
        // When click the Next button, we will validate the current step
        switch (validationStepper._currentIndex) {
          case 0:
            FormValidation1.validate();
            break;

          case 1:
            FormValidation2.validate();
            break;

          case 2:
            FormValidation3.validate();
            break;

          case 3:
            FormValidation4.validate();
            break;

          default:
            break;
        }
      });
    });

    wizardCheckoutPrev.forEach(item => {
      item.addEventListener('click', event => {
        switch (validationStepper._currentIndex) {
          case 3:
            validationStepper.previous();
            break;

          case 2:
            validationStepper.previous();
            break;

          case 1:
            validationStepper.previous();
            break;

          case 0:

          default:
            break;
        }
      });
    });
  }
  $('#addPlayer').on('click', function () {
    var row = '';
    var select2player = $('.select2player');
    var select = $('.select2Basic').select2('destroy');
    row = $('.playerRow').first().clone();

    var numPlayers = ($('.numPlayers').length) + 1;
    console.log(row.find('.select2player').first().data('select2Name', 'player' + (numPlayers.length)));
    console.log(row.find('.select2category').last().data('select2Name', 'category' + (numPlayers.length)));


    row.insertBefore("#tool-placeholder");



    row.find('.playerNr').text(('Player ' + numPlayers));

    if ($('.select2category').length) {
      $('.select2category').each(function () {
        var $this = $(this);
        $this.wrap('<div class="position-relative"></div>').select2({
          placeholder: 'Select value',
          dropdownParent: $this.parent(),
          searchInputPlaceholder: 'Type here to search..',


        });
      });
    }
    if ($('.select2gender').length) {
      $('.select2gender').each(function () {
        var $this = $(this);
        $this.wrap('<div class="position-relative"></div>').select2({
          placeholder: 'Select value',
          dropdownParent: $this.parent(),
          searchInputPlaceholder: 'Type here to search..',


        });
      });
    }
    if ($('.select2player').length) {
      $('.select2player').each(function (key, value) {
        var $this = $(this);
        $this.wrap('<div class="position-relative"></div>').select2({
          placeholder: 'Select value',
          dropdownParent: $this.parent(),
          searchInputPlaceholder: 'Type here to search..',
          language: {
            noResults: function () {
              return $("<div class='btn btn-primary btn-sm' data-index='" + key + "' data-bs-toggle='modal' data-bs-target='#addPlayerModal'>Add New Player</div>");
            }
          }

        });
      });
    }
    $('.select2Basic').on('change', function () {
      $('.playersReciept').empty();
      var ul = $('#myUl');
      var rows = $('.playerRow');
      var listClone = $('.recieptList').clone();
      var ul = $('#myUl').empty();
      appendPlayers(rows, ul, listClone)


    });
  });

  $('.select2Basic').on('change', function () {
    $('.playersReciept').empty();

    var ul = $('#myUl');
    var rows = $('.playerRow');
    var listClone = $('.recieptList').clone();
    var ul = $('#myUl').empty();
    appendPlayers(rows, ul, listClone);




  });


})();
function appendPlayers(rows, ul, listClone) {
  $('.playersCart').empty();
  var myname;
  var orderTotal = 0;

  rows.each(function (k, v) {
   
    var cart = $('.playersCart')
    var nameValue = $(v).find('.select2Basic').first().val();
    var catValue = $(v).find('.select2Basic').last().val();
    var category = $(v).find('.select2Basic').last().find(":selected").data("name");
    myname = $(v).find('.select2Basic').first().find(":selected").data("name");
    var itemPrice = $(v).find('.select2Basic').last().find(":selected").data("price")
    var eventPrice = $('#eventPrice').val();
   
    var price;
    
    if (itemPrice == 0) {
      price = eventPrice;
    } else {
      price = itemPrice;
    }

    var dats = '<div>' + myname + ' - ' + category + '</div>';
    $(cart).append(dats);
    // ul.append(clone.find('.itemName').html(myname + ' - ' + category));
   
    $('#myUl').append('<li class="list-group-item p-4 recieptList">' + myname + ' - ' + category + ' @ R' + price + '</li>');
    orderTotal += parseInt(price);


  });
  $('.orderTotal').html('R' + orderTotal);
  console.log('ot',orderTotal);
  $('#amount').val(orderTotal+'.00');
  var event = $('#myevent').val();
  
  $('#item_name').val(event);
}





